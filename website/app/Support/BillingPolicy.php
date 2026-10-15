<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use Illuminate\Validation\ValidationException;

final class BillingPolicy
{
    public const FREE = '__free_unlimited__';

    public static function isFree(): bool
    {
        return SystemSetting::get('default_plan', 'free') === self::FREE;
    }

    public static function defaultPlan(): ?Plan
    {
        if (self::isFree()) {
            return null;
        }
        $value = SystemSetting::get('default_plan', 'free');
        return Plan::where('is_active', true)
            ->where(fn ($q) => $q->where('slug', $value)->orWhere('id', ctype_digit((string) $value) ? (int) $value : 0))
            ->first();
    }

    public static function setDefault(string $value): void
    {
        if ($value === self::FREE) {
            // Do not hide billing while a payment provider can still renew a subscription.
            if (Subscription::withTrashed()->whereNotNull('stripe_subscription_id')
                ->whereNotIn('status', ['canceled', 'incomplete_expired'])->exists()) {
                throw ValidationException::withMessages(['default_plan' =>
                    'Cancel recurring subscriptions with the payment provider and sync their canceled status before enabling unlimited free mode.']);
            }
        } elseif (!Plan::where('slug', $value)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['default_plan' => 'Choose an active plan.']);
        }
        SystemSetting::set('default_plan', $value, 'authentication');
    }

    public static function assertBillingEnabled(): void
    {
        abort_if(self::isFree(), 403, 'Billing is disabled. All features are free and unlimited.');
    }
}
