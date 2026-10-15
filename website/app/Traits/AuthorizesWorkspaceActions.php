<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Provides workspace-scoped authorization and operation locking for Livewire components.
 *
 * Role hierarchy: owner (4) > admin (3) > agent (2) > viewer (1)
 * Permission levels:
 *   - view:      viewer+   (read dashboards, reports, data)
 *   - interact:  agent+    (reply, tag, assign, create contacts/deals)
 *   - create:    agent+    (alias for interact)
 *   - manage:    admin+    (settings, billing, send campaigns, AI config)
 *   - dangerous: owner only (delete workspace, remove members, dangerous operations)
 */
trait AuthorizesWorkspaceActions
{
    /**
     * Get the current user's role in the active workspace (cached 60s).
     */
    protected function getWorkspaceRole(): string
    {
        $user = Auth::user();

        if (! $user || ! $user->active_workspace_id) {
            return 'viewer';
        }

        $cacheKey = "ws_role:{$user->id}:{$user->active_workspace_id}";

        // FIX-005/006: Reduced from 60s to 10s to minimize privilege escalation window
        return Cache::remember($cacheKey, 10, function () use ($user) {
            $membership = $user->workspaces()
                ->where('workspaces.id', $user->active_workspace_id)
                ->first();

            return $membership?->pivot?->role ?? 'viewer';
        });
    }

    /**
     * Authorize the current action. Returns false and flashes error if unauthorized.
     *
     * Usage in Livewire methods:
     *   if (! $this->authorizeWorkspaceAction('manage')) return;
     */
    protected function authorizeWorkspaceAction(string $level): bool
    {
        $role = $this->getWorkspaceRole();

        $roleWeight = match ($role) {
            'owner' => 4,
            'admin' => 3,
            'agent' => 2,
            'viewer' => 1,
            default => 0,
        };

        $requiredWeight = match ($level) {
            'view' => 1,
            'interact' => 2,
            'create' => 2,
            'manage' => 3,
            'dangerous' => 4,
            default => 4,
        };

        if ($roleWeight < $requiredWeight) {
            session()->flash('error', 'You do not have permission to perform this action.');
            return false;
        }

        return true;
    }

    /**
     * Check if the user has at least a given role (for conditional UI rendering).
     */
    protected function isAtLeast(string $minimumRole): bool
    {
        $roleWeight = match ($this->getWorkspaceRole()) {
            'owner' => 4, 'admin' => 3, 'agent' => 2, 'viewer' => 1, default => 0,
        };

        $requiredWeight = match ($minimumRole) {
            'owner' => 4, 'admin' => 3, 'agent' => 2, 'viewer' => 1, default => 4,
        };

        return $roleWeight >= $requiredWeight;
    }

    /**
     * Execute a callback inside an operation lock to prevent duplicate actions.
     * Returns null and flashes error if lock cannot be acquired.
     *
     * Usage:
     *   return $this->withOperationLock("send-campaign-{$id}", function () {
     *       // ... send logic
     *   });
     */
    protected function withOperationLock(string $operation, callable $callback, int $ttlSeconds = 30): mixed
    {
        $key = "op_lock:{$operation}:" . Auth::id();
        $lock = Cache::lock($key, $ttlSeconds);

        if (! $lock->get()) {
            session()->flash('error', 'This operation is already in progress. Please wait.');
            return null;
        }

        try {
            return $callback();
        } finally {
            $lock->forceRelease();
        }
    }

    /**
     * Flush cached role when workspace membership changes.
     */
    protected function flushRoleCache(): void
    {
        $user = Auth::user();
        if ($user && $user->active_workspace_id) {
            Cache::forget("ws_role:{$user->id}:{$user->active_workspace_id}");
        }
    }
}
