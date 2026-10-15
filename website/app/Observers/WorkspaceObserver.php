<?php

namespace App\Observers;

use App\Models\Workspace;
use App\Services\CacheService;

/**
 * Invalidate workspace-scoped caches when settings change.
 *
 * Registered in AppServiceProvider::boot().
 */
class WorkspaceObserver
{
    /**
     * Handle the Workspace "saved" event (covers both create and update).
     */
    public function saved(Workspace $workspace): void
    {
        CacheService::invalidateWorkspace($workspace->id);
    }

    /**
     * Handle the Workspace "deleted" event (including soft-delete).
     */
    public function deleted(Workspace $workspace): void
    {
        CacheService::invalidateWorkspace($workspace->id);
    }
}
