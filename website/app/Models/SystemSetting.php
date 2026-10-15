<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = ['key', 'value', 'group'];

    /**
     * Keys whose values are stored encrypted at rest.
     */
    public const ENCRYPTED_KEYS = [
        'openai_api_key', 'anthropic_api_key', 'gemini_api_key', 'mistral_api_key',
        'pinecone_api_key', 'smtp_password',
        'google_client_secret', 'slack_client_secret', 'slack_signing_secret',
        'stripe_secret', 'stripe_webhook_secret',
        'twilio_auth_token', 'whatsapp_access_token', 'whatsapp_verify_token',
        'salesforce_client_secret', 'hubspot_client_secret',
        'microsoft_client_secret', 'github_client_secret',
        'pusher_secret', 'telegram_bot_token',
        'hcaptcha_secret_key',
    ];

    /**
     * Retrieve a setting value with optional default.
     * Results are cached for 60 seconds to avoid repeated DB hits.
     */
    public static function get(string $key, mixed $default = ''): string
    {
        $all = Cache::remember('system_settings_all', 60, function () {
            try {
                return DB::table('system_settings')->pluck('value', 'key')->toArray();
            } catch (\Exception) {
                return [];
            }
        });

        $value = $all[$key] ?? $default;

        // Decrypt encrypted values
        if (in_array($key, static::ENCRYPTED_KEYS) && !empty($value) && $value !== $default) {
            try {
                $value = decrypt($value);
            } catch (\Exception) {
                // Value may not be encrypted yet (legacy), return as-is
            }
        }

        return (string) $value;
    }

    /**
     * Persist a setting. Sensitive values are encrypted automatically.
     */
    public static function set(string $key, ?string $value, string $group = 'general'): void
    {
        $storeValue = $value ?? '';

        if (in_array($key, static::ENCRYPTED_KEYS) && $storeValue !== '') {
            $storeValue = encrypt($storeValue);
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $storeValue, 'group' => $group, 'updated_at' => now()]
        );

        Cache::forget('system_settings_all');
    }

    /**
     * Check if a boolean-style setting is truthy.
     */
    public static function enabled(string $key, bool $default = false): bool
    {
        $val = static::get($key, $default ? 'true' : 'false');
        return in_array(strtolower($val), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Flush the settings cache (call after bulk updates).
     */
    public static function clearCache(): void
    {
        Cache::forget('system_settings_all');
    }
}
