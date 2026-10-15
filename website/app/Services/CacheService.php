<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Centralized caching service for frequently-read, rarely-written data.
 *
 * Design rationale:
 * ─────────────────
 * SaaS apps have a class of data that is read on virtually every request but
 * changes infrequently: workspace settings, plan feature flags, tag lists,
 * AI configuration.  Caching these avoids N+1 queries on every page load and
 * keeps p95 API latency well under 50 ms.
 *
 * All cache keys are prefixed with "am:" (automail) and scoped by entity type
 * + ID so invalidation is surgical — you never need to flush the entire cache.
 *
 * TTLs are tiered by volatility:
 *   - Plan features: 24 h  (admin changes plans rarely)
 *   - Workspace settings: 1 h  (changed occasionally in settings UI)
 *   - AI config: 1 h  (changed occasionally in AI settings)
 *   - Workspace tags: 30 min  (created/reordered more often)
 *
 * Cache stampede protection: Laravel's Cache::remember() is atomic on Redis
 * (SETNX), so concurrent requests during a cache miss will not all hit the DB.
 *
 * Usage:
 *   $settings = CacheService::workspaceSettings($workspaceId, fn () => $workspace->settings);
 *   CacheService::invalidateWorkspace($workspaceId); // after settings change
 */
class CacheService
{
    /*
    |--------------------------------------------------------------------------
    | TTL Constants (seconds)
    |--------------------------------------------------------------------------
    */

    private const TTL_PLAN_FEATURES      = 86400;  // 24 hours
    private const TTL_WORKSPACE_SETTINGS = 3600;   // 1 hour
    private const TTL_AI_CONFIG          = 3600;   // 1 hour
    private const TTL_WORKSPACE_TAGS     = 1800;   // 30 minutes

    /*
    |--------------------------------------------------------------------------
    | Cache Readers — remember pattern with typed keys
    |--------------------------------------------------------------------------
    */

    /**
     * Cache workspace settings (JSON blob from workspaces.settings column).
     *
     * Typical callers: middleware that resolves workspace, any page that reads
     * timezone / business hours / notification preferences.
     */
    public static function workspaceSettings(int $workspaceId, callable $callback): mixed
    {
        return Cache::remember(
            static::key('ws_settings', $workspaceId),
            self::TTL_WORKSPACE_SETTINGS,
            $callback,
        );
    }

    /**
     * Cache plan feature flags and limits (seats, contacts, AI credits, etc.).
     *
     * Typical callers: PlanLimitService, billing middleware, upgrade prompts.
     */
    public static function planFeatures(int $planId, callable $callback): mixed
    {
        return Cache::remember(
            static::key('plan_features', $planId),
            self::TTL_PLAN_FEATURES,
            $callback,
        );
    }

    /**
     * Cache the full tag list for a workspace (id, name, color, sort_order).
     *
     * Typical callers: tag selector dropdown, conversation filter sidebar,
     * contact tagging modal.
     */
    public static function workspaceTags(int $workspaceId, callable $callback): mixed
    {
        return Cache::remember(
            static::key('ws_tags', $workspaceId),
            self::TTL_WORKSPACE_TAGS,
            $callback,
        );
    }

    /**
     * Cache AI configuration for a workspace (provider, model, temperature, etc.).
     *
     * Typical callers: AutoReplyService, AI draft generation, knowledge base search.
     */
    public static function aiConfig(int $workspaceId, callable $callback): mixed
    {
        return Cache::remember(
            static::key('ai_config', $workspaceId),
            self::TTL_AI_CONFIG,
            $callback,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Invalidation — called from model observers
    |--------------------------------------------------------------------------
    |
    | These methods forget every cache key scoped to the given entity.
    | Observers call these on saved/deleted events so stale data never
    | survives longer than a single request cycle.
    |
    */

    /**
     * Invalidate all workspace-scoped caches (settings, tags, AI config).
     */
    public static function invalidateWorkspace(int $workspaceId): void
    {
        Cache::forget(static::key('ws_settings', $workspaceId));
        Cache::forget(static::key('ws_tags', $workspaceId));
        Cache::forget(static::key('ai_config', $workspaceId));
    }

    /**
     * Invalidate plan feature cache.
     */
    public static function invalidatePlan(int $planId): void
    {
        Cache::forget(static::key('plan_features', $planId));
    }

    /**
     * Invalidate only the tag cache for a workspace.
     *
     * More surgical than invalidateWorkspace() — use this from TagObserver
     * so workspace settings and AI config stay cached.
     */
    public static function invalidateWorkspaceTags(int $workspaceId): void
    {
        Cache::forget(static::key('ws_tags', $workspaceId));
    }

    /*
    |--------------------------------------------------------------------------
    | Key Builder
    |--------------------------------------------------------------------------
    */

    /**
     * Build a namespaced cache key.
     *
     * Format: "am:{type}:{part1}:{part2}:..."
     * Example: "am:ws_settings:42"
     *
     * The "am:" prefix avoids collisions with other packages or Laravel's
     * own internal cache keys.
     */
    private static function key(string $type, int|string ...$parts): string
    {
        return 'am:' . $type . ':' . implode(':', $parts);
    }
}
