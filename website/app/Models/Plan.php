<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'monthly_price',
        'yearly_price',
        'description',
        'features',
        'is_active',
        'is_popular',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // Relationships

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // Helpers

    public function isFree(): bool
    {
        return $this->monthly_price <= 0 && $this->yearly_price <= 0;
    }

    public function featureLimit(string $key): ?int
    {
        if (\App\Support\BillingPolicy::isFree()) return null;
        return $this->planFeatures()
            ->where('feature_key', $key)
            ->where('enabled', true)
            ->value('limit');
    }

    public function hasFeature(string $key): bool
    {
        if (\App\Support\BillingPolicy::isFree()) return true;
        $feature = $this->planFeatures()
            ->where('feature_key', $key)
            ->first();

        // If feature exists in plan_features table, check if enabled
        if ($feature) {
            return (bool) $feature->enabled;
        }

        // If feature key not in table, paid plans get all features by default
        // Free plans only get features explicitly listed
        return !$this->isFree();
    }
}
