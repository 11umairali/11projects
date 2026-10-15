<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\AI\SentimentAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeSentimentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 15;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * The queue this job should be dispatched to.
     */

    public function __construct(
        private readonly Message $message,
    ) {}

    public function handle(SentimentAnalyzer $sentimentAnalyzer): void
    {
        $message = $this->message;

        // Only analyze inbound messages
        if ($message->direction !== 'inbound') {
            return;
        }

        // Skip if already analyzed
        if ($message->sentiment !== null) {
            return;
        }

        $text = $message->body_text ?? strip_tags($message->body_html ?? '');
        if (empty(trim($text))) {
            return;
        }

        try {
            $result = $sentimentAnalyzer->analyze($text, $message->workspace_id);

            // Update message sentiment
            $message->update([
                'sentiment' => $result['sentiment'],
            ]);

            // Update conversation sentiment if it differs
            $conversation = $message->conversation;
            if ($conversation && $conversation->sentiment !== $result['sentiment']) {
                $conversation->update([
                    'sentiment' => $result['sentiment'],
                ]);
            }

            Log::debug('Sentiment analysis completed', [
                'message_id' => $message->id,
                'sentiment' => $result['sentiment'],
                'confidence' => $result['confidence'],
                'key_phrases' => $result['key_phrases'],
            ]);
        } catch (\Exception $e) {
            Log::error('AnalyzeSentimentJob failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
        $this->onQueue('ai');
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('AnalyzeSentimentJob permanently failed', [
            'message_id' => $this->message->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
