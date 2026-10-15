<?php

namespace App\Services;

use App\Models\AutoReplyRule;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Email\EmailSendService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AutoReplyService
{
    /**
     * Check if any keyword auto-reply rules match the incoming message and send the auto-reply.
     *
     * Returns true if a rule matched and a reply was sent, false otherwise.
     */
    public function processIncomingMessage(Message $message, Conversation $conversation): bool
    {
        $workspaceId = $conversation->workspace_id;
        $channel = $conversation->channel ?? 'email';
        $text = $message->body_text ?? strip_tags($message->body_html ?? '');

        if (empty(trim($text))) {
            return false;
        }

        // Loop guard: never auto-reply to our own auto-reply (sender_type
        // 'system') or to AI-generated drafts. Everything else is fair game —
        // no rate limiting, no once-per-hour throttle. Every customer message
        // that matches a rule gets its reply.
        if ($message->sender_type === 'system' || $message->sender_type === 'ai') {
            return false;
        }

        // Get active rules for this workspace, ordered by priority (highest first)
        $rules = AutoReplyRule::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->where(function ($q) use ($channel) {
                $q->where('channel', 'all')->orWhere('channel', $channel);
            })
            ->orderByDesc('priority')
            ->get();

        // Pre-compute inbound count once (avoids N+1 if multiple rules have first_message_only)
        $inboundCount = $conversation->messages()->where('direction', 'inbound')->count();

        foreach ($rules as $rule) {
            // Skip if first_message_only and this isn't the first inbound message
            if ($rule->first_message_only && $inboundCount > 1) {
                continue;
            }

            if ($rule->matches($text)) {
                $this->sendAutoReply($rule, $message, $conversation);
                $rule->increment('usage_count');

                Log::info('AutoReply: Keyword rule matched', [
                    'rule_id'         => $rule->id,
                    'rule_name'       => $rule->name,
                    'conversation_id' => $conversation->id,
                    'message_id'      => $message->id,
                ]);

                return true; // First matching rule wins
            }
        }

        return false;
    }

    /**
     * Send the auto-reply message for a matched rule.
     */
    protected function sendAutoReply(AutoReplyRule $rule, Message $incomingMessage, Conversation $conversation): void
    {
        $subject = $rule->reply_subject ?? 'Re: ' . ($conversation->subject ?? '(no subject)');

        // Build threading headers for proper email threading
        $inReplyTo = $incomingMessage->message_id_header;
        $refs = $incomingMessage->references_header ?? [];
        if ($inReplyTo) {
            $refs[] = $inReplyTo;
        }

        $replyMessage = Message::create([
            'conversation_id'   => $conversation->id,
            'workspace_id'      => $conversation->workspace_id,
            'direction'         => 'outbound',
            'sender_type'       => 'system',
            'type'              => 'message',
            'subject'           => $subject,
            'body_html'         => $rule->reply_body,
            'body_text'         => strip_tags($rule->reply_body),
            'to_emails'         => $conversation->contact?->email ? [$conversation->contact->email] : [],
            'from_email'        => $conversation->emailAccount?->email,
            'from_name'         => $conversation->emailAccount?->display_name,
            'in_reply_to'       => $inReplyTo,
            'references_header' => array_values(array_unique($refs)),
            // `delivery_status` is an ENUM: queued/sent/delivered/failed/bounced.
            // Start as 'queued' while we attempt delivery; each channel-specific
            // send method flips it to 'sent' on success or 'failed' on error.
            'delivery_status'   => 'queued',
            'sent_at'           => now(),
        ]);

        // Dispatch through the appropriate channel API. Each branch is
        // best-effort and tags the message as 'failed' with the error if the
        // external API rejects the send, so operators can see why the reply
        // never left the building.
        $channel = $conversation->channel ?? 'email';

        try {
            switch ($channel) {
                case 'email':
                    $this->sendViaEmail($replyMessage, $conversation, $rule);
                    break;
                case 'telegram':
                    $this->sendViaTelegram($replyMessage, $conversation, $rule);
                    break;
                case 'whatsapp':
                    $this->sendViaWhatsApp($replyMessage, $conversation, $rule);
                    break;
                case 'sms':
                    $this->sendViaSms($replyMessage, $conversation, $rule);
                    break;
                case 'slack':
                    $this->sendViaSlack($replyMessage, $conversation, $rule);
                    break;
                case 'chat':
                case 'live_chat':
                    // Live chat is in-app — persisting with status 'sent' is
                    // sufficient; the visitor widget picks it up via the
                    // existing polling/broadcast channel.
                    $replyMessage->update(['delivery_status' => 'sent']);
                    break;
                default:
                    Log::warning('AutoReply: unsupported channel, marking sent as fallback', [
                        'channel' => $channel,
                        'rule_id' => $rule->id,
                    ]);
                    $replyMessage->update(['delivery_status' => 'sent']);
            }
        } catch (\Throwable $e) {
            Log::error('AutoReply: channel dispatch failed', [
                'channel' => $channel,
                'rule_id' => $rule->id,
                'message_id' => $replyMessage->id,
                'error' => $e->getMessage(),
            ]);
            $replyMessage->update([
                'delivery_status' => 'failed',
                'delivery_error'  => \Illuminate\Support\Str::limit($e->getMessage(), 240),
            ]);
        }

        // Update conversation timestamps
        $conversation->update([
            'last_message_at'   => now(),
            'first_response_at' => $conversation->first_response_at ?? now(),
        ]);
        $conversation->increment('messages_count');
    }

    /**
     * Send the auto-reply via email using the account's configured provider.
     */
    protected function sendViaEmail(Message $replyMessage, Conversation $conversation, AutoReplyRule $rule): void
    {
        $emailAccount = $conversation->emailAccount;

        if (! $emailAccount) {
            Log::warning('AutoReply: No email account on conversation', [
                'conversation_id' => $conversation->id,
                'rule_id'         => $rule->id,
            ]);
            $replyMessage->update(['delivery_status' => 'failed', 'delivery_error' => 'No email account configured']);
            return;
        }

        try {
            $sendService = app(EmailSendService::class);
            $sendService->sendReply($replyMessage, $emailAccount);
            // sendReply already updates delivery_status to 'sent' on success
        } catch (\Throwable $e) {
            Log::error('AutoReply: Failed to send email', [
                'error'   => $e->getMessage(),
                'rule_id' => $rule->id,
                'message_id' => $replyMessage->id,
            ]);
            $replyMessage->update([
                'delivery_status' => 'failed',
                'delivery_error'  => \Illuminate\Support\Str::limit($e->getMessage(), 240),
            ]);
        }
    }

    protected function getChannelIntegration(int $workspaceId, string $channel): ?\App\Models\ChannelIntegration
    {
        return \App\Models\ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', $channel)
            ->where('status', 'active')
            ->first();
    }

    protected function sendViaTelegram(Message $replyMessage, Conversation $conversation, AutoReplyRule $rule): void
    {
        $integration = $this->getChannelIntegration($conversation->workspace_id, 'telegram');
        $botToken = $integration?->credentials['bot_token'] ?? null;
        $chatId = $conversation->channel_conversation_id;
        if (! $botToken || ! $chatId) {
            throw new \RuntimeException('Telegram not configured or missing chat_id');
        }
        $text = strip_tags($rule->reply_body ?? '');
        $result = (new \App\Services\Channels\TelegramService($botToken))->sendMessage($chatId, $text);
        $replyMessage->update([
            'delivery_status' => 'sent',
            'channel_message_id' => isset($result['message_id']) ? "tg_{$chatId}_{$result['message_id']}" : null,
        ]);
    }

    protected function sendViaWhatsApp(Message $replyMessage, Conversation $conversation, AutoReplyRule $rule): void
    {
        $integration = $this->getChannelIntegration($conversation->workspace_id, 'whatsapp');
        $creds = $integration?->credentials ?? [];
        $phoneNumberId = $creds['phone_number_id'] ?? null;
        $accessToken = $creds['access_token'] ?? null;
        $to = $conversation->contact?->phone;
        if (! $phoneNumberId || ! $accessToken || ! $to) {
            throw new \RuntimeException('WhatsApp not configured or missing phone');
        }
        $text = strip_tags($rule->reply_body ?? '');
        $result = app(\App\Services\Channels\WhatsAppService::class)
            ->sendText($phoneNumberId, $accessToken, $to, $text);
        $replyMessage->update([
            'delivery_status' => 'sent',
            'channel_message_id' => $result['messages'][0]['id'] ?? null,
        ]);
    }

    protected function sendViaSms(Message $replyMessage, Conversation $conversation, AutoReplyRule $rule): void
    {
        $integration = $this->getChannelIntegration($conversation->workspace_id, 'sms');
        $creds = $integration?->credentials ?? [];
        $sid = $creds['sid'] ?? null;
        $token = $creds['auth_token'] ?? null;
        $from = $creds['phone_number'] ?? null;
        $to = $conversation->contact?->phone;
        if (! $sid || ! $token || ! $from || ! $to) {
            throw new \RuntimeException('SMS not configured or missing phone');
        }
        $text = strip_tags($rule->reply_body ?? '');
        $result = app(\App\Services\Channels\TwilioSMSService::class)
            ->sendSMS($to, $text, $from, $sid, $token);
        $replyMessage->update([
            'delivery_status' => 'sent',
            'channel_message_id' => $result->sid ?? null,
        ]);
    }

    protected function sendViaSlack(Message $replyMessage, Conversation $conversation, AutoReplyRule $rule): void
    {
        $integration = $this->getChannelIntegration($conversation->workspace_id, 'slack');
        $botToken = $integration?->credentials['bot_token'] ?? ($integration?->credentials['access_token'] ?? null);
        $slackChannel = $conversation->channel_conversation_id;
        if (! $botToken || ! $slackChannel) {
            throw new \RuntimeException('Slack not configured or missing channel id');
        }
        $text = strip_tags($rule->reply_body ?? '');
        $result = app(\App\Services\Channels\SlackService::class)
            ->postMessage($botToken, $slackChannel, $text);
        $replyMessage->update([
            'delivery_status' => 'sent',
            'channel_message_id' => $result['ts'] ?? null,
        ]);
    }
}
