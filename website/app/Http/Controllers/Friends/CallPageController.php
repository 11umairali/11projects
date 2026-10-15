<?php

namespace App\Http\Controllers\Friends;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Meetings\MeetingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The in-call screen of an audio / video call (like WhatsApp or Instagram): no "Join now", no meeting menus.
 * It talks to the same signalling endpoints as the phone app (/meetings/api/{code}/...), so website <-> app calls keep working.
 * Real meetings keep their own page (/meet/{code}).
 */
class CallPageController extends Controller
{
    /** only send people back to a page of the friends area */
    private function returnPath(Request $r): string
    {
        $raw = (string) $r->query('return_to', '');
        $path = parse_url($raw, PHP_URL_PATH) ?: '';
        if ($path !== '' && $path[0] === '/' && !str_starts_with($path, '//') && preg_match('#^/(friends|people)(/|$)#', $path)) {
            $query = parse_url($raw, PHP_URL_QUERY);
            return $path . ($query ? '?' . $query : '');
        }
        return '/friends';
    }

    public function show(Request $r, string $code)
    {
        $svc = app(MeetingService::class);
        $m = $svc->find($code);
        abort_unless($m && $m->kind === 'call', 404);
        $me = $r->user();
        $return = $this->returnPath($r);
        if (in_array($m->status, ['ended', 'cancelled'], true)) return redirect($return);

        $isHost = (int) $m->host_id === (int) $me->id;
        $invited = DB::table('meeting_invitees')->where('meeting_id', $m->id)->where('user_id', $me->id)->exists();
        abort_unless($isHost || $invited, 403, 'This call is private.');

        // the call row that belongs to me (the original call, or the invitation I received when I was added)
        $row = DB::table('friend_calls')->where('meeting_code', $m->code)
            ->where(fn ($q) => $q->where('caller_id', $me->id)->orWhere('callee_id', $me->id))
            ->orderByDesc('id')->first();
        $original = DB::table('friend_calls')->where('meeting_code', $m->code)->where('group_invite', 0)->orderBy('id')->first();
        $peerId = $row ? ((int) $row->caller_id === (int) $me->id ? (int) $row->callee_id : (int) $row->caller_id) : (int) $m->host_id;
        $peer = User::find($peerId);

        return view('calls.room', [
            'code' => $m->code,
            'callId' => $row ? (int) $row->id : 0,
            'audioOnly' => (bool) ($original->audio_only ?? false) || $r->boolean('audio'),
            'role' => ($row && (int) $row->caller_id === (int) $me->id) ? 'caller' : 'callee',
            'me' => ['id' => $me->id, 'name' => $me->name],
            'peer' => ['id' => $peerId, 'name' => $peer?->name ?? 'Friend', 'avatar_url' => ($peer && $peer->avatar_path) ? asset('storage/' . $peer->avatar_path) : null],
            'return' => $return,
        ]);
    }
}