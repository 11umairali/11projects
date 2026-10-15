<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToWorkspace
{
    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder) {
            if (auth()->check() && auth()->user()->active_workspace_id) {
                $builder->where($builder->getModel()->getTable() . '.workspace_id', auth()->user()->active_workspace_id);
            }
        });

        static::creating(function ($model) {
            if (!$model->workspace_id && auth()->check() && auth()->user()->active_workspace_id) {
                $model->workspace_id = auth()->user()->active_workspace_id;
            }
        });
    }
}
