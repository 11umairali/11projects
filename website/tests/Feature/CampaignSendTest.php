<?php

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Campaign\CampaignService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake(); // Prevent real SMTP connections
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'owner_id' => $this->user->id,
    ]);
    $this->user->update(['active_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner', 'status' => 'offline']);
});

test('campaign cannot be sent twice (double-send prevention)', function () {
    $emailAccount = EmailAccount::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'email' => 'test@example.com',
        'provider' => 'imap',
        'status' => 'connected',
        'smtp_host' => 'smtp.test.com',
        'smtp_port' => 587,
    ]);

    $campaign = Campaign::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Campaign',
        'status' => 'draft',
        'subject' => 'Hello',
        'body_html' => '<p>Test</p>',
        'email_account_id' => $emailAccount->id,
        'audience_type' => 'all',
    ]);

    Contact::create([
        'workspace_id' => $this->workspace->id,
        'email' => 'contact@test.com',
        'first_name' => 'John',
        'status' => 'active',
    ]);

    $service = app(CampaignService::class);
    $service->sendCampaign($campaign);

    // Second send should throw
    expect(fn () => $service->sendCampaign($campaign->fresh()))
        ->toThrow(\InvalidArgumentException::class);
});

test('campaign with no recipients completes immediately', function () {
    $emailAccount = EmailAccount::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'email' => 'test@example.com',
        'provider' => 'imap',
        'status' => 'connected',
        'smtp_host' => 'smtp.test.com',
        'smtp_port' => 587,
    ]);

    $campaign = Campaign::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Empty Campaign',
        'status' => 'draft',
        'subject' => 'Hello',
        'body_html' => '<p>Test</p>',
        'email_account_id' => $emailAccount->id,
        'audience_type' => 'all',
    ]);

    $service = app(CampaignService::class);
    $service->sendCampaign($campaign);

    $campaign->refresh();
    expect($campaign->status)->toBe('sent');
    expect($campaign->recipients_count)->toBe(0);
});
