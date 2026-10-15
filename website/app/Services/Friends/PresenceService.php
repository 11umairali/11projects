<?php

namespace App\Services\Friends;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Online / offline and "last seen" for friends and teammates.
 * Same rule as WhatsApp: if YOU hide your status you cannot see anybody else's either, and nobody sees yours.
 * "Online" = the app or website was open in the last 70 seconds (they send a heartbeat with the call/alert poll).
 */
class PresenceService
{
    private const ONLINE_SECONDS = 70;

    public function touch(int $userId): void
    {
        if (Cache::get("presence:$userId")) return; // at most one write every 20 s
        Cache::put("presence:$userId", 1, 20);
        $n = DB::table('user_presence')->where('user_id', $userId)->update(['last_seen_at' => now()]);
        if (!$n) DB::table('user_presence')->insertOrIgnore(['user_id' => $userId, 'last_seen_at' => now(), 'show_presence' => 1]);
    }

    public function enabled(int $userId): bool
    {
        $v = DB::table('user_presence')->where('user_id', $userId)->value('show_presence');
        return $v === null ? true : (bool) $v;
    }

    public function setEnabled(int $userId, bool $on): void
    {
        $n = DB::table('user_presence')->where('user_id', $userId)->update(['show_presence' => $on ? 1 : 0]);
        if (!$n) DB::table('user_presence')->insertOrIgnore(['user_id' => $userId, 'last_seen_at' => now(), 'show_presence' => $on ? 1 : 0]);
    }

    /** may people find this person by name / username? (exact e-mail and verified phone number follow their own rules) */
    public function findable(int $userId): bool
    {
        try { $v = DB::table('user_presence')->where('user_id', $userId)->value('findable'); } catch (\Throwable $e) { return true; }
        return $v === null ? true : (bool) $v;
    }

    public function setFindable(int $userId, bool $on): void
    {
        $n = DB::table('user_presence')->where('user_id', $userId)->update(['findable' => $on ? 1 : 0]);
        if (!$n) DB::table('user_presence')->insertOrIgnore(['user_id' => $userId, 'last_seen_at' => now(), 'show_presence' => 1, 'findable' => $on ? 1 : 0]);
    }

    public function receiptsEnabled(int $userId): bool
    {
        try { $v = DB::table('user_presence')->where('user_id', $userId)->value('read_receipts'); } catch (\Throwable $e) { return true; }
        return $v === null ? true : (bool) $v;
    }

    public function setReceipts(int $userId, bool $on): void
    {
        $n = DB::table('user_presence')->where('user_id', $userId)->update(['read_receipts' => $on ? 1 : 0]);
        if (!$n) DB::table('user_presence')->insertOrIgnore(['user_id' => $userId, 'last_seen_at' => now(), 'show_presence' => 1, 'read_receipts' => $on ? 1 : 0]);
    }

    /** @param int[] $ids @return array<int,array{online:bool,label:?string}>  (an entry is empty when it must stay hidden) */
    public function forViewer(User $viewer, array $ids): array
    {
        $out = [];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return $out;
        $iShow = $this->enabled($viewer->id);
        $rows = DB::table('user_presence')->whereIn('user_id', $ids)->get()->keyBy('user_id');
        $tz = $viewer->timezone ?: config('app.timezone', 'UTC');
        foreach ($ids as $id) {
            $r = $rows->get($id);
            $theyShow = $r ? (bool) $r->show_presence : true;
            if (!$iShow || !$theyShow || !$r || !$r->last_seen_at) { $out[$id] = ['online' => false, 'label' => null]; continue; }
            $t = Carbon::parse($r->last_seen_at, 'UTC')->setTimezone($tz);
            if ($t->diffInSeconds(now()) <= self::ONLINE_SECONDS) { $out[$id] = ['online' => true, 'label' => 'Online']; continue; }
            $now = Carbon::now($tz);
            $label = $t->isSameDay($now) ? 'Last seen today at ' . $t->format('H:i')
                : ($t->isSameDay($now->copy()->subDay()) ? 'Last seen yesterday at ' . $t->format('H:i')
                : ($t->diffInDays($now) < 7 ? 'Last seen ' . $t->format('l') . ' at ' . $t->format('H:i') : 'Last seen ' . $t->format('M j')));
            $out[$id] = ['online' => false, 'label' => $label];
        }
        return $out;
    }
}
