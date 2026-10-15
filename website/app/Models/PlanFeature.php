<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    protected $fillable = [
        'plan_id',
        'feature_key',
        'enabled',
        'limit',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'limit' => 'integer',
        ];
    }

    // Relationships

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // Helpers

    public function isUnlimited(): bool
    {
        return $this->enabled && $this->limit === null;
    }
}
