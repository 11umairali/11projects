<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowExecution extends Model
{
    protected $fillable = [
        'workflow_id',
        'contact_id',
        'status',
        'trigger_data',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_data' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Relationships

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(WorkflowStepLog::class, 'execution_id');
    }

    /**
     * Get the currently waiting step log (for resumable executions).
     */
    public function waitingStepLog(): ?WorkflowStepLog
    {
        return $this->stepLogs()
            ->where('status', 'waiting')
            ->latest()
            ->first();
    }

    /**
     * Get the last completed node ID for this execution.
     */
    public function lastCompletedNodeId(): ?int
    {
        return $this->stepLogs()
            ->where('status', 'success')
            ->latest()
            ->value('node_id');
    }
}
