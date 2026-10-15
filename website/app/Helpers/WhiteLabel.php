<?php

namespace App\Helpers;

use App\Models\Workspace;
use App\Services\PlanLimitService;

/**
 * Centralized white-label decision helper.
 *
 * Layout files call WhiteLabel::shouldShowPoweredBy() to decide whether
 * to render a "Powered by [BrandName]" footer line. The result depends
 * on the current workspace's plan — workspaces with the `white_label`
 * plan feature enabled get a clean unbranded view; others see the
 * platform credit (which the admin operator can still rebrand site-wide
 * via Settings → Branding → site_name).
 *
 * Falls back to "show" (true) when there's no workspace context (public
 * marketing pages, login screens) — those are always unbranded by the
 * platform-level site_name and don't need the per-tenant flag.
 */
class WhiteLabel
{
    /**
     * Return true if the current workspace should DISPLAY the platform's
     * "Powered by" credit. Returns false when the workspace has white_label
     * enabled on its plan.
     */
    public static function shouldShowPoweredBy(?Workspace $workspace = null): bool
    {
        // Resolve workspace from auth() if not passed explicitly
        if (!$workspace && auth()->check()) {
            $workspace = auth()->user()->activeWorkspace ?? null;
        }

        if (!$workspace) {
            return true;
        }

        try {
            return !app(PlanLimitService::class)->hasFeature($workspace, 'white_label');
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Inverse helper for templates that want a positive condition
     * (more readable in blade @if checks).
     */
    public static function isWhiteLabeled(?Workspace $workspace = null): bool
    {
        return !self::shouldShowPoweredBy($workspace);
    }

    /**
     * The brand name to display anywhere — falls back to config('app.name')
     * when the admin hasn't set site_name yet.
     */
    public static function brandName(): string
    {
        $siteName = (string) \App\Models\SystemSetting::get('site_name', '');
        return $siteName !== '' ? $siteName : (string) config('app.name', 'MailTrixy');
    }
}
