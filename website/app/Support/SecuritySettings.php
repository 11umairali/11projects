<?php

namespace App\Support;

use App\Models\SystemSetting;

class SecuritySettings
{
    /**
     * Default security configuration values.
     */
    private static array $defaults = [
        'max_login_attempts' => 5,
        'lockout_minutes' => 1,
        'captcha_enabled' => false,
        'captcha_site_key' => '',
        'captcha_secret_key' => '',
        'two_factor_admin_only' => true,
        'two_factor_issuer' => 'MailTrixy',
        'single_session' => false,
        'password_min_length' => 10,
        'password_max_length' => 72,
        'password_require_lowercase' => true,
        'username_min_length' => 3,
        'username_max_length' => 30,
        'username_allow_uppercase' => true,
        'username_allow_numbers' => true,
        'username_allow_dots' => true,
        'username_allow_dashes' => true,
        'username_allow_underscores' => true,
        'password_require_uppercase' => true,
        'password_require_numbers' => true,
        'password_require_symbols' => true,
        'session_lifetime_minutes' => 120,
        'ip_blocking_enabled' => true,
        'location_blocking_enabled' => false,
    ];

    /**
     * Get all security settings merged with defaults.
     */
    public static function get(): array
    {
        $stored = [];
        foreach (self::$defaults as $key => $default) {
            $stored[$key] = SystemSetting::get("security.{$key}", $default);
        }
        return $stored;
    }

    /**
     * Get a specific security setting value.
     */
    public static function getValue(string $key, $default = null)
    {
        return SystemSetting::get("security.{$key}", $default ?? (self::$defaults[$key] ?? null));
    }

    /**
     * Set a security setting value.
     */
    public static function setValue(string $key, $value): void
    {
        SystemSetting::set("security.{$key}", $value);
    }

    /**
     * Update multiple security settings at once.
     */
    public static function update(array $settings): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($settings) {
            foreach ($settings as $key => $value) {
                if (array_key_exists($key, self::$defaults)) static::setValue($key, $value);
            }
        });
        SystemSetting::clearCache();
    }
}
