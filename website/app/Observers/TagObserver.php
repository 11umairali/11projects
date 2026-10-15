<?php

namespace App\Observers;

use App\Models\Tag;
use App\Services\CacheService;

/**
 * Invalidate the workspace tag cache when any tag is created, updated, or deleted.
 *
 * Uses the surgical invalidateWorkspaceTags() instead of invalidateWorkspace()
 * so that workspace settings and AI config remain cached.
 *
 * Registered in AppServiceProvider::boot().
 */
class TagObserver
{
    /**
     * Handle the Tag "saved" event (covers both create and update).
     */
    public function saved(Tag $tag): void
    {
        CacheService::invalidateWorkspaceTags($tag->workspace_id);
    }

    /**
     * Handle the Tag "deleted" event.
     */
    public function deleted(Tag $tag): void
    {
        CacheService::invalidateWorkspaceTags($tag->workspace_id);
    }
}
