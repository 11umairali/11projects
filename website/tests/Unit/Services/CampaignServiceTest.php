<?php

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailSuppression;
use App\Models\Segment;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Campaign\CampaignService;

/*
|--------------------------------------------------------------------------
| CampaignService Unit Tests
|--------------------------------------------------------------------------
| Tests the audience resolution engine: chunked contact retrieval,
| suppression exclusion, segment matching.
*/

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create();
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['active_workspace_id' => $this->workspace->id]);

    $this->emailAccount = EmailAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->service = app(CampaignService::class);
});

test('resolveAudience returns correct contacts for audience_type=all', function () {
    Contact::factory()->count(5)->create([
        'workspace_id' => $this->workspace->id,
        'status' => 'active',
    ]);

    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'audience_type' => 'all',
    ]);

    $contacts = $this->service->resolveAudience($campaign);

    expect($contacts)->toHaveCount(5);
});

test('suppressed contacts excluded from audience', function () {
    $contacts = Contact::factory()->count(5)->create([
        'workspace_id' => $this->workspace->id,
        'status' => 'active',
    ]);

    // Suppress 2 contacts
    foreach ($contacts->take(2) as $contact) {
        EmailSuppression::create([
            'workspace_id' => $this->workspace->id,
            'email' => $contact->email,
            'reason' => 'hard_bounce',
        ]);
    }

    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'audience_type' => 'all',
    ]);

    $result = $this->service->resolveAudience($campaign);

    expect($result)->toHaveCount(3);
});

test('contacts without email excluded from audience', function () {
    Contact::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'status' => 'active',
    ]);

    Contact::factory()->count(2)->withoutEmail()->create([
        'workspace_id' => $this->workspace->id,
        'status' => 'active',
    ]);

    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'audience_type' => 'all',
    ]);

    $result = $this->service->resolveAudience($campaign);

    expect($result)->toHaveCount(3);
});

test('unsubscribed contacts excluded from audience', function () {
    Contact::factory()->count(4)->create([
        'workspace_id' => $this->workspace->id,
        'status' => 'active',
    ]);

    Contact::factory()->unsubscribed()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'audience_type' => 'all',
    ]);

    $result = $this->service->resolveAudience($campaign);

    // Unsubscribed contacts have status != 'active', so they're excluded
    expect($result)->toHaveCount(4);
});

test('segment matching with all conditions (AND)', function () {
    // Create contacts with varying data
    Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'country' => 'US',
        'lead_score' => 80,
        'status' => 'active',
    ]);

    Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'country' => 'US',
        'lead_score' => 30, // Below threshold
        'status' => 'active',
    ]);

    Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'country' => 'UK', // Wrong country
        'lead_score' => 80,
        'status' => 'active',
    ]);

    $segment = Segment::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'High-value US',
        'rules' => [
            'match' => 'all',
            'conditions' => [
                ['field' => 'country', 'operator' => 'equals', 'value' => 'US'],
                ['field' => 'lead_score', 'operator' => 'greater_than', 'value' => 50],
            ],
        ],
    ]);

    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'audience_type' => 'segment',
        'audience_id' => $segment->id,
    ]);

    $result = $this->service->resolveAudience($campaign);

    // Only the US + high lead_score contact should match
    expect($result)->toHaveCount(1);
    expect($result->first()->country)->toBe('US');
    expect($result->first()->lead_score)->toBeGreaterThan(50);
});

test('segment matching with any conditions (OR)', function () {
    Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'country' => 'US',
        'lead_score' => 20,
        'status' => 'active',
    ]);

    Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'country' => 'UK',
        'lead_score' => 80,
        'status' => 'active',
    ]);

    Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'country' => 'DE',
        'lead_score' => 20,
        'status' => 'active',
    ]);

    $segment = Segment::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'US or High Score',
        'rules' => [
            'match' => 'any',
            'conditions' => [
                ['field' => 'country', 'operator' => 'equals', 'value' => 'US'],
                ['field' => 'lead_score', 'operator' => 'greater_than', 'value' => 50],
            ],
        ],
    ]);

    $campaign = Campaign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'audience_type' => 'segment',
        'audience_id' => $segment->id,
    ]);

    $result = $this->service->resolveAudience($campaign);

    // US contact + high score contact (OR condition)
    expect($result)->toHaveCount(2);
});

test('campaign checkAndCompleteIfDone finalizes when all recipients processed', function () {
    $campaign = Campaign::factory()->sending()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'email_account_id' => $this->emailAccount->id,
        'recipients_count' => 2,
    ]);

    $contact1 = Contact::factory()->create(['workspace_id' => $this->workspace->id]);
    $contact2 = Contact::factory()->create(['workspace_id' => $this->workspace->id]);

    // All recipients are in terminal states (not pending)
    \App\Models\CampaignRecipient::create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact1->id,
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    \App\Models\CampaignRecipient::create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact2->id,
        'status' => 'delivered',
        'sent_at' => now(),
    ]);

    $this->service->checkAndCompleteIfDone($campaign);

    $campaign->refresh();
    expect($campaign->status)->toBe('sent');
    expect($campaign->completed_at)->not->toBeNull();
});
