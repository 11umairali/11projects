<?php
namespace App\Jobs;

use App\Models\TempMailDomain;
use App\Services\TempMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTempMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];
    public int $timeout = 300;

    public function __construct(
        public TempMailDomain $domain,
    ) {
        $this->queue = 'email-sync';
    }

    public function handle(TempMailService $service): void
    {
        if (!$this->domain->isActive()) {
            Log::info("TempMail sync skipped: domain {$this->domain->domain} is not active");
            return;
        }

        $count = $service->syncDomain($this->domain);
        Log::info("TempMail sync complete: {$this->domain->domain} — {$count} new message(s)");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("TempMail sync FAILED permanently for {$this->domain->domain}: {$e->getMessage()}");
        $this->domain->update([
            'status' => 'error',
            'error_message' => 'Sync failed after all retries: ' . \Illuminate\Support\Str::limit($e->getMessage(), 400),
        ]);
    }
}
