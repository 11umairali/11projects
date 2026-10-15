<?php

namespace App\Services\Campaign;

use App\Jobs\SendCampaignEmailJob;
use App\Models\AbTestVariant;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ABTestService
{
    /**
     * FIX-067: Configurable weights for winner determination scoring.
     * Score = (open_rate * OPEN_RATE_WEIGHT) + (click_rate * CLICK_RATE_WEIGHT)
     * These should sum to 1.0. Override via campaign settings or adjust here.
     */
    protected const OPEN_RATE_WEIGHT = 0.7;

    protected const CLICK_RATE_WEIGHT = 0.3;

    /**
     * Set up an A/B test by splitting the audience across variants.
     *
     * @param  Campaign  $campaign  The campaign (must have type=ab_test).
     * @param  array  $variants  Array of variant configs:
     *                           [
     *                           ['variant' => 'A', 'subject' => '...', 'body_html' => '...', 'percentage' => 50],
     *                           ['variant' => 'B', 'subject' => '...', 'body_html' => '...', 'percentage' => 50],
     *                           ]
     *
     * @throws \InvalidArgumentException If percentages don't sum to 100.
     */
    public function setupTest(Campaign $campaign, array $variants): void
    {
        $totalPercentage = array_sum(array_column($variants, 'percentage'));

        if ($totalPercentage !== 100) {
            throw new \InvalidArgumentException(
                "A/B test variant percentages must sum to 100, got {$totalPercentage}."
            );
        }

        if (count($variants) < 2) {
            throw new \InvalidArgumentException(
                'A/B tests require at least 2 variants.'
            );
        }

        DB::transaction(function () use ($campaign, $variants) {
            // Remove any existing variants
            $campaign->abTestVariants()->delete();

            foreach ($variants as $variant) {
                AbTestVariant::create([
                    'campaign_id' => $campaign->id,
                    'variant' => $variant['variant'],
                    'subject' => $variant['subject'],
                    'body_html' => $variant['body_html'] ?? null,
                    'percentage' => $variant['percentage'],
                    'is_winner' => false,
                ]);
            }

            $campaign->update(['type' => 'ab_test']);
        });

        Log::info('A/B test setup complete', [
            'campaign_id' => $campaign->id,
            'variants' => count($variants),
        ]);
    }

    /**
     * Assign variants to recipients based on configured percentages.
     * Call this after recipients are created but before sending.
     */
    public function assignVariantsToRecipients(Campaign $campaign): void
    {
        $variants = $campaign->abTestVariants()->orderBy('variant')->get();

        if ($variants->isEmpty()) {
            throw new \RuntimeException(
                "Campaign {$campaign->id} has no A/B test variants configured."
            );
        }

        // Only load IDs, not full models
        $recipientIds = $campaign->campaignRecipients()
            ->whereNull('variant')
            ->pluck('id');

        if ($recipientIds->isEmpty()) {
            return;
        }

        // Shuffle IDs to randomize assignment
        $shuffled = $recipientIds->shuffle()->values();
        $total = $shuffled->count();
        $assigned = 0;

        foreach ($variants as $variant) {
            $count = (int) round(($variant->percentage / 100) * $total);

            // Ensure last variant gets whatever remains
            if ($variant->variant === $variants->last()->variant) {
                $count = $total - $assigned;
            }

            $chunkIds = $shuffled->slice($assigned, $count)->toArray();

            if (! empty($chunkIds)) {
                // Batch update in chunks to avoid too-large WHERE IN
                foreach (array_chunk($chunkIds, 1000) as $idChunk) {
                    CampaignRecipient::whereIn('id', $idChunk)
                        ->update(['variant' => $variant->variant]);
                }
            }

            $assigned += $count;
        }

        Log::info('A/B test variants assigned', [
            'campaign_id' => $campaign->id,
            'total_recipients' => $total,
        ]);
    }

    /**
     * Determine the winning variant by comparing open rates and click rates.
     *
     * @return string The winning variant letter (e.g., 'A' or 'B').
     */
    public function determineWinner(Campaign $campaign): string
    {
        $variants = $campaign->abTestVariants()->get();
        $bestScore = -1;
        $winner = 'A';

        foreach ($variants as $variant) {
            $stats = $campaign->campaignRecipients()
                ->where('variant', $variant->variant)
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(CASE WHEN status IN ('opened', 'clicked') THEN 1 END) as opens,
                    COUNT(CASE WHEN status = 'clicked' THEN 1 END) as clicks
                ")
                ->first();

            $total = $stats->total ?: 1; // prevent division by zero

            // FIX-067: Read weights from campaign settings if available, fall back to class constants
            $settings = $campaign->body_json['ab_test_settings'] ?? [];
            $openWeight = (float) ($settings['open_rate_weight'] ?? static::OPEN_RATE_WEIGHT);
            $clickWeight = (float) ($settings['click_rate_weight'] ?? static::CLICK_RATE_WEIGHT);

            $openRate = $stats->opens / $total;
            $clickRate = $stats->clicks / $total;
            $score = ($openRate * $openWeight) + ($clickRate * $clickWeight);

            Log::info("A/B test variant {$variant->variant} stats", [
                'campaign_id' => $campaign->id,
                'total' => $stats->total,
                'opens' => $stats->opens,
                'clicks' => $stats->clicks,
                'score' => round($score, 4),
            ]);

            if ($score > $bestScore) {
                $bestScore = $score;
                $winner = $variant->variant;
            }
        }

        // Mark the winner in the database
        $campaign->abTestVariants()
            ->where('variant', $winner)
            ->update(['is_winner' => true]);

        Log::info('A/B test winner determined', [
            'campaign_id' => $campaign->id,
            'winner' => $winner,
            'score' => round($bestScore, 4),
        ]);

        return $winner;
    }

    /**
     * After determining a winner, send the winning variant to any
     * remaining unsent recipients (those not part of the test sample).
     */
    public function sendWinnerToRemaining(Campaign $campaign): void
    {
        $winningVariant = $campaign->abTestVariants()
            ->where('is_winner', true)
            ->first();

        if (! $winningVariant) {
            $winner = $this->determineWinner($campaign);
            $winningVariant = $campaign->abTestVariants()
                ->where('variant', $winner)
                ->first();
        }

        // Assign the winning variant to all remaining recipients in bulk
        $updatedCount = $campaign->campaignRecipients()
            ->where('status', 'pending')
            ->update(['variant' => $winningVariant->variant]);

        if ($updatedCount === 0) {
            Log::info('No remaining recipients for A/B test winner send', [
                'campaign_id' => $campaign->id,
            ]);

            return;
        }

        // Dispatch send jobs using chunked query to avoid loading all into memory
        $batchSize = 10;
        $dispatched = 0;

        $campaign->campaignRecipients()
            ->where('status', 'pending')
            ->select(['id', 'campaign_id', 'contact_id'])
            ->chunkById(500, function ($recipients) use ($batchSize, &$dispatched) {
                foreach ($recipients as $recipient) {
                    $delaySeconds = intdiv($dispatched, $batchSize);
                    SendCampaignEmailJob::dispatch($recipient)
                        ->onQueue('campaigns')
                        ->delay(now()->addSeconds($delaySeconds));
                    $dispatched++;
                }
            });

        Log::info('A/B test winner sent to remaining recipients', [
            'campaign_id' => $campaign->id,
            'winner' => $winningVariant->variant,
            'remaining_count' => $updatedCount,
        ]);
    }
}
