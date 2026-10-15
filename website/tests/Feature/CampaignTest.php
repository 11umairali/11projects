<?php

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailSuppression;
use App\Models\AbTestVariant;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Campaign\CampaignService;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Campaign Tests
|--------------------------------------------------------------------------
| Tests campaign lifecycle: create, schedule, send, A/B testing, pause,
| suppression, duplicate send prevention, and stats.
*/

beforeEach(function () {
    Mail::fake(); // Prevent real SMTP connections in tests

    $setup = createUserWithWorkspace('owner');
    $this->user = $setup['user'];
    $this->workspace = $setup['workspace'];
    $this->actingAs($this->user);

    $this->emailAccount = EmailAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
});

// ---- Create ----

test('create campaign draft', function () {
    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'status' => 'draft',
    ]);

    expect($campaign->status)->toBe('draft');
    expect($campaign->isDraft())->toBeTrue();
    expect($campaign->uuid)->not->toBeNull();
    expect($campaign->workspace_id)->toBe($this->workspace->id);
});

// ---- Schedule ----

test('schedule campaign sets scheduled_at', function () {
    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
    ]);

    $scheduledTime = now()->addHours(2);
    $campaign->update([
        'status' => 'scheduled',
        'scheduled_at' => $scheduledTime,
    ]);

    $campaign->refresh();
    expect($campaign->status)->toBe('scheduled');
    expect($campaign->scheduled_at)->not->toBeNull();
});

// ---- Send ----

test('campaign send creates recipient records', function () {
    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'status' => 'draft',
        'audience_type' => 'all',
    ]);

    // Create contacts in the workspace
    Contact::factory()->count(5)->create([
        'workspace_id' => $this->workspace->id,
        'status' => 'active',
    ]);

    $service = app(CampaignService::class);
    $service->sendCampaign($campaign);

    $campaign->refresh();
    expect($campaign->status)->toBe('sending');
    expect($campaign->recipients_count)->toBe(5);
    expect($campaign->campaignRecipients()->count())->toBe(5);
});

test('campaign sends only to non-suppressed contacts', function () {
    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'status' => 'draft',
        'audience_type' => 'all',
    ]);

    // Create 5 active contacts
    $contacts = Contact::factory()->count(5)->create([
        'workspace_id' => $this->workspace->id,
        'status' => 'active',
    ]);

    // Suppress 2 of them
    foreach ($contacts->take(2) as $c) {
        EmailSuppression::create([
            'workspace_id' => $this->workspace->id,
            'email' => $c->email,
            'reason' => 'hard_bounce',
        ]);
    }

    $service = app(CampaignService::class);
    $service->sendCampaign($campaign);

    $campaign->refresh();
    expect($campaign->recipients_count)->toBe(3);
});

test('contacts without email are excluded from campaign', function () {
    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'status' => 'draft',
        'audience_type' => 'all',
    ]);

    // 3 contacts with email, 2 without
    Contact::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'status' => 'active',
    ]);

    Contact::factory()->count(2)->withoutEmail()->create([
        'workspace_id' => $this->workspace->id,
        'status' => 'active',
    ]);

    $service = app(CampaignService::class);
    $service->sendCampaign($campaign);

    $campaign->refresh();
    expect($campaign->recipients_count)->toBe(3);
});

// ---- Double-send prevention ----

test('duplicate send prevention blocks second send', function () {
    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'status' => 'draft',
        'audience_type' => 'all',
    ]);

    Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'status' => 'active',
    ]);

    $service = app(CampaignService::class);
    $service->sendCampaign($campaign);

    // Second send should throw because status is no longer draft/scheduled
    expect(fn () => $service->sendCampaign($campaign->fresh()))
        ->toThrow(RuntimeException::class);
});

// ---- Campaign with no recipients ----

test('campaign with no recipients completes immediately', function () {
    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'status' => 'draft',
        'audience_type' => 'all',
    ]);

    // No contacts in workspace

    $service = app(CampaignService::class);
    $service->sendCampaign($campaign);

    $campaign->refresh();
    expect($campaign->status)->toBe('sent');
    expect($campaign->recipients_count)->toBe(0);
    expect($campaign->completed_at)->not->toBeNull();
});

// ---- Campaign missing configuration ----

test('campaign without email account throws exception', function () {
    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => null,
        'status' => 'draft',
    ]);

    $service = app(CampaignService::class);

    expect(fn () => $service->sendCampaign($campaign))
        ->toThrow(InvalidArgumentException::class);
});

test('campaign without subject throws exception', function () {
    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'subject' => null,
        'status' => 'draft',
    ]);

    $service = app(CampaignService::class);

    expect(fn () => $service->sendCampaign($campaign))
        ->toThrow(InvalidArgumentException::class);
});

// ---- A/B Testing ----

test('A/B test variant creation', function () {
    $campaign = Campaign::factory()->abTest()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
    ]);

    AbTestVariant::create([
        'campaign_id' => $campaign->id,
        'variant' => 'A',
        'subject' => 'Subject A',
        'body_html' => '<p>Body A</p>',
        'percentage' => 50,
    ]);

    AbTestVariant::create([
        'campaign_id' => $campaign->id,
        'variant' => 'B',
        'subject' => 'Subject B',
        'body_html' => '<p>Body B</p>',
        'percentage' => 50,
    ]);

    expect($campaign->abTestVariants()->count())->toBe(2);
    expect($campaign->type)->toBe('ab_test');
});

// ---- Pause ----

test('pause campaign updates status', function () {
    $campaign = Campaign::factory()->sending()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
    ]);

    $campaign->update(['status' => 'paused']);
    $campaign->refresh();

    expect($campaign->status)->toBe('paused');
});

// ---- Stats ----

test('campaign stats calculation with open and click rates', function () {
    $campaign = Campaign::factory()->sent()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'delivered_count' => 100,
        'opened_count' => 40,
        'clicked_count' => 10,
    ]);

    expect($campaign->open_rate)->toBe(40.0);
    expect($campaign->click_rate)->toBe(10.0);
});

test('campaign stats with zero deliveries returns zero rates', function () {
    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'delivered_count' => 0,
        'opened_count' => 0,
        'clicked_count' => 0,
    ]);

    expect($campaign->open_rate)->toBe(0.0);
    expect($campaign->click_rate)->toBe(0.0);
});

// ---- Unsubscribed contacts excluded ----

test('unsubscribed contacts are excluded from audience', function () {
    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'status' => 'draft',
        'audience_type' => 'all',
    ]);

    Contact::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'status' => 'active',
    ]);

    Contact::factory()->unsubscribed()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $service = app(CampaignService::class);
    $service->sendCampaign($campaign);

    $campaign->refresh();
    // The audience_type='all' query filters status='active' -- unsubscribed excluded
    expect($campaign->recipients_count)->toBe(3);
});
