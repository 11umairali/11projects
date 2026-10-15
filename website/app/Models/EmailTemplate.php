<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'name',
        'blocks',
        'thumbnail_path',
        'category',
        'is_default',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_default' => 'boolean',
            'usage_count' => 'integer',
        ];
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    // Scopes

    public function scopeDefaults($query)
    {
        // Same reason as forWorkspace: the global scope would filter out
        // workspace_id=NULL default templates.
        return $query->withoutGlobalScope('workspace')
            ->where('is_default', true);
    }

    public function scopeForWorkspace($query, int $workspaceId)
    {
        // The BelongsToWorkspace trait installs a global scope that restricts
        // every query to the caller's active workspace_id. Seeded/default
        // templates ship with workspace_id=NULL and is_default=true, which
        // the global scope silently strips away — so "No templates yet" even
        // when the DB has dozens. We remove the global scope here and rebuild
        // the filter to explicitly include workspace-owned templates AND the
        // global default catalog.
        return $query->withoutGlobalScope('workspace')
            ->where(function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId)
                    ->orWhere('is_default', true);
            });
    }

    // Helpers

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
