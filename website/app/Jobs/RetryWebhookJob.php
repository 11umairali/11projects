<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RetryWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Exponential backoff: 60s, 5min between retries.
     */
    public array $backoff = [60, 300];

    /**
     * Maximum seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * The queue this job should be dispatched to.
     */

    public function __construct(
        public WebhookLog $webhookLog
    ) {}

    public function handle(): void
    {
        $log = $this->webhookLog;

        // Guard: already succeeded or exhausted retries
        if ($log->status === 'success') {
            return;
        }

        if ($log->attempts >= $log->max_attempts) {
            $log->markAsFailed('Maximum retry attempts exhausted.');
            return;
        }

        $log->markAsRetrying();

        $startTime = hrtime(true);

        try {
            $response = Http::withHeaders($log->headers ?? [])
                ->timeout(120)
                ->connectTimeout(5)
                ->send($log->method, $log->url, [
                    'json' => $log->payload,
                ]);

            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            if ($response->successful()) {
                $log->markAsSuccess(
                    $response->status(),
                    mb_substr($response->body(), 0, 10_000),
                    $durationMs
                );

                Log::info('Webhook retry succeeded', [
                    'webhook_log_id' => $log->id,
                    'url' => $log->url,
                    'attempt' => $log->attempts,
                ]);
            } else {
                $log->markAsFailed(
                    "HTTP {$response->status()}: " . mb_substr($response->body(), 0, 500),
                    $response->status(),
                    mb_substr($response->body(), 0, 10_000),
                    $durationMs
                );

                Log::warning('Webhook retry received non-2xx response', [
                    'webhook_log_id' => $log->id,
                    'url' => $log->url,
                    'status' => $response->status(),
                    'attempt' => $log->attempts,
                ]);

                // Re-throw so the queue retries the job
                throw new \RuntimeException("Webhook delivery failed with HTTP {$response->status()}");
            }
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            $log->markAsFailed(
                $e->getMessage(),
                null,
                null,
                $durationMs
            );

            Log::error('Webhook retry failed with exception', [
                'webhook_log_id' => $log->id,
                'url' => $log->url,
                'error' => $e->getMessage(),
                'attempt' => $log->attempts,
            ]);

            throw $e; // Let the queue retry
        }
        $this->onQueue('processing');
    }

    /**
     * Handle a job failure after all retries exhausted.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('RetryWebhookJob permanently failed', [
            'webhook_log_id' => $this->webhookLog->id,
            'url' => $this->webhookLog->url,
            'error' => $exception?->getMessage(),
        ]);
    }
}
