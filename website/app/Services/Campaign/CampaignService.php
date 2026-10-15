<?php

namespace App\Services\Campaign;

use App\Helpers\HtmlSanitizer;
use App\Jobs\SendCampaignEmailJob;
use App\Jobs\SendCampaignSmsJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\Segment;
use App\Services\Email\EmailSendService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CampaignService
{
    /**
     * Main campaign sender. Resolves audience, creates recipient records,
     * and dispatches per-recipient jobs with queue-based throttling.
     *
     * Branches on $campaign->channel:
     *   - 'email' (default): SendCampaignEmailJob, requires subject + body_html + email_account_id.
     *   - 'sms': SendCampaignSmsJob, requires body_text + from_number, sends via Twilio.
     *
     * Throttling (emails_per_minute / batch_size / batch_delay_seconds) is
     * channel-agnostic — the same delay logic schedules SMS or email jobs.
     *
     * @throws \InvalidArgumentException If campaign is not in a sendable state.
     * @throws \RuntimeException On critical failures.
     */
    public function sendCampaign(Campaign $campaign): void
    {
        $channel = $campaign->channel ?: 'email';

        // Channel-specific pre-flight validation
        if ($channel === 'sms') {
            if (! $campaign->body_text || trim($campaign->body_text) === '') {
                throw new \InvalidArgumentException(
                    "SMS campaign {$campaign->id} has no message body."
                );
            }
            if (! $campaign->from_number) {
                throw new \InvalidArgumentException(
                    "SMS campaign {$campaign->id} has no from number selected."
                );
            }
        } else {
            if (! $campaign->email_account_id) {
                throw new \InvalidArgumentException(
                    "Campaign {$campaign->id} has no email account configured."
                );
            }
            if (! $campaign->subject || ! $campaign->body_html) {
                throw new \InvalidArgumentException(
                    "Campaign {$campaign->id} is missing subject or body content."
                );
            }
        }

        // Atomic status transition + audience resolution inside a single transaction.
        // The row lock prevents double-send when two workers pick up the same campaign.
        $recipientCount = 0;

        DB::transaction(function () use ($campaign, &$recipientCount, $channel) {
            // Lock the campaign row and verify it is still in a sendable state
            $locked = Campaign::where('id', $campaign->id)
                ->whereIn('status', ['draft', 'scheduled'])
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new \RuntimeException(
                    "Campaign {$campaign->id} is no longer in a sendable state."
                );
            }

            $locked->update(['status' => 'sending', 'sent_at' => now()]);

            // Delete any stale recipients from a previous aborted attempt
            $locked->campaignRecipients()->delete();

            // Channel-specific audience resolution: email path filters on
            // contact.email, SMS path filters on contact.phone.
            $recipientCount = $channel === 'sms'
                ? $this->resolveSmsAudienceChunked($locked)
                : $this->resolveAudienceChunked($locked);

            $locked->update(['recipients_count' => $recipientCount]);
        });

        // Refresh the campaign to pick up all changes written inside the transaction
        $campaign->refresh();

        if ($recipientCount === 0) {
            Log::warning('Campaign has no valid recipients', [
                'campaign_id' => $campaign->id,
            ]);

            $campaign->update([
                'status' => 'sent',
                'recipients_count' => 0,
                'completed_at' => now(),
            ]);

            return;
        }

        Log::info('Starting campaign send', [
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
            'recipient_count' => $recipientCount,
        ]);

        // Read per-campaign throttle settings, with safe fallbacks for legacy
        // rows that pre-date the migration. `emails_per_minute` sets the
        // steady rate (used for both email AND SMS); `batch_size` +
        // `batch_delay_seconds` add an optional cool-down after each batch
        // so we don't hammer provider quotas.
        //
        // SMS-specific note: Twilio's default account cap is 1 msg/sec
        // (60/min). Higher caps are available but require enabling toll-free
        // verified or 10DLC short-code. We default to 60/min for safety;
        // user can dial up in the editor.
        $messagesPerMinute = max(1, (int) ($campaign->emails_per_minute ?: 60));
        $batchSize = max(0, (int) ($campaign->batch_size ?: 0));
        $batchDelay = max(0, (int) ($campaign->batch_delay_seconds ?: 0));
        $channel = $campaign->channel ?: 'email';

        // Convert messages-per-minute into a fractional per-send delay.
        // 60/min = 1 sec between sends; 600/min = 0.1 sec; 30/min = 2 sec.
        $perSendDelayMs = (int) round(60000 / $messagesPerMinute);

        $dispatched = 0;

        $campaign->campaignRecipients()
            ->select(['id', 'campaign_id', 'contact_id'])
            ->chunkById(500, function ($recipients) use (&$dispatched, $perSendDelayMs, $batchSize, $batchDelay, $channel) {
                foreach ($recipients as $recipient) {
                    // Base cumulative delay from the steady rate.
                    $delayMs = $dispatched * $perSendDelayMs;

                    // Add an additional `batch_delay_seconds` pause for every
                    // *completed* batch we've already scheduled. Nth send in
                    // batch K ⇒ K full batch-delays already applied.
                    if ($batchSize > 0 && $batchDelay > 0) {
                        $batchesDone = intdiv($dispatched, $batchSize);
                        $delayMs += $batchesDone * $batchDelay * 1000;
                    }

                    // Dispatch the channel-specific job. Both jobs accept
                    // the same CampaignRecipient signature; only the
                    // delivery transport differs.
                    if ($channel === 'sms') {
                        SendCampaignSmsJob::dispatch($recipient)
                            ->onQueue('campaigns')
                            ->delay(now()->addMilliseconds($delayMs));
                    } else {
                        SendCampaignEmailJob::dispatch($recipient)
                            ->onQueue('campaigns')
                            ->delay(now()->addMilliseconds($delayMs));
                    }

                    $dispatched++;
                }
            });

        // Estimate the full send window for logging/observability.
        $totalMs = $dispatched > 0 ? ($dispatched - 1) * $perSendDelayMs : 0;
        if ($batchSize > 0 && $batchDelay > 0 && $dispatched > $batchSize) {
            $totalMs += (intdiv($dispatched - 1, $batchSize)) * $batchDelay * 1000;
        }

        Log::info('Campaign jobs dispatched', [
            'campaign_id' => $campaign->id,
            'channel' => $channel,
            'recipient_count' => $dispatched,
            'messages_per_minute' => $messagesPerMinute,
            'batch_size' => $batchSize,
            'batch_delay_seconds' => $batchDelay,
            'estimated_duration_seconds' => (int) ceil($totalMs / 1000),
        ]);
    }

    /**
     * Resolve the SMS-channel audience: contacts with a non-empty `phone`
     * column, excluding unsubscribed/suppressed numbers. Mirrors the email
     * path's chunked-insert structure for memory safety on large lists.
     */
    protected function resolveSmsAudienceChunked(Campaign $campaign): int
    {
        $workspaceId = $campaign->workspace_id;

        $query = match ($campaign->audience_type) {
            'segment' => $this->buildSegmentQuery($workspaceId, $campaign->audience_id),
            'list' => $this->buildListQuery($workspaceId, $campaign->audience_id),
            'contacts' => $this->buildSpecificContactsQuery($workspaceId, $campaign->audience_meta['contact_ids'] ?? []),
            'all' => Contact::where('workspace_id', $workspaceId)->where('status', 'active'),
            default => Contact::where('workspace_id', $workspaceId)->where('status', 'active'),
        };

        // SMS-specific filter: must have a phone number and not be unsubscribed
        $query->where('status', 'active')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNull('unsubscribed_at')
            ->whereNotIn('id', function ($sub) use ($campaign) {
                $sub->select('contact_id')
                    ->from('campaign_recipients')
                    ->where('campaign_id', $campaign->id);
            });

        $recipientCount = 0;

        $query->chunkById(500, function ($contacts) use ($campaign, &$recipientCount) {
            $records = $contacts->map(fn (Contact $contact) => [
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'phone' => $contact->phone,
                'email' => null,
                'status' => 'pending',
                'uuid' => Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            CampaignRecipient::insertOrIgnore($records);
            $recipientCount += count($records);
        });

        return $recipientCount;
    }

    /**
     * Send a test/preview email for a campaign without creating recipient records
     * or affecting campaign stats. Subject is prefixed with [TEST] and a visible
     * banner is injected at the top of the body.
     *
     * @throws \RuntimeException On send failure.
     */
    public function sendTestEmail(Campaign $campaign, string $toEmail, EmailAccount $account): void
    {
        if (! $campaign->subject || ! $campaign->body_html) {
            throw new \InvalidArgumentException(
                'Campaign is missing subject or body content.'
            );
        }

        $subject = '[TEST] ' . $campaign->subject;
        $bodyHtml = $campaign->body_html;

        // Replace template variables with sample data so the user can preview
        $bodyHtml = $this->replaceWithSampleData($bodyHtml);
        $subject = $this->replaceWithSampleData($subject);

        // Sanitize HTML to prevent stored XSS
        $bodyHtml = HtmlSanitizer::sanitize($bodyHtml);

        // Inject a clearly visible test banner at the top of the body
        $testBanner = '<div style="background-color:#FEF3C7;border:1px solid #F59E0B;border-radius:6px;padding:12px 16px;margin-bottom:16px;text-align:center;font-family:sans-serif;font-size:14px;color:#92400E;">'
            . '<strong>This is a test email.</strong> It was not sent to your actual recipients.'
            . '</div>';

        // Insert banner after <body> tag if present, otherwise prepend
        if (preg_match('/<body[^>]*>/i', $bodyHtml, $matches)) {
            $bodyHtml = str_ireplace($matches[0], $matches[0] . $testBanner, $bodyHtml);
        } else {
            $bodyHtml = $testBanner . $bodyHtml;
        }

        // Inject a test-flagged tracking pixel (uses test= param so it is not counted)
        $appUrl = config('app.url');
        $testPixel = '<img src="' . $appUrl . '/api/track/campaign/test/open?test=1" width="1" height="1" alt="" style="display:none;">';
        if (preg_match('/<\/body\b/i', $bodyHtml)) {
            $bodyHtml = preg_replace('/<\/body\b/i', $testPixel . '</body', $bodyHtml, 1);
        } else {
            $bodyHtml .= $testPixel;
        }

        // Send via EmailSendService — no CampaignRecipient, no stats
        $emailSendService = app(EmailSendService::class);

        $emailSendService->send(
            account: $account,
            to: $toEmail,
            subject: $subject,
            htmlBody: $bodyHtml,
            options: [
                'headers' => [
                    'X-Campaign-Test' => '1',
                    'X-Campaign-ID' => (string) ($campaign->uuid ?? $campaign->id),
                ],
            ]
        );

        Log::info('Campaign test email sent', [
            'campaign_id' => $campaign->id,
            'to' => $toEmail,
            'account_id' => $account->id,
        ]);
    }

    /**
     * Replace template merge tags with sample/placeholder data.
     * Uses the same tag format as SendCampaignEmailJob::replaceVariables().
     */
    protected function replaceWithSampleData(string $content): string
    {
        $sampleData = [
            '{first_name}' => 'Test',
            '{last_name}' => 'User',
            '{full_name}' => 'Test User',
            '{company}' => 'Your Company',
            '{email}' => 'test@example.com',
            '{job_title}' => 'Team Member',
            '{city}' => 'San Francisco',
            '{country}' => 'United States',
            '{unsubscribe_url}' => '#unsubscribe-preview',
        ];

        return str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $content
        );
    }

    /**
     * Resolve the campaign audience using chunked inserts into campaign_recipients.
     * Never loads the entire contact table into memory. Returns total recipient count.
     */
    protected function resolveAudienceChunked(Campaign $campaign): int
    {
        $workspaceId = $campaign->workspace_id;

        $query = match ($campaign->audience_type) {
            'segment' => $this->buildSegmentQuery($workspaceId, $campaign->audience_id),
            'list' => $this->buildListQuery($workspaceId, $campaign->audience_id),
            'contacts' => $this->buildSpecificContactsQuery($workspaceId, $campaign->audience_meta['contact_ids'] ?? []),
            'all' => Contact::where('workspace_id', $workspaceId)->where('status', 'active'),
            default => Contact::where('workspace_id', $workspaceId)->where('status', 'active'),
        };

        // Global exclusions: unsubscribed, bounced, suppressed
        $query->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotExists(function ($sub) use ($workspaceId) {
                $sub->select(DB::raw(1))
                    ->from('email_suppressions')
                    ->where('workspace_id', $workspaceId)
                    ->whereColumn('email', 'contacts.email');
            })
            ->whereNotIn('id', function ($sub) use ($campaign) {
                $sub->select('contact_id')
                    ->from('campaign_recipients')
                    ->where('campaign_id', $campaign->id);
            });

        $recipientCount = 0;

        $query->chunkById(500, function ($contacts) use ($campaign, &$recipientCount) {
            $records = $contacts->map(fn (Contact $contact) => [
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'status' => 'pending',
                'uuid' => Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            CampaignRecipient::insertOrIgnore($records);
            $recipientCount += count($records);
        });

        return $recipientCount;
    }

    /**
     * Resolve the campaign audience as a lazy collection (memory-efficient).
     * Excludes unsubscribed, bounced, and suppressed contacts.
     *
     * @return \Illuminate\Support\LazyCollection<Contact>
     */
    public function resolveAudience(Campaign $campaign): \Illuminate\Support\LazyCollection
    {
        $workspaceId = $campaign->workspace_id;

        $query = match ($campaign->audience_type) {
            'segment' => $this->buildSegmentQuery($workspaceId, $campaign->audience_id),
            'list' => $this->buildListQuery($workspaceId, $campaign->audience_id),
            'contacts' => $this->buildSpecificContactsQuery($workspaceId, $campaign->audience_meta['contact_ids'] ?? []),
            'all' => Contact::where('workspace_id', $workspaceId)->where('status', 'active'),
            default => Contact::where('workspace_id', $workspaceId)->where('status', 'active'),
        };

        // Global exclusions: unsubscribed, bounced, suppressed
        $query->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotExists(function ($sub) use ($workspaceId) {
                $sub->select(DB::raw(1))
                    ->from('email_suppressions')
                    ->where('workspace_id', $workspaceId)
                    ->whereColumn('email', 'contacts.email');
            });

        return $query->lazy(500);
    }

    /**
     * Build an Eloquent query from segment JSON rules.
     *
     * Segment rules format:
     * {
     *   "match": "all" | "any",
     *   "conditions": [
     *     {"field": "country", "operator": "equals", "value": "US"},
     *     {"field": "lead_score", "operator": "greater_than", "value": 50},
     *     {"field": "tags", "operator": "has_tag", "value": "vip"},
     *     {"field": "custom_fields.industry", "operator": "contains", "value": "tech"}
     *   ]
     * }
     */
    private function buildSegmentQuery(int $workspaceId, ?int $segmentId): Builder
    {
        if (! $segmentId) {
            return Contact::where('workspace_id', $workspaceId)->where('status', 'active');
        }

        $segment = Segment::find($segmentId);

        if (! $segment || empty($segment->rules)) {
            return Contact::where('workspace_id', $workspaceId)->where('status', 'active');
        }

        $rules = $segment->rules;
        $matchType = $rules['match'] ?? 'all';
        $conditions = $rules['conditions'] ?? [];

        $query = Contact::where('workspace_id', $workspaceId);

        $method = $matchType === 'all' ? 'where' : 'orWhere';

        $query->where(function (Builder $group) use ($conditions, $method) {
            foreach ($conditions as $condition) {
                $field = $condition['field'] ?? null;
                $operator = $condition['operator'] ?? 'equals';
                $value = $condition['value'] ?? null;

                if (! $field) {
                    continue;
                }

                $group->{$method}(function (Builder $q) use ($field, $operator, $value) {
                    $this->applyCondition($q, $field, $operator, $value);
                });
            }
        });

        return $query;
    }

    /**
     * Apply a single condition to the query builder.
     */
    private function applyCondition(Builder $query, string $field, string $operator, mixed $value): void
    {
        // Handle tag-based conditions
        if ($field === 'tags') {
            match ($operator) {
                'has_tag' => $query->whereHas('tags', fn (Builder $q) => $q->where('name', $value)),
                'not_has_tag' => $query->whereDoesntHave('tags', fn (Builder $q) => $q->where('name', $value)),
                default => null,
            };

            return;
        }

        // Handle custom_fields JSON conditions
        if (str_starts_with($field, 'custom_fields.')) {
            $jsonKey = str_replace('custom_fields.', '', $field);
            $field = "custom_fields->{$jsonKey}";
        }

        match ($operator) {
            'equals', 'is' => $query->where($field, '=', $value),
            'not_equals', 'is_not' => $query->where($field, '!=', $value),
            'contains' => $query->where($field, 'LIKE', "%{$value}%"),
            'not_contains' => $query->where($field, 'NOT LIKE', "%{$value}%"),
            'starts_with' => $query->where($field, 'LIKE', "{$value}%"),
            'ends_with' => $query->where($field, 'LIKE', "%{$value}"),
            'greater_than' => $query->where($field, '>', $value),
            'less_than' => $query->where($field, '<', $value),
            'greater_or_equal' => $query->where($field, '>=', $value),
            'less_or_equal' => $query->where($field, '<=', $value),
            'is_empty' => $query->whereNull($field)->orWhere($field, ''),
            'is_not_empty' => $query->whereNotNull($field)->where($field, '!=', ''),
            'in' => $query->whereIn($field, is_array($value) ? $value : explode(',', $value)),
            'not_in' => $query->whereNotIn($field, is_array($value) ? $value : explode(',', $value)),
            'between' => $query->whereBetween($field, is_array($value) ? $value : explode(',', $value)),
            'date_before' => $query->whereDate($field, '<', $value),
            'date_after' => $query->whereDate($field, '>', $value),
            'date_between' => $query->whereBetween($field, is_array($value) ? $value : explode(',', $value)),
            default => $query->where($field, '=', $value),
        };
    }

    /**
     * Build query from a static contact list.
     */
    private function buildListQuery(int $workspaceId, ?int $listId): Builder
    {
        if (! $listId) {
            return Contact::where('workspace_id', $workspaceId)->where('status', 'active');
        }

        return Contact::where('workspace_id', $workspaceId)
            ->whereIn('id', function ($sub) use ($listId) {
                $sub->select('contact_id')
                    ->from('contact_list_members')
                    ->where('contact_list_id', $listId);
            });
    }

    /**
     * Build query for the "specific contacts" audience type — only the
     * individual contact IDs the user picked on step 3 of the editor.
     * IDs are still scoped to the workspace to defend against tampering.
     *
     * @param  array<int>  $contactIds
     */
    private function buildSpecificContactsQuery(int $workspaceId, array $contactIds): Builder
    {
        if (empty($contactIds)) {
            // Return a query that matches nothing — we can't use `false` so
            // whereRaw('1=0') keeps this a real Eloquent Builder.
            return Contact::where('workspace_id', $workspaceId)->whereRaw('1=0');
        }

        return Contact::where('workspace_id', $workspaceId)
            ->whereIn('id', array_values(array_map('intval', $contactIds)));
    }

    /**
     * Mark a campaign as completed when all recipients have been processed.
     */
    public function checkAndCompleteIfDone(Campaign $campaign): void
    {
        $pending = $campaign->campaignRecipients()
            ->where('status', 'pending')
            ->count();

        if ($pending === 0) {
            // Atomic status transition: only one worker can complete the campaign
            // Use 'sent' directly — 'completing' is not a valid enum value
            $updated = Campaign::where('id', $campaign->id)
                ->where('status', 'sending')
                ->update(['status' => 'sent']);

            if (! $updated) {
                return; // Another worker already completed
            }

            $stats = $campaign->campaignRecipients()
                ->selectRaw("
                    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
                    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
                    COUNT(CASE WHEN status = 'opened' THEN 1 END) as opened,
                    COUNT(CASE WHEN status = 'clicked' THEN 1 END) as clicked,
                    COUNT(CASE WHEN status = 'bounced' THEN 1 END) as bounced,
                    COUNT(CASE WHEN status = 'unsubscribed' THEN 1 END) as unsubs,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed
                ")
                ->first();

            // FIX-066: Count per status tier in a single query to avoid multiple round-trips.
            // "Delivered" includes `sent` here because SendCampaignEmailJob sets
            // recipient status to 'sent' on successful handoff to the ISP —
            // only a later bounce webhook downgrades it. Without this, the
            // tier-recalculation at campaign completion was wiping the
            // per-send delivered_count back to 0 and making every rate read 0%.
            $tierCounts = $campaign->campaignRecipients()
                ->selectRaw("
                    COUNT(CASE WHEN status IN ('sent', 'delivered', 'opened', 'clicked') THEN 1 END) as sent_tier,
                    COUNT(CASE WHEN status IN ('sent', 'delivered', 'opened', 'clicked') THEN 1 END) as delivered_tier,
                    COUNT(CASE WHEN status IN ('opened', 'clicked') THEN 1 END) as opened_tier
                ")
                ->first();

            $campaign->update([
                'status' => 'sent',
                'sent_count' => $tierCounts->sent_tier,
                'delivered_count' => $tierCounts->delivered_tier,
                'opened_count' => $tierCounts->opened_tier,
                'clicked_count' => $stats->clicked,
                'bounced_count' => $stats->bounced,
                'unsubscribed_count' => $stats->unsubs,
                'completed_at' => now(),
            ]);

            Log::info('Campaign completed', [
                'campaign_id' => $campaign->id,
                'sent' => $stats->sent,
                'failed' => $stats->failed,
            ]);
        }
    }
}
