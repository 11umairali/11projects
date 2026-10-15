<?php

namespace App\Livewire\Campaigns;

use App\Exceptions\PlanLimitReachedException;
use App\Helpers\HtmlSanitizer;
use App\Models\AbTestVariant;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\Segment;
use App\Models\UsageRecord;
use App\Models\Workspace;
use App\Services\Campaign\CampaignService;
use App\Services\PlanLimitService;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CampaignEditor extends Component
{
    use AuthorizesWorkspaceActions;

    public ?int $campaignId = null;

    public int $currentStep = 1;

    // Step 1: Channel + Basics
    // 'email' (default) keeps the full drag-drop builder flow.
    // 'sms' switches the editor to a Twilio-number picker + plain-text body.
    public string $channel = 'email';

    public string $name = '';

    public string $type = 'regular';

    public string $subject = '';

    public string $previewText = '';

    // Step 2: Body (email path)
    public string $editorMode = 'visual'; // 'visual' or 'html'

    public bool $showEmailBuilder = false;

    public array $bodyBlocks = []; // Block data from visual builder

    public string $bodyHtml = '';

    // Step 2: SMS body (sms path)
    // Plain text only. 160 chars = 1 GSM-7 segment; 70 chars = 1 UCS-2
    // segment (when emoji/unicode is included). The blade shows a live
    // counter with segment estimation so the user knows their bill impact.
    public string $bodyText = '';

    // Twilio "from" phone number (E.164) — selected from active SMS
    // channel_integrations rows for the workspace.
    public ?string $fromNumber = null;

    // Step 3: Audience
    public string $audienceType = 'all';

    public ?int $audienceId = null;

    public int $audienceCount = 0;

    // Specific-contacts audience: user picks individual contacts.
    // Stored as an array of Contact IDs; persisted to campaigns.audience_meta.
    /** @var array<int> */
    public array $specificContactIds = [];

    // UI state for the contact picker (search box + autocomplete list)
    public string $contactSearch = '';

    public array $contactSearchResults = [];

    // Step 4: Schedule
    public string $sendOption = 'now';

    public ?string $scheduledAt = null;

    // Step 4: Throttle / anti-spam delivery controls
    // Defaults chosen to be safe without being painfully slow: 60/min (~1/s)
    // is well under Gmail's bulk-send thresholds and most SMTP provider limits.
    public int $emailsPerMinute = 60;

    public int $batchSize = 0;            // 0 = no batching, just steady rate

    public int $batchDelaySeconds = 0;    // only used when batchSize > 0

    // A/B Test fields
    public string $subjectA = '';

    public string $subjectB = '';

    public int $splitPercentage = 50;

    // From account
    public ?int $emailAccountId = null;

    // Test email (sandbox/preview)
    public string $testEmail = '';

    public bool $testSending = false;

    public ?string $testResult = null;

    // Allowed segment rule fields (whitelist for validation)
    protected array $allowedSegmentFields = [
        'email', 'first_name', 'last_name', 'phone', 'company', 'job_title',
        'city', 'country', 'timezone', 'status', 'lead_score', 'source',
        'tags', 'created_at', 'last_contacted_at',
    ];

    protected function rules(): array
    {
        // Channel-aware validation: email path requires subject+body_html;
        // SMS path requires body_text+from_number. Cross-channel fields
        // (name, audience, schedule, throttle) apply to both.
        $isSms = $this->channel === 'sms';

        return [
            'channel' => 'required|in:email,sms',
            'name' => 'required|string|max:255',
            'type' => 'required|in:regular,ab_test',
            'subject' => $isSms ? 'nullable|string|max:255' : 'required|string|max:255',
            'previewText' => 'nullable|string|max:255',
            'bodyHtml' => $isSms ? 'nullable|string' : 'required|string',
            'bodyText' => $isSms ? 'required|string|max:1600' : 'nullable|string|max:1600',
            'fromNumber' => $isSms ? 'required|string|max:32' : 'nullable|string|max:32',
            'audienceType' => 'required|in:all,segment,list,contacts',
            'audienceId' => 'nullable|integer',
            'sendOption' => 'required|in:now,schedule',
            'scheduledAt' => 'nullable|required_if:sendOption,schedule|date|after:now',
            'emailAccountId' => $isSms ? 'nullable|integer' : 'nullable|integer|exists:email_accounts,id',
            'emailsPerMinute' => 'required|integer|min:1|max:600',
            'batchSize' => 'required|integer|min:0|max:5000',
            'batchDelaySeconds' => 'required|integer|min:0|max:3600',
            'subjectA' => 'required_if:type,ab_test|string|max:255',
            'subjectB' => 'required_if:type,ab_test|string|max:255',
            'splitPercentage' => 'required_if:type,ab_test|integer|min:10|max:90',
        ];
    }

    /**
     * Reset channel-specific defaults when the user toggles between
     * email and SMS. Without this, the per-minute default (60 = sane for
     * email) stays at 60 for SMS too — which is exactly Twilio's default
     * cap, so we can keep it. But we DO want to clear opposing fields so
     * stale values don't accidentally persist.
     */
    public function updatedChannel(string $value): void
    {
        if ($value === 'sms') {
            // Clear email-specific draft state when switching to SMS so the
            // editor doesn't hold ghosts of an abandoned email design.
            $this->subject = '';
            // Don't wipe bodyHtml — user might switch back. Just hide it.
        } else {
            $this->bodyText = '';
            $this->fromNumber = null;
        }
        $this->computeAudienceCount();
    }

    public function mount(?int $campaignId = null): void
    {
        $this->campaignId = $campaignId;

        if ($campaignId) {
            $campaign = Campaign::where('workspace_id', $this->workspaceId())
                ->findOrFail($campaignId);

            $this->channel = $campaign->channel ?: 'email';
            $this->name = $campaign->name;
            $this->type = $campaign->type;
            $this->subject = $campaign->subject ?? '';
            $this->previewText = $campaign->preview_text ?? '';
            $this->bodyHtml = $campaign->body_html ?? '';
            $this->bodyText = $campaign->body_text ?? '';
            $this->fromNumber = $campaign->from_number;
            $this->bodyBlocks = $campaign->body_json ?? [];
            $this->editorMode = ! empty($campaign->body_json) ? 'visual' : 'html';
            $this->audienceType = $campaign->audience_type ?? 'all';
            $this->audienceId = $campaign->audience_id;
            if ($this->audienceType === 'contacts') {
                $meta = $campaign->audience_meta ?? [];
                $this->specificContactIds = array_values(array_map('intval', $meta['contact_ids'] ?? []));
            }
            $this->emailAccountId = $campaign->email_account_id;

            if ($campaign->scheduled_at) {
                $this->sendOption = 'schedule';
                // Convert the stored UTC timestamp back to the user's tz so
                // the datetime-local input shows the same wall-clock time
                // they originally picked — not a UTC-shifted value.
                $this->scheduledAt = $campaign->scheduled_at
                    ->copy()
                    ->setTimezone($this->userTimezone())
                    ->format('Y-m-d\TH:i');
            }

            $this->emailsPerMinute = $campaign->emails_per_minute ?: 60;
            $this->batchSize = $campaign->batch_size ?: 0;
            $this->batchDelaySeconds = $campaign->batch_delay_seconds ?: 0;

            if ($campaign->type === 'ab_test') {
                $variants = $campaign->abTestVariants;
                $variantA = $variants->firstWhere('variant', 'A');
                $variantB = $variants->firstWhere('variant', 'B');
                $this->subjectA = $variantA?->subject ?? '';
                $this->subjectB = $variantB?->subject ?? '';
                $this->splitPercentage = $variantA?->percentage ?? 50;
            }
        }

        $this->computeAudienceCount();

        // Default the test email to the current user's address
        $this->testEmail = auth()->user()->email ?? '';
    }

    public function updatedAudienceType(): void
    {
        if ($this->audienceType === 'all') {
            $this->audienceId = null;
        }
        if ($this->audienceType !== 'contacts') {
            // Clear picker state when switching away from "specific contacts"
            $this->contactSearch = '';
            $this->contactSearchResults = [];
        }
        $this->computeAudienceCount();
    }

    /**
     * Live search for contacts as the user types in the picker box.
     */
    public function updatedContactSearch(): void
    {
        $term = trim($this->contactSearch);
        if (strlen($term) < 2) {
            $this->contactSearchResults = [];
            return;
        }

        $like = '%' . $term . '%';
        $this->contactSearchResults = Contact::where('workspace_id', $this->workspaceId())
            ->where('status', 'active')
            ->whereNull('unsubscribed_at')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotIn('id', $this->specificContactIds)
            ->where(function ($q) use ($like) {
                $q->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('company', 'like', $like);
            })
            ->limit(10)
            ->get(['id', 'email', 'first_name', 'last_name', 'company'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'email' => $c->email,
                'name' => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: $c->email,
                'company' => $c->company,
            ])
            ->toArray();
    }

    public function addSpecificContact(int $contactId): void
    {
        if (in_array($contactId, $this->specificContactIds, true)) {
            return;
        }

        // Ownership check: contact must belong to the current workspace.
        $belongs = Contact::where('workspace_id', $this->workspaceId())
            ->where('id', $contactId)
            ->exists();

        if (! $belongs) {
            Log::warning('CampaignEditor: rejected contact add — not in workspace', [
                'contact_id' => $contactId,
                'workspace_id' => $this->workspaceId(),
            ]);
            return;
        }

        $this->specificContactIds[] = $contactId;
        $this->contactSearch = '';
        $this->contactSearchResults = [];
        $this->computeAudienceCount();
    }

    public function removeSpecificContact(int $contactId): void
    {
        $this->specificContactIds = array_values(array_filter(
            $this->specificContactIds,
            fn ($id) => $id !== $contactId
        ));
        $this->computeAudienceCount();
    }

    /**
     * Helper for the blade template: returns the selected contacts'
     * display info (used to render the chip list in the picker).
     *
     * @return array<int, array{id:int,email:string,name:string,company:?string}>
     */
    public function getSelectedContactsListProperty(): array
    {
        if (empty($this->specificContactIds)) {
            return [];
        }

        return Contact::where('workspace_id', $this->workspaceId())
            ->whereIn('id', $this->specificContactIds)
            ->get(['id', 'email', 'first_name', 'last_name', 'company'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'email' => $c->email,
                'name' => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: $c->email,
                'company' => $c->company,
            ])
            ->toArray();
    }

    /**
     * Human-readable estimate of how long this campaign will take to send
     * given the audience size, send rate, and batching settings. Returns
     * something like "1h 23m 45s". Computed on the server so the blade
     * doesn't need an in-template @php block (which was intermittently
     * losing variables across Livewire re-renders).
     */
    public function getEstimatedSendDurationProperty(): string
    {
        $audience = (int) $this->audienceCount;
        if ($audience <= 0) {
            return '';
        }

        $rate = max(1, (int) $this->emailsPerMinute);
        $ratePerSec = max(0.01, $rate / 60);
        $seconds = (int) ceil($audience / $ratePerSec);

        $batchSize = (int) $this->batchSize;
        $batchDelay = (int) $this->batchDelaySeconds;
        if ($batchSize > 0 && $batchDelay > 0 && $audience > $batchSize) {
            $batches = (int) ceil($audience / $batchSize);
            $seconds += max(0, $batches - 1) * $batchDelay;
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        $parts = [];
        if ($h > 0) $parts[] = $h . 'h';
        if ($m > 0 || $h > 0) $parts[] = $m . 'm';
        $parts[] = $s . 's';

        return implode(' ', $parts);
    }

    public function updatedAudienceId(): void
    {
        $this->validateAudienceOwnership();
        $this->computeAudienceCount();
    }

    /**
     * Verify the audienceId (segment) belongs to the current workspace.
     *
     * Prevents client-side manipulation of the public property to access
     * segments from other workspaces.
     */
    protected function validateAudienceOwnership(): void
    {
        if ($this->audienceId === null) {
            return;
        }

        $model = $this->audienceType === 'list'
            ? \App\Models\ContactList::class
            : Segment::class;

        $belongs = $model::where('workspace_id', $this->workspaceId())
            ->where('id', $this->audienceId)
            ->exists();

        if (! $belongs) {
            Log::warning('CampaignEditor: audienceId does not belong to workspace', [
                'audience_id' => $this->audienceId,
                'audience_type' => $this->audienceType,
                'workspace_id' => $this->workspaceId(),
                'user_id' => auth()->id(),
            ]);
            $this->audienceId = null;
            $this->addError('audienceId', 'The selected audience is not available.');
        }
    }

    protected function computeAudienceCount(): void
    {
        $workspaceId = $this->workspaceId();
        $isSms = $this->channel === 'sms';

        // Channel-aware reachability filter:
        //   email path  → contacts with a non-empty `email`
        //   sms path    → contacts with a non-empty `phone`
        // Without this, an SMS campaign would show inflated audience counts
        // and then silently drop ~half its recipients at send time.
        $reachable = function ($q) use ($isSms) {
            if ($isSms) {
                $q->whereNotNull('phone')->where('phone', '!=', '');
            } else {
                $q->whereNotNull('email')->where('email', '!=', '');
            }
        };

        if ($this->audienceType === 'all') {
            $this->audienceCount = Contact::where('workspace_id', $workspaceId)
                ->where('status', 'active')
                ->whereNull('unsubscribed_at')
                ->where($reachable)
                ->count();
        } elseif ($this->audienceType === 'segment' && $this->audienceId) {
            $segment = Segment::where('workspace_id', $workspaceId)
                ->find($this->audienceId);
            // Segment counts are pre-aggregated for email; for SMS we need a
            // live count filtered by phone presence.
            if ($segment && $isSms) {
                $this->audienceCount = Contact::where('workspace_id', $workspaceId)
                    ->whereNotNull('phone')->where('phone', '!=', '')
                    ->where('status', 'active')
                    ->whereNull('unsubscribed_at')
                    ->count();
            } else {
                $this->audienceCount = $segment?->contacts_count ?? 0;
            }
        } elseif ($this->audienceType === 'list' && $this->audienceId) {
            $list = \App\Models\ContactList::where('workspace_id', $workspaceId)
                ->find($this->audienceId);
            if ($list && $isSms) {
                $this->audienceCount = $list->contacts()
                    ->whereNotNull('phone')->where('phone', '!=', '')
                    ->count();
            } else {
                $this->audienceCount = $list?->contacts()->count() ?? 0;
            }
        } elseif ($this->audienceType === 'contacts') {
            // Count only workspace-owned, active, reachable, non-unsubscribed picks.
            $this->audienceCount = Contact::where('workspace_id', $workspaceId)
                ->whereIn('id', $this->specificContactIds ?: [0])
                ->where('status', 'active')
                ->whereNull('unsubscribed_at')
                ->where($reachable)
                ->count();
        } else {
            $this->audienceCount = 0;
        }
    }

    public function goToStep(int $step): void
    {
        // Validate current step before moving forward
        if ($step > $this->currentStep) {
            if ($this->currentStep === 1) {
                // Step 1 always requires channel + name + type.
                // Subject is only required for the email channel (SMS has none).
                $this->validate([
                    'channel' => 'required|in:email,sms',
                    'name' => 'required|string|max:255',
                    'type' => 'required|in:regular,ab_test',
                ]);

                if ($this->channel === 'email') {
                    $this->validate([
                        'subject' => 'required|string|max:255',
                    ]);
                }

                if ($this->type === 'ab_test') {
                    $this->validate([
                        'subjectA' => 'required|string|max:255',
                        'subjectB' => 'required|string|max:255',
                        'splitPercentage' => 'required|integer|min:10|max:90',
                    ]);
                }
            } elseif ($this->currentStep === 2) {
                // Step 2 body validation differs by channel.
                if ($this->channel === 'sms') {
                    $this->validate([
                        'bodyText' => 'required|string|max:1600',
                        'fromNumber' => 'required|string|max:32',
                    ]);
                } else {
                    $this->validate([
                        'bodyHtml' => 'required|string',
                    ]);
                }
            } elseif ($this->currentStep === 3) {
                $this->validate([
                    'audienceType' => 'required|in:all,segment,list,contacts',
                ]);

                if ($this->audienceType === 'contacts' && empty($this->specificContactIds)) {
                    $this->addError('audienceType', 'Please pick at least one contact before continuing.');
                    return;
                }
            }
        }

        $this->currentStep = $step;
    }

    /**
     * Send a test/preview email to the specified address.
     * Rate limited to 5 sends per hour per user.
     */
    public function sendTestEmail(): void
    {
        $this->testResult = null;
        $this->testSending = true;

        try {
            // Validate the test email address
            $this->validate([
                'testEmail' => 'required|email:rfc,dns|max:255',
            ]);

            // Require a subject and body before sending a test
            if (empty(trim($this->subject))) {
                $this->testResult = 'error:Please enter a subject line before sending a test.';
                return;
            }
            if (empty(trim($this->bodyHtml))) {
                $this->testResult = 'error:Please add email content before sending a test.';
                return;
            }

            // Require an email account to be selected
            if (! $this->emailAccountId) {
                $this->testResult = 'error:Please select an email account in Step 1 before sending a test.';
                return;
            }

            // Verify the email account exists, is connected, and belongs to this workspace
            $emailAccount = EmailAccount::where('workspace_id', $this->workspaceId())
                ->where('id', $this->emailAccountId)
                ->where('status', 'connected')
                ->first();

            if (! $emailAccount) {
                $this->testResult = 'error:The selected email account is not available. Please choose a connected account.';
                return;
            }

            // Rate limit: 5 test sends per hour per user
            $rateLimitKey = 'campaign-test-email:' . auth()->id();

            if (RateLimiter::tooManyAttempts($rateLimitKey, 60)) {
                $retryAfter = RateLimiter::availableIn($rateLimitKey);
                $minutes = (int) ceil($retryAfter / 60);
                $this->testResult = "error:You have reached the limit of 5 test emails per hour. Try again in {$minutes} minute(s).";
                return;
            }

            // Build a temporary Campaign object with the current editor state
            // so CampaignService::sendTestEmail can work without requiring a save
            $campaign = new Campaign([
                'workspace_id' => $this->workspaceId(),
                'subject' => $this->subject,
                'body_html' => $this->bodyHtml,
                'email_account_id' => $this->emailAccountId,
            ]);

            // If the campaign was already saved, use its ID/UUID for header tracking
            if ($this->campaignId) {
                $existing = Campaign::where('workspace_id', $this->workspaceId())
                    ->find($this->campaignId);
                if ($existing) {
                    $campaign = $existing;
                    // Use latest editor state (may not be saved yet)
                    $campaign->subject = $this->subject;
                    $campaign->body_html = $this->bodyHtml;
                }
            }

            /** @var CampaignService $campaignService */
            $campaignService = app(CampaignService::class);
            $campaignService->sendTestEmail($campaign, $this->testEmail, $emailAccount);

            // Count the attempt only on success
            RateLimiter::hit($rateLimitKey, 3600);

            $remaining = 5 - RateLimiter::attempts($rateLimitKey);
            $this->testResult = "success:Test email sent to {$this->testEmail}. ({$remaining} test(s) remaining this hour)";

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->testResult = 'error:' . collect($e->errors())->flatten()->first();
        } catch (\Throwable $e) {
            Log::error('Campaign test email failed', [
                'user_id' => auth()->id(),
                'email' => $this->testEmail,
                'error' => $e->getMessage(),
            ]);
            $this->testResult = 'error:Failed to send test email. ' . $e->getMessage();
        } finally {
            $this->testSending = false;
        }
    }

    /**
     * Receive compiled HTML and block data from the EmailBuilder component.
     * Called via Livewire event dispatch from the builder.
     */
    #[\Livewire\Attributes\On('builder-html-ready')]
    public function useBuilderHtml(string $html, array $blocks = []): void
    {
        $this->bodyHtml = $html;
        $this->bodyBlocks = $blocks;
        $this->showEmailBuilder = false;

        // When the builder overlay was open the viewport was inside that
        // fullscreen child component; after Livewire closes the overlay the
        // parent re-renders and the browser is still scrolled past the step
        // wizard/header. That's why the user reported "no steps showing" —
        // they just needed to scroll up. Do it for them.
        $this->js('window.scrollTo({ top: 0, behavior: "smooth" });');

        session()->flash('success', 'Email content loaded into campaign. You can now proceed to the next step.');
    }

    /**
     * Close the email builder overlay without saving.
     */
    #[\Livewire\Attributes\On('builder-close')]
    public function closeBuilder(): void
    {
        $this->showEmailBuilder = false;
        $this->js('window.scrollTo({ top: 0, behavior: "smooth" });');
    }

    public function saveDraft(): void
    {
        if (! $this->authorizeWorkspaceAction('create')) {
            return;
        }

        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        $campaign = $this->saveCampaign('draft');

        session()->flash('success', 'Campaign saved as draft.');
        $this->redirect(route('campaigns'), navigate: true);
    }

    public function schedule(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->validate();

        // Just require a valid date string — we do the "is it in the future?"
        // check manually below so it runs in the user's timezone, not in the
        // app's default tz (which is UTC and rejects valid future-IST picks).
        $this->validate([
            'scheduledAt' => 'required|date',
        ]);

        $tz = $this->userTimezone();
        $picked = \Carbon\Carbon::parse($this->scheduledAt, $tz);
        if ($picked->isPast()) {
            $this->addError('scheduledAt', 'Scheduled time must be in the future.');
            return;
        }

        // Verify audience has recipients before scheduling
        $this->computeAudienceCount();

        if ($this->audienceCount < 1) {
            $this->addError('audienceType', 'Your campaign has no recipients. Please add contacts or select a different audience before scheduling.');

            return;
        }

        // Pre-flight plan limit check — same as sendNow(). Catching it at
        // schedule time means the user isn't surprised by a campaign that
        // fails silently at 3 AM when the scheduler fires it.
        if (! $this->hasRoomForCampaign()) {
            return;
        }

        $this->withOperationLock("campaign-schedule-{$this->campaignId}-".auth()->id(), function () {
            $campaign = $this->saveCampaign('scheduled');
            session()->flash('success', 'Campaign scheduled successfully.');
            $this->redirect(route('campaigns'), navigate: true);
        });
    }

    public function sendNow(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $isSms = $this->channel === 'sms';

        // Plan gate: bulk SMS campaigns are a Pro-tier feature.
        // Even if the user crafted a Livewire payload to set channel='sms'
        // on a Free plan, the backend rejects here.
        if ($isSms) {
            $workspace = \App\Models\Workspace::find($this->workspaceId());
            $plan = app(\App\Services\PlanLimitService::class);
            if (!$plan->hasFeature($workspace, 'sms_campaigns')) {
                $this->addError('channel', __('Bulk SMS campaigns require a Pro plan. Please upgrade to send SMS broadcasts.'));
                return;
            }
        }

        // Channel-specific pre-flight validation.
        // Email path requires subject + body_html + connected email account.
        // SMS path requires body_text + from_number selected.
        if ($isSms) {
            $this->validate([
                'name' => 'required|string|max:255',
                'bodyText' => 'required|string|max:1600',
                'fromNumber' => 'required|string|max:32',
            ]);

            // Verify the chosen Twilio number actually belongs to an active
            // SMS integration on this workspace — defense against client-side
            // tampering of the dropdown value.
            $integration = \App\Models\ChannelIntegration::where('workspace_id', $this->workspaceId())
                ->where('channel', 'sms')
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->where('phone_number', $this->fromNumber);
                })
                ->first();

            if (!$integration) {
                $this->addError('fromNumber', 'Please select a valid connected Twilio number.');
                return;
            }
        } else {
            $this->validate([
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
                'bodyHtml' => 'required|string',
                'emailAccountId' => 'required|integer|exists:email_accounts,id',
            ]);

            // Verify email account belongs to workspace
            $emailAccount = EmailAccount::where('workspace_id', $this->workspaceId())
                ->where('id', $this->emailAccountId)
                ->where('status', 'connected')
                ->first();

            if (! $emailAccount) {
                $this->addError('emailAccountId', 'Please select a valid connected email account.');

                return;
            }
        }

        // Recompute audience count to get fresh number before sending
        $this->computeAudienceCount();

        if ($this->audienceCount < 1) {
            $this->addError('audienceType', 'Your campaign has no recipients. Please add contacts or select a different audience before sending.');

            return;
        }

        // Pre-flight plan limit check — fail loudly NOW instead of letting
        // the job silently mark every recipient 'failed' with "Monthly email
        // limit reached". Admins (is_admin=true) are exempt.
        if (! $this->hasRoomForCampaign()) {
            return; // addError was set inside hasRoomForCampaign()
        }

        $this->withOperationLock("campaign-send-{$this->campaignId}-".auth()->id(), function () {
            // Duplicate send prevention: check if already sending/sent
            if ($this->campaignId) {
                $existing = Campaign::where('workspace_id', $this->workspaceId())
                    ->where('id', $this->campaignId)
                    ->whereIn('status', ['sending', 'sent'])
                    ->exists();

                if ($existing) {
                    session()->flash('error', 'This campaign has already been sent or is currently sending.');

                    return;
                }
            }

            // Save as draft first; CampaignService::sendCampaign() will flip
            // the status to 'sending' inside its own transaction so the
            // atomic lock-and-transition logic in the service stays the
            // single source of truth for that state change.
            $campaign = $this->saveCampaign('draft');

            try {
                app(\App\Services\Campaign\CampaignService::class)->sendCampaign($campaign);
            } catch (\Throwable $e) {
                Log::error('CampaignEditor: sendCampaign failed', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
                $campaign->update(['status' => 'draft']);
                session()->flash('error', 'Failed to start campaign: ' . $e->getMessage());
                return;
            }

            $campaign->refresh();
            session()->flash('success', "Campaign is being sent to {$campaign->recipients_count} recipients.");
            $this->redirect(route('campaigns'), navigate: true);
        });
    }

    protected function saveCampaign(string $status): Campaign
    {
        // Enforce plan limit when creating a new campaign (not editing existing)
        if (! $this->campaignId) {
            try {
                $workspace = Workspace::findOrFail($this->workspaceId());
                app(PlanLimitService::class)->assertCanCreate($workspace, 'campaigns_per_month');
            } catch (PlanLimitReachedException $e) {
                session()->flash('error', $e->getMessage());
                throw $e; // Re-throw to abort the calling method
            }
        }

        // Validate audienceId ownership before persisting
        $this->validateAudienceOwnership();

        // Build audience_meta payload for types that need extra state
        // (currently only the specific-contacts picker).
        $audienceMeta = null;
        if ($this->audienceType === 'contacts') {
            // Re-validate ownership of every selected contact ID before persist.
            $ownedIds = Contact::where('workspace_id', $this->workspaceId())
                ->whereIn('id', $this->specificContactIds ?: [0])
                ->pluck('id')
                ->all();
            $audienceMeta = ['contact_ids' => array_values(array_map('intval', $ownedIds))];
        }

        // Convert the user's wall-clock pick ("2026-04-18T12:05") from their
        // timezone into UTC before storage. Without this, Laravel's datetime
        // cast reads the naive string as UTC and the campaign fires 5:30
        // hours late for India users (same bug that hit ComposeEmail).
        $scheduledUtc = null;
        if ($status === 'scheduled' && $this->scheduledAt) {
            $tz = $this->userTimezone();
            $scheduledUtc = \Carbon\Carbon::parse($this->scheduledAt, $tz)->utc();
            Log::info('CampaignEditor: scheduling in tz', [
                'input' => $this->scheduledAt,
                'tz_used' => $tz,
                'stored_utc' => $scheduledUtc->toDateTimeString(),
            ]);
        }

        $isSms = $this->channel === 'sms';

        $data = [
            'workspace_id' => $this->workspaceId(),
            'created_by' => auth()->id(),
            // SMS campaigns don't use an email_account; persist NULL.
            'email_account_id' => $isSms ? null : $this->emailAccountId,
            'channel' => $isSms ? 'sms' : 'email',
            'from_number' => $isSms ? $this->fromNumber : null,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $status,
            'subject' => $isSms ? null : $this->subject,
            // FIX-023: Sanitize HTML at save time, not just preview
            'body_html' => $isSms ? null : HtmlSanitizer::sanitize($this->bodyHtml),
            'body_text' => $isSms ? $this->bodyText : null,
            'body_json' => $isSms ? null : (! empty($this->bodyBlocks) ? $this->bodyBlocks : null),
            'preview_text' => $isSms ? null : $this->previewText,
            'audience_type' => $this->audienceType,
            'audience_id' => $this->audienceType === 'contacts' ? null : $this->audienceId,
            'audience_meta' => $audienceMeta,
            'recipients_count' => $this->audienceCount,
            'scheduled_at' => $scheduledUtc,
            // Throttle controls — clamp defensively in case validation was
            // bypassed (e.g. editing via API later). Used for both email + SMS.
            'emails_per_minute' => max(1, min(600, (int) $this->emailsPerMinute)),
            'batch_size' => max(0, min(5000, (int) $this->batchSize)),
            'batch_delay_seconds' => max(0, min(3600, (int) $this->batchDelaySeconds)),
        ];

        if ($this->campaignId) {
            $campaign = Campaign::where('workspace_id', $this->workspaceId())
                ->findOrFail($this->campaignId);
            $campaign->update($data);
        } else {
            $campaign = Campaign::create($data);
            $this->campaignId = $campaign->id;

            // Track campaign creation usage for plan limit enforcement
            UsageRecord::incrementUsage($this->workspaceId(), 'campaigns');
        }

        // Handle A/B test variants
        if ($this->type === 'ab_test') {
            $campaign->abTestVariants()->delete();

            AbTestVariant::create([
                'campaign_id' => $campaign->id,
                'variant' => 'A',
                'subject' => $this->subjectA,
                'body_html' => $this->bodyHtml,
                'percentage' => $this->splitPercentage,
            ]);

            AbTestVariant::create([
                'campaign_id' => $campaign->id,
                'variant' => 'B',
                'subject' => $this->subjectB,
                'body_html' => $this->bodyHtml,
                'percentage' => 100 - $this->splitPercentage,
            ]);
        }

        return $campaign;
    }

    protected function buildRecipients(Campaign $campaign): void
    {
        $workspaceId = $this->workspaceId();

        // Defense-in-depth: re-validate audienceId ownership before building recipients
        $this->validateAudienceOwnership();

        $contactQuery = Contact::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->whereNull('unsubscribed_at');

        // Specific-contacts audience: restrict the base query to the picked IDs
        if ($this->audienceType === 'contacts') {
            $ids = $this->specificContactIds ?: [0];
            $contactQuery->whereIn('id', $ids);
        }

        // Contact-list audience: join through the pivot so only list members match
        if ($this->audienceType === 'list' && $this->audienceId) {
            $contactQuery->whereHas('lists', fn ($q) => $q->where('contact_lists.id', $this->audienceId));
        }

        // Apply segment filtering if audience is a segment
        if ($this->audienceType === 'segment' && $this->audienceId) {
            $segment = Segment::where('workspace_id', $workspaceId)->find($this->audienceId);
            if ($segment && ! empty($segment->rules)) {
                $rules = $segment->rules;
                $conditions = $rules['conditions'] ?? [];
                $matchType = $rules['match'] ?? 'all';
                $method = $matchType === 'any' ? 'orWhere' : 'where';

                $contactQuery->where(function ($group) use ($conditions, $method) {
                    foreach ($conditions as $index => $condition) {
                        $field = $condition['field'] ?? null;
                        $operator = $condition['operator'] ?? 'equals';
                        $value = $condition['value'] ?? null;

                        // FIX-070: Log warning when condition field is missing
                        if (! $field) {
                            Log::warning('Segment condition skipped: missing field', [
                                'campaign_id' => $campaign->id,
                                'segment_id' => $this->audienceId,
                                'condition_index' => $index,
                                'condition' => $condition,
                            ]);

                            continue;
                        }

                        // Whitelist field names to prevent SQL injection
                        $isCustomField = str_starts_with($field, 'custom_fields.');
                        $isTagField = $field === 'tags';
                        if (! $isCustomField && ! $isTagField && ! in_array($field, $this->allowedSegmentFields)) {
                            // FIX-070: Log warning when condition field is not in allowed list
                            Log::warning('Segment condition skipped: field not in allowed list', [
                                'campaign_id' => $campaign->id,
                                'segment_id' => $this->audienceId,
                                'condition_index' => $index,
                                'field' => $field,
                                'allowed_fields' => $this->allowedSegmentFields,
                            ]);

                            continue;
                        }

                        $group->{$method}(function ($q) use ($field, $operator, $value) {
                            if ($field === 'tags' && $operator === 'has_tag') {
                                $q->whereHas('tags', fn ($tq) => $tq->where('name', $value));
                            } elseif (str_starts_with($field, 'custom_fields.')) {
                                $jsonKey = str_replace('custom_fields.', '', $field);
                                // Sanitize JSON key to prevent injection
                                $jsonKey = preg_replace('/[^a-zA-Z0-9_]/', '', $jsonKey);
                                $q->where("custom_fields->{$jsonKey}", $value);
                            } else {
                                match ($operator) {
                                    'equals', 'is' => $q->where($field, '=', $value),
                                    'not_equals' => $q->where($field, '!=', $value),
                                    'contains' => $q->where($field, 'LIKE', "%{$value}%"),
                                    'greater_than' => $q->where($field, '>', $value),
                                    'less_than' => $q->where($field, '<', $value),
                                    default => $q->where($field, '=', $value),
                                };
                            }
                        });
                    }
                });
            }
        }

        $contacts = $contactQuery->get(['id', 'email']);

        $recipients = $contacts->map(function ($contact) use ($campaign) {
            return [
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        if (! empty($recipients)) {
            // Insert in chunks to prevent memory issues with large audiences
            foreach (array_chunk($recipients, 1000) as $chunk) {
                $campaign->campaignRecipients()->insert($chunk);
            }
            $campaign->update(['recipients_count' => count($recipients)]);
        }
    }

    protected function workspaceId(): ?int
    {
        return auth()->user()->active_workspace_id;
    }

    /**
     * Pre-flight check: is the workspace allowed to send this campaign under
     * its plan's monthly email cap? Admins (is_admin=true) always pass.
     *
     * Sets a friendly validation error and returns false if the workspace
     * would exhaust its quota partway through — so the user sees "you'll
     * exceed your plan limit by N emails" BEFORE spending time on a doomed
     * campaign, rather than discovering it when recipients start failing.
     */
    protected function hasRoomForCampaign(): bool
    {
        $user = auth()->user();
        $workspace = $user?->activeWorkspace;

        if (!$workspace) {
            $this->addError('audienceType', 'No active workspace found.');
            return false;
        }

        $planLimits = app(PlanLimitService::class);

        // Admins bypass all limits. Also catches job-context via workspace
        // membership check inside isExempt().
        if ($user->is_admin) {
            return true;
        }

        // `canUse` returns false only when the workspace is over quota
        // (or at quota) for this period. For a multi-recipient campaign we
        // ALSO want to warn when the *total* send would blow past the cap,
        // not just when a single send would.
        if (!$planLimits->canUse($workspace, 'emails_per_month')) {
            $this->addError(
                'audienceType',
                'You have reached your monthly email limit. '
                . 'Please upgrade your plan before sending a campaign.'
            );
            return false;
        }

        // Nicer warning when this campaign will push them over the edge —
        // gives the user a chance to shrink the audience or upgrade.
        try {
            $used = \App\Models\UsageRecord::where('workspace_id', $workspace->id)
                ->where('feature_key', 'emails_sent')
                ->where('period', now()->format('Y-m'))
                ->value('quantity') ?? 0;

            $plan = $workspace->activeSubscription?->plan ?? null;
            $cap = $plan?->features()->where('feature_key', 'emails_per_month')->value('value');
            $cap = is_numeric($cap) ? (int) $cap : null;

            if ($cap !== null && ($used + $this->audienceCount) > $cap) {
                $overflow = ($used + $this->audienceCount) - $cap;
                $this->addError(
                    'audienceType',
                    "This campaign would exceed your monthly email limit by {$overflow}. "
                    . "You've used {$used} of {$cap}. Reduce the audience or upgrade your plan."
                );
                return false;
            }
        } catch (\Throwable $e) {
            // Non-fatal — worst case we skip the fancy preview warning and
            // fall back to the runtime check inside SendCampaignEmailJob.
            Log::warning('CampaignEditor: overflow preflight failed', ['error' => $e->getMessage()]);
        }

        return true;
    }

    /**
     * Resolve the authenticated user's timezone. The inbox JS persists the
     * browser's IANA tz into `users.timezone` on every inbox visit, so by
     * the time the user reaches the campaigns page this is almost always
     * accurate. Matches ComposeEmail::userTimezone() so scheduling behaves
     * identically across the app.
     */
    protected function userTimezone(): string
    {
        $profile = auth()->user()->timezone;
        $workspace = auth()->user()->activeWorkspace?->timezone;
        $resolved = $profile ?: ($workspace ?: config('app.timezone', 'UTC'));

        Log::info('CampaignEditor: userTimezone resolved', [
            'profile_tz' => $profile,
            'workspace_tz' => $workspace,
            'resolved' => $resolved,
        ]);

        return $resolved;
    }

    /**
     * Sanitized HTML for the in-editor preview pane.
     *
     * $bodyHtml is raw user input from a textarea — it MUST be sanitized
     * before rendering with {!! !!} to prevent stored/reflected XSS.
     */
    #[Computed]
    public function safeBodyHtml(): string
    {
        return HtmlSanitizer::sanitize($this->bodyHtml);
    }

    public function render()
    {
        $workspaceId = $this->workspaceId();

        $emailAccounts = EmailAccount::where('workspace_id', $workspaceId)
            ->where('status', 'connected')
            ->get();

        // Active SMS / Twilio integrations for this workspace — populates
        // the "From number" dropdown on the SMS path. Each row provides a
        // verified phone_number (E.164) the campaign can send from.
        $smsNumbers = \App\Models\ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'sms')
            ->where('status', 'active')
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->get(['id', 'phone_number', 'account_name']);

        $segments = Segment::where('workspace_id', $workspaceId)->get();
        $contactLists = \App\Models\ContactList::where('workspace_id', $workspaceId)->get();

        // Total reachable contacts depends on channel: emails-with-address vs
        // phones-with-number. Shown next to "All Contacts" in the audience step.
        $totalContacts = Contact::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->whereNull('unsubscribed_at')
            ->when($this->channel === 'sms',
                fn ($q) => $q->whereNotNull('phone')->where('phone', '!=', ''),
                fn ($q) => $q->whereNotNull('email')->where('email', '!=', '')
            )
            ->count();

        // Plan gate: SMS campaigns are a Pro feature. The blade uses this
        // to lock the SMS choice in Step 1 with an "Upgrade" badge.
        $workspace = \App\Models\Workspace::find($workspaceId);
        $plan = app(\App\Services\PlanLimitService::class);
        $planFeatures = [
            'sms_campaigns' => $workspace ? $plan->hasFeature($workspace, 'sms_campaigns') : false,
        ];

        return view('livewire.campaigns.campaign-editor', [
            'emailAccounts' => $emailAccounts,
            'smsNumbers' => $smsNumbers,
            'segments' => $segments,
            'contactLists' => $contactLists,
            'totalContacts' => $totalContacts,
            'planFeatures' => $planFeatures,
        ]);
    }
}
