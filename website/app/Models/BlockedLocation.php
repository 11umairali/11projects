<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class BlockedLocation extends Model
{
    protected $fillable = [
        'country_code',
        'country_name',
        'state',
        'city',
        'reason',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('blocked_locations_active'));
        static::deleted(fn () => Cache::forget('blocked_locations_active'));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all active blocked locations (cached).
     */
    public static function getActive(): \Illuminate\Support\Collection
    {
        return Cache::remember('blocked_locations_active', 300, function () {
            return static::where('is_active', true)->get();
        });
    }

    /**
     * Check if a given location is blocked.
     */
    public static function isBlocked(?string $countryCode, ?string $state = null, ?string $city = null): bool
    {
        $blocked = static::getActive();

        return $blocked->contains(function ($location) use ($countryCode, $state, $city) {
            // Country-level block
            if ($location->country_code && strtoupper($location->country_code) === strtoupper($countryCode ?? '')) {
                // If no state/city specified in rule, block entire country
                if (!$location->state && !$location->city) {
                    return true;
                }
                // State-level block
                if ($location->state && strtolower($location->state) === strtolower($state ?? '')) {
                    if (!$location->city) {
                        return true;
                    }
                    // City-level block
                    if ($location->city && strtolower($location->city) === strtolower($city ?? '')) {
                        return true;
                    }
                }
            }
            return false;
        });
    }
}
