<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a succeeded payment into an active subscription.
 *
 * Extracted from PaymentCallbackController so the same logic runs whether
 * a gateway confirms the payment (callback/webhook) or an admin manually
 * approves a bank transfer. Behaviour is unchanged from the original
 * private method, plus one addition: the workspace's cached plan-feature
 * checks are cleared so the new plan takes effect immediately instead of
 * after the 5-minute cache expires.
 */
class SubscriptionActivator
{
    public function __construct(private readonly StripeService $stripeService) {}

    /**
     * @return bool true when a subscription was created
     */
    public function activate(Payment $payment): bool
    {
        $metadata = $payment->metadata ?? [];
        $planId = $metadata['plan_id'] ?? null;
        $billingCycle = $metadata['billing_cycle'] ?? 'monthly';

        if (! $planId) {
            Log::warning('Payment succeeded but no plan_id in metadata', ['payment_id' => $payment->id]);

            return false;
        }

        $plan = Plan::find($planId);
        if (! $plan) {
            Log::warning('Payment succeeded but plan no longer exists', ['payment_id' => $payment->id, 'plan_id' => $planId]);

            return false;
        }

        DB::transaction(function () use ($payment, $plan, $billingCycle) {
            // Cancel existing non-canceled subscriptions for this workspace
            Subscription::where('workspace_id', $payment->workspace_id)
                ->where('status', '!=', 'canceled')
                ->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                ]);

            $periodEnd = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();

            $subscription = Subscription::create([
                'workspace_id' => $payment->workspace_id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $billingCycle,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
            ]);

            $payment->update(['subscription_id' => $subscription->id]);
        });

        $this->stripeService->clearPlanFeatureCache((int) $payment->workspace_id);

        Log::info('Subscription activated', [
            'payment_id' => $payment->id,
            'workspace_id' => $payment->workspace_id,
            'plan_id' => $planId,
            'gateway' => $payment->gateway_slug,
        ]);

        return true;
    }
}
