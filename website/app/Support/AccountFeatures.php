<?php

namespace App\Support;

use App\Models\SystemSetting;

final class AccountFeatures
{
    public static function emailRecovery(): bool
    {
        return SystemSetting::enabled('account.email_recovery_enabled', true);
    }

    public static function phraseRecovery(): bool
    {
        return SystemSetting::enabled('account.phrase_recovery_enabled', true);
    }

    public static function phraseRecoveryDisabled(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => __('Recovery phrase password reset is disabled.'),
            'code' => 'phrase_recovery_disabled',
        ], 403);
    }

    public static function onboarding(): bool
    {
        // Preserve the deployment's existing behavior until an administrator saves a choice.
        return SystemSetting::enabled('account.onboarding_enabled', !config('dahify.auto_provision_workspace', true));
    }

    public static function tour(): bool
    {
        return SystemSetting::enabled('account.product_tour_enabled', true);
    }

    public static function checklist(): bool
    {
        return SystemSetting::enabled('account.getting_started_enabled', true);
    }

    public static function all(): array
    {
        return ['email_recovery_enabled' => self::emailRecovery(), 'phrase_recovery_enabled' => self::phraseRecovery(), 'onboarding_enabled' => self::onboarding(),
            'product_tour_enabled' => self::tour(), 'getting_started_enabled' => self::checklist()];
    }

    public static function emailRecoveryDisabled(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => __('Email password recovery is disabled.'),
            'code' => 'email_recovery_disabled',
        ], 403);
    }
}
