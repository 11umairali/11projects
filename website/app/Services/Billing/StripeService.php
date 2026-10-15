<?php

namespace App\Services\Billing;

use App\Jobs\HandleFailedPaymentJob;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\StripeClient;

class StripeService
{
    private ?StripeClient $stripe = null;

    public function __construct()
    {
        $secretKey = config('cashier.secret') ?: config('services.stripe.secret');

        if ($secretKey) {
            $this->stripe = new StripeClient($secretKey);
            Stripe::setApiKey($secretKey);
        }
    }

    public function isConfigured(): bool
    {
        return $this->stripe !== null;
    }

    /**
     * Create a Stripe Checkout Session for subscription purchase.
     *
     * @throws ApiErrorException
     */
    public function createCheckoutSession(
        Workspace $workspace,
        Plan $plan,
        string $billingCycle = 'monthly'
    ): CheckoutSession {
        \App\Support\BillingPolicy::assertBillingEnabled();
        $priceId = $billingCycle === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        if (!$priceId) {
            throw new \InvalidArgumentException(
                "Plan '{$plan->name}' has no Stripe price ID for {$billingCycle} billing."
            );
        }

        // Get or create Stripe customer
        $customerId = $this->ensureStripeCustomer($workspace);

        $sessionParams = [
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'success_url' => config('app.url') . '/settings/billing?session_id={CHECKOUT_SESSION_ID}&status=success',
            'cancel_url' => config('app.url') . '/settings/billing?status=canceled',
            'subscription_data' => [
                'metadata' => [
                    'workspace_id' => $workspace->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                ],
            ],
            'metadata' => [
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
            ],
            'allow_promotion_codes' => true,
        ];

        // Add trial period if plan has one and workspace hasn't trialed before
        $existingSub = $workspace->subscription;
        if (!$existingSub && ($plan->features['trial_days'] ?? 0) > 0) {
            $sessionParams['subscription_data']['trial_period_days'] = $plan->features['trial_days'];
        }

        $session = $this->stripe->checkout->sessions->create($sessionParams);

        Log::info('Stripe checkout session created', [
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'session_id' => $session->id,
            'billing_cycle' => $billingCycle,
        ]);

        return $session;
    }

    /**
     * Create a Stripe Customer Portal session for self-service billing management.
     *
     * @throws ApiErrorException
     */
    public function createCustomerPortalSession(Workspace $workspace): PortalSession
    {
        \App\Support\BillingPolicy::assertBillingEnabled();
        $customerId = $this->getStripeCustomerId($workspace);

        if (!$customerId) {
            throw new \RuntimeException("Workspace {$workspace->id} has no Stripe customer.");
        }

        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => config('app.url') . '/settings/billing',
        ]);

        return $session;
    }

    /**
     * Validate and construct a Stripe webhook event from the raw payload.
     *
     * @throws \RuntimeException On signature verification failure or missing config.
     */
    public function handleWebhook(string $payload, string $signature): Event
    {
        $webhookSecret = config('services.stripe.webhook_secret') ?: config('cashier.webhook.secret');

        if (!$webhookSecret) {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new \RuntimeException('Invalid Stripe webhook signature.');
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        return $event;
    }

    /**
     * Dispatch a validated Stripe event to the appropriate handler.
     */
    public function dispatchEvent(Event $event): void
    {
        match ($event->type) {
            'customer.subscription.created' => $this->handleSubscriptionCreated($event),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($event),
            'invoice.payment_failed' => $this->handlePaymentFailed($event),
            'customer.subscription.trial_will_end' => $this->handleTrialEnding($event),
            default => Log::info("Unhandled Stripe webhook event: {$event->type}"),
        };
    }

    /**
     * Handle subscription created: activate plan features.
     */
    private function handleSubscriptionCreated(Event $event): void
    {
        $stripeSubscription = $event->data->object;

        // Idempotency: skip if this Stripe subscription already exists locally
        $existing = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
        if ($existing) {
            Log::info('Stripe webhook: duplicate subscription event ignored', ['sub_id' => $stripeSubscription->id]);
            return;
        }

        $metadata = $stripeSubscription->metadata ?? (object) [];
        $workspaceId = $metadata->workspace_id ?? null;
        $planId = $metadata->plan_id ?? null;
        $billingCycle = $metadata->billing_cycle ?? 'monthly';

        if (!$workspaceId) {
            // Try to find workspace by Stripe customer ID
            $workspaceId = $this->resolveWorkspaceFromCustomer($stripeSubscription->customer);
        }

        if (!$workspaceId) {
            Log::error('Stripe webhook: Cannot resolve workspace for subscription', [
                'stripe_subscription_id' => $stripeSubscription->id,
            ]);
            return;
        }

        $workspace = Workspace::find($workspaceId);
        if (!$workspace) {
            return;
        }

        // Resolve plan from price ID if not in metadata
        if (!$planId && !empty($stripeSubscription->items->data)) {
            $priceId = $stripeSubscription->items->data[0]->price->id ?? null;
            $plan = Plan::where('stripe_monthly_price_id', $priceId)
                ->orWhere('stripe_yearly_price_id', $priceId)
                ->first();
            $planId = $plan?->id;

            if ($plan) {
                $billingCycle = $plan->stripe_yearly_price_id === $priceId ? 'yearly' : 'monthly';
            }
        }

        if (!$planId) {
            Log::error('Stripe webhook: Cannot resolve plan for subscription', [
                'stripe_subscription_id' => $stripeSubscription->id,
            ]);
            return;
        }

        // Atomically cancel old subscriptions and create the new one in a single
        // transaction to prevent dangling records if a crash occurs between the two.
        $subscription = DB::transaction(function () use ($workspaceId, $planId, $stripeSubscription, $billingCycle) {
            // Cancel all non-canceled subscriptions for this workspace in bulk
            $canceledCount = Subscription::where('workspace_id', $workspaceId)
                ->where('status', '!=', 'canceled')
                ->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'grace_period_ends_at' => now(),
                ]);

            if ($canceledCount > 0) {
                Log::info('Old subscriptions canceled during plan change', [
                    'workspace_id' => $workspaceId,
                    'count' => $canceledCount,
                ]);
            }

            // Create local subscription record
            return Subscription::create([
                'workspace_id' => $workspaceId,
                'plan_id' => $planId,
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_customer_id' => $stripeSubscription->customer,
                'status' => $this->mapStripeStatus($stripeSubscription->status),
                'billing_cycle' => $billingCycle,
                'trial_ends_at' => $stripeSubscription->trial_end
                    ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
                    : null,
                'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            ]);
        });

        // Invalidate plan feature cache for this workspace
        $this->clearPlanFeatureCache($workspaceId);

        Log::info('Subscription created from Stripe webhook', [
            'subscription_id' => $subscription->id,
            'workspace_id' => $workspaceId,
            'plan_id' => $planId,
            'status' => $subscription->status,
        ]);
    }

    /**
     * Handle subscription updated: sync plan and status changes.
     */
    private function handleSubscriptionUpdated(Event $event): void
    {
        $stripeSubscription = $event->data->object;

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if (!$subscription) {
            Log::warning('Stripe webhook: Subscription not found for update', [
                'stripe_subscription_id' => $stripeSubscription->id,
            ]);
            return;
        }

        // Check if plan changed
        $newPriceId = $stripeSubscription->items->data[0]->price->id ?? null;

        if ($newPriceId) {
            $newPlan = Plan::where('stripe_monthly_price_id', $newPriceId)
                ->orWhere('stripe_yearly_price_id', $newPriceId)
                ->first();

            if ($newPlan && $newPlan->id !== $subscription->plan_id) {
                $subscription->plan_id = $newPlan->id;
                $subscription->billing_cycle = $newPlan->stripe_yearly_price_id === $newPriceId
                    ? 'yearly'
                    : 'monthly';
            }
        }

        $subscription->status = $this->mapStripeStatus($stripeSubscription->status);
        $subscription->current_period_start = Carbon::createFromTimestamp($stripeSubscription->current_period_start);
        $subscription->current_period_end = Carbon::createFromTimestamp($stripeSubscription->current_period_end);

        if ($stripeSubscription->cancel_at_period_end) {
            $subscription->canceled_at = $subscription->canceled_at ?? now();
            $subscription->grace_period_ends_at = $subscription->current_period_end;
        } else {
            // Reactivated
            $subscription->canceled_at = null;
            $subscription->grace_period_ends_at = null;
        }

        $subscription->save();

        // Invalidate plan feature cache for this workspace
        $this->clearPlanFeatureCache($subscription->workspace_id);

        Log::info('Subscription updated from Stripe webhook', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
        ]);
    }

    /**
     * Handle subscription deleted: downgrade to free plan.
     */
    private function handleSubscriptionDeleted(Event $event): void
    {
        $stripeSubscription = $event->data->object;

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if (!$subscription) {
            return;
        }

        // Invalidate cache FIRST to prevent stale premium access during downgrade
        $this->clearPlanFeatureCache($subscription->workspace_id);

        DB::transaction(function () use ($subscription) {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);

            // Downgrade workspace to free plan.
            // Prefer the canonical "free" slug; fall back to any zero-price plan.
            $freePlan = Plan::where('slug', 'free')->first()
                ?? Plan::where('monthly_price', 0)->where('is_active', true)->first();

            if (!$freePlan) {
                Log::critical('Subscription downgrade failed: no free plan exists in the database. '
                    . 'A plan with slug "free" or monthly_price=0 must be seeded before subscriptions can be canceled.', [
                    'workspace_id' => $subscription->workspace_id,
                    'canceled_stripe_subscription_id' => $subscription->stripe_subscription_id,
                ]);
                throw new \LogicException(
                    "Cannot downgrade workspace {$subscription->workspace_id}: no free plan found. "
                    . 'Ensure a plan with slug "free" or monthly_price=0 exists in the plans table.'
                );
            }

            Subscription::create([
                'workspace_id' => $subscription->workspace_id,
                'plan_id' => $freePlan->id,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
            ]);
        });

        // Clear cache again after transaction to ensure consistency
        $this->clearPlanFeatureCache($subscription->workspace_id);

        Log::info('Subscription canceled, workspace downgraded to free', [
            'workspace_id' => $subscription->workspace_id,
            'previous_plan_id' => $subscription->plan_id,
        ]);
    }

    /**
     * Handle successful invoice payment.
     */
    private function handlePaymentSucceeded(Event $event): void
    {
        $invoice = $event->data->object;

        // Skip non-subscription invoices or $0 invoices
        if (!$invoice->subscription || ($invoice->amount_paid ?? 0) <= 0) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $invoice->subscription)->first();

        if (!$subscription) {
            return;
        }

        // Atomic idempotent insert: unique constraint on stripe_invoice_id prevents duplicates
        try {
            DB::transaction(function () use ($invoice, $subscription) {
                Payment::create([
                    'workspace_id' => $subscription->workspace_id,
                    'subscription_id' => $subscription->id,
                    'stripe_payment_id' => $invoice->payment_intent,
                    'stripe_invoice_id' => $invoice->id,
                    'amount' => $invoice->amount_paid / 100,
                    'currency' => $invoice->currency ?? 'usd',
                    'status' => 'succeeded',
                    'description' => 'Subscription payment - ' . ($subscription->plan->name ?? 'Plan'),
                ]);

                // Reset monthly usage counters for the new billing period
                $period = now()->format('Y-m');
                UsageRecord::where('workspace_id', $subscription->workspace_id)
                    ->where('period', $period)
                    ->delete();

                // Ensure subscription is marked as active
                if ($subscription->status === 'past_due') {
                    $subscription->update(['status' => 'active']);
                }
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            Log::info('Stripe webhook: duplicate payment event ignored (unique constraint)', [
                'invoice_id' => $invoice->id,
            ]);
            return;
        }

        // Invalidate plan feature cache AFTER successful transaction
        $this->clearPlanFeatureCache($subscription->workspace_id);

        Log::info('Payment succeeded, usage counters reset', [
            'workspace_id' => $subscription->workspace_id,
            'amount' => $invoice->amount_paid / 100,
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Handle failed invoice payment.
     */
    private function handlePaymentFailed(Event $event): void
    {
        $invoice = $event->data->object;

        if (!$invoice->subscription) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $invoice->subscription)->first();

        if (!$subscription) {
            return;
        }

        // Create failed payment record
        Payment::create([
            'workspace_id' => $subscription->workspace_id,
            'subscription_id' => $subscription->id,
            'stripe_payment_id' => $invoice->payment_intent,
            'stripe_invoice_id' => $invoice->id,
            'amount' => ($invoice->amount_due ?? 0) / 100,
            'currency' => $invoice->currency ?? 'usd',
            'status' => 'failed',
            'description' => 'Subscription payment failed',
            'failure_reason' => $invoice->last_finalization_error?->message ?? 'Unknown payment failure',
        ]);

        // Update subscription status
        $subscription->update(['status' => 'past_due']);

        // Invalidate plan feature cache
        $this->clearPlanFeatureCache($subscription->workspace_id);

        // Dispatch dunning flow job
        HandleFailedPaymentJob::dispatch($subscription->id)
            ->onQueue('billing');

        Log::warning('Payment failed, dunning flow initiated', [
            'workspace_id' => $subscription->workspace_id,
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Handle trial ending notification (3 days before trial ends).
     */
    private function handleTrialEnding(Event $event): void
    {
        $stripeSubscription = $event->data->object;

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if (!$subscription) {
            return;
        }

        $workspace = $subscription->workspace;
        $owner = $workspace?->owner();

        if ($owner) {
            $owner->notify(new \App\Notifications\TrialEndingNotification($subscription));

            Log::info('Trial ending notification sent', [
                'workspace_id' => $workspace->id,
                'trial_ends_at' => $subscription->trial_ends_at?->toDateTimeString(),
                'owner_email' => $owner->email,
            ]);
        }
    }

    /**
     * Sync the local subscription state with Stripe's actual state.
     *
     * @throws ApiErrorException
     */
    public function syncSubscription(Workspace $workspace): void
    {
        $subscription = $workspace->subscription;

        if (!$subscription || !$subscription->stripe_subscription_id) {
            return;
        }

        try {
            $stripeSubscription = $this->stripe->subscriptions->retrieve(
                $subscription->stripe_subscription_id
            );

            $subscription->update([
                'status' => $this->mapStripeStatus($stripeSubscription->status),
                'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                'trial_ends_at' => $stripeSubscription->trial_end
                    ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
                    : null,
            ]);

            // Sync plan if price changed
            $priceId = $stripeSubscription->items->data[0]->price->id ?? null;
            if ($priceId) {
                $plan = Plan::where('stripe_monthly_price_id', $priceId)
                    ->orWhere('stripe_yearly_price_id', $priceId)
                    ->first();

                if ($plan && $plan->id !== $subscription->plan_id) {
                    $subscription->update(['plan_id' => $plan->id]);
                }
            }
        } catch (ApiErrorException $e) {
            Log::error('Stripe subscription sync failed', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get usage for a specific feature within the current billing period.
     *
     * @return array{used: int, limit: int|null, remaining: int|null}
     */
    public function getUsage(Workspace $workspace, string $featureKey): array
    {
        $subscription = $workspace->subscription;
        $plan = $subscription?->plan;

        $limit = $plan?->featureLimit($featureKey);

        $period = now()->format('Y-m');
        // 'emails_per_month' is the plan limit; sends are recorded under 'emails_sent'.
        $usageKey = $featureKey === 'emails_per_month' ? 'emails_sent' : $featureKey;

        $usageRecord = UsageRecord::where('workspace_id', $workspace->id)
            ->where('feature_key', $usageKey)
            ->where('period', $period)
            ->first();

        $used = $usageRecord?->quantity ?? 0;

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit !== null ? max(0, $limit - $used) : null,
        ];
    }

    /**
     * Check if a workspace can use a specific feature.
     * Returns true if the feature is enabled and within usage limits.
     * Results are cached for 5 minutes to avoid per-request DB queries.
     */
    public function canUse(Workspace $workspace, string $featureKey): bool
    {
        if (\App\Support\BillingPolicy::isFree()) return true;
        $cacheKey = "plan_feature:{$workspace->id}:{$featureKey}";

        return cache()->remember($cacheKey, 300, function () use ($workspace, $featureKey) {
            $subscription = $workspace->subscription;

            if (!$subscription || !$subscription->isActive()) {
                // Check if there's a free plan with this feature
                $freePlan = Plan::where('slug', 'free')
                    ->orWhere('monthly_price', 0)
                    ->first();

                if ($freePlan && $freePlan->hasFeature($featureKey)) {
                    $limit = $freePlan->featureLimit($featureKey);
                    if ($limit === null) {
                        return true; // Unlimited
                    }
                    $usage = $this->getUsage($workspace, $featureKey);
                    return $usage['used'] < $limit;
                }

                return false;
            }

            $plan = $subscription->plan;

            if (!$plan->hasFeature($featureKey)) {
                return false;
            }

            $limit = $plan->featureLimit($featureKey);

            // Null limit = unlimited
            if ($limit === null) {
                return true;
            }

            $usage = $this->getUsage($workspace, $featureKey);

            return $usage['used'] < $limit;
        });
    }

    /**
     * Clear cached plan feature checks for a workspace.
     * Called when subscription state changes (created, updated, deleted, payment).
     *
     * When a specific Plan is provided, only clears that plan's feature keys.
     * Otherwise, looks up the workspace's current subscription plan to minimize
     * the number of cache keys cleared (avoids loading ALL plans).
     */
    public function clearPlanFeatureCache(int $workspaceId, ?Plan $plan = null): void
    {
        if (!$plan) {
            // Look up only the workspace's current subscription plan
            $subscription = Subscription::where('workspace_id', $workspaceId)
                ->whereIn('status', ['active', 'trialing', 'past_due'])
                ->latest()
                ->first();

            $plan = $subscription?->plan;
        }

        if ($plan) {
            // Clear only this plan's feature keys via the plan_features table
            $featureKeys = $plan->planFeatures()->pluck('feature_key');

            foreach ($featureKeys as $featureKey) {
                cache()->forget("plan_feature:{$workspaceId}:{$featureKey}");
            }
        } else {
            // No active plan found -- fall back to clearing all known feature keys,
            // but only query the PlanFeature table (not loading full Plan models).
            $featureKeys = PlanFeature::distinct()->pluck('feature_key');

            foreach ($featureKeys as $featureKey) {
                cache()->forget("plan_feature:{$workspaceId}:{$featureKey}");
            }
        }
    }

    /**
     * Atomically reserve usage if under limit. Returns true if reserved, false if over limit.
     * Uses database-level atomic increment with WHERE to prevent concurrent overuse.
     */
    public function tryReserveUsage(Workspace $workspace, string $featureKey): bool
    {
        $subscription = $workspace->subscription;
        $plan = $subscription?->plan;
        $limit = $plan?->featureLimit($featureKey);

        // Null limit = unlimited
        if ($limit === null) {
            $this->incrementUsage($workspace, $featureKey);
            return true;
        }

        $period = now()->format('Y-m');

        // Atomic: increment only if current quantity < limit
        $record = UsageRecord::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'feature_key' => $featureKey,
                'period' => $period,
            ],
            ['quantity' => 0]
        );

        $affected = UsageRecord::where('id', $record->id)
            ->where('quantity', '<', $limit)
            ->increment('quantity');

        if ($affected === 0) {
            return false; // Already at or over limit
        }

        // Clear the canUse cache since usage changed
        cache()->forget("plan_feature:{$workspace->id}:{$featureKey}");

        return true;
    }

    /**
     * Increment usage for a feature. Call this after each use of a metered feature.
     *
     * Uses firstOrCreate with a UniqueConstraintViolationException catch to handle
     * the race where two processes both attempt to create the same record concurrently.
     * The atomic increment on the resolved record is safe regardless.
     */
    public function incrementUsage(Workspace $workspace, string $featureKey, int $quantity = 1): void
    {
        $period = now()->format('Y-m');

        $attributes = [
            'workspace_id' => $workspace->id,
            'feature_key' => $featureKey,
            'period' => $period,
        ];

        try {
            $record = UsageRecord::firstOrCreate($attributes, ['quantity' => 0]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Another process created the record between our check and insert.
            // Retrieve the existing record that won the race.
            $record = UsageRecord::where($attributes)->first();

            if (!$record) {
                Log::error('UsageRecord race: record not found after UniqueConstraintViolation', $attributes);
                throw $e;
            }
        }

        $record->increment('quantity', $quantity);

        Log::debug('Usage incremented', [
            'workspace_id' => $workspace->id,
            'feature_key' => $featureKey,
            'quantity' => $quantity,
            'period' => $period,
        ]);
    }

    /**
     * Get or create a Stripe customer for the workspace.
     */
    private function ensureStripeCustomer(Workspace $workspace): string
    {
        $existingCustomerId = $this->getStripeCustomerId($workspace);

        if ($existingCustomerId) {
            return $existingCustomerId;
        }

        $owner = $workspace->owner();

        $customer = $this->stripe->customers->create([
            'name' => $workspace->name,
            'email' => $owner?->email,
            'metadata' => [
                'workspace_id' => $workspace->id,
                'workspace_name' => $workspace->name,
            ],
        ]);

        // Store customer ID on any existing subscription
        $subscription = $workspace->subscription;
        if ($subscription) {
            $subscription->update(['stripe_customer_id' => $customer->id]);
        }

        Log::info('Stripe customer created', [
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
        ]);

        return $customer->id;
    }

    /**
     * Get the Stripe customer ID for a workspace.
     */
    private function getStripeCustomerId(Workspace $workspace): ?string
    {
        return $workspace->subscription?->stripe_customer_id;
    }

    /**
     * Resolve workspace ID from a Stripe customer ID.
     */
    private function resolveWorkspaceFromCustomer(string $customerId): ?int
    {
        $subscription = Subscription::where('stripe_customer_id', $customerId)
            ->latest()
            ->first();

        return $subscription?->workspace_id;
    }

    /**
     * Map Stripe subscription status to our local status.
     */
    private function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'unpaid' => 'past_due',
            'incomplete' => 'incomplete',
            'incomplete_expired' => 'canceled',
            'paused' => 'paused',
            default => 'active',
        };
    }
}
