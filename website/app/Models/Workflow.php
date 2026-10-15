<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Workflow extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'created_by',
        'name',
        'description',
        'status',
        'canvas_data',
        'version',
        'executions_count',
        'webhook_token',
    ];

    protected function casts(): array
    {
        return [
            'canvas_data' => 'array',
            'version' => 'integer',
            'executions_count' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Workflow $workflow) {
            $workflow->uuid = $workflow->uuid ?? Str::uuid();
            $workflow->webhook_token = $workflow->webhook_token ?? Str::random(64);
        });
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workflowNodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class);
    }

    public function workflowExecutions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    public function workflowEdges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class);
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Rotate the webhook token for security.
     * Should be called periodically or when a token may be compromised.
     */
    public function rotateWebhookToken(): string
    {
        $this->webhook_token = Str::random(64);
        $this->save();

        return $this->webhook_token;
    }
}
