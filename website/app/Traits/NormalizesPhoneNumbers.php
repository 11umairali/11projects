<?php

namespace App\Traits;

/**
 * Provides basic E.164 phone number normalization for channel services.
 *
 * E.164 format: +[country code][subscriber number], max 15 digits.
 * This normalizer strips formatting characters (spaces, dashes, parentheses, dots)
 * and ensures the result starts with '+'. It does NOT validate country codes.
 */
trait NormalizesPhoneNumbers
{
    /**
     * Normalize a phone number to E.164-like format.
     *
     * Strips spaces, dashes, parentheses, and dots, then ensures the number
     * starts with '+'. Returns the original value unchanged if it doesn't
     * look like a phone number (e.g., Slack user IDs, Telegram chat IDs).
     */
    protected function normalizePhone(string $phone): string
    {
        // Strip common formatting characters
        $cleaned = preg_replace('/[\s\-\(\)\.]+/', '', $phone);

        // If empty after cleaning, return original
        if ($cleaned === '' || $cleaned === null) {
            return $phone;
        }

        // If it starts with a digit (no +), prepend +
        // This handles numbers like "14155551234" -> "+14155551234"
        if (ctype_digit($cleaned)) {
            return '+' . $cleaned;
        }

        // If it starts with + followed by digits, it's already E.164
        if (preg_match('/^\+\d+$/', $cleaned)) {
            return $cleaned;
        }

        // Not a standard phone number (e.g., "slack:U12345", alphanumeric ID).
        // Return the cleaned version without prepending +.
        return $cleaned;
    }
}
