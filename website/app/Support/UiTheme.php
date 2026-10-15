<?php

namespace App\Support;

/** Brand accents and readable foregrounds; surfaces are deliberately independent. */
final class UiTheme
{
    public static function variables(?string $primary = null, ?string $secondary = null, ?string $accent = null): array
    {
        $brand = self::hex($primary, '#2563eb');
        $hover = self::mix($brand, '#000000', 0.12);

        return [
            '--color-brand' => $brand,
            '--color-brand-strong' => $hover,
            '--color-primary-600' => $brand,
            '--color-secondary-600' => self::hex($secondary, '#475569'),
            '--color-accent' => self::hex($accent, '#64748b'),
            '--color-on-brand' => self::on($brand),
            '--color-on-brand-hover' => self::on($hover),
            '--color-link' => self::readable($brand, '#ffffff'),
            '--color-link-dark' => self::readable($brand, '#181f29'),
            '--color-lp-brand' => $brand,
            '--color-lp-brand-hover' => $hover,
        ];
    }

    private static function hex(?string $value, string $fallback): string
    {
        if (preg_match('/^#[a-f0-9]{6}$/iD', $value ?? '')) {
            return strtolower($value);
        }
        if (preg_match('/^#([a-f0-9])([a-f0-9])([a-f0-9])$/iD', $value ?? '', $m)) {
            return strtolower('#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3]);
        }
        return $fallback;
    }

    private static function luminance(string $hex): float
    {
        $sum = 0;
        foreach ([0.2126, 0.7152, 0.0722] as $i => $weight) {
            $v = hexdec(substr($hex, 1 + $i * 2, 2)) / 255;
            $sum += $weight * ($v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4);
        }
        return $sum;
    }

    public static function contrast(string $a, string $b): float
    {
        $x = self::luminance($a);
        $y = self::luminance($b);
        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    private static function on(string $hex): string
    {
        return self::contrast($hex, '#ffffff') >= self::contrast($hex, '#000000') ? '#ffffff' : '#000000';
    }

    private static function mix(string $a, string $b, float $amount): string
    {
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $x = hexdec(substr($a, 1 + $i * 2, 2));
            $y = hexdec(substr($b, 1 + $i * 2, 2));
            $out .= sprintf('%02x', (int) round($x + ($y - $x) * $amount));
        }
        return $out;
    }

    private static function readable(string $color, string $surface): string
    {
        $target = self::luminance($surface) > 0.5 ? '#000000' : '#ffffff';
        for ($i = 0; $i < 24 && self::contrast($color, $surface) < 4.5; $i++) {
            $color = self::mix($color, $target, 0.12);
        }
        return $color;
    }
}
