<?php

namespace App\Livewire\Inbox;

use App\Jobs\SendScheduledEmailJob;
use App\Models\Attachment;
use App\Models\CannedResponse;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIManager;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ReplyComposer extends Component
{
    use WithFileUploads;
    use AuthorizesWorkspaceActions;

    public ?int $conversationId = null;
    public string $body = '';
    public string $mode = 'reply'; // reply, forward, note
    public string $ccEmails = '';
    public string $bccEmails = '';
    public bool $showCcBcc = false;

    // File uploads
    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    // AI suggestion state
    public bool $showAiSuggestion = false;
    public string $aiSuggestion = '';
    public bool $aiLoading = false;

    // AI Write from prompt
    public string $aiWritePrompt = '';
    public string $aiWriteTone = 'professional';
    public bool $showAiWritePrompt = false;

    // Canned responses
    public bool $showCannedResponses = false;
    public string $cannedSearch = '';

    // Schedule send
    public bool $showSchedule = false;
    public ?string $scheduledAt = null;

    // Status
    public bool $sending = false;

    // Undo send
    public bool $showUndoBar = false;
    public ?int $pendingSendMessageId = null;
    public int $undoCountdown = 10;

    // Conversation channel (for hiding CC/BCC on non-email)
    public string $conversationChannel = 'email';

    /**
     * Profile tz → workspace tz → app default. The inbox JS writes the
     * browser tz to the profile on load so this is always up-to-date.
     */
    protected function userTimezone(): string
    {
        $profile = auth()->user()->timezone;
        $workspace = auth()->user()->activeWorkspace?->timezone;
        $resolved = $profile ?: ($workspace ?: config('app.timezone', 'UTC'));

        \Illuminate\Support\Facades\Log::info('ReplyComposer: userTimezone resolved', [
            'profile_tz' => $profile,
            'workspace_tz' => $workspace,
            'resolved' => $resolved,
        ]);

        return $resolved;
    }

    public function mount(?int $conversationId = null): void
    {
        if ($conversationId) {
            $this->conversationId = $conversationId;
            $conv = Conversation::where('workspace_id', auth()->user()->active_workspace_id)->find($conversationId);
            $this->conversationChannel = $conv?->channel ?? 'email';
        }
    }

    #[On('conversation-selected')]
    public function onConversationSelected($conversationId = null): void
    {
        \Illuminate\Support\Facades\Log::info('ReplyComposer::onConversationSelected fired', [
            'raw_args' => func_get_args(),
        ]);

        // Livewire dispatches with a keyed payload (e.g.
        // Livewire.dispatch('conversation-selected', {conversationId: 42})).
        // Depending on the version / call shape, the listener may receive
        // either the named scalar or the full array — accept both to stay
        // robust. Also coerce strings-that-look-like-ids to int.
        if (is_array($conversationId)) {
            $conversationId = $conversationId['conversationId'] ?? ($conversationId[0] ?? null);
        }
        $conversationId = (int) $conversationId;
        if ($conversationId <= 0) {
            \Illuminate\Support\Facades\Log::warning('ReplyComposer::onConversationSelected — invalid payload, ignoring', [
                'raw' => func_get_args(),
            ]);
            return;
        }

        $this->conversationId = $conversationId;
        $this->body = '';
        $this->mode = 'reply';
        $this->ccEmails = '';
        $this->bccEmails = '';
        $this->showCcBcc = false;
        $this->attachments = [];
        $this->showAiSuggestion = false;
        $this->aiSuggestion = '';
        $this->showAiWritePrompt = false;
        $this->aiWritePrompt = '';
        $this->aiWriteTone = 'professional';
        $this->showCannedResponses = false;
        $this->cannedSearch = '';
        $this->showSchedule = false;
        $this->scheduledAt = null;

        $conv = Conversation::where('workspace_id', auth()->user()->active_workspace_id)->find($conversationId);
        $this->conversationChannel = $conv?->channel ?? 'email';
    }

    #[On('load-ai-draft-to-composer')]
    public function loadAiDraft(string $body): void
    {
        $this->body = $body;
        $this->mode = 'reply';
    }

    public function updatedBody(): void
    {
        // The reply box is a rich-text editor, so $this->body arrives as HTML
        // like "<p>/hii</p>". Strip tags and decode entities before checking
        // for the "/" shortcut trigger, otherwise the leading "<" swallows it.
        $plain = trim(html_entity_decode(
            strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $this->body ?? '')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        if (str_starts_with($plain, '/') && strlen($plain) > 1) {
            // Only use the first line/word after "/" as the search — avoids
            // matching the entire multi-paragraph body against the shortcut.
            $firstLine = explode("\n", $plain, 2)[0];
            $this->cannedSearch = ltrim($firstLine, '/');
            $this->showCannedResponses = true;
        } else {
            $this->showCannedResponses = false;
            $this->cannedSearch = '';
        }
    }

    public function setMode(string $mode): void
    {
        if (in_array($mode, ['reply', 'forward', 'note'])) {
            $this->mode = $mode;
        }
    }

    protected function getConversation(): ?Conversation
    {
        if (!$this->conversationId) return null;

        return Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->with('contact')
            ->find($this->conversationId);
    }

    // ---- CC/BCC Validation ----

    protected function validateCcBccEmails(): bool
    {
        if ($this->ccEmails) {
            $ccList = array_filter(array_map('trim', explode(',', $this->ccEmails)));
            foreach ($ccList as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->addError('ccEmails', "Invalid CC email: {$email}");
                    return false;
                }
            }
        }

        if ($this->bccEmails) {
            $bccList = array_filter(array_map('trim', explode(',', $this->bccEmails)));
            foreach ($bccList as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->addError('bccEmails', "Invalid BCC email: {$email}");
                    return false;
                }
            }
        }

        return true;
    }

    // ---- Phone validation ----

    protected function validatePhoneForChannel(Conversation $conversation): bool
    {
        $channel = $conversation->channel ?? 'email';
        if (!in_array($channel, ['sms', 'whatsapp'])) return true;

        $phone = $conversation->contact?->phone;
        if (!$phone || !preg_match('/^\+?[1-9]\d{6,14}$/', preg_replace('/[\s\-\(\)]/', '', $phone))) {
            session()->flash('error', 'Contact does not have a valid phone number for ' . strtoupper($channel) . ' delivery.');
            return false;
        }

        return true;
    }

    // ---- Send methods ----

    public function send(?int $fallbackConversationId = null): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if ($this->sending) return;
        $this->sending = true;

        // Alpine passes the currently-active conversation id as a fallback
        // so a race between the Livewire `conversation-selected` listener
        // and the Send click cannot strand the user with a null composer
        // state. Only adopted when our own state is genuinely missing, so
        // an intentional composition still wins.
        if (!$this->conversationId && $fallbackConversationId) {
            $this->conversationId = $fallbackConversationId;
            \Illuminate\Support\Facades\Log::info('ReplyComposer::send() adopted fallback conversationId from client', [
                'fallback' => $fallbackConversationId,
            ]);
        }

        \Illuminate\Support\Facades\Log::info('ReplyComposer::send() started', [
            'conversation_id' => $this->conversationId,
            'mode' => $this->mode,
            'body_length' => strlen($this->body),
            'user_id' => auth()->id(),
        ]);

        if (!$this->conversationId || !trim($this->body)) {
            \Illuminate\Support\Facades\Log::warning('ReplyComposer::send() aborted - no conversation or empty body');
            $this->sending = false;
            return;
        }

        $conversation = $this->getConversation();
        if (!$conversation) {
            $this->sending = false;
            return;
        }

        // Auto-reopen closed conversations when replying (like most email clients)
        if ($this->mode !== 'note' && $conversation->status === 'closed') {
            $conversation->update(['status' => 'open', 'resolved_at' => null]);
        }

        // Block replies to spam only (not closed — those get auto-reopened above)
        if ($this->mode !== 'note' && $conversation->status === 'spam') {
            session()->flash('error', 'Cannot reply to a spam conversation. Remove from spam first.');
            $this->sending = false;
            return;
        }

        // Validate CC/BCC emails
        if (!$this->validateCcBccEmails()) {
            $this->sending = false;
            return;
        }

        // Validate phone for SMS/WhatsApp channels
        if ($this->mode !== 'note' && !$this->validatePhoneForChannel($conversation)) {
            $this->sending = false;
            return;
        }

        // Validate scheduled date if provided
        if ($this->scheduledAt) {
            $this->validate(['scheduledAt' => 'date|after:now']);
        }

        // Remove expired temp files before validation
        if (!empty($this->attachments)) {
            $this->attachments = array_values(array_filter($this->attachments, fn ($f) => $f && method_exists($f, 'exists') && $f->exists()));
        }
        if (!empty($this->attachments)) {
            $this->validate([
                'attachments.*' => 'file|max:25600|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip,mp4,mp3',
            ]);
        }

        $currentMode = $this->mode;
        $currentScheduledAt = $this->scheduledAt;

        $this->withOperationLock("reply-{$this->conversationId}", function () use ($conversation, $currentMode, $currentScheduledAt) {
            $messageData = [
                'conversation_id' => $conversation->id,
                'workspace_id' => $conversation->workspace_id,
                'direction' => 'outbound',
                'sender_type' => 'agent',
                'sender_id' => auth()->id(),
                'from_email' => auth()->user()->email,
                'from_name' => auth()->user()->name,
            ];

            // $this->body already contains HTML from the rich-text editor
            // (e.g. "<p>hiiii</p>"). Store HTML as-is for body_html and extract
            // a plain-text version for body_text. Escaping it again with e()
            // would produce literal "&lt;p&gt;..." strings in the stored HTML.
            //
            // For non-email channels (telegram/whatsapp/sms/slack/live_chat)
            // we intentionally leave body_html empty so the inbox renders via
            // the plain-text bubble template (dark theme) instead of the
            // email-iframe renderer, which defaults to a white background.
            $bodyHtml = $this->body;
            $bodyPlain = trim(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $bodyHtml)));
            $channel = $conversation->channel ?? 'email';
            $storedBodyHtml = $channel === 'email' ? $bodyHtml : '';

            if ($this->mode === 'note') {
                $messageData['type'] = 'note';
                $messageData['body_text'] = $bodyPlain;
                $messageData['body_html'] = $storedBodyHtml;
            } else {
                $messageData['type'] = 'message';
                $messageData['body_text'] = $bodyPlain;
                $messageData['body_html'] = $storedBodyHtml;
                $messageData['subject'] = $this->mode === 'forward'
                    ? 'Fwd: ' . ($conversation->subject ?? '(no subject)')
                    : 'Re: ' . ($conversation->subject ?? '(no subject)');
                $messageData['to_emails'] = $conversation->contact?->email ? [$conversation->contact->email] : [];

                // Set threading headers for proper email threading
                $lastInbound = $conversation->messages()
                    ->where('direction', 'inbound')
                    ->whereNotNull('message_id_header')
                    ->latest()
                    ->first();
                if ($lastInbound) {
                    $messageData['in_reply_to'] = $lastInbound->message_id_header;
                    $refs = $lastInbound->references_header ?? [];
                    $refs[] = $lastInbound->message_id_header;
                    $messageData['references_header'] = array_unique($refs);
                }
                $messageData['delivery_status'] = 'queued';

                if ($this->ccEmails) {
                    $messageData['cc_emails'] = array_filter(array_map('trim', explode(',', $this->ccEmails)));
                }
                if ($this->bccEmails) {
                    $messageData['bcc_emails'] = array_filter(array_map('trim', explode(',', $this->bccEmails)));
                }

                if ($this->scheduledAt) {
                    // Parse in user's tz, store as UTC — same logic as ComposeEmail
                    $tz = $this->userTimezone();
                    $messageData['scheduled_at'] = \Carbon\Carbon::parse($this->scheduledAt, $tz)->utc();
                    $messageData['schedule_status'] = 'pending';
                    $messageData['delivery_status'] = 'queued';
                } elseif ($conversation->channel === 'email') {
                    // Undo-send: 10-second grace period for email
                    $messageData['scheduled_at'] = now()->addSeconds(10);
                    $messageData['schedule_status'] = 'pending';
                    $messageData['delivery_status'] = 'queued';
                } else {
                    // Non-email channels: send immediately
                    $messageData['sent_at'] = now();
                    $messageData['delivery_status'] = 'sent';
                }
            }

            $message = Message::create($messageData);

            // Handle attachments
            if (!empty($this->attachments)) {
                foreach ($this->attachments as $file) {
                    $path = $file->store('attachments/' . $conversation->workspace_id, 'local');
                    Attachment::create([
                        'message_id' => $message->id,
                        'filename' => basename($path),
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'storage_path' => $path,
                        'is_inline' => false,
                    ]);
                }
            }

            // Determine delivery path
            $useUndoSend = $currentMode !== 'note'
                && !$currentScheduledAt
                && $conversation->channel === 'email';

            if ($currentMode !== 'note' && !$currentScheduledAt && !$useUndoSend) {
                // Non-email channels: deliver immediately
                \Illuminate\Support\Facades\Log::info('ReplyComposer: delivering message', [
                    'message_id' => $message->id,
                    'channel' => $conversation->channel,
                    'to_emails' => $message->to_emails,
                    'subject' => $message->subject,
                ]);
                $this->deliverViaChannel($message, $conversation);
            }

            // Update conversation
            if ($this->mode !== 'note') {
                $conversation->update([
                    'preview' => \Illuminate\Support\Str::limit($this->body, 200),
                    'last_message_at' => now(),
                    'first_response_at' => $conversation->first_response_at ?? now(),
                    'is_read' => true,
                ]);
                $conversation->increment('messages_count');
            }

            // Reset composer fields
            $this->body = '';
            $this->ccEmails = '';
            $this->bccEmails = '';
            $this->showCcBcc = false;
            $this->attachments = [];
            $this->showAiSuggestion = false;
            $this->aiSuggestion = '';
            $this->showSchedule = false;
            $this->scheduledAt = null;
            $this->mode = 'reply';

            $this->dispatch('conversations-updated');

            if ($useUndoSend) {
                // Show undo bar instead of success flash
                $this->pendingSendMessageId = $message->id;
                $this->showUndoBar = true;
                $this->undoCountdown = 10;
            } elseif ($currentScheduledAt) {
                session()->flash('success', 'Reply scheduled.');
            } else {
                $testLabel = ($currentMode !== 'note' && $conversation->channel !== 'email' && config('services.channel_test_mode')) ? ' (Test Mode — not actually delivered)' : '';
                session()->flash('success', ($currentMode === 'note' ? 'Note added.' : 'Reply sent.') . $testLabel);
            }
        });

        $this->sending = false;
    }

    public function sendAndClose(?int $fallbackConversationId = null): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $this->send($fallbackConversationId);

        if ($this->conversationId) {
            Conversation::where('workspace_id', auth()->user()->active_workspace_id)
                ->where('id', $this->conversationId)
                ->update([
                    'status' => 'closed',
                    'resolved_at' => now(),
                ]);
            $this->dispatch('conversations-updated');
            session()->flash('success', 'Reply sent and conversation closed.');
        }
    }

    public function scheduleSend(?int $fallbackConversationId = null): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        // Adopt the Alpine-provided fallback when our own state is null —
        // same safety net as send().
        if (!$this->conversationId && $fallbackConversationId) {
            $this->conversationId = $fallbackConversationId;
        }

        if (!$this->scheduledAt) return;

        // Parse the datetime-local input in the user's timezone — otherwise
        // an India user picking 11:30 IST would fail isPast() if server is
        // still at 10:00 UTC (looks like future) but succeed when checked
        // against 10:00 IST (past). Either way, consistency matters.
        if (\Carbon\Carbon::parse($this->scheduledAt, $this->userTimezone())->isPast()) {
            session()->flash('error', __('Scheduled time must be in the future.'));
            return;
        }

        $this->send();
    }

    public function addNote(): void
    {
        $this->mode = 'note';
    }

    // ---- Undo Send ----

    public function undoSend(): void
    {
        if ($this->pendingSendMessageId) {
            Message::where('id', $this->pendingSendMessageId)
                ->where('schedule_status', 'pending')
                ->update(['schedule_status' => 'cancelled']);

            $this->showUndoBar = false;
            $this->pendingSendMessageId = null;
            session()->flash('success', 'Email send cancelled.');
        }
    }

    public function confirmSend(): void
    {
        if ($this->pendingSendMessageId) {
            $message = Message::find($this->pendingSendMessageId);
            if ($message && $message->schedule_status === 'pending') {
                SendScheduledEmailJob::dispatchSync($message);
            }
            $this->showUndoBar = false;
            $this->pendingSendMessageId = null;
            $this->dispatch('conversations-updated');
        }
    }

    #[On('insert-canned-response')]
    public function onInsertCannedResponse(string $content): void
    {
        $this->body = ($this->body ? $this->body . "\n\n" : '') . $content;
    }

    // ---- Channel delivery ----

    protected function deliverViaChannel(Message $message, Conversation $conversation): void
    {
        try {
            $channel = $conversation->channel ?? 'email';
            $workspaceId = $conversation->workspace_id;

            // Test mode: bypass actual API calls for non-email channels
            if ($channel !== 'email' && config('services.channel_test_mode')) {
                \Illuminate\Support\Facades\Log::info("ReplyComposer: TEST MODE — bypassing {$channel} API, marking as sent", [
                    'message_id' => $message->id,
                    'channel' => $channel,
                ]);
                $message->update(['delivery_status' => 'sent', 'sent_at' => now()]);
                return;
            }

            match ($channel) {
                'sms' => $this->deliverSms($message, $conversation, $workspaceId),
                'whatsapp' => $this->deliverWhatsApp($message, $conversation, $workspaceId),
                'telegram' => $this->deliverTelegram($message, $conversation, $workspaceId),
                'slack' => $this->deliverSlack($message, $conversation, $workspaceId),
                'live_chat' => $this->deliverLiveChat($message, $conversation, $workspaceId),
                'email' => $this->deliverEmail($message, $conversation, $workspaceId),
                default => $message->update(['delivery_status' => 'sent']),
            };
        } catch (\Throwable $e) {
            // delivery_error is VARCHAR(255) — truncate so storing an API error
            // body (often 400+ chars from Telegram/Meta) does not itself crash.
            $message->update([
                'delivery_status' => 'failed',
                'delivery_error' => \Illuminate\Support\Str::limit($e->getMessage(), 240),
            ]);
            \Illuminate\Support\Facades\Log::error("Reply delivery failed [{$conversation->channel}]", [
                'error' => $e->getMessage(),
                'message_id' => $message->id,
            ]);
        }
    }

    protected function deliverSms(Message $message, Conversation $conversation, int $workspaceId): void
    {
        $integration = \App\Models\ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'sms')->where('status', 'active')->first();

        if (!$integration) {
            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'SMS not connected']);
            return;
        }

        $creds = $integration->credentials ?? [];
        $toPhone = $conversation->contact?->phone;

        if (!$toPhone) {
            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'No phone number for contact']);
            return;
        }

        $smsService = app(\App\Services\Channels\TwilioSMSService::class);
        $result = $smsService->sendSMS($toPhone, $message->body_text, $creds['phone_number'] ?? null, $creds['sid'] ?? null, $creds['auth_token'] ?? null);
        $message->update([
            'delivery_status' => 'sent',
            'sent_at' => now(),
            'channel_message_id' => $result->sid ?? null,
        ]);
    }

    protected function deliverWhatsApp(Message $message, Conversation $conversation, int $workspaceId): void
    {
        $integration = \App\Models\ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'whatsapp')->where('status', 'active')->first();

        if (!$integration) {
            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'WhatsApp not connected']);
            return;
        }

        $creds = $integration->credentials ?? [];
        $toPhone = $conversation->contact?->phone;

        if (!$toPhone) {
            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'No phone number for contact']);
            return;
        }

        $waService = app(\App\Services\Channels\WhatsAppService::class);
        $waResult = $waService->sendText($creds['phone_number_id'] ?? '', $creds['access_token'] ?? '', $toPhone, $message->body_text);
        $message->update([
            'delivery_status' => 'sent',
            'sent_at' => now(),
            'channel_message_id' => $waResult['messages'][0]['id'] ?? null,
        ]);
    }

    protected function deliverTelegram(Message $message, Conversation $conversation, int $workspaceId): void
    {
        $integration = \App\Models\ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'telegram')->where('status', 'active')->first();

        if (!$integration) {
            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'Telegram not connected']);
            return;
        }

        $chatId = $conversation->channel_conversation_id;
        if (!$chatId) {
            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'No Telegram chat ID for this conversation']);
            return;
        }

        $creds = $integration->credentials ?? [];
        $botToken = $creds['bot_token'] ?? '';

        $telegramService = new \App\Services\Channels\TelegramService($botToken);
        $tgResult = $telegramService->sendMessage($chatId, $message->body_text);
        $message->update([
            'delivery_status' => 'sent',
            'sent_at' => now(),
            'channel_message_id' => isset($tgResult['message_id']) ? "tg_{$chatId}_{$tgResult['message_id']}" : null,
        ]);
    }

    protected function deliverSlack(Message $message, Conversation $conversation, int $workspaceId): void
    {
        $integration = \App\Models\ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'slack')->where('status', 'active')->first();

        if (!$integration) {
            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'Slack not connected']);
            return;
        }

        $channelId = $conversation->channel_conversation_id;
        if (!$channelId) {
            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'No Slack channel ID for this conversation']);
            return;
        }

        $creds = $integration->credentials ?? [];
        $botToken = $creds['access_token'] ?? $creds['bot_token'] ?? '';

        $slackService = app(\App\Services\Channels\SlackService::class);
        $slackService->postMessage($botToken, $channelId, $message->body_text);
        $message->update(['delivery_status' => 'sent', 'sent_at' => now()]);
    }

    protected function deliverLiveChat(Message $message, Conversation $conversation, int $workspaceId): void
    {
        $sessionId = $conversation->channel_conversation_id;
        if (!$sessionId) {
            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'No live chat session ID for this conversation']);
            return;
        }

        $liveChatService = app(\App\Services\Channels\LiveChatService::class);
        $liveChatService->sendToVisitor($sessionId, $message->body_text, auth()->user()->name, 'agent');
        $message->update(['delivery_status' => 'sent', 'sent_at' => now()]);
    }

    protected function deliverEmail(Message $message, Conversation $conversation, int $workspaceId): void
    {
        $emailAccount = $conversation->emailAccount
            ?? \App\Models\EmailAccount::where('workspace_id', $workspaceId)->where('status', 'connected')->first();

        if ($emailAccount) {
            \Illuminate\Support\Facades\Log::info('ReplyComposer: sending email via ' . $emailAccount->provider, [
                'message_id' => $message->id,
                'account_id' => $emailAccount->id,
                'account_email' => $emailAccount->email,
                'to' => $message->to_emails,
                'subject' => $message->subject,
            ]);
            try {
                $sendService = app(\App\Services\Email\EmailSendService::class);
                $sendService->sendReply($message, $emailAccount);
                $message->update(['delivery_status' => 'sent', 'sent_at' => now()]);
                \Illuminate\Support\Facades\Log::info('ReplyComposer: email sent successfully', ['message_id' => $message->id]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('ReplyComposer: email send FAILED', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $message->update(['delivery_status' => 'failed', 'delivery_error' => $e->getMessage()]);
                session()->flash('error', 'Failed to send email. Check logs for details.');
            }
        } else {
            \Illuminate\Support\Facades\Log::error('ReplyComposer: no email account found — cannot send', [
                'message_id' => $message->id,
                'workspace_id' => $workspaceId,
            ]);
            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'No email account connected. Add one in Settings → Email.']);
            session()->flash('error', 'No email account connected. Please add one in Settings → Email.');
        }
    }

    // ---- AI suggestion ----

    public function aiSuggest(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $conversation = $this->getConversation();
        if (!$conversation) return;

        $this->aiLoading = true;
        $this->showAiSuggestion = false;
        $this->aiSuggestion = '';

        $lastMessage = $conversation->messages()
            ->where('direction', 'inbound')
            ->where('type', 'message')
            ->latest()
            ->first();

        if (!$lastMessage) {
            $this->aiLoading = false;
            session()->flash('error', 'No inbound message found to generate a reply for.');
            return;
        }

        try {
            $workspace = $conversation->workspace;
            $aiManager = app(AIManager::class);

            $history = $conversation->messages()
                ->whereIn('type', ['message'])
                ->orderBy('created_at')
                ->limit(10)
                ->get()
                ->map(fn ($m) => [
                    'role' => $m->direction === 'inbound' ? 'user' : 'assistant',
                    'content' => $m->body_text ?? strip_tags($m->body_html ?? ''),
                ])->toArray();

            $response = $aiManager->generateReply($workspace, $lastMessage->body_text ?? strip_tags($lastMessage->body_html ?? ''), [
                'conversation_history' => $history,
                'sender_name' => $conversation->contact?->full_name,
                'subject' => $conversation->subject,
                'agent_name' => auth()->user()->name,
            ]);

            $this->aiSuggestion = $response->content;
            $this->showAiSuggestion = true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AI suggestion failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'AI generation failed. Please try again.');
        }

        $this->aiLoading = false;
    }

    public function acceptAiSuggestion(): void
    {
        $this->body = $this->aiSuggestion;
        $this->showAiSuggestion = false;
        $this->aiSuggestion = '';
    }

    public function discardAiSuggestion(): void
    {
        $this->showAiSuggestion = false;
        $this->aiSuggestion = '';
    }

    public function regenerateAiSuggestion(): void
    {
        $this->showAiSuggestion = false;
        $this->aiSuggestion = '';
        $this->aiSuggest();
    }

    // ---- AI Write from prompt ----

    public function openAiWrite(): void
    {
        $this->showAiWritePrompt = true;
        $this->aiWritePrompt = '';
        $this->aiWriteTone = 'professional';
    }

    public function submitAiWrite(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (!trim($this->aiWritePrompt)) return;

        $this->aiLoading = true;
        $this->showAiWritePrompt = false;

        try {
            $workspace = auth()->user()->activeWorkspace;
            $aiManager = app(AIManager::class);

            $conversation = $this->getConversation();
            $response = $aiManager->generateCompose($workspace, $this->aiWritePrompt, $this->aiWriteTone, [
                'subject' => $conversation?->subject,
                'recipient_name' => $conversation?->contact?->full_name,
                'sender_name' => auth()->user()->name,
            ]);

            $this->aiSuggestion = $response->content;
            $this->showAiSuggestion = true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AI write failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'AI write failed. Please try again.');
        }

        $this->aiLoading = false;
    }

    // ---- AI Transform existing text ----

    public function aiTransform(string $action, ?string $param = null): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (!trim($this->body)) {
            session()->flash('error', 'Write some text first before using AI transform.');
            return;
        }

        // Validate action and param against allowlists to prevent prompt injection
        $allowedActions = ['improve', 'shorter', 'longer', 'grammar', 'tone', 'translate'];
        if (!in_array($action, $allowedActions)) return;

        $allowedTones = ['professional', 'friendly', 'casual', 'persuasive'];
        $allowedLanguages = ['English', 'Spanish', 'French', 'German', 'Portuguese', 'Italian', 'Dutch', 'Japanese', 'Chinese', 'Korean', 'Arabic', 'Hindi', 'Russian'];

        if ($action === 'tone' && !in_array($param, $allowedTones)) return;
        if ($action === 'translate' && !in_array($param, $allowedLanguages)) return;

        $this->aiLoading = true;

        try {
            $workspace = auth()->user()->activeWorkspace;
            $aiManager = app(AIManager::class);

            $prompt = match ($action) {
                'improve' => "Improve the following email reply. Make it clearer, more professional, and well-structured. Keep the same meaning:\n\n{$this->body}",
                'shorter' => "Make the following email reply shorter and more concise while keeping the key points:\n\n{$this->body}",
                'longer' => "Expand the following email reply with more detail, examples, or context while keeping it natural:\n\n{$this->body}",
                'grammar' => "Fix any grammar, spelling, and punctuation errors in the following text. Keep the same tone and style:\n\n{$this->body}",
                'tone' => "Rewrite the following email reply in a {$param} tone. Keep the same meaning:\n\n{$this->body}",
                'translate' => "Translate the following email reply to {$param}. Keep the same tone and meaning:\n\n{$this->body}",
                default => $this->body,
            };

            $response = $aiManager->generateCompose($workspace, $prompt, 'professional');

            $this->aiSuggestion = $response->content;
            $this->showAiSuggestion = true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AI transform failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'AI transform failed. Please try again.');
        }

        $this->aiLoading = false;
    }

    // ---- Canned responses ----

    public function insertCannedResponse(int $cannedId): void
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $canned = CannedResponse::where('workspace_id', $workspaceId)->find($cannedId);

        if ($canned) {
            // Escape HTML in canned response content to prevent XSS
            $this->body = $canned->content;
            $canned->increment('usage_count');
        }

        $this->showCannedResponses = false;
        $this->cannedSearch = '';
    }

    // ---- Attachment upload ----

    public function uploadAttachment(): void
    {
        $this->validate([
            'attachments.*' => 'file|max:25600|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip,mp4,mp3',
        ]);
    }

    public function removeAttachment(int $index): void
    {
        if (isset($this->attachments[$index])) {
            unset($this->attachments[$index]);
            $this->attachments = array_values($this->attachments);
        }
    }

    public function render()
    {
        $cannedResponses = collect();

        if ($this->showCannedResponses && $this->cannedSearch) {
            $workspaceId = auth()->user()->active_workspace_id;
            $term = '%' . $this->cannedSearch . '%';
            $cannedResponses = CannedResponse::where('workspace_id', $workspaceId)
                ->where(function ($q) use ($term) {
                    $q->where('title', 'like', $term)
                        ->orWhere('shortcut', 'like', $term)
                        ->orWhere('content', 'like', $term);
                })
                ->where(function ($q) {
                    $q->whereNull('user_id')
                        ->orWhere('user_id', auth()->id());
                })
                ->orderBy('usage_count', 'desc')
                ->limit(8)
                ->get();
        }

        return view('livewire.inbox.reply-composer', [
            'cannedResponses' => $cannedResponses,
        ]);
    }
}
