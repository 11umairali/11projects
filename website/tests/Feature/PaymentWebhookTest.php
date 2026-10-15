<?php

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'owner_id' => $this->user->id,
    ]);
});

test('duplicate stripe_invoice_id is rejected by unique constraint', function () {
    $plan = Plan::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'monthly_price' => 79,
        'is_active' => true,
    ]);

    $subscription = Subscription::create([
        'workspace_id' => $this->workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'stripe_subscription_id' => 'sub_test_123',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    // First payment succeeds
    Payment::create([
        'workspace_id' => $this->workspace->id,
        'subscription_id' => $subscription->id,
        'stripe_invoice_id' => 'inv_duplicate_test',
        'amount' => 79,
        'currency' => 'usd',
        'status' => 'succeeded',
    ]);

    // Second payment with same invoice ID should fail
    expect(fn () => Payment::create([
        'workspace_id' => $this->workspace->id,
        'subscription_id' => $subscription->id,
        'stripe_invoice_id' => 'inv_duplicate_test',
        'amount' => 79,
        'currency' => 'usd',
        'status' => 'succeeded',
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('payment count stays correct with unique constraint', function () {
    $plan = Plan::create([
        'name' => 'Starter',
        'slug' => 'starter',
        'monthly_price' => 29,
        'is_active' => true,
    ]);

    $subscription = Subscription::create([
        'workspace_id' => $this->workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    Payment::create([
        'workspace_id' => $this->workspace->id,
        'subscription_id' => $subscription->id,
        'stripe_invoice_id' => 'inv_unique_1',
        'amount' => 29,
        'currency' => 'usd',
        'status' => 'succeeded',
    ]);

    Payment::create([
        'workspace_id' => $this->workspace->id,
        'subscription_id' => $subscription->id,
        'stripe_invoice_id' => 'inv_unique_2',
        'amount' => 29,
        'currency' => 'usd',
        'status' => 'succeeded',
    ]);

    expect(Payment::where('workspace_id', $this->workspace->id)->count())->toBe(2);
});
