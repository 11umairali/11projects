<?php

namespace App\Services\Recording;

use App\Models\SystemSetting;

/**
 * The Super Admin's recording policy: ONE active mode for all audio calls, video calls and meetings, plus what people see
 * and hear for that mode.
 *
 *   0  Off
 *   1  Automatic        – starts by itself with the call; delivered to everybody
 *   2  Any participant  – anybody starts it, it records everybody; delivered to everybody
 *   3  Unanimous        – everybody connected must accept; one "no" and nothing is recorded; delivered to everybody
 *   4  Host only        – only the host / initiator starts it; delivered to everybody
 *   5  Private copy     – anybody records for themselves; ONLY that person receives the file
 *
 * DISCLOSURE FLOOR (not configurable): while a recording runs, every participant sees at least a REC icon or a banner.
 * Recording people without any visible sign is unlawful in many countries (all-party-consent rules) and is rejected by the
 * App Store / Google Play, so the switches can hide buttons and sounds – never the fact that a recording is running.
 */
class RecordingPolicy
{
    public const MODES = [
        1 => ['name' => 'Automatic recording', 'text' => 'Starts by itself when the call / meeting starts. Everybody receives the file when it ends.'],
        2 => ['name' => 'Any participant starts it', 'text' => 'Any connected participant can start recording; it records everybody. Everybody receives the file.'],
        3 => ['name' => 'Everybody must agree', 'text' => 'A participant asks; every connected person must accept. If anybody declines nothing is recorded. Everybody receives the file.'],
        4 => ['name' => 'Host / initiator only', 'text' => 'Only the host or the person who started the call can start recording. Everybody receives the file.'],
        5 => ['name' => 'Private copy', 'text' => 'Any participant records for themselves without asking. Only that person receives the file. Others still see the recording sign (required).'],
    ];

    public const BUTTONS = ['all' => 'All participants', 'host' => 'Host / co-hosts only', 'initiator' => 'Initiator only', 'hidden' => 'Hidden'];

    /** which button choices make sense for a mode (a hidden button in a mode that needs a starter would make recording impossible) */
    public const ALLOWED_BUTTONS = [1 => ['hidden'], 2 => ['all', 'host', 'initiator'], 3 => ['all', 'host', 'initiator'], 4 => ['host', 'initiator'], 5 => ['all', 'host', 'initiator']];

    private const DEFAULTS = [
        1 => ['button' => 'hidden', 'icon' => true, 'banner' => true, 'chime' => false],
        2 => ['button' => 'all', 'icon' => true, 'banner' => true, 'chime' => true],
        3 => ['button' => 'all', 'icon' => true, 'banner' => true, 'chime' => true],
        4 => ['button' => 'host', 'icon' => true, 'banner' => true, 'chime' => true],
        5 => ['button' => 'all', 'icon' => true, 'banner' => true, 'chime' => false],
    ];

    public static function mode(): int
    {
        $m = (int) SystemSetting::get('rec_mode', '0');
        return ($m >= 1 && $m <= 5) ? $m : 0;
    }

    public static function enabled(): bool { return self::mode() > 0; }

    /** @return array{button:string,icon:bool,banner:bool,chime:bool} */
    public static function cfg(int $mode): array
    {
        $d = self::DEFAULTS[$mode] ?? self::DEFAULTS[2];
        $j = json_decode((string) SystemSetting::get("rec_cfg_{$mode}", ''), true);
        $c = is_array($j) ? array_merge($d, array_intersect_key($j, $d)) : $d;
        return self::clean($mode, $c);
    }

    /** applies the rules that cannot be switched off */
    public static function clean(int $mode, array $c): array
    {
        $allowed = self::ALLOWED_BUTTONS[$mode] ?? ['all'];
        $btn = (string) ($c['button'] ?? $allowed[0]);
        if (!in_array($btn, $allowed, true)) $btn = $allowed[0];
        $icon = (bool) ($c['icon'] ?? true);
        $banner = (bool) ($c['banner'] ?? true);
        if (!$icon && !$banner) $banner = true;               // disclosure floor
        return ['button' => $btn, 'icon' => $icon, 'banner' => $banner, 'chime' => (bool) ($c['chime'] ?? false)];
    }

    public static function consentSeconds(): int { return max(10, min(120, (int) SystemSetting::get('rec_consent_seconds', '30'))); }

    public static function maxMb(): int { return max(5, min(500, (int) SystemSetting::get('rec_max_mb', '100'))); }

    /** @param array $in the admin form: mode, consent_seconds, max_mb, cfg[1..5][button|icon|banner|chime] */
    public static function save(array $in): void
    {
        $mode = (int) ($in['mode'] ?? 0);
        SystemSetting::set('rec_mode', (string) (($mode >= 0 && $mode <= 5) ? $mode : 0), 'friends');
        SystemSetting::set('rec_consent_seconds', (string) max(10, min(120, (int) ($in['consent_seconds'] ?? 30))), 'friends');
        SystemSetting::set('rec_max_mb', (string) max(5, min(500, (int) ($in['max_mb'] ?? 100))), 'friends');
        foreach (self::MODES as $n => $_) {
            $raw = (array) ($in['cfg'][$n] ?? []);
            $c = self::clean($n, ['button' => $raw['button'] ?? null, 'icon' => !empty($raw['icon']), 'banner' => !empty($raw['banner']), 'chime' => !empty($raw['chime'])]);
            SystemSetting::set("rec_cfg_{$n}", json_encode($c), 'friends');
        }
    }
}
