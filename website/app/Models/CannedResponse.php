<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CannedResponse extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'title',
        'shortcut',
        'content',
        'category',
        'channels',
        'scope',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'usage_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: fetch canned responses visible to a specific user in a workspace.
     * Team-scoped responses are visible to everyone; personal ones only to the owner.
     */
    public function scopeForWorkspace($query, int $workspaceId, int $userId)
    {
        return $query->where('workspace_id', $workspaceId)
            ->where(function ($q) use ($userId) {
                $q->where('scope', 'team')
                    ->orWhere(function ($q2) use ($userId) {
                        $q2->where('scope', 'personal')
                            ->where('user_id', $userId);
                    });
            });
    }
}
