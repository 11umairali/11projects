<?php

namespace App\Observers;

use App\Models\Plan;
use App\Services\CacheService;

/**
 * Invalidate plan feature cache when plan details change.
 *
 * Plan changes are rare (admin panel only) but when they happen, every
 * workspace on that plan must see updated limits immediately — stale
 * feature flags could let tenants exceed quotas or block paid features.
 *
 * Registered in AppServiceProvider::boot().
 */
class PlanObserver
{
    /**
     * Handle the Plan "saved" event (covers both create and update).
     */
    public function saved(Plan $plan): void
    {
        CacheService::invalidatePlan($plan->id);
    }

    /**
     * Handle the Plan "deleted" event.
     */
    public function deleted(Plan $plan): void
    {
        CacheService::invalidatePlan($plan->id);
    }
}
