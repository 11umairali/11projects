<?php
namespace App\Helpers;

use App\Models\Currency;

class CurrencyHelper
{
    /**
     * Format amount in user's preferred currency.
     */
    public static function format(float $amount, ?string $currencyCode = null): string
    {
        $code = $currencyCode ?? auth()->user()?->currency_code ?? 'USD';
        $currency = Currency::findByCode($code);

        if (!$currency) {
            return '$' . number_format($amount, 2);
        }

        return $currency->format($amount);
    }

    /**
     * Convert amount from base (USD) to target currency.
     */
    public static function convert(float $amount, ?string $toCurrencyCode = null): float
    {
        $code = $toCurrencyCode ?? auth()->user()?->currency_code ?? 'USD';
        $currency = Currency::findByCode($code);

        if (!$currency) return $amount;

        return $currency->convertFromBase($amount);
    }

    /**
     * Format with conversion: convert from USD and display in target currency.
     */
    public static function display(float $amountInUsd, ?string $currencyCode = null): string
    {
        $code = $currencyCode ?? auth()->user()?->currency_code ?? 'USD';
        $currency = Currency::findByCode($code);

        if (!$currency) return '$' . number_format($amountInUsd, 2);

        $converted = $currency->convertFromBase($amountInUsd);
        return $currency->format($converted);
    }
}
