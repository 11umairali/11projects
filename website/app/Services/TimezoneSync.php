<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the account's timezone equal to the device / browser timezone, and remembers the IP it last signed in from.
 * The timezone comes from the client (Intl on the web, the phone's setting in the app, header X-Timezone); the IP is
 * always read by the server from the request, never trusted from the client.
 */
class TimezoneSync
{
    public function valid(?string $tz): bool
    {
        if ($tz === null || $tz === '' || strlen($tz) > 50) return false;
        try { new \DateTimeZone($tz); return true; } catch (\Throwable $e) { return false; }
    }

    public function apply(User $user, ?string $tz, ?string $ip): void
    {
        $tz = $this->valid($tz) ? $tz : null;
        $stamp = ($tz ?? '-') . '|' . ($ip ?? '-');
        $key = 'tzsync:' . $user->id;
        if (Cache::get($key) === $stamp) return; // nothing changed since the last request → no database write
        Cache::put($key, $stamp, now()->addMinutes(30));

        $update = [];
        if ($tz && $user->timezone !== $tz) $update['timezone'] = $tz;
        if ($ip && $user->last_login_ip !== $ip) $update['last_login_ip'] = $ip;
        if ($update) {
            DB::table('users')->where('id', $user->id)->update($update + ['updated_at' => now()]);
            foreach ($update as $k => $v) $user->setAttribute($k, $v);
        }
    }
}
