<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Super-admin controls for phone numbers (Admin → Phone Verification). Stored in system_settings, group "phone".
 * Defaults are the SAFE ones: phone is optional at sign-up and verification is OFF until a super admin turns it on.
 */
class PhoneSettings
{
    private static function on(string $key, string $default): bool
    {
        return SystemSetting::get($key, $default) === '1';
    }

    /** off = no phone field at sign-up · optional · required */
    public static function registrationMode(): string
    {
        $m = SystemSetting::get('phone_registration', 'optional');
        return in_array($m, ['off', 'optional', 'required'], true) ? $m : 'optional';
    }

    public static function verificationEnabled(): bool { return self::on('phone_verification_enabled', '0'); }
    public static function smsEnabled(): bool { return self::on('phone_verify_sms', '1'); }
    public static function whatsappEnabled(): bool { return self::on('phone_verify_whatsapp', '0'); }

    /**
     * OFF by default. When ON, a person can be found by phone number even though the number was NOT verified by a code.
     * Friends then see a "number not verified" note. Risk: someone can claim another person's number.
     */
    public static function unverifiedDiscovery(): bool { return self::on('phone_discovery_unverified', '0'); }

    /** Can friend discovery work at all? (numbers are verified by code, or the admin allows unverified numbers) */
    public static function discoveryAvailable(): bool { return self::channels() !== [] || self::unverifiedDiscovery(); }

    /** Twilio (Admin → Settings → Integrations) */
    public static function smsConfigured(): bool
    {
        return SystemSetting::get('twilio_sid') !== '' && SystemSetting::get('twilio_auth_token') !== '' && SystemSetting::get('twilio_phone_number') !== '';
    }

    /** WhatsApp Business Cloud API (Admin → Settings → Integrations) */
    public static function whatsappConfigured(): bool
    {
        return SystemSetting::get('whatsapp_phone_number_id') !== '' && SystemSetting::get('whatsapp_access_token') !== '';
    }

    /** TESTING ONLY: FRIENDS_SMS_DRIVER=log in .env writes codes to the log instead of sending them */
    public static function testMode(): bool { return config('friends.sms_driver') === 'log'; }

    /** Channels a user can actually receive a code on right now. Empty = verification is not available. */
    public static function channels(): array
    {
        if (!self::verificationEnabled()) return [];
        $c = [];
        if (self::smsEnabled() && (self::smsConfigured() || self::testMode())) $c[] = 'sms';
        if (self::whatsappEnabled() && (self::whatsappConfigured() || self::testMode())) $c[] = 'whatsapp';
        return $c;
    }
}
