<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Models\Message;
use App\Models\UsageRecord;
use App\Services\Email\EmailSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledEmailJob implements ShouldQueue, \Illuminate\Contracts\Queue\ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;
    public function uniqueId(): string { return (string) $this->message->id; }

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [30, 120, 600];
    // Queue is set via constructor: $this->onQueue('campaigns')

    public function __construct(public Message $message)
    {
        $this->onQueue('campaigns');
    }

    public function handle(EmailSendService $emailSendService): void
    {
        // Re-read from DB so concurrent updates (undo-send cancel, manual
        // re-schedule, etc.) are respected.
        $this->message->refresh();

        // Guard: skip if already sent or cancelled since dispatch
        if ($this->message->schedule_status !== 'pending') {
            return;
        }

        $conversation = $this->message->conversation;
        if (!$conversation) {
            Log::error('SendScheduledEmailJob: no conversation found', [
                'message_id' => $this->message->id,
            ]);
            $this->message->update([
                'schedule_status' => 'cancelled',
                'delivery_status' => 'failed',
                'delivery_error' => 'Conversation not found',
            ]);
            return;
        }

        $emailAccount = $conversation->emailAccount
            ?? EmailAccount::where('workspace_id', $conversation->workspace_id)
                ->where('status', 'connected')
                ->first();

        // No account to send from — fail the message immediately instead of
        // silently marking it 'sent'. Marking as 'failed' (not 'pending')
        // keeps readyToSend() from re-picking it every minute forever.
        if (! $emailAccount) {
            Log::error('SendScheduledEmailJob: no connected email account for workspace', [
                'message_id' => $this->message->id,
                'workspace_id' => $conversation->workspace_id,
            ]);
            $this->message->update([
                'schedule_status' => 'failed',
                'delivery_status' => 'failed',
                'delivery_error' => 'No connected email account available to send from.',
            ]);
            return;
        }

        try {
            if (!$emailSendService->sendReply($this->message, $emailAccount)) return;

            $this->message->update([
                'schedule_status' => 'sent',
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SendScheduledEmailJob: failed to send', [
                'message_id' => $this->message->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            $this->message->refresh();
            if (in_array($this->message->delivery_status, ['sent', 'delivered', 'read', 'bounced'], true)) return; // Delivery succeeded; never retry because bookkeeping failed.
            $this->message->update([
                'delivery_status' => 'failed',
                'delivery_error' => $e->getMessage(),
            ]);

            throw $e; // Let the queue retry
        }
    }

    /**
     * Called by the queue worker once all retries are exhausted. Flip
     * schedule_status to 'failed' so the every-minute SendScheduledEmails
     * command stops re-dispatching this message — otherwise a permanently
     * broken send loops forever (the "never-ending mail" bug).
     */
    public function failed(\Throwable $exception): void
    {
        try {
            $this->message->refresh();
            if ($this->message->schedule_status === 'pending') {
                $this->message->update([
                    'schedule_status' => 'failed',
                    'delivery_status' => 'failed',
                    'delivery_error' => \Illuminate\Support\Str::limit($exception->getMessage(), 500),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('SendScheduledEmailJob::failed could not mark message', [
                'message_id' => $this->message->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
