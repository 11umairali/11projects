<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\StripeService;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'owner_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner', 'status' => 'offline']);
    $this->user->update(['active_workspace_id' => $this->workspace->id]);
});

test('plan pricing cannot be changed with active subscriptions', function () {
    $plan = Plan::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'monthly_price' => 79,
        'yearly_price' => 790,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    Subscription::create([
        'workspace_id' => $this->workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    $response = $this->actingAs($this->user)->put("/admin/plans/{$plan->id}", [
        'name' => 'Pro',
        'description' => 'Pro plan',
        'monthly_price' => 99, // Price change!
        'yearly_price' => 990,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $response->assertSessionHas('error');
    expect($plan->fresh()->monthly_price)->toBe(79.0);
});

test('plan features can be changed with active subscriptions', function () {
    $plan = Plan::create([
        'name' => 'Starter',
        'slug' => 'starter',
        'monthly_price' => 29,
        'yearly_price' => 290,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Subscription::create([
        'workspace_id' => $this->workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    // Same price, different name — should succeed
    $response = $this->actingAs($this->user)->put("/admin/plans/{$plan->id}", [
        'name' => 'Starter Plus',
        'description' => 'Updated starter',
        'monthly_price' => 29, // Same price
        'yearly_price' => 290,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    expect($plan->fresh()->name)->toBe('Starter Plus');
});

test('tryReserveUsage returns false when over limit', function () {
    $plan = Plan::create([
        'name' => 'Free',
        'slug' => 'free',
        'monthly_price' => 0,
        'is_active' => true,
    ]);

    \App\Models\PlanFeature::create([
        'plan_id' => $plan->id,
        'feature_key' => 'ai_replies',
        'enabled' => true,
        'limit' => 5,
    ]);

    Subscription::create([
        'workspace_id' => $this->workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    // Set usage to limit
    \App\Models\UsageRecord::create([
        'workspace_id' => $this->workspace->id,
        'feature_key' => 'ai_replies',
        'period' => now()->format('Y-m'),
        'quantity' => 5,
    ]);

    $stripeService = app(StripeService::class);
    $result = $stripeService->tryReserveUsage($this->workspace, 'ai_replies');

    expect($result)->toBeFalse();

    // Usage should NOT have increased
    $usage = \App\Models\UsageRecord::where('workspace_id', $this->workspace->id)
        ->where('feature_key', 'ai_replies')
        ->where('period', now()->format('Y-m'))
        ->value('quantity');

    expect($usage)->toBe(5);
});

test('tryReserveUsage succeeds when under limit', function () {
    $plan = Plan::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'monthly_price' => 79,
        'is_active' => true,
    ]);

    \App\Models\PlanFeature::create([
        'plan_id' => $plan->id,
        'feature_key' => 'ai_replies',
        'enabled' => true,
        'limit' => 100,
    ]);

    Subscription::create([
        'workspace_id' => $this->workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    $stripeService = app(StripeService::class);
    $result = $stripeService->tryReserveUsage($this->workspace, 'ai_replies');

    expect($result)->toBeTrue();

    $usage = \App\Models\UsageRecord::where('workspace_id', $this->workspace->id)
        ->where('feature_key', 'ai_replies')
        ->where('period', now()->format('Y-m'))
        ->value('quantity');

    expect($usage)->toBe(1);
});
