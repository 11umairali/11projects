<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DripStep extends Model
{
    protected $fillable = [
        'sequence_id',
        'position',
        'delay_value',
        'delay_unit',
        'action_type',
        'action_data',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'delay_value' => 'integer',
            'action_data' => 'array',
        ];
    }

    // Relationships

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(DripSequence::class, 'sequence_id');
    }

    /**
     * Get the Carbon interval for this step's delay.
     */
    public function getDelayInterval(): \DateInterval
    {
        return match ($this->delay_unit) {
            'minutes' => \Carbon\CarbonInterval::minutes($this->delay_value),
            'hours' => \Carbon\CarbonInterval::hours($this->delay_value),
            'days' => \Carbon\CarbonInterval::days($this->delay_value),
            default => \Carbon\CarbonInterval::days($this->delay_value),
        };
    }
}
