<?php

namespace App\Services\Friends;

use App\Models\FriendRequest;
use App\Models\User;
use App\Support\PhoneKey;
use App\Support\PhoneSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Find a person to add as a friend: by username, full name, the FULL e-mail address, or a phone number.
 *  - e-mail: the whole address must be typed (nobody can browse addresses)
 *  - phone: only numbers the owner verified AND allowed for discovery (same rules as before)
 *  - username / name: from 3 characters, only people who allow it in Privacy ("Let people find me by name")
 * You can send a request only to somebody your search just showed you.
 */
class FriendFinder
{
    public function __construct(private readonly PhoneVerifier $phones, private readonly PresenceService $presence) {}

    private function avatar($u): ?string
    {
        return $u->avatar_path ? asset('storage/' . $u->avatar_path) : null;
    }

    /** @return array{results:array,hint:?string} */
    public function search(User $me, string $q): array
    {
        $usernameOnly = str_starts_with(trim($q), '@');
        $q = \App\Support\Username::plain((string) preg_replace('/\s+/', ' ', $q));
        if (mb_strlen($q) < 3) return ['results' => [], 'hint' => 'Type at least 3 characters.'];
        $limiter = "friend-find:{$me->id}";
        if (RateLimiter::tooManyAttempts($limiter, 40)) return ['results' => [], 'hint' => 'Too many searches. Wait a minute.'];
        RateLimiter::hit($limiter, 60);

        $found = []; // id => [user, via]
        if (str_contains($q, '@')) {
            $u = User::where('status', 'active')->whereRaw('LOWER(email) = ?', [mb_strtolower($q)])->first();
            if ($u) $found[$u->id] = [$u, 'email'];
        } else {
            $digits = preg_replace('/\D+/', '', $q);
            if (!$usernameOnly && preg_match('/^[+(\d][\d\s().\-]{5,}$/', $q) && strlen($digits) >= 7) {
                $key = PhoneKey::of($q);
                if ($key) {
                    $users = User::where('phone_key', $key)->where('discoverable', 1)->where('status', 'active')
                        ->when(!PhoneSettings::unverifiedDiscovery(), fn ($x) => $x->whereNotNull('phone_verified_at'))->limit(5)->get();
                    foreach ($users as $v) if ($this->phones->canDiscover($v)) $found[$v->id] = [$v, 'phone'];
                }
            }
            $s = mb_strtolower(ltrim($q, '@'));
            $like = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s);
            $hidden = [];
            try { $hidden = DB::table('user_presence')->where('findable', 0)->pluck('user_id')->all(); } catch (\Throwable $e) {}
            $users = User::where('status', 'active')
                ->where(fn ($w) => $w->whereRaw(\App\Support\Username::SQL.' = ?', [$s])->orWhereRaw(\App\Support\Username::SQL." LIKE ? ESCAPE '!'", [$like . '%'])
                    ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like . '%'])->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", ['% ' . $like . '%']))
                ->when($hidden, fn ($x) => $x->whereNotIn('id', $hidden))
                ->orderByRaw(\App\Support\Username::SQL.' = ? DESC', [$s])->orderBy('name')->limit(20)->get();
            foreach ($users as $v) $found[$v->id] ??= [$v, 'name'];
        }
        unset($found[$me->id]);
        if (!$found) return ['results' => [], 'hint' => null];

        $ids = array_keys($found);
        $reqs = FriendRequest::where(fn ($x) => $x->where('requester_id', $me->id)->whereIn('addressee_id', $ids))->orWhere(fn ($x) => $x->where('addressee_id', $me->id)->whereIn('requester_id', $ids))->get();
        $rel = [];
        foreach ($reqs as $r) {
            $other = (int) ($r->requester_id === $me->id ? $r->addressee_id : $r->requester_id);
            if ($r->status === 'accepted') $rel[$other] = ['friend', null];
            elseif ($r->status === 'pending') $rel[$other] = [$r->requester_id === $me->id ? 'sent' : 'received', $r->id];
        }
        $team = app(FriendChatService::class);
        $out = [];
        foreach ($found as $id => [$u, $via]) {
            Cache::put("fsearch:{$me->id}:{$id}", 1, 3600);
            [$relation, $reqId] = $rel[$id] ?? [($team->teammates($me->id, $id) ? 'team' : 'none'), null];
            $out[] = ['id' => (int) $id, ...app(ContactNames::class)->fields($me, $u), 'username' => $u->username, 'chat_url' => $u->chatUrl(), 'avatar_url' => $this->avatar($u), 'via' => $via, 'relation' => $relation, 'request_id' => $reqId];
        }
        return ['results' => $out, 'hint' => null];
    }

    /** true when this person's search just listed $to */
    public function canFind(User $me, User $to): bool
    {
        return Cache::has("fsearch:{$me->id}:{$to->id}");
    }
}
