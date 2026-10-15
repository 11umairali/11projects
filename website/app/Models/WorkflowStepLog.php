<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepLog extends Model
{
    protected $fillable = [
        'execution_id',
        'node_id',
        'status',
        'input_data',
        'output_data',
        'error_message',
        'duration_ms',
        'executed_at',
        'resume_at',
    ];

    protected function casts(): array
    {
        return [
            'input_data' => 'array',
            'output_data' => 'array',
            'duration_ms' => 'integer',
            'executed_at' => 'datetime',
            'resume_at' => 'datetime',
        ];
    }

    // Relationships

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'execution_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'node_id');
    }
}
