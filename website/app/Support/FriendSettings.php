<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;

/** Super-admin controls for friend chat, file sharing and audio calls (Admin → Phone & Friends). */
class FriendSettings
{
    private static function on(string $key, string $default = '1'): bool
    {
        return SystemSetting::get($key, $default) === '1';
    }

    public static function chatEnabled(): bool { return self::on('friends_chat_enabled'); }
    public static function filesEnabled(): bool { return self::chatEnabled() && self::on('friends_files_enabled'); }
    public static function callsEnabled(): bool { return self::on('friends_calls_enabled'); }

    /** super-admin switch (Admin → Phone & Friends). OFF by default: read the legal note in INSTALL_STEP1.md first. */
    public static function callRecordingEnabled(): bool { return self::callsEnabled() && \App\Services\Recording\RecordingPolicy::enabled(); }

    public static function callRecordingMaxMb(): int
    {
        return max(5, min(500, (int) SystemSetting::get('friends_call_recording_max_mb', '100')));
    }

    public static function fileMaxMb(): int
    {
        return max(1, min(50, (int) SystemSetting::get('friends_file_max_mb', '10')));
    }

    public static function features(): array
    {
        return ['chat' => self::chatEnabled(), 'files' => self::filesEnabled(), 'calls' => self::callsEnabled(), 'max_mb' => self::fileMaxMb(), 'call_recording' => self::callRecordingEnabled()];
    }

    /**
     * STUN finds a phone's public address; TURN relays the audio when a direct connection is impossible (strict networks,
     * many mobile carriers). Without a TURN server some calls cannot connect.
     */
    public static function iceServers(): array
    {
        $stun = trim(SystemSetting::get('friends_stun_url', 'stun:stun.l.google.com:19302')) ?: 'stun:stun.l.google.com:19302';
        $servers = [['urls' => $stun]];
        $turn = trim(SystemSetting::get('friends_turn_url'));
        if ($turn !== '') {
            $servers[] = [
                'urls' => array_values(array_filter(array_map('trim', explode(',', $turn)))),
                'username' => SystemSetting::get('friends_turn_user'),
                'credential' => self::turnPassword(),
            ];
        }
        return $servers;
    }

    public static function turnPassword(): string
    {
        $raw = SystemSetting::get('friends_turn_pass');
        if ($raw === '') return '';
        try { return Crypt::decryptString($raw); } catch (\Throwable $e) { return ''; }
    }

    public static function saveTurnPassword(string $plain): void
    {
        SystemSetting::set('friends_turn_pass', $plain === '' ? '' : Crypt::encryptString($plain), 'friends');
    }
}
