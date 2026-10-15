<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HandleFailedPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Exponential backoff: 2min, 10min between retries.
     */
    public array $backoff = [120, 600];

    /**
     * Maximum seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Dunning schedule (days since first failure):
     * Day 0: Send immediate failure notification
     * Day 3: Send payment reminder
     * Day 7: Send final warning notice
     * Day 10: Restrict workspace features (past_due enforcement)
     * Day 30: Cancel subscription entirely
     */
    private const DUNNING_SCHEDULE = [
        0 => 'immediate_notice',
        3 => 'reminder',
        7 => 'final_warning',
        10 => 'restrict',
        30 => 'cancel',
    ];

    public function __construct(
        public int $subscriptionId,
    ) {}

    public function handle(): void
    {
        if (\App\Support\BillingPolicy::isFree()) return;
        $subscription = Subscription::with(['workspace', 'plan'])->find($this->subscriptionId);

        if (!$subscription) {
            Log::warning('HandleFailedPaymentJob: Subscription not found', [
                'subscription_id' => $this->subscriptionId,
            ]);
            return;
        }

        // If payment has been resolved, stop dunning
        if ($subscription->status === 'active') {
            Log::info('HandleFailedPaymentJob: Payment resolved, stopping dunning', [
                'subscription_id' => $this->subscriptionId,
            ]);
            return;
        }

        // If already canceled, nothing to do
        if ($subscription->status === 'canceled') {
            return;
        }

        $workspace = $subscription->workspace;
        if (!$workspace) {
            return;
        }

        // Determine how many days since the payment first failed
        $firstFailedPayment = $workspace->payments()
            ->where('status', 'failed')
            ->where('subscription_id', $subscription->id)
            ->oldest()
            ->first();

        $daysSinceFailure = $firstFailedPayment
            ? (int) $firstFailedPayment->created_at->diffInDays(now())
            : 0;

        $owner = $workspace->owner();
        $ownerEmail = $owner?->email;

        if (!$ownerEmail) {
            Log::warning('HandleFailedPaymentJob: No owner email found', [
                'workspace_id' => $workspace->id,
            ]);
            return;
        }

        // Determine the current dunning action
        $action = $this->determineDunningAction($daysSinceFailure);

        Log::info('HandleFailedPaymentJob: Processing dunning step', [
            'subscription_id' => $subscription->id,
            'workspace_id' => $workspace->id,
            'days_since_failure' => $daysSinceFailure,
            'action' => $action,
        ]);

        match ($action) {
            'immediate_notice' => $this->sendImmediateNotice($workspace, $ownerEmail, $subscription),
            'reminder' => $this->sendReminder($workspace, $ownerEmail, $subscription),
            'final_warning' => $this->sendFinalWarning($workspace, $ownerEmail, $subscription),
            'restrict' => $this->restrictWorkspace($workspace, $ownerEmail, $subscription),
            'cancel' => $this->cancelSubscription($workspace, $ownerEmail, $subscription),
            default => null,
        };

        // Schedule the next dunning check
        $nextCheckDays = $this->getNextDunningDay($daysSinceFailure);

        if ($nextCheckDays !== null) {
            $delayDays = $nextCheckDays - $daysSinceFailure;
            self::dispatch($this->subscriptionId)
                ->onQueue('billing')
                ->delay(now()->addDays(max(1, $delayDays)));
        }
    }

    /**
     * Determine which dunning action to take based on days since failure.
     */
    private function determineDunningAction(int $daysSinceFailure): string
    {
        $action = 'immediate_notice';

        foreach (self::DUNNING_SCHEDULE as $day => $dayAction) {
            if ($daysSinceFailure >= $day) {
                $action = $dayAction;
            }
        }

        return $action;
    }

    /**
     * Get the next dunning day after the current number of days.
     */
    private function getNextDunningDay(int $currentDays): ?int
    {
        foreach (array_keys(self::DUNNING_SCHEDULE) as $day) {
            if ($day > $currentDays) {
                return $day;
            }
        }

        return null; // No more dunning steps
    }

    /**
     * Day 0: Send immediate payment failure notification.
     */
    private function sendImmediateNotice(Workspace $workspace, string $email, Subscription $subscription): void
    {
        $billingUrl = config('app.url') . '/settings/billing';

        Mail::html(
            $this->buildDunningEmail(
                'Payment Failed',
                "We were unable to process the payment for your {$subscription->plan?->name} plan.",
                'Please update your payment method to continue enjoying uninterrupted service.',
                $billingUrl,
                'Update Payment Method'
            ),
            function ($message) use ($email) {
                $message->to($email)
                    ->subject('[Action Required] Payment failed for your ' . config('app.name') . ' subscription');
            }
        );

        Log::info('Dunning: Immediate notice sent', ['workspace_id' => $workspace->id]);
    }

    /**
     * Day 3: Send payment reminder.
     */
    private function sendReminder(Workspace $workspace, string $email, Subscription $subscription): void
    {
        $billingUrl = config('app.url') . '/settings/billing';

        Mail::html(
            $this->buildDunningEmail(
                'Payment Reminder',
                "We still haven't been able to process payment for your {$subscription->plan?->name} plan. "
                    . "It's been a few days since the first attempt.",
                'Please update your payment information to avoid any service interruptions.',
                $billingUrl,
                'Update Payment Method'
            ),
            function ($message) use ($email) {
                $message->to($email)
                    ->subject('[Reminder] Please update your payment method - ' . config('app.name'));
            }
        );

        Log::info('Dunning: Reminder sent', ['workspace_id' => $workspace->id]);
    }

    /**
     * Day 7: Send final warning.
     */
    private function sendFinalWarning(Workspace $workspace, string $email, Subscription $subscription): void
    {
        $billingUrl = config('app.url') . '/settings/billing';

        Mail::html(
            $this->buildDunningEmail(
                'Final Payment Notice',
                "This is your final notice regarding the failed payment for your {$subscription->plan?->name} plan. "
                    . "Your workspace features will be restricted in 3 days if payment is not received.",
                'To avoid losing access to premium features, please update your payment method immediately.',
                $billingUrl,
                'Update Payment Now'
            ),
            function ($message) use ($email) {
                $message->to($email)
                    ->subject('[Final Notice] Your ' . config('app.name') . ' subscription will be restricted soon');
            }
        );

        Log::info('Dunning: Final warning sent', ['workspace_id' => $workspace->id]);
    }

    /**
     * Day 10: Restrict workspace to free plan features.
     */
    private function restrictWorkspace(Workspace $workspace, string $email, Subscription $subscription): void
    {
        // Mark subscription as restricted (past_due already set)
        // The CheckPlanFeature middleware will enforce feature restrictions

        $billingUrl = config('app.url') . '/settings/billing';

        Mail::html(
            $this->buildDunningEmail(
                'Account Restricted',
                "Due to continued payment failure, your {$subscription->plan?->name} plan features have been restricted. "
                    . "You now only have access to free plan features.",
                'Update your payment method to restore full access. '
                    . "Your account will be downgraded permanently in 20 days if payment isn't received.",
                $billingUrl,
                'Restore My Account'
            ),
            function ($message) use ($email) {
                $message->to($email)
                    ->subject('[Account Restricted] Your ' . config('app.name') . ' subscription has been limited');
            }
        );

        Log::info('Dunning: Workspace restricted', ['workspace_id' => $workspace->id]);
    }

    /**
     * Day 30: Cancel subscription and downgrade to free.
     */
    private function cancelSubscription(Workspace $workspace, string $email, Subscription $subscription): void
    {
        // Cancel the subscription
        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        // Create a free plan subscription
        $freePlan = \App\Models\Plan::where('slug', 'free')
            ->orWhere('monthly_price', 0)
            ->first();

        if ($freePlan) {
            // Guard against duplicate free subscriptions on retry
            $existingFree = Subscription::where('workspace_id', $workspace->id)
                ->where('plan_id', $freePlan->id)
                ->where('status', 'active')
                ->exists();

            if (!$existingFree) {
                Subscription::create([
                    'workspace_id' => $workspace->id,
                    'plan_id' => $freePlan->id,
                    'status' => 'active',
                    'billing_cycle' => 'monthly',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addYear(),
                ]);
            }
        }

        // Try to cancel on Stripe as well
        $stripeKey = config('cashier.secret') ?: config('services.stripe.secret');
        if ($subscription->stripe_subscription_id && !empty($stripeKey)) {
            try {
                $stripe = new \Stripe\StripeClient([
                    'api_key' => $stripeKey,
                    'timeout' => 120,
                    'connect_timeout' => 10,
                ]);
                $stripe->subscriptions->cancel($subscription->stripe_subscription_id);
            } catch (\Throwable $e) {
                Log::error('Failed to cancel subscription on Stripe', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Mail::html(
            $this->buildDunningEmail(
                'Subscription Canceled',
                "Your {$subscription->plan?->name} subscription has been canceled due to prolonged payment failure.",
                'Your workspace has been downgraded to the free plan. You can resubscribe at any time.',
                config('app.url') . '/settings/billing',
                'View Plans'
            ),
            function ($message) use ($email) {
                $message->to($email)
                    ->subject('Your ' . config('app.name') . ' subscription has been canceled');
            }
        );

        Log::info('Dunning: Subscription canceled', [
            'workspace_id' => $workspace->id,
            'subscription_id' => $subscription->id,
        ]);
    }

    /**
     * Build a consistent dunning email HTML.
     */
    private function buildDunningEmail(
        string $heading,
        string $message,
        string $cta,
        string $ctaUrl,
        string $ctaLabel
    ): string {
        $appName = config('app.name');
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f5f5; padding: 40px 20px;">
            <div style="max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div style="text-align: center; margin-bottom: 24px;">
                    <div style="display: inline-block; background-color: #4F46E5; color: white; width: 40px; height: 40px; line-height: 40px; border-radius: 8px; font-weight: bold; font-size: 18px;">M</div>
                </div>
                <h1 style="font-size: 22px; color: #111827; text-align: center; margin-bottom: 16px;">{$heading}</h1>
                <p style="font-size: 15px; color: #374151; line-height: 1.6; margin-bottom: 12px;">{$message}</p>
                <p style="font-size: 15px; color: #374151; line-height: 1.6; margin-bottom: 24px;">{$cta}</p>
                <div style="text-align: center;">
                    <a href="{$ctaUrl}" style="display: inline-block; background-color: #4F46E5; color: white; padding: 12px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 15px;">{$ctaLabel}</a>
                </div>
                <hr style="margin: 32px 0; border: none; border-top: 1px solid #e5e7eb;">
                <p style="font-size: 12px; color: #9CA3AF; text-align: center;">
                    {$appName} &mdash; AI-Powered Communication Automation
                </p>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('HandleFailedPaymentJob failed', [
            'subscription_id' => $this->subscriptionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
