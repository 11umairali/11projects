<?php

namespace App\Support;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class SecurityAuditLogger
{
    /**
     * Log a security event.
     */
    public static function log(
        string $eventType,
        string $status,
        ?User $user,
        ?Request $request = null,
        array $metadata = []
    ): SecurityAuditLog {
        return SecurityAuditLog::create([
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'status' => $status,
            'ip_address' => $request?->ip() ?? '0.0.0.0',
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata ?: null,
            'logged_at' => now(),
        ]);
    }

    /**
     * Quick helpers for common events.
     */
    public static function loginSuccess(User $user, Request $request, array $metadata = []): SecurityAuditLog
    {
        return static::log('login_success', 'success', $user, $request, $metadata);
    }

    public static function loginFailed(?User $user, Request $request, array $metadata = []): SecurityAuditLog
    {
        return static::log('login_failed', 'failed', $user, $request, $metadata);
    }

    public static function loginThrottled(?User $user, Request $request, array $metadata = []): SecurityAuditLog
    {
        return static::log('login_throttled', 'blocked', $user, $request, $metadata);
    }

    public static function twoFactorEnabled(User $user, Request $request): SecurityAuditLog
    {
        return static::log('two_factor_enabled', 'success', $user, $request);
    }

    public static function twoFactorDisabled(User $user, Request $request): SecurityAuditLog
    {
        return static::log('two_factor_disabled', 'info', $user, $request);
    }

    public static function twoFactorVerified(User $user, Request $request): SecurityAuditLog
    {
        return static::log('two_factor_verified', 'success', $user, $request);
    }

    public static function twoFactorFailed(User $user, Request $request): SecurityAuditLog
    {
        return static::log('two_factor_failed', 'failed', $user, $request);
    }

    public static function ipBlocked(Request $request, array $metadata = []): SecurityAuditLog
    {
        return static::log('ip_blocked', 'blocked', null, $request, $metadata);
    }

    public static function locationBlocked(Request $request, array $metadata = []): SecurityAuditLog
    {
        return static::log('location_blocked', 'blocked', null, $request, $metadata);
    }

    public static function roleChanged(User $user, Request $request, array $metadata = []): SecurityAuditLog
    {
        return static::log('role_changed', 'info', $user, $request, $metadata);
    }

    public static function permissionChanged(User $user, Request $request, array $metadata = []): SecurityAuditLog
    {
        return static::log('permission_changed', 'info', $user, $request, $metadata);
    }

    public static function userSuspended(User $user, Request $request, array $metadata = []): SecurityAuditLog
    {
        return static::log('user_suspended', 'info', $user, $request, $metadata);
    }

    public static function settingsChanged(User $user, Request $request, array $metadata = []): SecurityAuditLog
    {
        return static::log('settings_changed', 'info', $user, $request, $metadata);
    }
}
