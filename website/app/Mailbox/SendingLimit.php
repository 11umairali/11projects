<?php

namespace App\Mailbox;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Outgoing e-mail limits, ALL editable by an admin (Admin → Sending limits). Every recipient (To, Cc, Bcc) counts once.
 *   per account: per hour · per day (new / established / trusted account) · per week · per year
 *   per IP address and per device: per day (all accounts together)
 * 0 = no limit for that rule. The user is only told "limit reached" – never the numbers.
 */
class SendingLimit
{
    public const KEYS = [
        'send_new_days' => 7, 'send_established_days' => 30,
        'send_new_daily' => 20, 'send_established_daily' => 100, 'send_trusted_daily' => 200,
        'send_hourly' => 0, 'send_weekly' => 0, 'send_yearly' => 0,
        'send_ip_daily' => 0, 'send_device_daily' => 0,
    ];

    public static function value(string $key): int
    {
        $d = self::KEYS[$key] ?? 0;
        $cfg = ['send_new_daily' => 'new_account_daily', 'send_established_daily' => 'established_daily', 'send_trusted_daily' => 'trusted_daily'];
        if (isset($cfg[$key])) $d = (int) config('dahify.sending.' . $cfg[$key], $d);
        $v = SystemSetting::get($key, '');
        return $v === '' || $v === null ? (int) $d : max(0, (int) $v);
    }

    /** the browser (a long-lived cookie) or the app install (X-Device-Id header); null = unknown, then the device rule is skipped */
    public static function deviceId(): ?string
    {
        try {
            $r = request();
            $id = $r->header('X-Device-Id') ?: $r->cookie('dahi_dev');
            if (!$id && $r->hasSession() && !str_starts_with((string) $r->userAgent(), 'Dart/')) {
                $id = bin2hex(random_bytes(16));
                \Illuminate\Support\Facades\Cookie::queue('dahi_dev', $id, 60 * 24 * 730);
            }
            return $id ? substr(sha1((string) $id), 0, 40) : null;
        } catch (\Throwable $e) { return null; }
    }

    public function dailyLimit(User $user): int
    {
        if ($user->daily_send_limit !== null) return (int) $user->daily_send_limit; // a limit set on this one user wins
        $days = $user->created_at?->diffInDays(now()) ?? 0;
        return match (true) {
            $days < self::value('send_new_days') => self::value('send_new_daily'),
            $days < self::value('send_established_days') => self::value('send_established_daily'),
            default => self::value('send_trusted_daily'),
        };
    }

    private function sum(string $col, $val, $since): int
    {
        if ($col === 'device_hash' && !Schema::hasColumn('sent_messages', 'device_hash')) return 0;
        return (int) DB::table('sent_messages')->where($col, $val)->where('created_at', '>=', $since)->sum('recipients');
    }

    public function usedToday(User $user): int { return $this->sum('user_id', $user->id, now()->subDay()); }

    public function remaining(User $user): int { return max(0, $this->dailyLimit($user) - $this->usedToday($user)); }

    /** @return string|null  null = allowed, otherwise the message to show (without numbers) */
    public function check(User $user, int $recipients, ?string $ip): ?string
    {
        $dev = self::deviceId();
        $rules = [
            ['account, day', $this->dailyLimit($user), fn () => $this->usedToday($user)],
            ['account, hour', self::value('send_hourly'), fn () => $this->sum('user_id', $user->id, now()->subHour())],
            ['account, week', self::value('send_weekly'), fn () => $this->sum('user_id', $user->id, now()->subWeek())],
            ['account, year', self::value('send_yearly'), fn () => $this->sum('user_id', $user->id, now()->subYear())],
            ['IP, day', self::value('send_ip_daily'), fn () => $ip ? $this->sum('ip_address', $ip, now()->subDay()) : 0],
            ['device, day', self::value('send_device_daily'), fn () => $dev ? $this->sum('device_hash', $dev, now()->subDay()) : 0],
        ];
        foreach ($rules as [$name, $limit, $used]) {
            if ($name !== 'account, day' && $limit <= 0) continue;
            if ($recipients + $used() > $limit) {
                Log::notice('Sending limit hit', ['rule' => $name, 'limit' => $limit, 'user' => $user->id, 'ip' => $ip]);
                return 'You cannot send this message right now because a sending limit was reached. Please try again later or contact support.';
            }
        }
        return null;
    }

    public function record(User $user, int $recipients, ?string $ip): void
    {
        $row = ['user_id' => $user->id, 'recipients' => $recipients, 'ip_address' => $ip, 'created_at' => now()];
        if (Schema::hasColumn('sent_messages', 'device_hash')) $row['device_hash'] = self::deviceId();
        DB::table('sent_messages')->insert($row);
    }
}
