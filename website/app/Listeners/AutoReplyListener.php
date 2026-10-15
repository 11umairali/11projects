<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Jobs\GenerateAIReplyJob;
use App\Models\Workspace;
use App\Services\AutoReplyService;
use Illuminate\Support\Facades\Log;

/**
 * Runs BOTH keyword auto-reply rules AND the AI auto-reply fallback the
 * instant a MessageReceived event fires, on the same request that processed
 * the inbound webhook. This listener is intentionally SYNCHRONOUS (no
 * ShouldQueue) so a customer's "hey" message gets its reply within the same
 * second — delegating to the queue would delay the reply by up to a minute
 * on the scheduler tick.
 *
 * Keyword rules run first (cheap). If no keyword matched and the workspace
 * has AI auto-reply enabled, we dispatch GenerateAIReplyJob synchronously so
 * the AI reply / draft lands in the conversation before the HTTP response
 * returns.
 *
 * The inline paths in InboxApiController / SyncEmailAccountJob already fire
 * the same two checks for email specifically; this listener extends the same
 * behavior to Telegram / WhatsApp / SMS / Slack / Live Chat. De-dupe is
 * handled by GenerateAIReplyJob's unique lock on the message id.
 */
class AutoReplyListener
{
    public function handle(MessageReceived $event): void
    {
        $message = $event->message;
        $conversation = $event->conversation;

        if ($message->direction !== 'inbound') {
            return;
        }

        // 1) Keyword auto-reply (cheap, deterministic). Returns true if a rule
        //    matched and a reply was sent; in that case we do NOT also fire AI.
        $keywordReplied = false;
        try {
            $keywordReplied = app(AutoReplyService::class)
                ->processIncomingMessage($message, $conversation);
        } catch (\Throwable $e) {
            Log::error('AutoReplyListener: keyword processing failed', [
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($keywordReplied) {
            return;
        }

        // 2) AI auto-reply fallback. Email has a per-account flag
        //    (EmailAccount::ai_auto_reply) handled inline by the email sync
        //    paths — we don't duplicate it here to avoid competing writes.
        //    Non-email channels fall back to the workspace's AIConfig toggle
        //    (auto_reply_enabled).
        $channel = $conversation->channel ?? 'email';
        if ($channel === 'email') {
            return;
        }

        try {
            $workspace = Workspace::with('aiConfig')->find($conversation->workspace_id);
            $aiEnabled = (bool) ($workspace?->aiConfig?->auto_reply_enabled ?? false);
            if (! $aiEnabled) {
                return;
            }

            if (! class_exists(GenerateAIReplyJob::class)) {
                return;
            }

            // dispatchSync runs the job in the current PHP process — same
            // speed as the keyword reply above. The job itself de-dupes via
            // its uniqueId() lock on the message id.
            GenerateAIReplyJob::dispatchSync($message, null);
        } catch (\Throwable $e) {
            Log::warning('AutoReplyListener: AI reply dispatch failed', [
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
