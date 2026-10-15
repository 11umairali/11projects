<?php

namespace App\Livewire\Settings;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Services\Billing\StripeService;
use App\Services\PlanLimitService;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Component;

class BillingManager extends Component
{
    use AuthorizesWorkspaceActions;

    public string $billingCycle = 'monthly';

    public function switchCycle(string $cycle): void
    {
        $this->billingCycle = $cycle;
    }

    public function upgradePlan(int $planId): void
    {
        \App\Support\BillingPolicy::assertBillingEnabled();
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        try {
            $workspace = auth()->user()->activeWorkspace;

            $plan = Plan::where('is_active', true)
                ->where('id', $planId)
                ->firstOrFail();

            if ($plan->isFree()) {
                session()->flash('error', 'Cannot upgrade to a free plan via this method.');
                return;
            }

            // Prevent upgrading to the same plan
            $currentPlan = $workspace->subscription?->plan;
            if ($currentPlan && $currentPlan->id === $plan->id) {
                session()->flash('error', 'You are already on this plan.');
                return;
            }

            // Always redirect to checkout page — it handles gateway selection
            $this->redirect(route('checkout.show', [
                'plan' => $plan->id,
                'cycle' => $this->billingCycle,
            ]));
        } catch (\Stripe\Exception\ApiErrorException $e) {
            session()->flash('error', 'Payment error: ' . $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'Could not create checkout session. Please try again later.');
        }
    }

    public function manageBilling(): void
    {
        \App\Support\BillingPolicy::assertBillingEnabled();
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        try {
            $workspace = auth()->user()->activeWorkspace;
            $stripeService = app(StripeService::class);
            $session = $stripeService->createCustomerPortalSession($workspace);

            $this->redirect($session->url);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            session()->flash('error', 'Stripe error: ' . $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'Could not open billing portal. Please try again later.');
        }
    }

    public function render()
    {
        \App\Support\BillingPolicy::assertBillingEnabled();
        $workspaceId = auth()->user()->active_workspace_id;

        $subscription = Subscription::where('workspace_id', $workspaceId)
            ->with('plan')
            ->latest()
            ->first();

        $currentPlan = $subscription?->plan;

        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->with('planFeatures')
            ->get();

        // Usage data — combine metered records with resource counts for full visibility
        $period = now()->format('Y-m');
        $usageRecords = UsageRecord::where('workspace_id', $workspaceId)
            ->where('period', $period)
            ->get()
            ->keyBy('feature_key');

        // Add resource-count usage — count actual DB records, not usage_records table
        $workspace = auth()->user()->activeWorkspace;
        $resourceUsage = [];
        if ($currentPlan && $workspace) {
            $counts = [
                'contacts' => $workspace->contacts()->count(),
                'email_accounts' => $workspace->emailAccounts()->count(),
                'team_members' => $workspace->members()->count(),
                'workflows' => $workspace->workflows()->count(),
                'kb_documents' => \App\Models\KbDocument::where('workspace_id', $workspace->id)->count(),
            ];

            foreach ($counts as $key => $used) {
                $limit = $currentPlan->featureLimit($key);
                // Show even if unlimited (limit=null) — display as "X used"
                $resourceUsage[$key] = [
                    'used' => $used,
                    'limit' => $limit, // null = unlimited
                ];
            }

            // Add metered features from plan_features that have limits
            $meteredKeys = ['ai_replies', 'campaigns_per_month', 'storage_mb'];
            $period = now()->format('Y-m');
            foreach ($meteredKeys as $key) {
                $limit = $currentPlan->featureLimit($key);
                $usageRec = $usageRecords->get($key);
                $resourceUsage[$key] = [
                    'used' => $usageRec?->quantity ?? 0,
                    'limit' => $limit,
                ];
            }
        }

        // Billing history
        $billingHistory = \App\Models\Payment::where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('livewire.settings.billing-manager', [
            'subscription' => $subscription,
            'currentPlan' => $currentPlan,
            'plans' => $plans,
            'usageRecords' => $usageRecords,
            'resourceUsage' => $resourceUsage,
            'billingHistory' => $billingHistory,
        ]);
    }
}
