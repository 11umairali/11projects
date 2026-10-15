<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Services\Friends\PeopleService;
use App\Services\Meetings\MeetingService;
use App\Services\Recording\RecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Meetings. The JSON endpoints are used by the website room and by the mobile app; a signed-in person is recognised by their
 * session / token, a guest by the secret token they received when joining (header X-Guest-Token).
 */
class MeetingController extends Controller
{
    public function __construct(private readonly MeetingService $svc) {}

    /** the participant row of the caller for this meeting, or null */
    private function me(Request $r, string $code): ?object
    {
        $m = $this->svc->find($code);
        $pid = (int) ($r->input('pid') ?? $r->query('pid'));
        if (!$m || $pid <= 0) return null;
        $p = DB::table('meeting_participants')->where('id', $pid)->where('meeting_id', $m->id)->first();
        if (!$p) return null;
        if ($p->user_id) return ($r->user() && (int) $r->user()->id === (int) $p->user_id) ? $p : null;
        $t = (string) ($r->header('X-Guest-Token') ?: $r->input('gt'));
        return ($p->guest_token && $t !== '' && hash_equals($p->guest_token, $t)) ? $p : null;
    }

    private function deny(): JsonResponse { return response()->json(['message' => 'You are not in this meeting.'], 403); }

    // ───────────── website pages ─────────────

    public function index(Request $r)
    {
        $u = $r->user();
        if ($r->boolean('_live')) {
            return response()->view('meetings._list', ['lists' => $this->svc->listFor($u)])->header('Cache-Control', 'no-store');
        }
        $dir = app(PeopleService::class)->directory($u);
        $people = [];
        foreach (array_merge($dir['friends'] ?? [], $dir['team'] ?? []) as $p) $people[$p['id']] = ['id' => $p['id'], 'name' => $p['name'], 'avatar_url' => $p['avatar_url'] ?? null];
        usort($people, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return view('meetings.index', ['lists' => $this->svc->listFor($u), 'people' => $people]);
    }

    /** form: "Start now" (instant) or "Schedule" */
    public function store(Request $r)
    {
        $d = $r->validate(['title' => 'nullable|string|max:150', 'date' => 'nullable|date_format:Y-m-d', 'time' => 'nullable|date_format:H:i', 'duration' => 'nullable|integer|min:15|max:480', 'invitees' => 'nullable|array', 'invitees.*' => 'integer']);
        $instant = $r->input('mode') === 'now';
        $m = $this->svc->create($r->user(), [
            'title' => $d['title'] ?? '', 'duration' => $d['duration'] ?? 60, 'invitees' => $d['invitees'] ?? [],
            'scheduled_at' => (!$instant && !empty($d['date'])) ? $d['date'] . ' ' . ($d['time'] ?? '09:00') : null,
            'waiting_room' => $r->boolean('waiting_room'), 'allow_guests' => $r->boolean('allow_guests'), 'mute_on_entry' => $r->boolean('mute_on_entry'),
        ]);
        return $instant || empty($d['date']) ? redirect('/meet/' . $m->code . '?auto=1') : redirect('/meetings')->with('status', 'Meeting scheduled. Share the link: ' . url('/meet/' . $m->code));
    }

    public function cancelForm(Request $r, string $code)
    {
        [$ok, $msg] = $this->svc->cancel($r->user(), $code);
        return redirect('/meetings')->with($ok ? 'status' : 'error', $msg);
    }

    /** the room (also for guests) */
    public function room(Request $r, string $code)
    {
        $m = $this->svc->find($code);
        abort_unless($m && $m->status !== 'cancelled', 404);
        $u = $r->user();
        // A source-only deployment may still have the previous Vite manifest.
        // Realtime accelerates the room's existing REST polling; missing assets
        // must not prevent the call screen itself from loading.
        $realtimeAssets = '';
        try {
            $realtimeAssets = app(\Illuminate\Foundation\Vite::class)(['resources/js/realtime.js']);
        } catch (\Illuminate\Foundation\ViteException $e) {
            \Illuminate\Support\Facades\Log::warning('Call room realtime assets unavailable; rebuild and deploy public/build. Polling remains enabled.', [
                'exception' => get_class($e),
            ]);
        }
        return view('meetings.room', [
            'realtimeAssets' => $realtimeAssets,
            'meeting' => $this->svc->info($m), 'ended' => $m->status === 'ended', 'allowGuests' => (bool) $m->allow_guests,
            'user' => $u ? ['id' => $u->id, 'name' => $u->name] : null,
        ]);
    }

    // ───────────── JSON ─────────────

    public function list(Request $r): JsonResponse { return response()->json(['data' => $this->svc->listFor($r->user())]); }

    public function create(Request $r): JsonResponse
    {
        $d = $r->validate(['title' => 'nullable|string|max:150', 'description' => 'nullable|string|max:1000', 'scheduled_at' => 'nullable|string|max:40', 'duration' => 'nullable|integer|min:15|max:480', 'invitees' => 'nullable|array', 'invitees.*' => 'integer']);
        $m = $this->svc->create($r->user(), $d + [
            'waiting_room' => $r->boolean('waiting_room', true), 'allow_guests' => $r->boolean('allow_guests', true), 'mute_on_entry' => $r->boolean('mute_on_entry'),
        ]);
        return response()->json(['message' => 'Created.', 'data' => $this->svc->summary($m, $r->user())]);
    }

    public function show(Request $r, string $code): JsonResponse
    {
        $m = $this->svc->find($code);
        if (!$m) return response()->json(['message' => 'Meeting not found.'], 404);
        return response()->json(['data' => $this->svc->summary($m, $r->user()) + ['entry' => $this->svc->entryState($r->user(), $code), 'audio_only' => $this->svc->info($m)['audio_only']]]);
    }

    public function join(Request $r, string $code): JsonResponse
    {
        [$ok, $msg, $data] = $this->svc->join($r->user(), $r->input('name'), $code, $r->boolean('audio', true), $r->boolean('video', true));
        return response()->json(['message' => $msg, 'data' => $data], $ok ? 200 : 422);
    }

    // ───────────── recording (policy engine: app/Services/Recording) ─────────────

    private function rec(Request $r, string $code): array
    {
        $p = $this->me($r, $code);
        return [$p, $p ? DB::table('meetings')->find($p->meeting_id) : null];
    }

    public function recStart(Request $r, string $code): JsonResponse
    {
        [$p, $m] = $this->rec($r, $code); if (!$p) return $this->deny();
        [$ok, $msg] = app(RecordingService::class)->start($m, $p);
        return response()->json(['message' => $msg, 'data' => app(RecordingService::class)->stateFor($m, $p)], $ok ? 200 : 422);
    }

    public function recRespond(Request $r, string $code): JsonResponse
    {
        [$p, $m] = $this->rec($r, $code); if (!$p) return $this->deny();
        $d = $r->validate(['session_id' => 'required|integer', 'accept' => 'required']);
        [$ok, $msg] = app(RecordingService::class)->respond($m, $p, (int) $d['session_id'], filter_var($d['accept'], FILTER_VALIDATE_BOOLEAN));
        return response()->json(['message' => $msg, 'data' => app(RecordingService::class)->stateFor($m, $p)], $ok ? 200 : 422);
    }

    public function recStop(Request $r, string $code): JsonResponse
    {
        [$p, $m] = $this->rec($r, $code); if (!$p) return $this->deny();
        [$ok, $msg] = app(RecordingService::class)->stop($m, $p);
        return response()->json(['message' => $msg, 'data' => app(RecordingService::class)->stateFor($m, $p)], $ok ? 200 : 422);
    }

    /** a device that can capture sound / picture offers to record this session */
    public function recClaim(Request $r, string $code): JsonResponse
    {
        [$p, $m] = $this->rec($r, $code); if (!$p) return $this->deny();
        return response()->json(['data' => ['claimed' => app(RecordingService::class)->claim($m, $p)]]);
    }

    public function recUpload(Request $r, string $code): JsonResponse
    {
        [$p, $m] = $this->rec($r, $code); if (!$p) return $this->deny();
        $r->validate(['file' => 'required|file', 'duration' => 'nullable|integer|min:0|max:86400', 'video' => 'nullable', 'session_id' => 'nullable|integer|min:1']);
        [$ok, $msg] = app(RecordingService::class)->store($m, $p, $r->file('file'), $r->filled('duration') ? (int) $r->input('duration') : null, filter_var($r->input('video'), FILTER_VALIDATE_BOOLEAN), $r->filled('session_id') ? (int) $r->input('session_id') : null);
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }

    public function poll(Request $r, string $code): JsonResponse
    {
        $p = $this->me($r, $code);
        if (!$p) return $this->deny();
        return response()->json(['data' => $this->svc->poll($p, (int) $r->query('sig', 0), (int) $r->query('chat', 0), $r->boolean('state_only'))]);
    }

    public function signal(Request $r, string $code): JsonResponse
    {
        $p = $this->me($r, $code);
        if (!$p) return $this->deny();
        $d = $r->validate(['to' => 'required|integer', 'type' => 'required|string|in:offer,answer,ice', 'payload' => 'required|string']);
        [$ok, $msg] = $this->svc->signal($p, (int) $d['to'], $d['type'], $d['payload']);
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }

    public function state(Request $r, string $code): JsonResponse
    {
        $p = $this->me($r, $code);
        if (!$p) return $this->deny();
        $this->svc->state($p, $r->only(['audio', 'video', 'hand', 'sharing', 'ack_mute']));
        return response()->json(['message' => 'OK']);
    }

    public function chat(Request $r, string $code): JsonResponse
    {
        $p = $this->me($r, $code);
        if (!$p) return $this->deny();
        [$ok, $msg] = $this->svc->chat($p, (string) $r->input('body'));
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }

    public function host(Request $r, string $code): JsonResponse
    {
        $p = $this->me($r, $code);
        if (!$p) return $this->deny();
        $d = $r->validate(['action' => 'required|string', 'target' => 'nullable|integer']);
        [$ok, $msg] = $this->svc->host($p, $d['action'], isset($d['target']) ? (int) $d['target'] : null);
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }

    public function leave(Request $r, string $code): JsonResponse
    {
        $p = $this->me($r, $code);
        if ($p) $this->svc->leave($p);
        return response()->json(['message' => 'Left.']);
    }

    public function cancel(Request $r, string $code): JsonResponse
    {
        [$ok, $msg] = $this->svc->cancel($r->user(), $code);
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }

    // ───────────── v2: add people to a call, edit / delay, calendar file ─────────────

    /** friends and teammates you can add, online ones first */
    public function invitable(Request $r, string $code): JsonResponse
    {
        $p = $this->me($r, $code);
        if (!$p || !$p->user_id) return $this->deny();
        $m = $this->svc->find($code);
        $u = $r->user();
        $dir = app(\App\Services\Friends\PeopleService::class)->directory($u);
        $inside = DB::table('meeting_participants')->where('meeting_id', $m->id)->where('status', 'joined')->whereNotNull('user_id')->pluck('user_id')->map(fn ($i) => (int) $i)->all();
        $ringing = DB::table('friend_calls')->where('status', 'ringing')->where('meeting_code', $m->code)->pluck('callee_id')->map(fn ($i) => (int) $i)->all();
        $seen = []; $out = [];
        foreach (array_merge($dir['friends'] ?? [], $dir['team'] ?? []) as $x) {
            $id = (int) $x['id']; if (isset($seen[$id])) continue; $seen[$id] = 1;
            $out[] = ['id' => $id, 'name' => $x['name'], 'avatar_url' => $x['avatar_url'] ?? null, 'online' => (bool) ($x['online'] ?? false), 'status' => $x['status_label'] ?? null,
                'in_call' => in_array($id, $inside, true), 'ringing' => in_array($id, $ringing, true)];
        }
        usort($out, fn ($a, $b) => [$b['online'], $a['name']] <=> [$a['online'], $b['name']]);
        return response()->json(['data' => $out]);
    }

    public function invite(Request $r, string $code): JsonResponse
    {
        $p = $this->me($r, $code);
        if (!$p || !$p->user_id) return $this->deny();
        $d = $r->validate(['user_id' => 'required|integer']);
        [$ok, $msg] = app(\App\Services\Friends\FriendCallService::class)->inviteToMeeting($r->user(), $code, (int) $d['user_id']);
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }

    /** host edits title / time / length (API: JSON; website: form) */
    public function update(Request $r, string $code)
    {
        $d = $r->validate(['title' => 'nullable|string|max:150', 'description' => 'nullable|string|max:1000', 'scheduled_at' => 'nullable|string|max:40', 'date' => 'nullable|date_format:Y-m-d', 'time' => 'nullable|date_format:H:i', 'duration' => 'nullable|integer|min:15|max:480']);
        if (!array_key_exists('scheduled_at', $d) && !empty($d['date'])) $d['scheduled_at'] = $d['date'] . ' ' . ($d['time'] ?? '09:00');
        [$ok, $msg] = $this->svc->update($r->user(), $code, $d + ($r->has('waiting_room') ? ['waiting_room' => $r->boolean('waiting_room')] : []));
        if ($r->expectsJson()) return response()->json(['message' => $msg, 'data' => $ok ? $this->svc->summary($this->svc->find($code), $r->user()) : null], $ok ? 200 : 422);
        return redirect('/meetings')->with($ok ? 'status' : 'error', $msg);
    }

    public function ics(Request $r, string $code)
    {
        $m = $this->svc->find($code);
        abort_unless($m, 404);
        return response($this->svc->ics($m), 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="meeting-' . $m->code . '.ics"']);
    }
}
