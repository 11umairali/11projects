<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DripSequence extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'name',
        'status',
    ];

    // Relationships

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(DripStep::class, 'sequence_id')->orderBy('position');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(DripEnrollment::class, 'sequence_id');
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
