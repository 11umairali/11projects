<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = ['code', 'stripe_coupon_id', 'name', 'type', 'value', 'currency', 'max_uses', 'times_used', 'applicable_plans', 'expires_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'applicable_plans' => 'array',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'max_uses' => 'integer',
            'times_used' => 'integer',
        ];
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_uses !== null && $this->times_used >= $this->max_uses) return false;
        return true;
    }

    public function isApplicableToPlan(int $planId): bool
    {
        if (empty($this->applicable_plans)) return true; // null = all plans
        return in_array($planId, $this->applicable_plans);
    }

    public function calculateDiscount(float $price): float
    {
        if ($this->type === 'percent') {
            return round($price * ($this->value / 100), 2);
        }
        return min($this->value, $price); // fixed amount, can't exceed price
    }

    public function getDiscountedPrice(float $price): float
    {
        return max(0, $price - $this->calculateDiscount($price));
    }

    public function incrementUsage(): void
    {
        $this->increment('times_used');
    }
}
