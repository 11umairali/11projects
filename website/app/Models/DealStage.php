<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DealStage extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'pipeline_id',
        'name',
        'color',
        'win_probability',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'win_probability' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // Relationships

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
