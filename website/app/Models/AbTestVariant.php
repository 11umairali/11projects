<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbTestVariant extends Model
{
    protected $fillable = [
        'campaign_id',
        'variant',
        'subject',
        'body_html',
        'percentage',
        'is_winner',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'integer',
            'is_winner' => 'boolean',
        ];
    }

    // Relationships

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
