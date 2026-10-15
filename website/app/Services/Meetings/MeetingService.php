<?php

namespace App\Services\Meetings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\MeetingNotification;
use App\Services\Friends\FriendChatService;
use App\Support\FriendSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Built-in video meetings (no third-party service): rooms with a link, lobby / waiting room, host controls, chat, hand raising and
 * screen sharing. Media goes directly between the participants (WebRTC, one connection per pair); the server only carries the
 * signalling (a short poll) and the room state. Also powers 1:1 video calls (kind "call").
 */
class MeetingService
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private const STALE_SECONDS = 30; // nobody has heard from this participant for that long → they left

    public function __construct(private readonly FriendChatService $chat) {}

    // ───────────────────────── codes and small helpers ─────────────────────────

    public static function normalize(string $code): string { return strtolower(preg_replace('/[^a-z0-9]/i', '', $code)); }

    public static function pretty(string $code): string { return strlen($code) === 10 ? substr($code, 0, 3) . '-' . substr($code, 3, 4) . '-' . substr($code, 7) : $code; }

    private function newCode(): string
    {
        do {
            $c = '';
            for ($i = 0; $i < 10; $i++) $c .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        } while (DB::table('meetings')->where('code', $c)->exists());
        return $c;
    }

    public function enabled(): bool { return SystemSetting::get('friends_meetings_enabled', '1') === '1'; }

    /** a mesh of direct connections works well up to about 6–8 people */
    public function max(): int { return max(2, min(25, (int) SystemSetting::get('friends_meeting_max', '8'))); }

    public function find(string $code): ?object { return DB::table('meetings')->where('code', self::normalize($code))->first(); }

    private function tz(?User $u): string { return ($u && $u->timezone) ? $u->timezone : config('app.timezone', 'UTC'); }

    private function when(object $m, ?User $viewer): string
    {
        if (!$m->scheduled_at) return '';
        try { return Carbon::parse($m->scheduled_at, 'UTC')->setTimezone($this->tz($viewer))->format('D, M j · H:i'); } catch (\Throwable $e) { return ''; }
    }

    // ───────────────────────── creating, listing ─────────────────────────

    /** @param array{title?:string,description?:string,scheduled_at?:string,duration?:int,invitees?:array,waiting_room?:bool,allow_guests?:bool,mute_on_entry?:bool,kind?:string} $o */
    public function create(User $host, array $o): object
    {
        $scheduled = null;
        if (!empty($o['scheduled_at'])) {
            try { $scheduled = Carbon::parse($o['scheduled_at'], $this->tz($host))->setTimezone('UTC'); } catch (\Throwable $e) { $scheduled = null; }
        }
        $kind = ($o['kind'] ?? 'meeting') === 'call' ? 'call' : 'meeting';
        $id = DB::table('meetings')->insertGetId([
            'code' => $this->newCode(), 'host_id' => $host->id,
            'title' => Str::limit(trim((string) ($o['title'] ?? '')) ?: ($kind === 'call' ? 'Video call' : $host->name . "'s meeting"), 150, ''),
            'description' => !empty($o['description']) ? Str::limit(trim((string) $o['description']), 1000, '') : null,
            'scheduled_at' => $scheduled, 'duration_min' => max(15, min(480, (int) ($o['duration'] ?? 60))),
            'status' => 'scheduled', 'kind' => $kind,
            'waiting_room' => (int) ($o['waiting_room'] ?? true), 'allow_guests' => (int) ($o['allow_guests'] ?? ($kind === 'meeting')), 'mute_on_entry' => (int) ($o['mute_on_entry'] ?? false),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $m = DB::table('meetings')->find($id);
        foreach (array_unique(array_map('intval', (array) ($o['invitees'] ?? []))) as $uid) {
            if ($uid === $host->id || !$this->chat->areFriends($host->id, $uid)) continue; // friends and teammates only
            DB::table('meeting_invitees')->insertOrIgnore(['meeting_id' => $id, 'user_id' => $uid]);
            if ($kind === 'meeting') {
                try { User::find($uid)?->notify(MeetingNotification::invited($host->name, $m->title, $this->when($m, User::find($uid)), $m->code, $host->avatar_path ? asset('storage/'.$host->avatar_path) : '')); } catch (\Throwable $e) {}
            }
        }
        return $m;
    }

    /** a 1:1 video call is a meeting of kind "call" where the friend is invited and no lobby applies */
    public function startCall(User $caller, int $calleeId, bool $audio = false): object
    {
        return $this->create($caller, ['kind' => 'call', 'title' => $audio ? 'Audio call' : 'Video call', 'invitees' => [$calleeId], 'waiting_room' => false, 'allow_guests' => false]);
    }

    public function summary(object $m, ?User $viewer = null): array
    {
        $host = User::find($m->host_id);
        $names = DB::table('meeting_invitees as i')->join('users as u', 'u.id', '=', 'i.user_id')->where('i.meeting_id', $m->id)->limit(8)->pluck('u.name')->all();
        return [
            'code' => $m->code, 'pretty' => self::pretty($m->code), 'title' => $m->title, 'description' => $m->description, 'kind' => $m->kind, 'status' => $m->status,
            'host' => $host?->name, 'is_host' => $viewer && (int) $m->host_id === $viewer->id,
            'scheduled_at' => $m->scheduled_at ? Carbon::parse($m->scheduled_at, 'UTC')->toIso8601String() : null, 'when' => $this->when($m, $viewer),
            'duration' => (int) $m->duration_min, 'url' => url('/meet/' . $m->code),
            'participants' => (int) DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'joined')->count(),
            'waiting_room' => (bool) $m->waiting_room, 'allow_guests' => (bool) $m->allow_guests, 'mute_on_entry' => (bool) $m->mute_on_entry, 'invitees' => $names,
        ];
    }

    /** @return array{upcoming:array,recent:array} */
    public function listFor(User $u): array
    {
        $invited = DB::table('meeting_invitees')->where('user_id', $u->id)->pluck('meeting_id')->all();
        $q = DB::table('meetings')->where('kind', 'meeting')->where(fn ($w) => $w->where('host_id', $u->id)->orWhereIn('id', $invited));
        $utc = Carbon::now('UTC');
        $upcoming = (clone $q)->whereIn('status', ['scheduled', 'live'])
            ->where(fn ($w) => $w->where(fn ($x) => $x->whereNull('scheduled_at')->where('created_at', '>', now()->subHours(12)))->orWhere('scheduled_at', '>', (clone $utc)->subHours(3)))
            ->orderByRaw('COALESCE(scheduled_at, created_at)')->limit(50)->get();
        $recent = (clone $q)->where('status', 'ended')->where('ended_at', '>', now()->subDays(14))->orderByDesc('ended_at')->limit(20)->get();
        return ['ongoing' => $this->resumableFor($u), 'upcoming' => $upcoming->map(fn ($m) => $this->summary($m, $u))->all(), 'recent' => $recent->map(fn ($m) => $this->summary($m, $u))->all()];
    }

    private function invitationExpired(object $m): bool
    {
        if ($m->status !== 'scheduled') return false;
        if ($m->scheduled_at) return now()->gt(Carbon::parse($m->scheduled_at, 'UTC')->addMinutes((int) $m->duration_min));
        return $m->created_at && now()->gt(Carbon::parse($m->created_at, 'UTC')->addHours(12));
    }

    /** Current invitation eligibility. Historical notification text is never authority to join. */
    public function entryState(?User $user, string $code): array
    {
        $m = $this->find($code);
        $state = fn (string $status, string $label, bool $join = false) => ['status' => $status, 'label' => $label, 'can_join' => $join];
        if (!$m || !$this->enabled()) return $state('unavailable', 'Session unavailable');
        if (in_array($m->status, ['ended', 'cancelled'], true)) return $state($m->status, $m->kind === 'call' ? 'Call ended' : 'Meeting '.$m->status);
        $previous = $user ? DB::table('meeting_participants')->where('meeting_id', $m->id)->where('user_id', $user->id)->orderByDesc('id')->first() : null;
        if ($previous && in_array($previous->status, ['removed', 'denied'], true)) return $state('unavailable', 'Access no longer available');
        $creator = $user && (int) $m->host_id === $user->id;
        $host = $creator && !DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'joined')->where('role', 'host')->where('user_id', '!=', $user->id)->exists();
        $invited = $user && DB::table('meeting_invitees')->where('meeting_id', $m->id)->where('user_id', $user->id)->exists();
        if ($m->kind === 'call' && !$creator && !$invited) return $state('unavailable', 'Private call');
        if (!$user && !$m->allow_guests) return $state('unavailable', 'Sign in to join');
        if ($this->invitationExpired($m)) return $state('expired', 'Invitation expired');
        if ($m->locked && !$host && $previous?->status !== 'joined') return $state('locked', 'Meeting locked');
        if ($m->scheduled_at && !$host && now()->lt(Carbon::parse($m->scheduled_at, 'UTC')->subMinutes(15))) return $state('scheduled', 'Scheduled · not open yet');
        if ($m->status === 'live' && !DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'joined')->where('last_seen_at', '>=', now()->subSeconds(self::STALE_SECONDS))->exists()) return $state('unavailable', 'No active session');
        return $state($m->status, $m->status === 'live' ? 'In progress' : 'Ready to join', true);
    }

    /** Active memberships and invitations, independent of notification dismissal. */
    public function resumableFor(User $u): array
    {
        if (!$this->enabled()) return [];
        $rows = DB::table('meetings as m')
            ->leftJoin('meeting_participants as p', fn ($join) => $join->on('p.meeting_id', '=', 'm.id')->where('p.user_id', $u->id))
            ->where('m.status', 'live')
            ->where(fn ($q) => $q->whereIn('p.status', ['joined', 'left', 'waiting'])->orWhere(fn ($new) => $new->whereNull('p.id')
                ->whereExists(fn ($invite) => $invite->selectRaw('1')->from('meeting_invitees as i')->whereColumn('i.meeting_id', 'm.id')->where('i.user_id', $u->id))))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('meeting_participants as newer')->whereColumn('newer.meeting_id', 'p.meeting_id')->whereColumn('newer.user_id', 'p.user_id')->whereColumn('newer.id', '>', 'p.id'))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('meeting_participants as peer')->whereColumn('peer.meeting_id', 'm.id')->where('peer.status', 'joined')->where('peer.last_seen_at', '>=', now()->subSeconds(self::STALE_SECONDS)))
            ->where(fn ($q) => $q->where('m.locked', 0)->orWhere('p.status', 'joined'))
            ->select('m.code', 'm.title', 'm.kind', 'p.id as membership_id')->orderByDesc('m.id')->limit(10)->get();
        return $rows->map(fn ($m) => ['code' => $m->code, 'title' => $m->title, 'kind' => $m->kind, 'rejoin' => $m->membership_id !== null, 'audio_only' => $m->kind === 'call' && (bool) DB::table('friend_calls')->where('meeting_code', $m->code)->where('group_invite', 0)->value('audio_only')])->all();
    }

    public function cancel(User $u, string $code): array
    {
        $m = $this->find($code);
        if (!$m || (int) $m->host_id !== $u->id) return [false, 'Only the host can cancel a meeting.'];
        if ($m->status === 'live') return [false, 'The meeting is running. End it from inside the meeting.'];
        DB::table('meetings')->where('id', $m->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        return [true, 'Meeting cancelled.'];
    }

    // ───────────────────────── joining and leaving ─────────────────────────

    /** @return array{0:bool,1:string,2:?array} */
    public function join(?User $user, ?string $guestName, string $code, bool $audio, bool $video): array
    {
        return DB::transaction(fn () => $this->joinLocked($user, $guestName, $code, $audio, $video));
    }

    private function joinLocked(?User $user, ?string $guestName, string $code, bool $audio, bool $video): array
    {
        if (!$this->enabled()) return [false, 'Meetings are switched off.', null];
        $m = DB::table('meetings')->where('code', self::normalize($code))->lockForUpdate()->first();
        if (!$m || in_array($m->status, ['ended', 'cancelled'], true)) return [false, 'This meeting has ended or does not exist.', null];
        if ($this->invitationExpired($m)) return [false, 'This invitation has expired. Ask for a new meeting.', null];
        $isHost = $user && (int) $m->host_id === $user->id;
        $previous = $user ? DB::table('meeting_participants')->where('meeting_id', $m->id)->where('user_id', $user->id)->orderByDesc('id')->first() : null;
        if ($previous && in_array($previous->status, ['removed', 'denied'], true)) return [false, 'The host has declined your access to this meeting.', null];
        // After host handover the returning creator joins as a participant.
        $isHost = $isHost && !DB::table('meeting_participants')->where('meeting_id', $m->id)->where('role', 'host')->where('status', 'joined')->where('user_id', '!=', $user->id)->exists();
        $invited = $user && DB::table('meeting_invitees')->where('meeting_id', $m->id)->where('user_id', $user->id)->exists();
        if (!$user) {
            if (!$m->allow_guests) return [false, 'Sign in to join this meeting.', null];
            $guestName = trim(strip_tags((string) $guestName));
            if ($guestName === '') return [false, 'Enter your name to join.', null];
        }
        if ($m->scheduled_at && !$isHost && now()->lt(Carbon::parse($m->scheduled_at, 'UTC')->subMinutes(15))) return [false, 'This meeting has not started yet. It opens 15 minutes before the start time.', null];
        if ($m->kind === 'call' && !$isHost && !$invited && !($user && (int) $m->host_id === $user->id)) return [false, 'This call is private.', null];
        $this->reap($m);
        // the same person opening the meeting again (reload, second device): the old connection is closed first, quietly –
        // the meeting keeps running, and the person keeps their role (host / co-host) and does not wait in the lobby again
        $inherit = null;
        $wasIn = false;
        if ($user) {
            foreach (DB::table('meeting_participants')->where('meeting_id', $m->id)->where('user_id', $user->id)->whereIn('status', ['joined', 'waiting'])->get() as $old) {
                if ($old->status === 'joined') $wasIn = true;
                if (in_array($old->role, ['host', 'cohost'], true)) $inherit = $old->role;
                $this->markLeft($old, false);
            }
        }
        $m = DB::table('meetings')->find($m->id);
        if ($m->status === 'ended') return [false, 'This meeting has ended.', null];
        if ($m->locked && !$isHost && !$wasIn) return [false, 'The host has locked this meeting.', null];
        if (DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'joined')->count() >= $this->max()) return [false, 'This meeting is full (' . $this->max() . ' people).', null];

        // who waits in the lobby: guests always; other people when the waiting room is on. The host and invited people walk in.
        $admittedBefore = $previous && $previous->joined_at && in_array($previous->status, ['joined', 'left'], true);
        $lobby = !$isHost && !$invited && !$wasIn && !$admittedBefore && ($m->waiting_room || !$user) && $m->kind !== 'call';
        $ringInvitees = $isHost && $m->kind === 'meeting'
            && !DB::table('meeting_participants')->where('meeting_id', $m->id)->where('user_id', $user->id)->exists();
        $token = $user ? null : Str::random(40);
        $pid = DB::table('meeting_participants')->insertGetId([
            'meeting_id' => $m->id, 'user_id' => $user?->id, 'guest_name' => $user ? null : $guestName, 'guest_token' => $token,
            'name' => $user?->name ?? $guestName, 'role' => $isHost ? 'host' : ($inherit ?? 'participant'), 'status' => $lobby ? 'waiting' : 'joined',
            'audio_on' => (int) ($audio && !($m->mute_on_entry && !$isHost)), 'video_on' => (int) $video, 'hand' => 0, 'sharing' => 0, 'force_mute' => 0,
            'joined_at' => $lobby ? null : now(), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if (!$lobby && $m->status === 'scheduled') DB::table('meetings')->where('id', $m->id)->update(['status' => 'live', 'started_at' => now(), 'updated_at' => now()]);
        $this->changed((int) $m->id);
        if ($ringInvitees) {
            // Ring once when the host first arrives, on either platform. Scheduled
            // invitations stay quiet until then; reconnects never ring everyone again.
            DB::afterCommit(function () use ($user, $m) {
                foreach (DB::table('meeting_invitees')->where('meeting_id', $m->id)->pluck('user_id') as $invitee) {
                    if ((int) $invitee === $user->id) continue;
                    try {
                        app(\App\Services\Friends\FriendCallService::class)->inviteToMeeting($user, $m->code, (int) $invitee);
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
                        // A former friend may retain the scheduled invitation, but
                        // cannot be called after the connection was removed.
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });
        }
        return [true, $lobby ? 'Waiting for the host to let you in.' : 'Joined.', [
            'pid' => $pid, 'token' => $token, 'status' => $lobby ? 'waiting' : 'joined', 'role' => $isHost ? 'host' : ($inherit ?? 'participant'), 'name' => $user?->name ?? $guestName,
            'meeting' => $this->info($m), 'ice_servers' => FriendSettings::iceServers(), 'max' => $this->max(),
        ]];
    }

    public function addInvitee(int $meetingId, int $userId): void { DB::table('meeting_invitees')->insertOrIgnore(['meeting_id' => $meetingId, 'user_id' => $userId]); }

    /** Host: change title / time / length ("delay" or bring forward) of a meeting that has not ended. People invited are told. */
    public function update(User $u, string $code, array $o): array
    {
        $m = $this->find($code);
        if (!$m || (int) $m->host_id !== $u->id) return [false, 'Only the host can change this meeting.'];
        if (in_array($m->status, ['ended', 'cancelled'], true)) return [false, 'This meeting is over.'];
        $upd = ['updated_at' => now()];
        if (isset($o['title']) && trim((string) $o['title']) !== '') $upd['title'] = Str::limit(trim((string) $o['title']), 150, '');
        if (array_key_exists('description', $o)) $upd['description'] = trim((string) $o['description']) !== '' ? Str::limit(trim((string) $o['description']), 1000, '') : null;
        if (isset($o['duration'])) $upd['duration_min'] = max(15, min(480, (int) $o['duration']));
        $timeChanged = false;
        if (array_key_exists('scheduled_at', $o)) {
            if ($o['scheduled_at'] === null || $o['scheduled_at'] === '') { $upd['scheduled_at'] = null; $timeChanged = true; }
            else {
                try { $upd['scheduled_at'] = Carbon::parse($o['scheduled_at'], $this->tz($u))->setTimezone('UTC'); $timeChanged = true; }
                catch (\Throwable $e) { return [false, 'That date or time is not valid.']; }
            }
        }
        if (isset($o['waiting_room'])) $upd['waiting_room'] = (int) (bool) $o['waiting_room'];
        if (isset($o['allow_guests'])) $upd['allow_guests'] = (int) (bool) $o['allow_guests'];
        if (isset($o['mute_on_entry'])) $upd['mute_on_entry'] = (int) (bool) $o['mute_on_entry'];
        DB::table('meetings')->where('id', $m->id)->update($upd);
        $m = DB::table('meetings')->find($m->id);
        if ($timeChanged || isset($upd['title'])) {
            foreach (DB::table('meeting_invitees')->where('meeting_id', $m->id)->pluck('user_id') as $uid) {
                $x = User::find($uid);
                try { $x?->notify(new MeetingNotification($u->name . ' changed “' . $m->title . '”', $this->when($m, $x) ?: 'Open to see the new time.', '/meet/' . $m->code, $u->name, $u->avatar_path ? asset('storage/'.$u->avatar_path) : '', 'meeting_updated')); } catch (\Throwable $e) {}
            }
        }
        return [true, 'Meeting updated.'];
    }

    /** calendar file (.ics) so the meeting can be added to Google / Outlook / Apple calendar */
    public function ics(object $m): string
    {
        $start = $m->scheduled_at ? Carbon::parse($m->scheduled_at, 'UTC') : Carbon::now('UTC');
        $end = $start->copy()->addMinutes((int) $m->duration_min);
        $f = fn (Carbon $c) => $c->format('Ymd\THis\Z');
        $esc = fn (string $t) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $t);
        return implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Dahimail//Meetings//EN', 'BEGIN:VEVENT', 'UID:' . $m->code . '@' . parse_url(url('/'), PHP_URL_HOST),
            'DTSTAMP:' . $f(Carbon::now('UTC')), 'DTSTART:' . $f($start), 'DTEND:' . $f($end), 'SUMMARY:' . $esc((string) $m->title),
            'DESCRIPTION:' . $esc('Join: ' . url('/meet/' . $m->code)), 'URL:' . url('/meet/' . $m->code), 'END:VEVENT', 'END:VCALENDAR']) . "\r\n";
    }

    public function info(object $m): array
    {
        $host = User::find($m->host_id);
        $start = $m->scheduled_at ? Carbon::parse($m->scheduled_at, 'UTC') : null;
        $callAudioOnly = $m->kind === 'call'
            ? (bool) DB::table('friend_calls')->where('meeting_code', $m->code)->where('group_invite', 0)->value('audio_only')
            : false;
        return [
            'scheduled_at' => $start?->toIso8601String(), 'duration' => (int) $m->duration_min,
            'ends_at' => $start ? $start->copy()->addMinutes((int) $m->duration_min)->toIso8601String() : null,
            'started_at' => $m->started_at ? Carbon::parse($m->started_at, 'UTC')->toIso8601String() : null,
            'code' => $m->code, 'pretty' => self::pretty($m->code), 'title' => $m->title, 'kind' => $m->kind, 'audio_only' => $callAudioOnly, 'call_status' => $m->kind === 'call' ? DB::table('friend_calls')->where('meeting_code', $m->code)->where('group_invite', 0)->value('status') : null, 'status' => $m->status, 'locked' => (bool) $m->locked,
            'waiting_room' => (bool) $m->waiting_room, 'mute_on_entry' => (bool) $m->mute_on_entry, 'host_name' => $host?->name, 'url' => url('/meet/' . $m->code),
        ];
    }

    public function leave(object $p): void
    {
        $this->markLeft($p);
    }

    /** Wake peers immediately; the authorized poll supplies the current roster. */
    private function changed(int $meetingId): void
    {
        $m = DB::table('meetings')->find($meetingId);
        if (!$m || in_array($m->status, ['ended', 'cancelled'], true)) return;
        $users = DB::table('meeting_participants')->where('meeting_id', $meetingId)->whereIn('status', ['joined', 'waiting'])->whereNotNull('user_id')->pluck('user_id')->all();
        \App\Services\RealtimeUpdates::users($users, ['type' => 'meeting_changed', 'code' => $m->code]);
    }

    private function markLeft(object $p, bool $check = true): void
    {
        DB::transaction(function () use ($p, $check) {
            // Serialize roster changes with joins and other departures. Re-read the
            // participant so retries and stale polling snapshots cannot end a room.
            $m = DB::table('meetings')->where('id', $p->meeting_id)->lockForUpdate()->first();
            $p = DB::table('meeting_participants')->find($p->id);
            if (!$m || !$p || !in_array($p->status, ['joined', 'waiting'], true)) return;
            $count = DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'joined')->count();
            if ($check && $p->status === 'joined' && $count === 2) {
                $this->finish($m);
                return;
            }
            DB::table('meeting_participants')->where('id', $p->id)->update(['status' => 'left', 'left_at' => now(), 'updated_at' => now()]);
            if (!$check) return; // replacing this user's connection preserves the room
            if ($p->status === 'joined' && $p->role === 'host') $this->promote((int) $m->id, (int) $p->id);
            $this->afterLeave((int) $m->id);
            $this->changed((int) $m->id);
        });
    }

    /** Keep host controls available, preferring an existing co-host, then a signed-in participant. */
    private function promote(int $meetingId, int $leavingId): void
    {
        $remaining = DB::table('meeting_participants')->where('meeting_id', $meetingId)->where('status', 'joined')->where('id', '!=', $leavingId);
        if ((clone $remaining)->where('role', 'host')->exists()) return;
        $next = $remaining->orderByRaw("CASE WHEN role = 'cohost' THEN 0 WHEN user_id IS NOT NULL THEN 1 ELSE 2 END")->orderBy('joined_at')->orderBy('id')->first();
        if ($next) DB::table('meeting_participants')->where('id', $next->id)->update(['role' => 'host', 'updated_at' => now()]);
    }

    /** nobody left in a running meeting → it ends (and a 1:1 call is closed with its log line) */
    private function afterLeave(int $meetingId): void
    {
        $m = DB::table('meetings')->find($meetingId);
        if (!$m || $m->status !== 'live') return;
        if (DB::table('meeting_participants')->where('meeting_id', $meetingId)->where('status', 'joined')->exists()) return;
        $this->finish($m);
    }

    private function finish(object $m): void
    {
        DB::transaction(function () use ($m) {
            if (!DB::table('meetings')->where('id', $m->id)->whereNotIn('status', ['ended', 'cancelled'])
                ->update(['status' => 'ended', 'ended_at' => now(), 'updated_at' => now()])) return;
            DB::table('meeting_participants')->where('meeting_id', $m->id)->whereIn('status', ['joined', 'waiting'])
                ->update(['status' => 'left', 'left_at' => now(), 'updated_at' => now()]);
            $this->finishCall($m); // regular meetings can also have ringing invitations
            $users = DB::table('meeting_participants')->where('meeting_id', $m->id)->whereNotNull('user_id')->pluck('user_id')->all();
            \App\Services\RealtimeUpdates::users($users, ['type' => 'meeting_ended', 'code' => $m->code]);
            DB::table('meeting_signals')->where('meeting_id', $m->id)->delete();
        });
    }

    /** Close every live invitation in this call, including added participants. */
    private function finishCall(object $m): void
    {
        $calls = DB::table('friend_calls')->where('meeting_code', $m->code)->whereIn('status', ['ringing', 'active'])->lockForUpdate()->get();
        foreach ($calls as $c) {
            $status = $c->status === 'active' ? 'ended' : 'cancelled';
            if (!DB::table('friend_calls')->where('id', $c->id)->where('status', $c->status)
                ->update(['status' => $status, 'ended_at' => now(), 'updated_at' => now()])) continue;
            $kind = ($c->audio_only ?? false) ? 'Audio' : 'Video';
            if ($c->status === 'active') {
                $seconds = $c->answered_at ? max(0, now()->getTimestamp() - Carbon::parse($c->answered_at)->getTimestamp()) : 0;
                $text = sprintf('%s call · %02d:%02d', $kind, intdiv($seconds, 60), $seconds % 60);
            } else {
                $text = 'Missed ' . strtolower($kind) . ' call';
                DB::afterCommit(fn () => \App\Services\Friends\FriendPush::missedCall($c));
            }
            DB::afterCommit(fn () => \App\Services\Friends\FriendPush::stopRinging($c, $status));
            DB::table('friend_messages')->insert(['sender_id' => $c->caller_id, 'recipient_id' => $c->callee_id,
                'body' => $text, 'kind' => 'call', 'call_id' => $c->id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function endByCode(string $code): void
    {
        $m = $this->find($code);
        if ($m && !in_array($m->status, ['ended', 'cancelled'], true)) $this->finish($m);
    }

    /** An unanswered invitation must never terminate a room already used by peers. */
    public function cancelCallInvitation(string $code, ?int $leavingUserId = null): void
    {
        DB::transaction(function () use ($code, $leavingUserId) {
            $m = DB::table('meetings')->where('code', $code)->lockForUpdate()->first();
            if (!$m || $m->kind !== 'call' || in_array($m->status, ['ended', 'cancelled'], true)) return;
            $joined = DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'joined');
            if ($joined->count() >= 2) {
                // Explicit hangup follows the same participant-count rule as Leave.
                // A declined/expired invitation is not a departure from the room.
                $participant = $leavingUserId ? (clone $joined)->where('user_id', $leavingUserId)->first() : null;
                if ($participant) $this->markLeft($participant);
                return;
            }
            $this->finish($m);
        });
    }

    /** people who stopped polling (closed the tab, lost the connection) are marked as gone */
    private function reap(object $m): void
    {
        $stale = DB::table('meeting_participants')->where('meeting_id', $m->id)->whereIn('status', ['joined', 'waiting'])->where('last_seen_at', '<', now()->subSeconds(self::STALE_SECONDS))->get();
        foreach ($stale as $p) $this->markLeft($p);
        if (random_int(1, 60) === 1) DB::table('meeting_signals')->where('created_at', '<', now()->subMinutes(20))->delete();
    }

    // ───────────────────────── while in the meeting ─────────────────────────

    /** one short call every second: who is here, signals for me, chat, what the host did */
    public function poll(object $p, int $sinceSignal, int $sinceChat, bool $stateOnly = false): array
    {
        DB::table('meeting_participants')->where('id', $p->id)->update(['last_seen_at' => now()]);
        $p = DB::table('meeting_participants')->find($p->id);
        $m = DB::table('meetings')->find($p->meeting_id);
        $this->reap($m);
        $m = DB::table('meetings')->find($m->id);
        $p = DB::table('meeting_participants')->find($p->id);
        $base = ['meeting' => $this->info($m), 'now' => now()->toIso8601String()];
        if (in_array($m->status, ['ended', 'cancelled'], true)) return $base + ['me' => ['pid' => (int) $p->id, 'status' => 'ended']];
        $me = ['pid' => (int) $p->id, 'status' => $p->status, 'role' => $p->role, 'force_mute' => (bool) $p->force_mute];
        if ($stateOnly || $p->status !== 'joined') return $base + ['me' => $me]; // waiting / removed / denied / left

        $rows = DB::table('meeting_participants as p')->leftJoin('users as u', 'u.id', '=', 'p.user_id')->where('p.meeting_id', $m->id)->where('p.status', 'joined')->orderBy('p.id')
            ->get(['p.id', 'p.name', 'p.role', 'p.audio_on', 'p.video_on', 'p.hand', 'p.sharing', 'p.user_id', 'u.avatar_path']);
        $mod = in_array($p->role, ['host', 'cohost'], true);
        $waiting = $mod ? DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'waiting')->orderBy('id')->get(['id', 'name', 'user_id'])->map(fn ($w) => ['pid' => (int) $w->id, 'name' => $w->name, 'guest' => $w->user_id === null])->all() : [];
        $signals = DB::table('meeting_signals')->where('meeting_id', $m->id)->where('to_pid', $p->id)->where('id', '>', $sinceSignal)->orderBy('id')->limit(300)->get(['id', 'from_pid', 'type', 'payload']);
        $chat = DB::table('meeting_messages')->where('meeting_id', $m->id)->where('id', '>', $sinceChat)->orderBy('id')->limit(100)->get();
        return $base + [
            'me' => $me,
            'recording' => app(\App\Services\Recording\RecordingService::class)->stateFor($m, $p),
            'participants' => $rows->map(fn ($r) => [
                'pid' => (int) $r->id, 'name' => $r->name, 'role' => $r->role, 'audio' => (bool) $r->audio_on, 'video' => (bool) $r->video_on, 'hand' => (bool) $r->hand, 'sharing' => (bool) $r->sharing,
                'avatar_url' => $r->avatar_path ? asset('storage/' . $r->avatar_path) : null, 'is_me' => (int) $r->id === (int) $p->id,
            ])->all(),
            'waiting' => $waiting,
            'signals' => $signals->map(fn ($s) => ['id' => (int) $s->id, 'from' => (int) $s->from_pid, 'type' => $s->type, 'payload' => $s->payload])->all(),
            'chat' => $chat->map(fn ($c) => ['id' => (int) $c->id, 'pid' => (int) $c->pid, 'name' => $c->name, 'body' => $c->body, 'time' => Carbon::parse($c->created_at)->format('H:i')])->all(),
        ];
    }

    /** @return array{0:bool,1:string} */
    public function signal(object $p, int $to, string $type, string $payload): array
    {
        if ($p->status !== 'joined' || !in_array($type, ['offer', 'answer', 'ice'], true) || strlen($payload) > 65000) return [false, 'Invalid signal.'];
        $t = DB::table('meeting_participants')->where('id', $to)->where('meeting_id', $p->meeting_id)->where('status', 'joined')->first();
        if (!$t) return [false, 'That person has left.'];
        $id = DB::table('meeting_signals')->insertGetId(['meeting_id' => $p->meeting_id, 'from_pid' => $p->id, 'to_pid' => $to, 'type' => $type, 'payload' => $payload, 'created_at' => now()]);
        $code = DB::table('meetings')->where('id', $p->meeting_id)->value('code');
        \App\Services\RealtimeUpdates::users([$t->user_id], ['type' => 'meeting_signal', 'code' => $code, 'to' => $to,
            'signal' => ['id' => $id, 'from' => (int) $p->id, 'type' => $type, 'payload' => $payload]]);
        return [true, 'OK'];
    }

    /** microphone / camera / hand / screen-share flags that everybody sees */
    public function state(object $p, array $s): void
    {
        if ($p->status !== 'joined') return;
        $u = ['updated_at' => now()];
        foreach (['audio' => 'audio_on', 'video' => 'video_on', 'hand' => 'hand', 'sharing' => 'sharing'] as $k => $col) if (array_key_exists($k, $s)) $u[$col] = (int) filter_var($s[$k], FILTER_VALIDATE_BOOLEAN);
        if (!empty($s['ack_mute'])) $u['force_mute'] = 0;
        if (!empty($u['sharing'])) DB::table('meeting_participants')->where('meeting_id', $p->meeting_id)->where('id', '!=', $p->id)->update(['sharing' => 0]); // one presenter at a time
        DB::table('meeting_participants')->where('id', $p->id)->update($u);
    }

    /** @return array{0:bool,1:string} */
    public function chat(object $p, string $body): array
    {
        $body = trim($body);
        if ($p->status !== 'joined' || $body === '') return [false, 'Nothing to send.'];
        $limiter = "meeting-chat:{$p->id}";
        if (RateLimiter::tooManyAttempts($limiter, 40)) return [false, 'You are sending too fast.'];
        RateLimiter::hit($limiter, 60);
        DB::table('meeting_messages')->insert(['meeting_id' => $p->meeting_id, 'pid' => $p->id, 'name' => $p->name, 'body' => Str::limit($body, 1000, ''), 'created_at' => now()]);
        return [true, 'Sent.'];
    }

    /**
     * Host controls: admit, admit_all, deny, remove, mute, mute_all, lower_hand, cohost, uncohost, lock, unlock, end.
     * @return array{0:bool,1:string}
     */
    public function host(object $p, string $action, ?int $target): array
    {
        return DB::transaction(function () use ($p, $action, $target) {
            DB::table('meetings')->where('id', $p->meeting_id)->lockForUpdate()->first();
            return $this->hostLocked(DB::table('meeting_participants')->find($p->id), $action, $target);
        });
    }

    private function hostLocked(object $p, string $action, ?int $target): array
    {
        $mod = in_array($p->role, ['host', 'cohost'], true);
        $m = DB::table('meetings')->find($p->meeting_id);
        if ($action === 'end' && $p->role === 'host' && $m && in_array($m->status, ['ended', 'cancelled'], true)) return [true, 'Meeting ended.'];
        if (!$m || in_array($m->status, ['ended', 'cancelled'], true)) return [false, 'This meeting has ended.'];
        if ($action === 'end' && $m->kind === 'call' && $p->status === 'left') return [true, 'You left the call.'];
        if ($p->status !== 'joined' || !$mod) return [false, 'Only the host can do that.'];
        $t = $target ? DB::table('meeting_participants')->where('id', $target)->where('meeting_id', $m->id)->first() : null;
        $touch = ['updated_at' => now()];
        switch ($action) {
            case 'admit':
                if (!$t || $t->status !== 'waiting') return [false, 'That person is no longer waiting.'];
                if (DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'joined')->count() >= $this->max()) return [false, 'The meeting is full.'];
                DB::table('meeting_participants')->where('id', $t->id)->update(['status' => 'joined', 'joined_at' => now(), 'last_seen_at' => now()] + $touch);
                return [true, "{$t->name} joined."];
            case 'admit_all':
                $free = $this->max() - DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'joined')->count();
                foreach (DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'waiting')->orderBy('id')->limit(max(0, $free))->get() as $w) {
                    DB::table('meeting_participants')->where('id', $w->id)->update(['status' => 'joined', 'joined_at' => now(), 'last_seen_at' => now()] + $touch);
                }
                return [true, 'Everybody was let in.'];
            case 'deny':
                if (!$t || $t->status !== 'waiting') return [false, 'That person is no longer waiting.'];
                DB::table('meeting_participants')->where('id', $t->id)->update(['status' => 'denied', 'left_at' => now()] + $touch);
                return [true, 'Request declined.'];
            case 'remove':
                if (!$t || !in_array($t->status, ['joined', 'waiting'], true) || (int) $t->id === (int) $p->id || $t->role === 'host') return [false, 'You cannot remove this person.'];
                if ($t->role === 'cohost' && $p->role !== 'host') return [false, 'Only the host can remove a co-host.'];
                DB::table('meeting_participants')->where('id', $t->id)->update(['status' => 'removed', 'left_at' => now()] + $touch);
                return [true, "{$t->name} was removed."];
            case 'mute':
                if (!$t || $t->status !== 'joined') return [false, 'Not found.'];
                DB::table('meeting_participants')->where('id', $t->id)->update(['force_mute' => 1, 'audio_on' => 0] + $touch);
                return [true, "{$t->name} was muted."];
            case 'mute_all':
                DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'joined')->where('id', '!=', $p->id)->whereNotIn('role', ['host'])->update(['force_mute' => 1, 'audio_on' => 0] + $touch);
                return [true, 'Everybody was muted.'];
            case 'lower_hand':
                if ($t) DB::table('meeting_participants')->where('id', $t->id)->update(['hand' => 0] + $touch);
                return [true, 'OK'];
            case 'cohost':
            case 'uncohost':
                if ($p->role !== 'host') return [false, 'Only the host can do that.'];
                if (!$t || $t->status !== 'joined' || $t->role === 'host' || $t->user_id === null) return [false, 'Only signed-in people can be co-hosts.'];
                DB::table('meeting_participants')->where('id', $t->id)->update(['role' => $action === 'cohost' ? 'cohost' : 'participant'] + $touch);
                return [true, 'OK'];
            case 'lock':
            case 'unlock':
                DB::table('meetings')->where('id', $m->id)->update(['locked' => $action === 'lock' ? 1 : 0] + $touch);
                return [true, $action === 'lock' ? 'The meeting is locked: nobody new can join.' : 'The meeting is unlocked.'];
            case 'end':
                if ($m->kind === 'call') { $this->markLeft($p); return [true, 'You left the call.']; }
                if ($p->role !== 'host') return [false, 'Only the host can end the meeting for everyone.'];
                $this->finish($m);
                return [true, 'The meeting was ended for everyone.'];
        }
        return [false, 'Unknown action.'];
    }
}
