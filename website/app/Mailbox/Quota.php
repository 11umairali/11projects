<?php

namespace App\Mailbox;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Mailbox storage use against the account's limit. Measuring means asking
 * the mail server about every folder, so the result is cached briefly.
 */
class Quota
{
    public function limitBytes(User $user): int
    {
        return ($user->quota_mb ?? config('dahify.quota_mb')) * 1048576;
    }

    public function usedBytes(User $user, Mailbox $mailbox): ?int
    {
        try {
            return Cache::remember($this->key($user), now()->addMinutes(5), fn () => $mailbox->usageBytes() ?? -1) ?: 0;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{used: int, limit: int, percent: int, full: bool, nearly: bool}|null
     */
    public function summary(User $user, Mailbox $mailbox): ?array
    {
        $used = $this->usedBytes($user, $mailbox);
        if ($used === null || $used < 0) {
            return null;
        }

        $limit = max(1, $this->limitBytes($user));
        $percent = (int) min(100, floor($used / $limit * 100));

        return ['used' => $used, 'limit' => $limit, 'percent' => $percent, 'full' => $used >= $limit, 'nearly' => $percent >= 90];
    }

    public function forget(User $user): void
    {
        Cache::forget($this->key($user));
    }

    public static function human(int $bytes): string
    {
        return match (true) {
            $bytes >= 1073741824 => rtrim(rtrim(number_format($bytes / 1073741824, 1), '0'), '.').' GB',
            $bytes >= 1048576 => round($bytes / 1048576).' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' B',
        };
    }

    private function key(User $user): string
    {
        return 'mailbox-usage:'.$user->id;
    }
}
