<?php

namespace App\Services\Friends;

use App\Models\FriendRequest;
use App\Models\User;
use App\Notifications\FriendNotification;
use App\Support\PhoneKey;
use App\Support\PhoneSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Friends by phone number – with privacy built in:
 *  • only VERIFIED numbers of people who switched discovery ON can be matched;
 *  • a person's own address book (contacts of workspaces THEY own) is the only thing searched;
 *  • a request can only be sent to someone who appears in the sender's suggestions;
 *  • suggestions show name, photo and username – the e-mail address is revealed only once a request is accepted.
 */
class FriendService
{
    public function __construct(private readonly PhoneVerifier $phones) {}

    private function avatar($u): ?string
    {
        return $u->avatar_path ? asset('storage/' . $u->avatar_path) : null;
    }

    private function person(User $viewer, $u, array $extra = []): array
    {
        return array_merge(['id' => $u->id, 'name' => $u->name, 'username' => \App\Support\Username::plain($u->username), 'chat_url' => $u->chatUrl(), 'avatar_url' => $this->avatar($u)], $extra, app(ContactNames::class)->fields($viewer, $u));
    }

    /** phone key => the name saved in THIS user's own address book */
    private function addressBook(User $u): array
    {
        $ws = DB::table('workspace_members')->where('user_id', $u->id)->where('role', 'owner')->pluck('workspace_id')->all();
        if (!$ws) return [];
        $rows = DB::table('contacts')->whereIn('workspace_id', $ws)->whereNull('deleted_at')->whereNotNull('phone')->where('phone', '!=', '')
            ->limit(20000)->get(['first_name', 'last_name', 'phone']);
        $map = [];
        foreach ($rows as $r) {
            $k = PhoneKey::of($r->phone);
            if ($k && !isset($map[$k])) $map[$k] = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
        }
        return $map;
    }

    /** every user who already has a request with $u (either direction), was dismissed, or was already told about */
    private function relatedIds(User $u): array
    {
        return FriendRequest::where('requester_id', $u->id)->whereNotIn('status', ['unfriended', 'declined'])->pluck('addressee_id')
            ->merge(FriendRequest::where('addressee_id', $u->id)->whereNotIn('status', ['unfriended', 'declined'])->pluck('requester_id'))
            ->merge(DB::table('friend_dismissals')->where('user_id', $u->id)->pluck('other_user_id'))
            ->unique()->values()->all();
    }

    public function suggestionsFor(User $u): array
    {
        $book = $this->addressBook($u);
        if (!$book) return [];
        $related = $this->relatedIds($u);
        $out = [];
        $users = User::whereIn('phone_key', array_keys($book))->where('discoverable', 1)
            ->when(!PhoneSettings::unverifiedDiscovery(), fn ($q) => $q->whereNotNull('phone_verified_at'))
            ->where('id', '!=', $u->id)->where('status', 'active')
            ->when($related, fn ($q) => $q->whereNotIn('id', $related))
            ->limit((int) config('friends.max_suggestions', 50))->get();
        foreach ($users as $v) {
            if (!$this->phones->canDiscover($v)) continue;
            $saved = $book[$v->phone_key] ?? null;
            $out[] = $this->person($u, $v, ['saved_as' => $saved !== '' ? $saved : null, 'verified' => $this->phones->isVerified($v)]);
        }
        return $out;
    }

    /**
     * People who are on the platform AND allowed discovery, found from a list of phone keys (last 10 digits of every number in
     * the person's PHONE contacts, sent by the app). Nothing from the list is stored. The matches are remembered for an hour so
     * that a friend request to one of them is allowed.
     */
    public function suggestionsForKeys(User $u, array $keys): array
    {
        $keys = array_values(array_unique(array_filter(array_map(fn ($k) => preg_match('/^\d{8,10}$/', (string) $k) ? (string) $k : null, $keys))));
        $keys = array_slice($keys, 0, 3000);
        if (!$keys) return [];
        $related = $this->relatedIds($u);
        $users = User::whereIn('phone_key', $keys)->where('discoverable', 1)
            ->when(!PhoneSettings::unverifiedDiscovery(), fn ($q) => $q->whereNotNull('phone_verified_at'))
            ->where('id', '!=', $u->id)->where('status', 'active')
            ->when($related, fn ($q) => $q->whereNotIn('id', $related))
            ->limit((int) config('friends.max_suggestions', 50))->get();
        $out = [];
        foreach ($users as $v) {
            if (!$this->phones->canDiscover($v)) continue;
            $out[] = $this->person($u, $v, ['saved_as' => null, 'verified' => $this->phones->isVerified($v), 'source' => 'phone']);
        }
        Cache::put("friend_candidates:{$u->id}", array_values(array_unique(array_merge((array) Cache::get("friend_candidates:{$u->id}", []), array_column($out, 'id')))), now()->addHour());
        return $out;
    }

    /** Complete device matches, including existing relationships, without exposing hidden accounts. */
    public function contactsForKeys(User $u, array $keys): array
    {
        $relations = FriendRequest::where(fn ($q) => $q->where('requester_id', $u->id)->orWhere('addressee_id', $u->id))->get()
            ->keyBy(fn ($r) => (int) $r->requester_id === (int) $u->id ? $r->addressee_id : $r->requester_id);
        $users = User::whereIn('phone_key', $keys)->where('discoverable', 1)
            ->when(!PhoneSettings::unverifiedDiscovery(), fn ($q) => $q->whereNotNull('phone_verified_at'))
            ->where('id', '!=', $u->id)->where('status', 'active')->get();
        $out = [];
        foreach ($users as $v) {
            if (!$this->phones->canDiscover($v)) continue;
            $relation = $relations->get($v->id);
            if ($relation && $relation->status === 'blocked') continue;
            $out[] = $this->person($u, $v, [
                'phone_key' => $v->phone_key, 'relation' => $relation?->status ?? 'none',
                'request_id' => $relation?->id,
            ]);
        }
        Cache::put("friend_candidates:{$u->id}", array_values(array_unique(array_merge((array) Cache::get("friend_candidates:{$u->id}", []), array_column($out, 'id')))), now()->addHour());
        return $out;
    }

    /** Accepted friends only, including friends without any conversation yet. */
    public function chatContacts(User $viewer): array
    {
        $friends = FriendRequest::with(['requester', 'addressee'])->where('status', 'accepted')
            ->where(fn ($q) => $q->where('requester_id', $viewer->id)->orWhere('addressee_id', $viewer->id))->get()
            ->map(function ($request) use ($viewer) {
                $person = (int) $request->requester_id === (int) $viewer->id ? $request->addressee : $request->requester;
                return $person && $person->status === 'active' ? $this->person($viewer, $person) : null;
            })->filter()->unique('id')->values()->all();
        usort($friends, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']) ?: $a['id'] <=> $b['id']);
        return $friends;
    }

    public function overview(User $u): array
    {
        $incoming = FriendRequest::with('requester')->where('addressee_id', $u->id)->where('status', 'pending')->latest()->get()
            ->filter(fn ($r) => $r->requester)->map(fn ($r) => $this->person($u, $r->requester, ['request_id' => $r->id, 'when' => $r->created_at?->diffForHumans()]))->values()->all();
        $outgoing = FriendRequest::with('addressee')->where('requester_id', $u->id)->where('status', 'pending')->latest()->get()
            ->filter(fn ($r) => $r->addressee)->map(fn ($r) => $this->person($u, $r->addressee, ['request_id' => $r->id, 'when' => $r->created_at?->diffForHumans()]))->values()->all();
        $unread = class_exists(FriendChatService::class) ? app(FriendChatService::class)->unreadCounts($u) : [];
        $friends = FriendRequest::with(['requester', 'addressee'])->where('status', 'accepted')
            ->where(fn ($q) => $q->where('requester_id', $u->id)->orWhere('addressee_id', $u->id))->latest('responded_at')->get()
            ->map(function ($r) use ($u, $unread) {
                $o = $r->requester_id === $u->id ? $r->addressee : $r->requester;
                return $o ? $this->person($u, $o, ['email' => $o->email, 'since' => $r->responded_at?->diffForHumans(), 'unread' => $unread[$o->id] ?? 0]) : null;
            })->filter()->values()->all();

        // people you were friends with: the chat stays readable, the dates stay visible
        $former = FriendRequest::with(['requester', 'addressee'])->where('status', '!=', 'accepted')->where(fn ($q) => $q->where('status', 'unfriended')->orWhereNotNull('unfriended_at'))
            ->where(fn ($q) => $q->where('requester_id', $u->id)->orWhere('addressee_id', $u->id))->latest('unfriended_at')->get()
            ->map(function ($r) use ($u, $unread) {
                $o = $r->requester_id === $u->id ? $r->addressee : $r->requester;
                return $o ? $this->person($u, $o, [
                    'since' => $r->responded_at?->format('M j, Y'), 'ended' => $r->unfriended_at ? \Carbon\Carbon::parse($r->unfriended_at)->format('M j, Y') : null,
                    'ended_at' => $r->unfriended_at ? \Carbon\Carbon::parse($r->unfriended_at)->toIso8601String() : null,
                    'unfriended_by_me' => (int) $r->unfriended_by === $u->id, 'unread' => $unread[$o->id] ?? 0,
                ]) : null;
            })->filter()->values()->all();

        return ['suggestions' => $this->suggestionsFor($u), 'incoming' => $incoming, 'outgoing' => $outgoing, 'friends' => $friends, 'former' => $former];
    }

    /** @return array{0:bool,1:string} */
    public function send(User $from, int $toId): array
    {
        $limiter = "friend-send:{$from->id}";
        if (RateLimiter::tooManyAttempts($limiter, 30)) return [false, 'You have sent many requests today. Please try again tomorrow.'];
        if ($toId === $from->id) return [false, 'You cannot add yourself.'];
        $to = User::where('status', 'active')->find($toId);
        if (!$to) return [false, 'This person is not available.'];

        $existing = FriendRequest::where(fn ($q) => $q->where('requester_id', $from->id)->where('addressee_id', $toId))
            ->orWhere(fn ($q) => $q->where('requester_id', $toId)->where('addressee_id', $from->id))->first();
        $former = null;
        if ($existing) {
            if ($existing->status === 'accepted') return [false, 'You are already friends.'];
            if ($existing->status === 'pending' && (int) $existing->addressee_id === $from->id) return $this->respond($from, $existing->id, 'accept'); // they asked first
            if ($existing->status === 'pending') return [false, 'Your request is already waiting for an answer.'];
            if (in_array($existing->status, ['unfriended', 'declined'], true)) $former = $existing;          // former friends may ask again
            else return [false, 'This request cannot be sent.']; // blocked: no details on purpose
        }
        $known = array_column($this->suggestionsFor($from), 'id');
        $searched = Cache::has("fsearch:{$from->id}:{$toId}");               // the person was just found with the search box
        if (!$former && !$searched && !in_array($toId, $known, true) && !in_array($toId, (array) Cache::get("friend_candidates:{$from->id}", []), true)) return [false, 'This person is not in your suggestions.'];

        RateLimiter::hit($limiter, 86400);
        if ($former) {
            $former->update(['requester_id' => $from->id, 'addressee_id' => $toId, 'status' => 'pending']);
        } else {
            FriendRequest::create(['requester_id' => $from->id, 'addressee_id' => $toId, 'status' => 'pending']);
        }
        $this->event($from->id, $toId, $from->id, 'requested');
        $to->notify(FriendNotification::request($from->name, $from->avatar_path ? asset('storage/'.$from->avatar_path) : ''));
        return [true, 'Friend request sent.'];
    }

    /** @return array{0:bool,1:string} */
    public function respond(User $u, int $id, string $action): array
    {
        $r = FriendRequest::where('id', $id)->where('addressee_id', $u->id)->where('status', 'pending')->first();
        if (!$r) return [false, 'This request is no longer available.'];
        if ($action === 'accept') {
            $r->update(['status' => 'accepted', 'responded_at' => now(), 'unfriended_at' => null, 'unfriended_by' => null]);
            $this->event($r->requester_id, $u->id, $u->id, 'became_friends');
            try { app(FriendChatService::class)->system($u->id, (int) $r->requester_id, 'Became friends'); } catch (\Throwable $e) {}
            User::find($r->requester_id)?->notify(FriendNotification::accepted($u->name, $u->avatar_path ? asset('storage/'.$u->avatar_path) : ''));
            return [true, 'You are now friends.'];
        }
        if ($action === 'decline') {
            $r->update(['status' => 'declined', 'responded_at' => now()]);
            $this->event($r->requester_id, $u->id, $u->id, 'declined');
            User::find($r->requester_id)?->notify(FriendNotification::declined($u->name, $u->avatar_path ? asset('storage/'.$u->avatar_path) : ''));
            return [true, 'Request declined.'];
        }
        if ($action === 'block') {
            $r->update(['status' => 'blocked', 'responded_at' => now()]);
            $this->event($r->requester_id, $u->id, $u->id, 'blocked');
            return [true, 'Blocked. They cannot send you another request.'];
        }
        return [false, 'Unknown action.'];
    }

    /** @return array{0:bool,1:string} */
    public function cancel(User $u, int $id): array
    {
        $r = FriendRequest::where('id', $id)->where('requester_id', $u->id)->where('status', 'pending')->first();
        // A renewed request must not erase the relationship that grants access
        // to existing messages. Only first-time pending requests are deleted.
        $n = $r && $r->unfriended_at
            ? $r->update(['status' => 'unfriended'])
            : $r?->delete();
        return $n ? [true, 'Request cancelled.'] : [false, 'This request is no longer available.'];
    }

    /**
     * Unfriend. The friendship row, every message, voice message, file and call recording STAY.
     * The chat becomes read-only (nobody can write or call) until one of you sends a new request and it is accepted.
     * @return array{0:bool,1:string}
     */
    public function remove(User $u, int $otherId): array
    {
        $r = FriendRequest::where('status', 'accepted')->where(fn ($q) => $q
            ->where(fn ($w) => $w->where('requester_id', $u->id)->where('addressee_id', $otherId))
            ->orWhere(fn ($w) => $w->where('requester_id', $otherId)->where('addressee_id', $u->id)))->first();
        if (!$r) return [false, 'Not found.'];
        $r->update(['status' => 'unfriended', 'unfriended_at' => now(), 'unfriended_by' => $u->id]);
        $this->event($u->id, $otherId, $u->id, 'unfriended');
        try { app(FriendChatService::class)->system($u->id, $otherId, 'Unfriended'); } catch (\Throwable $e) {}
        return [true, 'Friend removed. Your chat history is kept.'];
    }

    private function event(int $a, int $b, ?int $actor, string $event): void
    {
        try {
            DB::table('friendship_events')->insert(['user_low_id' => min($a, $b), 'user_high_id' => max($a, $b), 'actor_id' => $actor, 'event' => $event, 'created_at' => now()]);
        } catch (\Throwable $e) {} // history must never block the action
    }

    /**
     * The dates of this relationship, newest first – for the profile / history screen.
     * @return array<int,array{event:string,label:string,by_me:bool,at:string,date:string}>
     */
    public function history(User $me, int $otherId): array
    {
        $tz = $me->timezone ?: config('app.timezone', 'UTC');
        $rows = DB::table('friendship_events')->where('user_low_id', min($me->id, $otherId))->where('user_high_id', max($me->id, $otherId))->orderByDesc('id')->limit(100)->get();
        return $rows->map(function ($e) use ($me, $tz) {
            $byMe = (int) $e->actor_id === $me->id;
            $label = match ($e->event) {
                'requested' => $byMe ? 'You sent a friend request' : 'Friend request received',
                'became_friends' => 'Became friends',
                'unfriended' => $byMe ? 'You unfriended' : 'Unfriended you',
                'declined' => $byMe ? 'You declined the request' : 'Request declined',
                'blocked' => $byMe ? 'You blocked' : 'Blocked',
                default => (string) $e->event,
            };
            $t = \Carbon\Carbon::parse($e->created_at, 'UTC')->setTimezone($tz);
            return ['event' => $e->event, 'label' => $label, 'by_me' => $byMe, 'at' => $t->toIso8601String(), 'date' => $t->format('M j, Y · H:i')];
        })->all();
    }

    public function dismiss(User $u, int $otherId): array
    {
        DB::table('friend_dismissals')->insertOrIgnore(['user_id' => $u->id, 'other_user_id' => $otherId, 'created_at' => now()]);
        return [true, 'Hidden from your suggestions.'];
    }

    /**
     * Called when someone verified their number AND switched discovery on: tell the owners of address books that
     * contain this number ("Alex is on DahiMail"). Once per pair, never to people who already have a request or hid them.
     */
    public function onPhoneActivated(User $v): void
    {
        if (!$v->phone_key) return;
        $ws = DB::table('contacts')->whereNull('deleted_at')->whereNotNull('phone')
            ->whereRaw("RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10) = ?", [$v->phone_key])->pluck('workspace_id')->unique()->all();
        if (!$ws) return;
        $owners = DB::table('workspace_members')->whereIn('workspace_id', $ws)->where('role', 'owner')->pluck('user_id')->unique()
            ->reject(fn ($id) => (int) $id === $v->id)->take(200)->all();
        foreach (User::whereIn('id', $owners)->where('status', 'active')->get() as $owner) {
            if (in_array($v->id, $this->relatedIds($owner), true)) continue;
            $new = DB::table('friend_match_notices')->insertOrIgnore(['user_id' => $owner->id, 'other_user_id' => $v->id, 'created_at' => now()]);
            if ($new) $owner->notify(FriendNotification::suggestion($v->name, $v->avatar_path ? asset('storage/'.$v->avatar_path) : ''));
        }
    }
}
