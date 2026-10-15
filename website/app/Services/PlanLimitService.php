<?php

namespace App\Services;

use App\Exceptions\PlanLimitReachedException;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\KbDocument;
use App\Models\Plan;
use App\Models\TempMailAddress;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\Billing\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unified plan limit enforcement service.
 *
 * Wraps StripeService's canUse/tryReserveUsage with MailTrixy-style
 * assertCanCreate / assertFeatureEnabled / getUsage patterns for
 * ergonomic use in controllers and jobs.
 */
class PlanLimitService
{
    private array $resolvedPlans = [];

    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    // ───────────────────────────────────────────────────
    //  Assertion methods (throw PlanLimitReachedException)
    // ───────────────────────────────────────────────────

    /**
     * Assert that the workspace can use a metered feature (e.g. contacts, campaigns, emails_per_month).
     * Checks current usage against the plan limit and throws if over.
     *
     * @throws PlanLimitReachedException
     */
    public function assertCanCreate(Workspace $workspace, string $featureKey): void
    {
        if ($this->isExempt($workspace)) {
            return;
        }

        $plan = $this->resolvePlan($workspace);
        if (!$plan) {
            return;
        }

        if (!$plan->hasFeature($featureKey)) {
            throw new PlanLimitReachedException($featureKey, 0, 0);
        }

        $limit = $plan->featureLimit($featureKey);

        // Null limit = unlimited
        if ($limit === null) {
            return;
        }

        $usage = $this->getCurrentUsage($workspace, $featureKey);

        if ($usage >= $limit) {
            throw new PlanLimitReachedException($featureKey, $usage, $limit);
        }
    }

    /**
     * Assert that a boolean feature flag is enabled on the plan.
     * Use for features like ai_auto_reply, workflows, campaigns that are
     * either on or off (no metered limit).
     *
     * @throws PlanLimitReachedException
     */
    public function assertFeatureEnabled(Workspace $workspace, string $featureKey): void
    {
        if ($this->isExempt($workspace)) {
            return;
        }

        $plan = $this->resolvePlan($workspace);
        if (!$plan) {
            return;
        }

        if (!$plan->hasFeature($featureKey)) {
            throw new PlanLimitReachedException($featureKey, 0, 0);
        }
    }

    /**
     * Assert that metered usage can be incremented, and atomically reserve it.
     * This is the concurrency-safe version of assertCanCreate -- use when
     * you need to simultaneously check AND increment in one operation.
     *
     * @throws PlanLimitReachedException
     */
    public function assertAndReserve(Workspace $workspace, string $featureKey): void
    {
        if ($this->isExempt($workspace)) {
            $this->stripeService->incrementUsage($workspace, $featureKey);
            return;
        }

        $reserved = $this->stripeService->tryReserveUsage($workspace, $featureKey);

        if (!$reserved) {
            $usage = $this->stripeService->getUsage($workspace, $featureKey);
            throw new PlanLimitReachedException(
                $featureKey,
                $usage['used'],
                $usage['limit'] ?? 0,
            );
        }
    }

    // ───────────────────────────────────────────────────
    //  Non-throwing check methods
    // ───────────────────────────────────────────────────

    /**
     * Check if a workspace can use a feature without throwing.
     */
    public function canUse(Workspace $workspace, string $featureKey): bool
    {
        if ($this->isExempt($workspace)) {
            return true;
        }

        return $this->stripeService->canUse($workspace, $featureKey);
    }

    /**
     * Check if a boolean feature is enabled without throwing.
     */
    public function hasFeature(Workspace $workspace, string $featureKey): bool
    {
        if ($this->isExempt($workspace)) {
            return true;
        }

        $plan = $this->resolvePlan($workspace);
        if (!$plan) {
            return true;
        }

        return $plan->hasFeature($featureKey);
    }

    // ───────────────────────────────────────────────────
    //  Usage reporting
    // ───────────────────────────────────────────────────

    /**
     * Get a full usage report for the workspace across all plan features.
     *
     * @return array{
     *     plan: Plan|null,
     *     features: array<string, array{used: int, limit: int|null, remaining: int|null, enabled: bool}>,
     *     is_exempt: bool,
     * }
     */
    public function getUsage(Workspace $workspace): array
    {
        $exempt = $this->isExempt($workspace);
        $plan = $this->resolvePlan($workspace);

        $features = [];

        if ($plan) {
            foreach ($plan->planFeatures as $planFeature) {
                $key = $planFeature->feature_key;
                $usage = $this->stripeService->getUsage($workspace, $key);

                $features[$key] = [
                    'used' => $usage['used'],
                    'limit' => $exempt ? null : ($planFeature->enabled ? $planFeature->limit : 0),
                    'remaining' => $usage['remaining'],
                    'enabled' => $exempt || (bool) $planFeature->enabled,
                ];
            }
        }

        return [
            'plan' => $plan,
            'features' => $features,
            'is_exempt' => $exempt,
        ];
    }

    /**
     * Get usage for a single feature key.
     *
     * @return array{used: int, limit: int|null, remaining: int|null}
     */
    public function getFeatureUsage(Workspace $workspace, string $featureKey): array
    {
        return $this->stripeService->getUsage($workspace, $featureKey);
    }

    // ───────────────────────────────────────────────────
    //  Internal helpers
    // ───────────────────────────────────────────────────

    /**
     * Resolve the active plan for a workspace.
     * Uses in-memory caching to avoid repeated DB lookups within a single request.
     */
    private function resolvePlan(Workspace $workspace): ?Plan
    {
        $cacheKey = $workspace->id;

        if (isset($this->resolvedPlans[$cacheKey])) {
            return $this->resolvedPlans[$cacheKey];
        }

        $subscription = $workspace->subscription;

        if ($subscription && $subscription->isActive()) {
            $plan = $subscription->plan;
            if ($plan) {
                // Eager-load planFeatures to avoid N+1 in getUsage
                $plan->loadMissing('planFeatures');
                return $this->resolvedPlans[$cacheKey] = $plan;
            }
        }

        // Fall back to the free plan
        $freePlan = Plan::where('slug', 'free')->first()
            ?? Plan::where('monthly_price', 0)->where('is_active', true)->first();

        if ($freePlan) {
            $freePlan->loadMissing('planFeatures');
        }

        return $this->resolvedPlans[$cacheKey] = $freePlan;
    }

    /**
     * Non-throwing check: can the workspace create one more of this resource?
     */
    public function canCreate(Workspace $workspace, string $featureKey): bool
    {
        try {
            $this->assertCanCreate($workspace, $featureKey);
            return true;
        } catch (PlanLimitReachedException) {
            return false;
        }
    }

    /**
     * Get the plan limit for a feature, or null if unlimited.
     */
    public function getLimit(Workspace $workspace, string $featureKey): ?int
    {
        if ($this->isExempt($workspace)) {
            return null;
        }

        $plan = $this->resolvePlan($workspace);
        if (!$plan || !$plan->hasFeature($featureKey)) {
            return 0;
        }

        return $plan->featureLimit($featureKey);
    }

    /**
     * Get the raw plan feature limit for a key, bypassing usage checks.
     * Useful when you need the configured limit value (e.g. temp_mail_lifetime_hours).
     */
    public function getFeatureLimit(Workspace $workspace, string $featureKey): ?int
    {
        if (\App\Support\BillingPolicy::isFree()) return null;
        $plan = $this->resolvePlan($workspace);
        return $plan?->featureLimit($featureKey);
    }

    /**
     * Get remaining quota for a feature, or null if unlimited.
     */
    public function getRemainingQuota(Workspace $workspace, string $featureKey): ?int
    {
        if ($this->isExempt($workspace)) {
            return null;
        }

        $limit = $this->getLimit($workspace, $featureKey);
        if ($limit === null) {
            return null;
        }

        $usage = $this->getCurrentUsage($workspace, $featureKey);

        return max(0, $limit - $usage);
    }

    /**
     * Get the current usage count for a feature.
     *
     * For resource-count features (contacts, email_accounts, team_members,
     * workflows, kb_documents), counts directly from the source tables to
     * prevent drift between usage_records and reality.
     *
     * For metered/consumable features (ai_replies, campaigns_per_month,
     * emails_per_month), reads from the usage_records table which tracks
     * per-period consumption.
     */
    private function getCurrentUsage(Workspace $workspace, string $featureKey): int
    {
        return match ($featureKey) {
            'contacts' => Contact::where('workspace_id', $workspace->id)->count(),

            'email_accounts' => EmailAccount::where('workspace_id', $workspace->id)->count(),

            'team_members' => DB::table('workspace_members')
                ->where('workspace_id', $workspace->id)
                ->count(),

            'workflows' => Workflow::where('workspace_id', $workspace->id)->count(),

            'kb_documents' => KbDocument::where('workspace_id', $workspace->id)->count(),

            'temp_mail_addresses' => TempMailAddress::where('workspace_id', $workspace->id)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->count(),

            'campaigns_per_month' => Campaign::where('workspace_id', $workspace->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('created_at', '<', now()->startOfMonth()->addMonth())
                ->count(),

            // Sends are recorded under 'emails_sent'; the plan limit is 'emails_per_month'.
            'emails_per_month' => UsageRecord::where('workspace_id', $workspace->id)
                ->where('feature_key', 'emails_sent')
                ->where('period', now()->format('Y-m'))
                ->value('quantity') ?? 0,

            // All other features (ai_replies, storage_mb, etc.)
            // use the usage_records table for per-period metered tracking
            default => UsageRecord::where('workspace_id', $workspace->id)
                ->where('feature_key', $featureKey)
                ->where('period', now()->format('Y-m'))
                ->value('quantity') ?? 0,
        };
    }

    /**
     * Check if this workspace is exempt from plan limit enforcement.
     * Only super admins bypass limits, and only when accessing admin routes.
     * Regular admin users (support, etc.) are subject to normal plan limits
     * on workspace features.
     */
    private function isExempt(Workspace $workspace): bool
    {
        if (\App\Support\BillingPolicy::isFree()) return true;
        // 1. HTTP context — the acting user is known.
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->is_admin) {
            return true;
        }

        // 2. Queue/job context — there's no `auth()->user()`. Without this
        //    branch, campaign send jobs and scheduled-email jobs would hit
        //    the plan cap even for admins and silently fail recipients.
        //    Treat the workspace as exempt if ANY admin user is a member.
        if ($workspace->members()->where('users.is_admin', true)->exists()) {
            return true;
        }

        return false;
    }
}
