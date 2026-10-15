<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignLink extends Model
{
    protected $fillable = [
        'campaign_id',
        'original_url',
        'tracking_hash',
        'clicks_count',
    ];

    protected function casts(): array
    {
        return [
            'clicks_count' => 'integer',
        ];
    }

    // Relationships

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
