<?php

namespace App\Jobs;

use App\Models\CampaignLink;
use App\Models\CampaignRecipient;
use App\Services\Campaign\CampaignService;
use App\Services\Email\EmailSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying.
     */
    public array $backoff = [30, 120, 600];

    /**
     * Maximum seconds the job can run.
     */
    public int $timeout = 120;

    public function __construct(
        public CampaignRecipient $recipient
    ) {}

    public function handle(EmailSendService $emailSendService, CampaignService $campaignService): void
    {
        // FIX-016: Atomic idempotency — claim this recipient with DB-level WHERE
        // This prevents duplicate sends when two job workers pick up the same recipient
        $claimed = CampaignRecipient::where('id', $this->recipient->id)
            ->where('status', 'pending')
            ->update(['status' => 'processing']);

        if ($claimed === 0) {
            Log::info('Campaign email skipped — recipient already claimed/processed', [
                'recipient_id' => $this->recipient->id,
            ]);
            return;
        }

        $recipient = $this->recipient->fresh()->load(['contact', 'campaign.emailAccount']);
        $campaign = $recipient->campaign;
        $contact = $recipient->contact;

        // Plan limit check — block if monthly email limit reached
        $workspace = $campaign ? \App\Models\Workspace::find($campaign->workspace_id) : null;
        if ($workspace) {
            $planLimits = app(\App\Services\PlanLimitService::class);
            if (!$planLimits->canUse($workspace, 'emails_per_month')) {
                $recipient->update(['status' => 'failed', 'error_message' => 'Monthly email limit reached']);
                Log::warning('SendCampaignEmailJob: Monthly email limit reached', ['workspace_id' => $workspace->id]);
                return;
            }
        }

        // FIX-034: Verify campaign and email account belong to same workspace
        if ($campaign && $campaign->emailAccount
            && $campaign->workspace_id !== $campaign->emailAccount->workspace_id) {
            Log::error('Campaign/EmailAccount workspace mismatch', [
                'campaign_id' => $campaign->id,
                'campaign_workspace' => $campaign->workspace_id,
                'account_workspace' => $campaign->emailAccount->workspace_id,
            ]);
            $recipient->update(['status' => 'failed', 'error_message' => 'Workspace mismatch.']);
            return;
        }

        if (!$contact || !$contact->email) {
            $recipient->update([
                'status' => 'failed',
                'error_message' => 'Contact has no email address.',
            ]);
            return;
        }

        // Skip if contact has been unsubscribed or bounced since campaign started
        if (in_array($contact->status, ['unsubscribed', 'bounced', 'spam'])) {
            $recipient->update([
                'status' => 'failed',
                'error_message' => "Contact status is '{$contact->status}' — skipped.",
            ]);
            return;
        }

        if (!$campaign->emailAccount) {
            $recipient->update([
                'status' => 'failed',
                'error_message' => 'Campaign email account not found.',
            ]);
            return;
        }

        try {
            // Determine subject and body (support A/B test variants)
            $subject = $campaign->subject;
            $bodyHtml = $campaign->body_html;

            if ($campaign->type === 'ab_test' && $recipient->variant) {
                $variant = $campaign->abTestVariants()
                    ->where('variant', $recipient->variant)
                    ->first();

                if ($variant) {
                    $subject = $variant->subject;
                    $bodyHtml = $variant->body_html ?: $bodyHtml;
                }
            }

            // Variable replacement
            $bodyHtml = $this->replaceVariables($bodyHtml, $contact);
            $subject = $this->replaceVariables($subject, $contact);

            // FIX-052: Inject tracking pixel using case-insensitive replacement
            $trackingPixelUrl = url("/api/track/campaign/{$recipient->uuid}/open");
            $trackingPixel = '<img src="' . $trackingPixelUrl . '" width="1" height="1" alt="" style="display:none;">';
            $bodyHtml = preg_replace('/<\/body\b/i', $trackingPixel . '</body', $bodyHtml, 1, $count);

            // If no </body> tag found, append at the end
            if ($count === 0) {
                $bodyHtml .= $trackingPixel;
            }

            // Wrap all links for click tracking
            $bodyHtml = $this->wrapLinksWithTracking($bodyHtml, $campaign->id, $recipient->uuid);

            // Add CAN-SPAM compliant unsubscribe link
            $unsubscribeUrl = $this->generateUnsubscribeUrl($contact);
            $unsubscribeBlock = '<div style="text-align:center;padding:20px 0;font-size:12px;color:#666;">'
                . '<a href="' . $unsubscribeUrl . '" style="color:#666;text-decoration:underline;">Unsubscribe</a>'
                . ' from these emails.'
                . '</div>';

            // Insert before </body> or append
            if (str_contains($bodyHtml, '</body>')) {
                $bodyHtml = str_replace('</body>', $unsubscribeBlock . '</body>', $bodyHtml);
            } else {
                $bodyHtml .= $unsubscribeBlock;
            }

            // Send via the campaign's email account
            $emailSendService->send(
                account: $campaign->emailAccount,
                to: $contact->email,
                subject: $subject,
                htmlBody: $bodyHtml,
                options: [
                    'headers' => [
                        'List-Unsubscribe' => "<{$unsubscribeUrl}>",
                        'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                        'X-Campaign-ID' => (string) $campaign->uuid,
                        'X-Recipient-ID' => (string) $recipient->uuid,
                    ],
                ]
            );

            // Update recipient status
            $recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Increment email usage
            if ($workspace) {
                \App\Models\UsageRecord::incrementUsage($workspace->id, 'emails_sent');
            }

            // Increment campaign counters.
            // delivered_count is the denominator for open_rate / click_rate /
            // unsub_rate on the report. Without it, every ratio stays at 0% and
            // the dashboard looks broken even when emails sent fine. A later
            // bounce webhook can decrement delivered and increment bounced.
            $campaign->increment('sent_count');
            $campaign->increment('delivered_count');

            // Check if campaign is complete
            $campaignService->checkAndCompleteIfDone($campaign);

        } catch (\Throwable $e) {
            Log::error('Campaign email send failed', [
                'recipient_id' => $recipient->id,
                'campaign_id' => $campaign->id,
                'contact_email' => $contact->email,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // If this is the last attempt, mark as failed permanently
            if ($this->attempts() >= $this->tries) {
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => Str::limit($e->getMessage(), 250),
                ]);

                $campaignService->checkAndCompleteIfDone($campaign);
            }

            throw $e; // Let the queue system handle retry
        }
    }

    /**
     * Replace template variables in content.
     */
    private function replaceVariables(string $content, \App\Models\Contact $contact): string
    {
        $replacements = [
            '{first_name}' => e($contact->first_name ?? ''),
            '{last_name}' => e($contact->last_name ?? ''),
            '{full_name}' => e($contact->full_name ?? ''),
            '{company}' => e($contact->company ?? ''),
            '{email}' => e($contact->email ?? ''),
            '{job_title}' => e($contact->job_title ?? ''),
            '{city}' => e($contact->city ?? ''),
            '{country}' => e($contact->country ?? ''),
            // Unsubscribe URL is generated by us, not user-controlled — no escaping needed
            '{unsubscribe_url}' => $this->generateUnsubscribeUrl($contact),
        ];

        // Also support custom fields: {custom.field_name}
        if (is_array($contact->custom_fields)) {
            foreach ($contact->custom_fields as $key => $value) {
                $replacements["{custom.{$key}}"] = e(is_string($value) ? $value : (string) $value);
            }
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $content
        );
    }

    /**
     * Find all href links in HTML and replace with tracking URLs.
     * Creates CampaignLink records for each unique URL.
     *
     * Uses batch SELECT + INSERT instead of per-link firstOrCreate to reduce
     * N+1 queries down to 2 queries regardless of link count.
     */
    private function wrapLinksWithTracking(string $html, int $campaignId, string $recipientUuid): string
    {
        // Step 1: Collect all unique trackable URLs from the HTML
        $uniqueUrls = [];
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $allMatches);

        foreach ($allMatches[1] as $originalUrl) {
            if (
                str_contains($originalUrl, '/api/track/') ||
                str_contains($originalUrl, '/api/unsubscribe/') ||
                str_starts_with($originalUrl, 'mailto:') ||
                str_starts_with($originalUrl, 'tel:') ||
                str_starts_with($originalUrl, '#')
            ) {
                continue;
            }
            $uniqueUrls[$originalUrl] = true;
        }

        if (empty($uniqueUrls)) {
            return $html;
        }

        $uniqueUrlList = array_keys($uniqueUrls);

        // Step 2: Single SELECT to get all existing CampaignLinks for these URLs + campaign
        $existingLinks = CampaignLink::where('campaign_id', $campaignId)
            ->whereIn('original_url', $uniqueUrlList)
            ->get()
            ->keyBy('original_url');

        // Step 3: Batch INSERT any new URLs that don't exist yet
        $newRows = [];
        $now = now();
        foreach ($uniqueUrlList as $url) {
            if (!$existingLinks->has($url)) {
                $newRows[] = [
                    'campaign_id' => $campaignId,
                    'original_url' => $url,
                    'tracking_hash' => Str::random(32),
                    'clicks_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($newRows)) {
            CampaignLink::insertOrIgnore($newRows);

            // Re-fetch to get all links (including newly inserted) with their IDs/hashes
            $existingLinks = CampaignLink::where('campaign_id', $campaignId)
                ->whereIn('original_url', $uniqueUrlList)
                ->get()
                ->keyBy('original_url');
        }

        // Step 4: Replace links in HTML using the pre-loaded records
        return preg_replace_callback(
            '/href=["\']([^"\']+)["\']/i',
            function (array $matches) use ($existingLinks, $recipientUuid) {
                $originalUrl = $matches[1];

                if (
                    str_contains($originalUrl, '/api/track/') ||
                    str_contains($originalUrl, '/api/unsubscribe/') ||
                    str_starts_with($originalUrl, 'mailto:') ||
                    str_starts_with($originalUrl, 'tel:') ||
                    str_starts_with($originalUrl, '#')
                ) {
                    return $matches[0];
                }

                $campaignLink = $existingLinks->get($originalUrl);
                if (!$campaignLink) {
                    return $matches[0];
                }

                $trackingUrl = url("/api/track/campaign/{$recipientUuid}/click") .
                    '?url=' . urlencode($originalUrl) .
                    '&lh=' . $campaignLink->tracking_hash;

                return 'href="' . $trackingUrl . '"';
            },
            $html
        );
    }

    /**
     * Generate a signed unsubscribe URL for CAN-SPAM compliance.
     */
    private function generateUnsubscribeUrl(\App\Models\Contact $contact): string
    {
        return URL::signedRoute('unsubscribe', [
            'contactId' => $contact->uuid,
        ]);
    }

    /**
     * Handle a job failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Campaign email job permanently failed', [
            'recipient_id' => $this->recipient->id,
            'error' => $exception->getMessage(),
        ]);

        $this->recipient->update([
            'status' => 'failed',
            'error_message' => Str::limit($exception->getMessage(), 250),
        ]);
    }
}
