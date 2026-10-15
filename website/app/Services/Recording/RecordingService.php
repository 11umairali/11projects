<?php

namespace App\Services\Recording;

use App\Models\User;
use App\Notifications\FriendNotification;
use App\Services\Friends\FriendChatService;
use App\Services\Friends\FriendPush;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Recording sessions of calls and meetings under the Super Admin policy (see RecordingPolicy).
 *
 * The audio / video travels directly between the devices, so the SERVER cannot record it. Instead one device that CAN capture it
 * (today: the website in a browser) "claims" the session, records, and uploads the file when the call ends; the server then posts
 * it into the chat. The apps show the consent prompt, the record button and the REC sign, but they cannot capture yet – if nobody
 * in the room can capture, the session ends as "no_recorder" and the person who started it is told so.
 */
class RecordingService
{
    private const EXT = ['webm', 'm4a', 'mp4', 'ogg', 'oga', 'opus', 'aac', 'mp3', 'wav', 'mov', '3gp'];
    private const CLAIM_WAIT = 25; // seconds a started session waits for a device that can capture

    // ───────────────────────── who is who ─────────────────────────

    private function mod(object $p): bool { return in_array($p->role, ['host', 'cohost'], true); }

    private function initiator(object $m, object $p): bool { return $p->user_id && (int) $p->user_id === (int) $m->host_id; }

    private function live(object $m): bool { return !in_array($m->status, ['ended', 'cancelled'], true); }

    private function joined(int $meetingId)
    {
        return DB::table('meeting_participants')->where('meeting_id', $meetingId)->where('status', 'joined')->get();
    }

    /** may this participant start (or ask for) a recording under the active mode? */
    public function canStart(object $m, object $p, int $mode): bool
    {
        if ($mode < 2 || $p->status !== 'joined') return false;           // mode 1 starts by itself; 0 = off
        if ($mode === 5 && !$p->user_id) return false;                      // a private copy needs an account to be delivered to
        $btn = RecordingPolicy::cfg($mode)['button'];
        return match ($btn) {
            'all' => $mode !== 4,
            'host' => $this->mod($p),
            'initiator' => $this->initiator($m, $p),
            default => false,
        };
    }

    public function activeSession(int $meetingId): ?object
    {
        return DB::table('recording_sessions')->where('meeting_id', $meetingId)->whereIn('status', ['pending', 'recording'])->orderByDesc('id')->first();
    }

    public function isActiveForCode(?string $code): bool
    {
        if (!$code) return false;
        $id = DB::table('meetings')->where('code', $code)->value('id');
        $s = $id ? $this->activeSession((int) $id) : null;
        return $s && $s->status === 'recording';
    }

    // ───────────────────────── housekeeping (lazy + scheduler) ─────────────────────────

    /** expires consents, ends sessions of finished meetings, fails sessions nobody can capture, starts mode 1 */
    public function maintain(object $m): void
    {
        $mode = RecordingPolicy::mode();
        $s = $this->activeSession($m->id);
        if ($s) {
            if (!$this->live($m)) { $this->close($s, 'stopped', null); return; }
            if ($s->status === 'pending') { $this->evaluate($s); $s = DB::table('recording_sessions')->find($s->id); }
            if ($s->status === 'recording' && !$s->recorder_pid && $s->started_at && Carbon::parse($s->started_at)->diffInSeconds(now()) > self::CLAIM_WAIT) {
                $this->close($s, 'failed', 'no_recorder');
            }
            return;
        }
        // mode 1: automatic – once per meeting, when the call is really running
        if ($mode === 1 && $this->live($m) && !DB::table('recording_sessions')->where('meeting_id', $m->id)->exists()) {
            $joined = $this->joined($m->id);
            if ($joined->count() >= ($m->kind === 'call' ? 2 : 1)) {
                $host = $joined->firstWhere('user_id', $m->host_id) ?? $joined->first();
                $this->create($m, 1, $host, 'recording', false);
            }
        }
    }

    /** called every minute */
    public function sweep(): void
    {
        $ids = DB::table('recording_sessions')->whereIn('status', ['pending', 'recording'])->pluck('meeting_id')->unique();
        foreach ($ids as $id) { if ($m = DB::table('meetings')->find($id)) $this->maintain($m); }
    }

    private function create(object $m, int $mode, object $starter, string $status, bool $private): int
    {
        return (int) DB::table('recording_sessions')->insertGetId([
            'meeting_id' => $m->id, 'mode' => $mode, 'status' => $status, 'private' => $private ? 1 : 0,
            'started_by_pid' => $starter->id, 'started_by_user' => $starter->user_id,
            'requested_at' => now(), 'started_at' => $status === 'recording' ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function close(object $s, string $status, ?string $reason): void
    {
        DB::table('recording_sessions')->where('id', $s->id)->whereIn('status', ['pending', 'recording'])
            ->update(['status' => $status, 'reason' => $reason, 'stopped_at' => now(), 'updated_at' => now()]);
    }

    /** mode 3: look at the answers */
    private function evaluate(object $s): void
    {
        $rows = DB::table('recording_consents as c')->join('meeting_participants as p', 'p.id', '=', 'c.pid')->where('c.session_id', $s->id)
            ->get(['c.id', 'c.response', 'p.status as pstatus']);
        $rows = $rows->where('pstatus', 'joined');                          // somebody who left does not block
        if ($rows->contains('response', 'declined')) { $this->close($s, 'declined', 'declined'); return; }
        if ($s->status === 'pending') {
            if (Carbon::parse($s->requested_at)->diffInSeconds(now()) > RecordingPolicy::consentSeconds()) { $this->close($s, 'expired', 'no_answer'); return; }
            if (!$rows->contains('response', 'pending')) {
                DB::table('recording_sessions')->where('id', $s->id)->where('status', 'pending')->update(['status' => 'recording', 'started_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    // ───────────────────────── what the screens need (rides on the meeting poll) ─────────────────────────

    public function stateFor(object $m, object $p): array
    {
        $mode = RecordingPolicy::mode();
        if ($mode === 0) return ['mode' => 0, 'enabled' => false];
        try {
            $this->maintain($m);
            $cfg = RecordingPolicy::cfg($mode);
            $s = $this->activeSession($m->id);

            // mode 3: somebody who joined while it records must also say yes (a "no" stops it)
            if ($s && $mode === 3 && $s->status === 'recording' && !DB::table('recording_consents')->where('session_id', $s->id)->where('pid', $p->id)->exists()) {
                DB::table('recording_consents')->insertOrIgnore(['session_id' => $s->id, 'pid' => $p->id, 'response' => 'pending']);
            }

            $out = [
                'mode' => $mode, 'enabled' => true, 'max_mb' => RecordingPolicy::maxMb(),
                'ui' => ['button' => $cfg['button'], 'icon' => $cfg['icon'], 'banner' => $cfg['banner'], 'chime' => $cfg['chime']],
                'can_start' => !$s && $this->canStart($m, $p, $mode),
                'can_stop' => false, 'session' => null, 'consent' => null, 'claim_open' => false, 'recorder_mine' => false, 'last' => null,
            ];
            if ($s) {
                $mine = (int) $s->started_by_pid === (int) $p->id;
                $by = DB::table('meeting_participants')->where('id', $s->started_by_pid)->value('name');
                $out['can_stop'] = (bool) ($s->private ? $mine : ($mine || $this->mod($p) || $this->initiator($m, $p)));
                $out['claim_open'] = $s->status === 'recording' && !$s->recorder_pid && (!$s->private || $mine);
                $out['recorder_mine'] = (int) $s->recorder_pid === (int) $p->id;
                $out['session'] = [
                    'id' => (int) $s->id, 'status' => $s->status, 'private' => (bool) $s->private, 'by' => $by, 'by_me' => $mine,
                    'started_at' => $s->started_at ? Carbon::parse($s->started_at)->toIso8601String() : null,
                    'elapsed' => $s->started_at ? max(0, Carbon::parse($s->started_at)->diffInSeconds(now())) : 0,
                ];
                if ($s->status === 'pending') {
                    $c = DB::table('recording_consents')->where('session_id', $s->id)->where('pid', $p->id)->first();
                    if ($c && $c->response === 'pending') {
                        $out['consent'] = ['session_id' => (int) $s->id, 'requester' => $by, 'expires_in' => max(0, RecordingPolicy::consentSeconds() - Carbon::parse($s->requested_at)->diffInSeconds(now()))];
                    }
                    if ($mine) {
                        $out['session']['waiting_for'] = DB::table('recording_consents as c')->join('meeting_participants as q', 'q.id', '=', 'c.pid')->where('c.session_id', $s->id)->where('c.response', 'pending')->where('q.status', 'joined')->pluck('q.name')->all();
                    }
                } elseif ($mode === 3) {
                    $c = DB::table('recording_consents')->where('session_id', $s->id)->where('pid', $p->id)->first();
                    if ($c && $c->response === 'pending') $out['consent'] = ['session_id' => (int) $s->id, 'requester' => $by, 'expires_in' => RecordingPolicy::consentSeconds(), 'late' => true];
                }
            } else {
                // the last result, shown once to the person who asked: declined / nobody answered / no device could record / saved
                $l = DB::table('recording_sessions')->where('meeting_id', $m->id)->whereIn('status', ['declined', 'expired', 'failed', 'stopped'])->where('stopped_at', '>', now()->subSeconds(40))
                    ->where('started_by_pid', $p->id)->orderByDesc('id')->first();
                if ($l) {
                    $saved = DB::table('recording_files')->where('session_id', $l->id)->exists();
                    $out['last'] = ['status' => $l->status, 'reason' => $l->reason, 'saved' => $saved, 'session_id' => (int) $l->id];
                }
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('recording state failed: ' . $e->getMessage());
            return ['mode' => 0, 'enabled' => false];
        }
    }

    // ───────────────────────── actions ─────────────────────────

    /** @return array{0:bool,1:string} */
    public function start(object $m, object $p): array
    {
        $mode = RecordingPolicy::mode();
        if ($mode < 2) return [false, $mode === 1 ? 'Recording starts by itself in this setup.' : 'Recording is switched off.'];
        if (!$this->live($m) || $p->status !== 'joined') return [false, 'The call is not running.'];
        if (!$this->canStart($m, $p, $mode)) return [false, 'You are not allowed to start a recording here.'];
        if ($this->activeSession($m->id)) return [true, 'Already recording.'];

        if ($mode === 3) {
            $others = $this->joined($m->id)->where('id', '!=', $p->id);
            if ($others->isEmpty()) { $this->create($m, 3, $p, 'recording', false); return [true, 'Recording.']; }
            $sid = $this->create($m, 3, $p, 'pending', false);
            DB::table('recording_consents')->insert(['session_id' => $sid, 'pid' => $p->id, 'response' => 'accepted', 'responded_at' => now()]);
            foreach ($others as $o) DB::table('recording_consents')->insert(['session_id' => $sid, 'pid' => $o->id, 'response' => 'pending']);
            return [true, 'Waiting for everybody to accept…'];
        }
        $this->create($m, $mode, $p, 'recording', $mode === 5);
        return [true, 'Recording.'];
    }

    /** @return array{0:bool,1:string} */
    public function respond(object $m, object $p, int $sessionId, bool $accept): array
    {
        $s = DB::table('recording_sessions')->where('id', $sessionId)->where('meeting_id', $m->id)->first();
        if (!$s) return [false, 'Not found.'];
        $n = DB::table('recording_consents')->where('session_id', $s->id)->where('pid', $p->id)->where('response', 'pending')->update(['response' => $accept ? 'accepted' : 'declined', 'responded_at' => now()]);
        if (!$n) return [true, 'OK'];
        if (!$accept) $this->close($s, $s->status === 'pending' ? 'declined' : 'stopped', $s->status === 'pending' ? 'declined' : 'declined_late');
        else $this->evaluate($s);
        return [true, 'OK'];
    }

    /** @return array{0:bool,1:string} */
    public function stop(object $m, object $p): array
    {
        $s = $this->activeSession($m->id);
        if (!$s) return [true, 'Not recording.'];
        $mine = (int) $s->started_by_pid === (int) $p->id;
        $ok = $s->private ? $mine : ($mine || $this->mod($p) || $this->initiator($m, $p));
        if (!$ok) return [false, 'Only the person who started it, or the host, can stop the recording.'];
        $this->close($s, 'stopped', $s->status === 'pending' ? 'cancelled' : null);
        return [true, 'Stopped.'];
    }

    /** a device that can capture says "I will record this session". First one wins. */
    public function claim(object $m, object $p): bool
    {
        $s = $this->activeSession($m->id);
        if (!$s || $s->status !== 'recording') return false;
        if ($s->private && (int) $s->started_by_pid !== (int) $p->id) return false;
        DB::table('recording_sessions')->where('id', $s->id)->whereNull('recorder_pid')->update(['recorder_pid' => $p->id, 'recorder_user' => $p->user_id, 'updated_at' => now()]);
        return (int) DB::table('recording_sessions')->where('id', $s->id)->value('recorder_pid') === (int) $p->id;
    }

    // ───────────────────────── the file → chat ─────────────────────────

    /** @return array{0:bool,1:string} */
    public function store(object $m, object $p, UploadedFile $file, ?int $duration, bool $hasVideo, ?int $sessionId = null): array
    {
        $s = DB::table('recording_sessions')->where('meeting_id', $m->id)->where('recorder_pid', $p->id)->whereIn('status', ['recording', 'stopped'])->when($sessionId, fn ($q) => $q->where('id', $sessionId))->orderByDesc('id')->first();
        if (!$s) return [false, 'There is no recording for you to upload.'];
        if (DB::table('recording_files')->where('session_id', $s->id)->exists()) return [true, 'Already saved.'];
        if (!$file->isValid()) return [false, 'The recording could not be uploaded.'];
        $mb = RecordingPolicy::maxMb();
        if ($file->getSize() > $mb * 1024 * 1024) return [false, "The recording is larger than {$mb} MB.", ];
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = strtolower(trim(explode(';', (string) $file->getClientMimeType())[0]));
        if (!in_array($ext, self::EXT, true) || !(str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/') || in_array($mime, ['application/octet-stream', 'application/ogg'], true))) return [false, 'This recording format is not supported.'];
        if ($s->status === 'recording') $this->close($s, 'stopped', null);   // the room ended / the recorder left
        $s = DB::table('recording_sessions')->find($s->id);

        $audience = $this->audience($m, $s);
        $call = $m->kind === 'call' ? DB::table('friend_calls')->where('meeting_code', $m->code)->where('group_invite', 0)->orderByDesc('id')->first() : null;
        $oneToOne = $call && count($audience) <= 2 && $s->mode !== 0;
        $dir = $oneToOne ? 'friend-files/' . min($call->caller_id, $call->callee_id) . '-' . max($call->caller_id, $call->callee_id) : 'meeting-recordings/' . $m->id;
        $path = $file->storeAs($dir, Str::uuid() . '.' . $ext, 'local');
        if (!$path) return [false, 'Could not save the recording. Please retry.'];
        $fm = FriendChatService::mimeFor($mime === 'application/octet-stream' ? null : $mime, 'recording.' . $ext);
        $secs = $duration ? min(65535, max(1, $duration)) : ($s->started_at ? min(65535, Carbon::parse($s->started_at)->diffInSeconds($s->stopped_at ?? now())) : null);

        try {
            DB::transaction(function () use ($s, $m, $p, $path, $ext, $fm, $file, $secs, $hasVideo, $audience, $oneToOne, $call) {
                // Serialize retries of this session; file metadata and chat delivery commit together.
                DB::table('recording_sessions')->where('id', $s->id)->lockForUpdate()->first();
                if (DB::table('recording_files')->where('session_id', $s->id)->exists()) {
                    Storage::disk('local')->delete($path);
                    return;
                }
            $fid = (int) DB::table('recording_files')->insertGetId([
                'session_id' => $s->id, 'meeting_id' => $m->id, 'uploaded_by' => $p->user_id, 'file_path' => $path, 'file_name' => 'recording.' . $ext, 'file_mime' => $fm,
                'file_size' => $file->getSize(), 'duration' => $secs, 'has_video' => $hasVideo ? 1 : 0, 'audience' => json_encode(array_values($audience)), 'created_at' => now(),
            ]);
                $f = DB::table('recording_files')->find($fid);
                $oneToOne ? $this->postToChat($s, $f, $call, $hasVideo) : $this->postToMeeting($m, $s, $f);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            Log::error('Recording save/delivery failed', ['session_id' => $s->id, 'exception' => $e]);
            return [false, 'Could not deliver the recording. Please retry.'];
        }
        return [true, 'Saved.'];
    }

    /** user ids that receive the file: everybody who was connected (registered accounts), or only the starter in mode 5 */
    private function audience(object $m, object $s): array
    {
        if ($s->private) return $s->started_by_user ? [(int) $s->started_by_user] : [];
        $from = $s->started_at ?? $s->requested_at; $to = $s->stopped_at ?? now();
        return DB::table('meeting_participants')->where('meeting_id', $m->id)->whereNotNull('user_id')->whereNotNull('joined_at')
            ->where('joined_at', '<=', $to)->where(fn ($w) => $w->whereNull('left_at')->orWhere('left_at', '>=', $from))
            ->pluck('user_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /** 1:1 call → an ordinary media message in the chat thread (a voice message for audio, a file for video) */
    private function postToChat(object $s, object $f, object $call, bool $video): void
    {
        $a = (int) $call->caller_id; $b = (int) $call->callee_id;
        $sender = (int) ($s->private ? $s->started_by_user : ($s->started_by_user ?: $a));
        if (!in_array($sender, [$a, $b], true)) $sender = $a;
        $rcpt = $sender === $a ? $b : $a;
        $row = [
            'sender_id' => $sender, 'recipient_id' => $rcpt, 'kind' => $video ? 'file' : 'voice', 'body' => $video ? 'Call recording' : null,
            'file_path' => $f->file_path, 'file_name' => $f->file_name, 'file_mime' => $f->file_mime, 'file_size' => $f->file_size, 'duration' => $f->duration ? min(65535, (int) $f->duration) : null,
            'call_id' => $call->id, 'visible_to' => $s->private ? $sender : null, 'read_at' => $s->private ? now() : null, 'delivered_at' => $s->private ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ];
        $id = DB::table('friend_messages')->insertGetId($row);
        if (!$s->private) FriendPush::message(DB::table('friend_messages')->find($id));
    }

    /** meeting → a line in the meeting chat with the link + a notification for every registered participant */
    private function postToMeeting(object $m, object $s, object $f): void
    {
        $url = url('/meetings/recording/' . $f->id);
        $ids = json_decode((string) $f->audience, true) ?: [];
        if (!$s->private) {
            $pid = $s->started_by_pid ?: ($s->recorder_pid ?: DB::table('meeting_participants')->where('meeting_id', $m->id)->orderBy('id')->value('id'));
            if ($pid) DB::table('meeting_messages')->insert(['meeting_id' => $m->id, 'pid' => $pid, 'name' => 'Recording', 'body' => '🎙 The recording of this meeting is ready: ' . $url, 'created_at' => now()]);
        }
        foreach (User::whereIn('id', $ids)->get() as $u) {
            try { $u->notify(new FriendNotification('meeting_recording', 'Recording ready', 'The recording of "' . ($m->title ?: 'your meeting') . '" is available.', '/meetings/recording/' . $f->id)); } catch (\Throwable $e) {}
        }
    }

    /** may this user open a meeting recording? */
    public function fileForUser(User $u, int $fileId): ?object
    {
        $f = DB::table('recording_files')->find($fileId);
        if (!$f) return null;
        $ids = json_decode((string) $f->audience, true) ?: [];
        return in_array((int) $u->id, array_map('intval', $ids), true) ? $f : null;
    }
}
