<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    protected $fillable = [
        'name', 'code', 'symbol', 'symbol_position', 'decimal_separator',
        'thousand_separator', 'decimal_digits', 'exchange_rate',
        'is_active', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:6',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Format an amount in this currency.
     */
    public function format(float $amount): string
    {
        $formatted = number_format($amount, $this->decimal_digits, $this->decimal_separator, $this->thousand_separator);
        return $this->symbol_position === 'before'
            ? $this->symbol . $formatted
            : $formatted . ' ' . $this->symbol;
    }

    /**
     * Convert amount from base currency (USD) to this currency.
     */
    public function convertFromBase(float $amount): float
    {
        return round($amount * $this->exchange_rate, $this->decimal_digits);
    }

    /**
     * Convert amount from this currency to base currency (USD).
     */
    public function convertToBase(float $amount): float
    {
        if ($this->exchange_rate == 0) return $amount;
        return round($amount / $this->exchange_rate, 2);
    }

    public static function getDefault(): ?self
    {
        return Cache::remember('default_currency', 3600, fn() => static::where('is_default', true)->first());
    }

    public static function getActive(): \Illuminate\Support\Collection
    {
        return Cache::remember('active_currencies', 3600, fn() => static::where('is_active', true)->orderBy('sort_order')->get());
    }

    public static function findByCode(string $code): ?self
    {
        return Cache::remember("currency_{$code}", 3600, fn() => static::where('code', $code)->first());
    }

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('active_currencies');
            Cache::forget('default_currency');
        });
        static::deleted(function () {
            Cache::forget('active_currencies');
            Cache::forget('default_currency');
        });
    }
}
