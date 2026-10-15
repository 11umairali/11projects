<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Campaign\CampaignService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Picks up campaigns whose scheduled_at has passed and kicks off sending.
 *
 * Without this, "Schedule for Later" just saved the campaign with
 * status=scheduled and nothing ever triggered the send — the campaign sat
 * forever. We run every minute and hand each due campaign to
 * CampaignService::sendCampaign() which atomically flips status to 'sending'
 * and dispatches one SendCampaignEmailJob per recipient.
 */
class ProcessScheduledCampaignsCommand extends Command
{
    protected $signature = 'campaigns:process-scheduled';

    protected $description = 'Start sending any scheduled campaigns whose send time has arrived';

    public function handle(CampaignService $campaignService): int
    {
        $due = Campaign::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get();

        if ($due->isEmpty()) {
            Log::debug('[process-scheduled-campaigns] No campaigns due');
            return self::SUCCESS;
        }

        $started = 0;
        $failed = 0;

        foreach ($due as $campaign) {
            try {
                $campaignService->sendCampaign($campaign);
                $started++;
                Log::info("[process-scheduled-campaigns] Started campaign #{$campaign->id}: {$campaign->name}");
            } catch (\Throwable $e) {
                $failed++;
                Log::error("[process-scheduled-campaigns] Failed to start campaign #{$campaign->id}", [
                    'error' => $e->getMessage(),
                ]);

                // Flip status to 'failed' so we don't keep trying on every
                // minute forever (same class of bug as SendScheduledEmailJob).
                try {
                    $campaign->update([
                        'status' => 'failed',
                    ]);
                } catch (\Throwable $e2) {
                    // Ignore — logged above
                }
            }
        }

        $this->info("Scheduled campaigns: {$started} started, {$failed} failed.");
        Log::info("[process-scheduled-campaigns] Done. {$started} started, {$failed} failed");

        return self::SUCCESS;
    }
}
