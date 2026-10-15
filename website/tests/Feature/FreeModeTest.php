<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Services\Billing\StripeService;
use App\Services\PlanLimitService;
use App\Support\BillingPolicy;
use Illuminate\Support\Facades\Cache;

beforeEach(function () { Cache::flush(); });

test('only authorized administrators can set the default plan', function () {
    $s = createUserWithWorkspace();
    $this->actingAs($s['user'])->post('/admin/plans/default', ['default_plan' => BillingPolicy::FREE])->assertForbidden();
    expect(BillingPolicy::isFree())->toBeFalse();
    $s['user']->forceFill(['is_admin' => true, 'admin_role' => 'support'])->save();
    $this->postJson('/admin/plans/default', ['default_plan' => BillingPolicy::FREE])->assertForbidden();
    $s['user']->forceFill(['is_admin' => true, 'admin_role' => 'super_admin'])->save();
    $this->post('/admin/plans/default', ['default_plan' => BillingPolicy::FREE])->assertRedirect();
    expect(BillingPolicy::isFree())->toBeTrue();
    $this->get('/admin/plans')->assertOk()->assertSee('Free — unlimited for everyone');
});

test('free mode bypasses disabled features and exhausted quotas including cached denials', function () {
    $s = createUserWithWorkspace();
    $plan = Plan::create(['name' => 'Limited', 'slug' => 'limited', 'monthly_price' => 5, 'yearly_price' => 50, 'is_active' => true]);
    $plan->planFeatures()->create(['feature_key' => 'ai_replies', 'enabled' => false, 'limit' => 0]);
    Subscription::create(['workspace_id' => $s['workspace']->id, 'plan_id' => $plan->id, 'status' => 'active']);
    $stripe = app(StripeService::class);
    expect($stripe->canUse($s['workspace'], 'ai_replies'))->toBeFalse();
    BillingPolicy::setDefault(BillingPolicy::FREE);
    $limits = app(PlanLimitService::class);
    expect($limits->canUse($s['workspace'], 'ai_replies'))->toBeTrue();
    expect($limits->hasFeature($s['workspace'], 'a_new_feature'))->toBeTrue();
    expect($limits->getLimit($s['workspace'], 'contacts'))->toBeNull();
    $limits->assertCanCreate($s['workspace'], 'contacts');
    $limits->assertFeatureEnabled($s['workspace'], 'ai_replies');
    expect($stripe->tryReserveUsage($s['workspace'], 'ai_replies'))->toBeTrue();
    expect($stripe->getUsage($s['workspace'], 'ai_replies')['limit'])->toBeNull();
    expect($plan->hasFeature('a_new_feature'))->toBeTrue();
    expect($plan->featureLimit('ai_replies'))->toBeNull();
    BillingPolicy::setDefault('limited');
    expect($stripe->canUse($s['workspace'], 'ai_replies'))->toBeFalse();
});

test('free mode applies to workspaces without a subscription and mobile auth reports the mode', function () {
    $s = createUserWithWorkspace();
    BillingPolicy::setDefault(BillingPolicy::FREE);
    expect(app(PlanLimitService::class)->getLimit($s['workspace'], 'contacts'))->toBeNull();
    expect(app(StripeService::class)->canUse($s['workspace'], 'workflows'))->toBeTrue();
    $this->actingAs($s['user'], 'sanctum')->getJson('/api/v1/auth/me')
        ->assertOk()->assertJsonPath('user.billing_enabled', false);
});

test('free mode hides pricing and blocks web and mobile billing entry points', function () {
    $s = createUserWithWorkspace();
    BillingPolicy::setDefault(BillingPolicy::FREE);
    $this->seed(\Database\Seeders\PageSeeder::class);
    $this->get('/')->assertOk()->assertDontSee('id="pricing"', false)->assertDontSee('href="#pricing"', false);
    SystemSetting::set('frontend_homepage_theme', 'modern');
    $this->get('/')->assertOk()->assertDontSee('id="pricing"', false)->assertDontSee('href="#pricing"', false);
    $this->actingAs($s['user'])->get('/settings/billing')->assertForbidden();
    $this->get('/checkout/1')->assertForbidden();
    $this->post('/checkout/1/process')->assertForbidden();
    $this->actingAs($s['user'], 'sanctum')->getJson('/api/v1/billing')->assertForbidden();
    $this->getJson('/api/v1/payments')->assertForbidden();
    $this->postJson('/api/v1/checkout')->assertForbidden();
});

test('active default plans are selectable and invalid or disabled defaults are rejected', function () {
    $plan = Plan::create(['name' => 'Team', 'slug' => 'team', 'monthly_price' => 10, 'yearly_price' => 100, 'is_active' => true]);
    BillingPolicy::setDefault('team');
    expect(BillingPolicy::defaultPlan()->id)->toBe($plan->id);
    expect(fn () => BillingPolicy::setDefault('missing'))->toThrow(\Illuminate\Validation\ValidationException::class);
    $plan->update(['is_active' => false]);
    expect(fn () => BillingPolicy::setDefault('team'))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('recurring subscriptions must be canceled before billing can be hidden', function () {
    $s = createUserWithWorkspace();
    $plan = Plan::create(['name' => 'Paid', 'slug' => 'paid', 'monthly_price' => 10, 'yearly_price' => 100, 'is_active' => true]);
    $sub = Subscription::create(['workspace_id' => $s['workspace']->id, 'plan_id' => $plan->id, 'status' => 'active', 'stripe_subscription_id' => 'sub_synthetic']);
    expect(fn () => BillingPolicy::setDefault(BillingPolicy::FREE))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(BillingPolicy::isFree())->toBeFalse();
    $sub->update(['status' => 'canceled']);
    BillingPolicy::setDefault(BillingPolicy::FREE);
    expect(BillingPolicy::isFree())->toBeTrue();
});


test('new workspaces receive the selected default and free mode creates no expiring trial', function () {
    $plan = Plan::create(['name' => 'Default team', 'slug' => 'default-team', 'monthly_price' => 10, 'yearly_price' => 100, 'is_active' => true]);
    BillingPolicy::setDefault('default-team');
    $user = \App\Models\User::factory()->create();
    $workspace = app(\App\Services\DefaultWorkspaceProvisioner::class)->ensure($user);
    expect($workspace->subscription->plan_id)->toBe($plan->id);
    BillingPolicy::setDefault(BillingPolicy::FREE);
    $freeUser = \App\Models\User::factory()->create();
    $freeWorkspace = app(\App\Services\DefaultWorkspaceProvisioner::class)->ensure($freeUser);
    expect($freeWorkspace->subscription)->toBeNull();
    expect(app(PlanLimitService::class)->hasFeature($freeWorkspace, 'ai_replies'))->toBeTrue();
});
