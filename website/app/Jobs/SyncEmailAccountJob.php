<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Services\Email\EmailSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncEmailAccountJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Hold the uniqueness lock for 10 minutes so a long-running sync cannot be
     * duplicated by the every-minute scheduler tick. The lock auto-releases
     * when the job finishes (Laravel removes it on success/failure/release).
     */
    public int $uniqueFor = 600;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    /**
     * Hard cap on job runtime. The sync service aims for ~45s per run and
     * uses a resumable cursor (sync_folders JSON), so we don't need a long
     * timeout here — cutting it keeps the queue worker cycling smoothly.
     */
    public int $timeout = 90;

    public function __construct(
        public EmailAccount $emailAccount
    ) {
        $this->onQueue('email-sync');
    }

    /**
     * The unique ID for this job (prevents duplicate syncs for the same account).
     */
    public function uniqueId(): string
    {
        return 'sync-email-' . $this->emailAccount->id;
    }

    /**
     * Execute the job.
     */
    public function handle(EmailSyncService $syncService): void
    {
        Log::info("SyncEmailAccountJob: Starting sync for account {$this->emailAccount->id} ({$this->emailAccount->email})");

        try {
            // Refresh OAuth token if needed before sync, with a lock to prevent
            // concurrent refresh attempts across parallel sync workers
            if ($this->emailAccount->isOAuth() && $this->isTokenExpired()) {
                $lock = Cache::lock("oauth-refresh-{$this->emailAccount->id}", 60);

                if ($lock->get()) {
                    try {
                        // Run token refresh synchronously so the sync can proceed
                        // with a valid token (async dispatch would leave the token
                        // expired and cause the sync to fail)
                        (new RefreshOAuthTokenJob($this->emailAccount))->handle();
                        $this->emailAccount->refresh();
                    } finally {
                        $lock->forceRelease();
                    }
                } else {
                    // Another worker is already refreshing -- release back to queue with delay
                    Log::info("SyncEmailAccountJob: OAuth refresh already in progress for account {$this->emailAccount->id}, releasing...");
                    $this->release(5);
                    return;
                }
            }

            $newCount = $syncService->syncAccount($this->emailAccount);

            if ($newCount > 0) {
                Log::info("SyncEmailAccountJob: {$newCount} new messages for account {$this->emailAccount->id}");

                // FIX-022: Query new inbound messages directly instead of loading all conversations+messages
                $newMessages = \App\Models\Message::where('workspace_id', $this->emailAccount->workspace_id)
                    ->whereHas('conversation', fn ($q) => $q->where('email_account_id', $this->emailAccount->id))
                    ->where('direction', 'inbound')
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->limit(1000)
                    ->get();

                foreach ($newMessages as $message) {
                    $dispatchKey = "ai_dispatched:{$message->id}";

                    // Skip if already processed
                    if (!Cache::add($dispatchKey, true, 300)) {
                        continue;
                    }

                    // Sentiment analysis (non-blocking, can fail silently)
                    if (class_exists(\App\Jobs\AnalyzeSentimentJob::class)) {
                        try { \App\Jobs\AnalyzeSentimentJob::dispatchSync($message); } catch (\Throwable $e) {}
                    }

                    // Check keyword-based auto-reply rules first
                    $conversation = $message->conversation;
                    $keywordReplied = false;
                    if ($conversation) {
                        try {
                            $keywordReplied = app(\App\Services\AutoReplyService::class)
                                ->processIncomingMessage($message, $conversation);
                        } catch (\Throwable $e) {
                            Log::warning("SyncEmailAccountJob: Keyword auto-reply failed for message {$message->id}: {$e->getMessage()}");
                        }
                    }

                    // AI auto-reply — run directly, no queue
                    Log::info("SyncEmailAccountJob: AI check — keywordReplied={$keywordReplied}, ai_auto_reply={$this->emailAccount->ai_auto_reply}, msg={$message->id}");
                    if (! $keywordReplied && $this->emailAccount->ai_auto_reply && class_exists(\App\Jobs\GenerateAIReplyJob::class)) {
                        Log::info("SyncEmailAccountJob: Running AI reply for msg={$message->id}");
                        try {
                            \App\Jobs\GenerateAIReplyJob::dispatchSync($message, $this->emailAccount);
                            Log::info("SyncEmailAccountJob: AI reply completed for msg={$message->id}");
                        } catch (\Throwable $e) {
                            Log::error("SyncEmailAccountJob: AI reply failed for msg={$message->id}: {$e->getMessage()}");
                        }
                    }

                    // Fire workflow trigger for new email received.
                    // Shared dedupe key with InboxApiController::fireMessageReceivedForRecent()
                    // so the same message can't trigger the same workflow twice
                    // when the AJAX sync path saved it first and this job picks
                    // it up again a few seconds later.
                    if ($conversation) {
                        $workflowKey = "msg_received_fired:{$message->id}";
                        if (Cache::add($workflowKey, true, 3600)) {
                            try {
                                event(new \App\Events\MessageReceived($message, $conversation));
                            } catch (\Throwable $e) {
                                Log::warning("SyncEmailAccountJob: Workflow trigger failed for msg={$message->id}: {$e->getMessage()}");
                            }

                            // Fire Slack + Zapier inline too — don't depend on
                            // the integrations queue worker being drained.
                            // Mirrors InboxApiController::dispatchIntegrationsSync().
                            $this->dispatchIntegrationsInline($message, $conversation);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("SyncEmailAccountJob: Failed for account {$this->emailAccount->id} (attempt {$this->attempts()}/{$this->tries}): {$e->getMessage()}", [
                'account_id' => $this->emailAccount->id,
                'email' => $this->emailAccount->email,
                'provider' => $this->emailAccount->provider,
            ]);

            // Always re-throw so Laravel retries the job properly.
            // The failed() method handles permanent error status after
            // all retry attempts are exhausted.
            throw $e;
        }
    }

    /**
     * Post to Slack + Zapier inline (bypasses the integrations queue) so a
     * backlogged worker never silently drops customer-facing notifications.
     * Same dedupe keys as InboxApiController so running both paths for the
     * same message still fires each vendor exactly once per message.
     */
    protected function dispatchIntegrationsInline($message, $conversation): void
    {
        $workspaceId = $conversation->workspace_id;

        try {
            $slackKey = "int_slack_fired:{$message->id}";
            if (Cache::add($slackKey, true, 3600)) {
                $slack = app(\App\Services\Integrations\SlackIntegrationService::class);
                if ($slack->isActive($workspaceId)) {
                    $slack->postConversationUpdate($conversation, 'new_message');
                }
            }
        } catch (\Throwable $e) {
            Log::warning("SyncEmailAccountJob: Slack inline notify failed for msg={$message->id}: {$e->getMessage()}");
        }

        try {
            $zapKey = "int_zapier_fired:{$message->id}";
            if (Cache::add($zapKey, true, 3600)) {
                $zapier = app(\App\Services\Integrations\ZapierIntegrationService::class);
                if ($zapier->isActive($workspaceId)) {
                    $zapier->triggerWebhook($workspaceId, 'conversation.new', [
                        'conversation_id' => $conversation->id,
                        'message_id' => $message->id,
                        'subject' => $message->subject,
                        'from_email' => $message->from_email,
                        'from_name' => $message->from_name,
                        'channel' => $conversation->channel,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("SyncEmailAccountJob: Zapier inline webhook failed for msg={$message->id}: {$e->getMessage()}");
        }
    }

    /**
     * Check if the OAuth token is expired or about to expire.
     */
    protected function isTokenExpired(): bool
    {
        if (! $this->emailAccount->oauth_token_expires_at) {
            return true;
        }

        // Consider expired if it expires within the next 5 minutes
        return $this->emailAccount->oauth_token_expires_at->isPast()
            || $this->emailAccount->oauth_token_expires_at->isBefore(now()->addMinutes(5));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SyncEmailAccountJob: Failed for account {$this->emailAccount->id}", [
            'error' => $exception->getMessage(),
        ]);

        // Keep status as 'connected' so next schedule run retries.
        // Only store error message for admin visibility.
        try {
            $this->emailAccount->update([
                'error_message' => Str::limit($exception->getMessage(), 200),
            ]);
        } catch (\Throwable $e) {
            // If even saving the error fails (column too small), just log it
            Log::error("SyncEmailAccountJob: Could not save error message: " . Str::limit($e->getMessage(), 100));
        }
    }
}
