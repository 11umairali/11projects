<?php

namespace App\Support;

/** Compares phone numbers however they were typed: 0300 1234567, +92 300 1234567 and 00923001234567 share one key. */
class PhoneKey
{
    public static function of(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 8) return null;      // too short to identify anybody
        return substr($digits, -10);
    }
}
