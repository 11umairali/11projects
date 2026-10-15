<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DripEnrollment extends Model
{
    protected $fillable = [
        'sequence_id',
        'contact_id',
        'current_step',
        'status',
        'exit_reason',
        'retry_count',
        'next_step_at',
        'enrolled_at',
        'completed_at',
        'exited_at',
    ];

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'retry_count' => 'integer',
            'next_step_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    // Relationships

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(DripSequence::class, 'sequence_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDue(): bool
    {
        return $this->isActive() && $this->next_step_at && $this->next_step_at->isPast();
    }
}
