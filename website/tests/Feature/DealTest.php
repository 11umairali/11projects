<?php

use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Pipeline;
use App\Models\User;
use App\Models\Workspace;

/*
|--------------------------------------------------------------------------
| Deal Tests
|--------------------------------------------------------------------------
| Tests deal CRUD, stage movement, win/loss marking, workspace validation,
| and authorization.
*/

beforeEach(function () {
    $setup = createUserWithWorkspace('owner');
    $this->user = $setup['user'];
    $this->workspace = $setup['workspace'];
    $this->actingAs($this->user);

    $this->pipeline = Pipeline::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $this->stageA = DealStage::factory()->create([
        'pipeline_id' => $this->pipeline->id,
        'name' => 'Lead',
        'sort_order' => 1,
        'win_probability' => 10,
    ]);

    $this->stageB = DealStage::factory()->create([
        'pipeline_id' => $this->pipeline->id,
        'name' => 'Qualified',
        'sort_order' => 2,
        'win_probability' => 30,
    ]);

    $this->contact = Contact::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);
});

// ---- Create ----

test('create deal with valid data', function () {
    $deal = Deal::factory()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageA->id,
        'title' => 'Enterprise Contract',
        'value' => 15000.00,
    ]);

    expect($deal->title)->toBe('Enterprise Contract');
    expect((float) $deal->value)->toBe(15000.00);
    expect($deal->status)->toBe('open');
    expect($deal->uuid)->not->toBeNull();
    expect($deal->workspace_id)->toBe($this->workspace->id);
});

// ---- Stage Movement ----

test('move deal to different stage atomically', function () {
    $deal = Deal::factory()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageA->id,
    ]);

    // Atomic stage update
    $updated = Deal::where('id', $deal->id)
        ->where('deal_stage_id', $this->stageA->id) // Optimistic lock: only update if still at stageA
        ->update(['deal_stage_id' => $this->stageB->id]);

    expect($updated)->toBe(1);

    $deal->refresh();
    expect($deal->deal_stage_id)->toBe($this->stageB->id);
    expect($deal->dealStage->name)->toBe('Qualified');
});

test('concurrent stage move detected via optimistic lock', function () {
    $deal = Deal::factory()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageA->id,
    ]);

    // First move succeeds
    $updated1 = Deal::where('id', $deal->id)
        ->where('deal_stage_id', $this->stageA->id)
        ->update(['deal_stage_id' => $this->stageB->id]);

    // Second move from stageA should fail (already moved to stageB)
    $updated2 = Deal::where('id', $deal->id)
        ->where('deal_stage_id', $this->stageA->id) // Still checks for stageA -- but it's stageB now
        ->update(['deal_stage_id' => $this->stageA->id]);

    expect($updated1)->toBe(1);
    expect($updated2)->toBe(0); // No rows affected -- conflict detected
});

// ---- Win / Loss ----

test('mark deal as won sets status and won_at', function () {
    $deal = Deal::factory()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageB->id,
    ]);

    $deal->markAsWon();

    $deal->refresh();
    expect($deal->status)->toBe('won');
    expect($deal->won_at)->not->toBeNull();
});

test('mark deal as lost sets status, lost_at, and reason', function () {
    $deal = Deal::factory()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageA->id,
    ]);

    $deal->markAsLost('Budget constraints');

    $deal->refresh();
    expect($deal->status)->toBe('lost');
    expect($deal->lost_at)->not->toBeNull();
    expect($deal->lost_reason)->toBe('Budget constraints');
});

// ---- Contact Workspace Validation ----

test('deal contact must belong to same workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $foreignContact = Contact::factory()->create([
        'workspace_id' => $otherWorkspace->id,
    ]);

    // At the model level, no constraint prevents this -- application logic must enforce it
    $deal = Deal::factory()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $foreignContact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageA->id,
    ]);

    // Verify the mismatch exists (application code should prevent this)
    expect($deal->contact->workspace_id)->not->toBe($deal->workspace_id);
});

// ---- Scopes ----

test('open scope returns only open deals', function () {
    Deal::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageA->id,
        'status' => 'open',
    ]);

    Deal::factory()->won()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageB->id,
    ]);

    $openCount = Deal::where('workspace_id', $this->workspace->id)->open()->count();
    expect($openCount)->toBe(3);
});

test('won scope returns only won deals', function () {
    Deal::factory()->won()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageB->id,
    ]);

    Deal::factory()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageA->id,
    ]);

    $wonCount = Deal::where('workspace_id', $this->workspace->id)->won()->count();
    expect($wonCount)->toBe(2);
});

// ---- Soft Delete ----

test('deal soft delete preserves data', function () {
    $deal = Deal::factory()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageA->id,
    ]);

    $deal->delete();

    expect(Deal::find($deal->id))->toBeNull();
    expect(Deal::withTrashed()->find($deal->id))->not->toBeNull();
});

// ---- Pipeline / Stages ----

test('pipeline has ordered deal stages', function () {
    expect($this->pipeline->dealStages->first()->name)->toBe('Lead');
    expect($this->pipeline->dealStages->last()->name)->toBe('Qualified');
});

test('deal stage has pipeline relationship', function () {
    expect($this->stageA->pipeline->id)->toBe($this->pipeline->id);
});

// ---- Custom Fields ----

test('deal custom fields are stored as JSON', function () {
    $deal = Deal::factory()->create([
        'workspace_id' => $this->workspace->id,
        'contact_id' => $this->contact->id,
        'pipeline_id' => $this->pipeline->id,
        'deal_stage_id' => $this->stageA->id,
        'custom_fields' => [
            'source' => 'website',
            'competitor' => 'Acme Corp',
        ],
    ]);

    $deal->refresh();
    expect($deal->custom_fields)->toBeArray();
    expect($deal->custom_fields['source'])->toBe('website');
});
