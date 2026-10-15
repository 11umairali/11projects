<?php

use App\Events\StateChanged;
use App\Models\User;
use App\Services\Friends\FriendCallService;
use App\Services\Meetings\MeetingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'broadcasting.default' => 'log']);
    DB::purge('sqlite');
    DB::unprepared(file_get_contents(__DIR__.'/../../Fixtures/realtime.sqlite.sql'));
    DB::statement('CREATE TABLE meeting_signals (id INTEGER PRIMARY KEY, meeting_id INTEGER)');
    DB::statement('CREATE TABLE IF NOT EXISTS meeting_invitees (meeting_id INTEGER, user_id INTEGER)');
    Cache::flush();
    Event::fake([StateChanged::class]);
    DB::table('meetings')->insert(['id' => 1, 'code' => 'testroom', 'host_id' => 11, 'kind' => 'call', 'status' => 'live']);
    foreach ([11, 12, 13] as $index => $id) {
        DB::table('meeting_participants')->insert(['id' => $id, 'meeting_id' => 1, 'user_id' => $id,
            'name' => 'Synthetic participant '.$id, 'role' => $index === 0 ? 'host' : 'participant', 'status' => 'joined', 'last_seen_at' => now()]);
    }
    foreach ([[1, 11, 12, 'active', 0], [2, 11, 13, 'active', 1], [3, 12, 14, 'ringing', 1]] as [$id, $caller, $callee, $status, $group]) {
        DB::table('friend_calls')->insert(['id' => $id, 'caller_id' => $caller, 'callee_id' => $callee, 'status' => $status,
            'video' => 1, 'audio_only' => 1, 'meeting_code' => 'testroom', 'group_invite' => $group,
            'created_at' => now(), 'updated_at' => now(), 'answered_at' => $status === 'active' ? now()->subSeconds(20) : null]);
    }
    DB::table('meeting_signals')->insert(['id' => 1, 'meeting_id' => 1]);
    $this->user = function (int $id) { $user = new User(); $user->id = $id; return $user; };
});

test('either participant hanging up closes a two-person room and every live invitation', function (int $id) {
    DB::table('meeting_participants')->where('id', 13)->delete();
    $participant = DB::table('meeting_participants')->find($id);
    app(MeetingService::class)->leave($participant);
    expect(DB::table('meetings')->value('status'))->toBe('ended');
    expect(DB::table('meeting_participants')->where('status', 'joined')->count())->toBe(0);
    expect(DB::table('friend_calls')->orderBy('id')->pluck('status')->all())->toBe(['ended', 'ended', 'cancelled']);
    expect(DB::table('meeting_signals')->count())->toBe(0);
    expect(DB::table('friend_messages')->count())->toBe(3);
    app(MeetingService::class)->leave($participant);
    expect(DB::table('friend_messages')->count())->toBe(3);
})->with([11, 12]);

test('declining a group invitation leaves the shared call running', function () {
    expect(app(FriendCallService::class)->decline(($this->user)(14), 3)[0])->toBeTrue();
    expect(DB::table('friend_calls')->orderBy('id')->pluck('status')->all())->toBe(['active', 'active', 'declined']);
    expect(DB::table('meetings')->value('status'))->toBe('live');
    expect(DB::table('meeting_participants')->where('status', 'joined')->count())->toBe(3);
});

test('active call end endpoint closes a two-person room for both callers and callees', function (int $id) {
    DB::table('meeting_participants')->where('id', 13)->delete();
    expect(app(FriendCallService::class)->end(($this->user)($id), 1)[0])->toBeTrue();
    expect(DB::table('meetings')->value('status'))->toBe('ended');
    expect(DB::table('friend_calls')->whereIn('status', ['ringing', 'active'])->count())->toBe(0);
    expect(DB::table('friend_messages')->count())->toBe(3);
    app(FriendCallService::class)->end(($this->user)($id), 1);
    expect(DB::table('friend_messages')->count())->toBe(3);
})->with([11, 12]);

test('unrelated users cannot decline or terminate another call', function () {
    expect(app(FriendCallService::class)->decline(($this->user)(99), 3)[0])->toBeFalse();
    expect(app(FriendCallService::class)->end(($this->user)(99), 1)[0])->toBeFalse();
    expect(DB::table('meetings')->value('status'))->toBe('live');
    expect(DB::table('friend_messages')->count())->toBe(0);
    Event::assertNotDispatched(StateChanged::class);
});

test('leaving or declining an invitation to a regular meeting keeps other participants connected', function () {
    DB::table('meetings')->update(['kind' => 'meeting']);
    app(MeetingService::class)->leave(DB::table('meeting_participants')->find(12));
    expect(DB::table('meetings')->value('status'))->toBe('live');
    expect(DB::table('meeting_participants')->where('status', 'joined')->count())->toBe(2);
    expect(app(FriendCallService::class)->decline(($this->user)(14), 3)[0])->toBeTrue();
    expect(DB::table('meetings')->value('status'))->toBe('live');
});

test('failed room cleanup rolls back call status so hangup can be retried', function () {
    DB::table('meeting_participants')->where('id', 13)->delete();
    DB::statement("CREATE TRIGGER fail_cleanup BEFORE DELETE ON meeting_signals BEGIN SELECT RAISE(ABORT, 'synthetic cleanup failure'); END");
    expect(fn () => app(FriendCallService::class)->end(($this->user)(12), 1))->toThrow(\Illuminate\Database\QueryException::class);
    expect(DB::table('meetings')->value('status'))->toBe('live');
    expect(DB::table('friend_calls')->where('id', 1)->value('status'))->toBe('active');
    expect(DB::table('friend_messages')->count())->toBe(0);
    Event::assertNotDispatched(StateChanged::class);
});

test('meeting end publishes an immediate identity scoped terminal event', function () {
    app(MeetingService::class)->endByCode('testroom');
    Event::assertDispatched(StateChanged::class, fn ($e) => $e->broadcastWith() === ['type' => 'meeting_ended', 'code' => 'testroom']
        && array_map(fn ($c) => $c->name, $e->broadcastOn()) === ['private-user.11', 'private-user.12', 'private-user.13']);
    Event::assertDispatched(StateChanged::class, fn ($e) => $e->broadcastWith() === ['type' => 'call_cancel', 'call_id' => 1, 'reason' => 'ended', 'code' => 'testroom', 'group' => false, 'status' => 'ended']);
});

test('authorized call and meeting signals are delivered only to the peer with replay ids', function () {
    foreach (['from_pid INTEGER', 'to_pid INTEGER', 'type TEXT', 'payload TEXT', 'created_at TEXT'] as $column) DB::statement('ALTER TABLE meeting_signals ADD COLUMN '.$column);
    $payload = json_encode(['candidate' => 'synthetic-ice']);
    expect(app(FriendCallService::class)->signal(($this->user)(99), 1, 'ice', $payload)[0])->toBeFalse();
    Event::assertNotDispatched(StateChanged::class);
    expect(app(FriendCallService::class)->signal(($this->user)(11), 1, 'ice', $payload)[0])->toBeTrue();
    Event::assertDispatched(StateChanged::class, fn ($e) => $e->broadcastWith() === ['type' => 'call_signal', 'call_id' => 1,
        'signal' => ['id' => 1, 'type' => 'ice', 'payload' => $payload]] && $e->broadcastOn()[0]->name === 'private-user.12');
    expect(app(MeetingService::class)->signal(DB::table('meeting_participants')->find(11), 12, 'ice', $payload)[0])->toBeTrue();
    Event::assertDispatched(StateChanged::class, fn ($e) => $e->broadcastWith() === ['type' => 'meeting_signal', 'code' => 'testroom', 'to' => 12,
        'signal' => ['id' => 2, 'from' => 11, 'type' => 'ice', 'payload' => $payload]] && $e->broadcastOn()[0]->name === 'private-user.12');
});


test('host end retry acknowledges an already ended room without duplicate logs', function () {
    DB::table('meetings')->update(['kind' => 'meeting']);
    $svc = app(MeetingService::class);
    expect($svc->host(DB::table('meeting_participants')->find(11), 'end', null)[0])->toBeTrue();
    expect($svc->host(DB::table('meeting_participants')->find(11), 'end', null)[0])->toBeTrue();
    expect(DB::table('friend_messages')->count())->toBe(3);
    expect($svc->host(DB::table('meeting_participants')->find(12), 'end', null)[0])->toBeFalse();
});

test('leaving a regular meeting immediately invalidates the remaining peers roster', function () {
    DB::table('meetings')->update(['kind' => 'meeting']);
    app(MeetingService::class)->leave(DB::table('meeting_participants')->find(12));
    Event::assertDispatched(StateChanged::class, fn ($e) => $e->broadcastWith() === ['type' => 'meeting_changed', 'code' => 'testroom']
        && array_map(fn ($c) => $c->name, $e->broadcastOn()) === ['private-user.11', 'private-user.13']);
});


test('group departures preserve peers and duplicate leave requests do not end the remaining pair', function (string $kind, int $id) {
    DB::table('meetings')->update(['kind' => $kind]);
    $participant = DB::table('meeting_participants')->find($id);
    $svc = app(MeetingService::class);
    $svc->leave($participant);
    $svc->leave($participant);
    expect(DB::table('meetings')->value('status'))->toBe('live');
    expect(DB::table('meeting_participants')->where('status', 'joined')->count())->toBe(2);
    expect(DB::table('friend_messages')->count())->toBe(0);
    expect(DB::table('meeting_signals')->count())->toBe(1);
    Event::assertNotDispatched(StateChanged::class, fn ($e) => $e->broadcastWith()['type'] === 'meeting_ended');
})->with(['call', 'meeting'])->with([11, 12, 13]);

test('call end endpoint leaves only the requesting group participant', function () {
    $svc = app(FriendCallService::class);
    $svc->end(($this->user)(12), 1);
    $svc->end(($this->user)(12), 1);
    expect(DB::table('meetings')->value('status'))->toBe('live');
    expect(DB::table('meeting_participants')->where('status', 'joined')->pluck('id')->all())->toBe([11, 13]);
    // Disable chat summaries to isolate busy/current-call membership.
    DB::table('system_settings')->insert(['key' => 'friends_chat_enabled', 'value' => '0']);
    expect($svc->poll(($this->user)(12))['current'])->toBeNull();
    expect($svc->poll(($this->user)(11))['current'])->not->toBeNull();
});

test('host departure promotes the cohost and preserves end-for-everyone authority', function () {
    DB::table('meeting_participants')->where('id', 13)->update(['role' => 'cohost']);
    $svc = app(MeetingService::class);
    $svc->leave(DB::table('meeting_participants')->find(11));
    expect(DB::table('meeting_participants')->where('id', 13)->value('role'))->toBe('host');
    expect($svc->host(DB::table('meeting_participants')->find(12), 'end', null)[0])->toBeFalse();
    expect($svc->host(DB::table('meeting_participants')->find(13), 'end', null)[0])->toBeTrue();
    expect(DB::table('meetings')->value('status'))->toBe('ended');
});

test('two-person regular meetings end for both and close outstanding invitations', function () {
    DB::table('meetings')->update(['kind' => 'meeting']);
    DB::table('meeting_participants')->where('id', 13)->delete();
    app(MeetingService::class)->leave(DB::table('meeting_participants')->find(12));
    expect(DB::table('meetings')->value('status'))->toBe('ended');
    expect(DB::table('friend_calls')->whereIn('status', ['active', 'ringing'])->count())->toBe(0);
});

test('a waiting guest leaving does not close a two-person meeting', function () {
    DB::table('meeting_participants')->where('id', 13)->update(['status' => 'waiting', 'user_id' => null]);
    app(MeetingService::class)->leave(DB::table('meeting_participants')->find(13));
    expect(DB::table('meetings')->value('status'))->toBe('live');
    expect(DB::table('meeting_participants')->where('status', 'joined')->count())->toBe(2);
});

test('expired connections use the same participant-count policy', function (int $count) {
    if ($count === 2) DB::table('meeting_participants')->where('id', 13)->delete();
    DB::table('meeting_participants')->where('id', 12)->update(['last_seen_at' => now()->subSeconds(31)]);
    $svc = app(MeetingService::class);
    (new ReflectionMethod($svc, 'reap'))->invoke($svc, DB::table('meetings')->first());
    expect(DB::table('meetings')->value('status'))->toBe($count === 2 ? 'ended' : 'live');
})->with([2, 3]);

test('a guest uses the lobby and can join a group without a registered account', function () {
    DB::table('meetings')->update(['kind' => 'meeting', 'allow_guests' => 1, 'waiting_room' => 1]);
    $svc = app(MeetingService::class);
    [$ok, , $guest] = $svc->join(null, 'Guest visitor', 'testroom', true, false);
    expect($ok)->toBeTrue();
    expect($guest['status'])->toBe('waiting');
    expect($guest['token'])->not->toBeEmpty();
    expect($svc->host(DB::table('meeting_participants')->find(11), 'admit', $guest['pid'])[0])->toBeTrue();
    expect(DB::table('meeting_participants')->where('status', 'joined')->count())->toBe(4);
    $svc->leave(DB::table('meeting_participants')->find($guest['pid']));
    expect(DB::table('meetings')->value('status'))->toBe('live');
    expect(DB::table('meeting_participants')->where('status', 'joined')->count())->toBe(3);
});

test('guests cannot join private calls or ended meetings', function () {
    $svc = app(MeetingService::class);
    DB::table('meetings')->update(['allow_guests' => 0]);
    expect($svc->join(null, 'Guest', 'testroom', true, false)[0])->toBeFalse();
    $svc->endByCode('testroom');
    expect($svc->join(null, 'Guest', 'testroom', true, false)[0])->toBeFalse();
});

test('replacing a joined connection preserves a two-person room and host role', function () {
    DB::statement('CREATE TABLE IF NOT EXISTS meeting_invitees (meeting_id INTEGER, user_id INTEGER)');
    DB::table('meeting_participants')->where('id', 13)->delete();
    $host = ($this->user)(11);
    $host->name = 'Host';
    [$ok, , $connection] = app(MeetingService::class)->join($host, null, 'testroom', true, false);
    expect($ok)->toBeTrue();
    expect($connection['role'])->toBe('host');
    expect(DB::table('meetings')->value('status'))->toBe('live');
    expect(DB::table('meeting_participants')->where('status', 'joined')->count())->toBe(2);
    // A delayed pagehide from the replaced tab must not hang up the new session.
    app(MeetingService::class)->leave(DB::table('meeting_participants')->find(11));
    expect(DB::table('meetings')->value('status'))->toBe('live');
});

test('guest participant actions require the matching guest token', function () {
    DB::table('meetings')->update(['kind' => 'meeting']);
    [$ok, , $guest] = app(MeetingService::class)->join(null, 'Guest visitor', 'testroom', true, false);
    expect($ok)->toBeTrue();
    $controller = app(\App\Http\Controllers\Meetings\MeetingController::class);
    $request = \Illuminate\Http\Request::create('/meetings/api/testroom/leave', 'POST', ['pid' => $guest['pid']]);
    $request->setUserResolver(fn () => null);
    $request->headers->set('X-Guest-Token', 'wrong-token');
    // Leave is deliberately idempotent and opaque, but cannot mutate another guest.
    expect($controller->leave($request, 'testroom')->getStatusCode())->toBe(200);
    expect(DB::table('meeting_participants')->where('id', $guest['pid'])->value('status'))->toBe('waiting');
    $request->headers->set('X-Guest-Token', $guest['token']);
    expect($controller->leave($request, 'testroom')->getStatusCode())->toBe(200);
    expect(DB::table('meeting_participants')->where('id', $guest['pid'])->value('status'))->toBe('left');
    expect(DB::table('meetings')->value('status'))->toBe('live');
});


test('the first host join rings meeting invitees once and reconnects stay quiet', function () {
    DB::statement('CREATE TABLE IF NOT EXISTS meeting_invitees (meeting_id INTEGER, user_id INTEGER)');
    DB::table('meeting_invitees')->insert(['meeting_id' => 1, 'user_id' => 12]);
    DB::table('meetings')->update(['kind' => 'meeting']);
    DB::table('meeting_participants')->delete();
    $host = ($this->user)(11); $host->name = 'Host';
    $calls = Mockery::mock(FriendCallService::class);
    $calls->shouldReceive('inviteToMeeting')->once()->with($host, 'testroom', 12)->andReturn([true, 'Calling']);
    app()->instance(FriendCallService::class, $calls);
    $svc = app(MeetingService::class);
    expect($svc->join($host, null, 'testroom', true, true)[0])->toBeTrue();
    expect($svc->join($host, null, 'testroom', true, true)[0])->toBeTrue();
    expect(DB::table('meeting_participants')->where('status', 'joined')->count())->toBe(1);
});

test('removed and denied accounts cannot bypass moderation by joining again', function (string $status) {
    DB::table('meeting_participants')->where('id', 12)->update(['status' => $status]);
    DB::table('meeting_invitees')->insert(['meeting_id' => 1, 'user_id' => 12]);
    $result = app(MeetingService::class)->join(($this->user)(12), null, 'testroom', true, false);
    expect($result[0])->toBeFalse();
    expect(DB::table('meeting_participants')->count())->toBe(3);
})->with(['removed', 'denied']);

test('the original caller rejoins after host handover without creating two hosts', function () {
    $service = app(MeetingService::class);
    $service->leave(DB::table('meeting_participants')->find(11));
    $user = ($this->user)(11); $user->name = 'Returning caller';
    $result = $service->join($user, null, 'testroom', true, false);
    expect($result[0])->toBeTrue();
    expect($result[2]['role'])->toBe('participant');
    expect(DB::table('meeting_participants')->where('status', 'joined')->where('role', 'host')->count())->toBe(1);
});

test('rejoin discovery is account scoped and excludes moderated locked and ended rooms', function () {
    $service = app(MeetingService::class);
    $service->leave(DB::table('meeting_participants')->find(11));
    expect($service->resumableFor(($this->user)(11)))->toHaveCount(1);
    expect($service->resumableFor(($this->user)(99)))->toBeEmpty();
    DB::table('meetings')->where('id', 1)->update(['locked' => 1]);
    expect($service->resumableFor(($this->user)(11)))->toBeEmpty();
    DB::table('meetings')->where('id', 1)->update(['locked' => 0]);
    DB::table('meeting_participants')->where('id', 11)->update(['status' => 'removed']);
    expect($service->resumableFor(($this->user)(11)))->toBeEmpty();
    DB::table('meetings')->where('id', 1)->update(['status' => 'ended']);
    expect($service->resumableFor(($this->user)(12)))->toBeEmpty();
});

test('a call host using the legacy end-all action still leaves only their own group connection', function () {
    expect(app(MeetingService::class)->host(DB::table('meeting_participants')->find(11), 'end', null)[0])->toBeTrue();
    expect(DB::table('meetings')->value('status'))->toBe('live');
    expect(DB::table('meeting_participants')->where('status', 'joined')->count())->toBe(2);
});

test('link visitors obey the lobby while invited friends can enter directly', function () {
    DB::table('meetings')->update(['kind' => 'meeting', 'waiting_room' => 1]);
    $svc = app(MeetingService::class);
    $visitor = ($this->user)(15); $visitor->name = 'Link visitor';
    $friend = ($this->user)(16); $friend->name = 'Invited friend';
    DB::table('meeting_invitees')->insert(['meeting_id' => 1, 'user_id' => 16]);
    expect($svc->join($visitor, null, 'testroom', true, false)[2]['status'])->toBe('waiting');
    expect($svc->join($friend, null, 'testroom', true, false)[2]['status'])->toBe('joined');
});

test('an admitted member rejoins an unlocked meeting directly without reclaiming host', function () {
    DB::table('meetings')->update(['kind' => 'meeting', 'waiting_room' => 1]);
    DB::table('meeting_participants')->where('id', 11)->update(['joined_at' => now()]);
    $svc = app(MeetingService::class);
    $svc->leave(DB::table('meeting_participants')->find(11));
    $user = ($this->user)(11); $user->name = 'Returning host';
    $joined = $svc->join($user, null, 'testroom', true, false);
    expect($joined[0])->toBeTrue();
    expect($joined[2]['status'])->toBe('joined');
    expect($joined[2]['role'])->toBe('participant');
});

test('old invitations reflect current termination and moderation state', function () {
    $svc = app(MeetingService::class); $u = ($this->user)(11);
    expect($svc->entryState($u, 'testroom')['can_join'])->toBeTrue();
    DB::table('meetings')->update(['status' => 'ended']);
    expect($svc->entryState($u, 'testroom'))->toMatchArray(['status' => 'ended', 'can_join' => false, 'label' => 'Call ended']);
    DB::table('meetings')->update(['status' => 'live']);
    DB::table('meeting_participants')->where('id', 11)->update(['status' => 'removed']);
    expect($svc->entryState($u, 'testroom')['can_join'])->toBeFalse();
});

test('unstarted past invitations expire in both status lookup and join', function () {
    DB::table('meetings')->update(['kind' => 'meeting', 'status' => 'scheduled', 'scheduled_at' => now()->subHours(3), 'duration_min' => 60]);
    $svc = app(MeetingService::class); $u = ($this->user)(11);
    expect($svc->entryState($u, 'testroom')['status'])->toBe('expired');
    expect($svc->join($u, null, 'testroom', true, false)[0])->toBeFalse();
});

test('private links, future meetings and abandoned rooms do not advertise join', function () {
    $svc = app(MeetingService::class);
    expect($svc->entryState(($this->user)(99), 'testroom')['can_join'])->toBeFalse();
    DB::table('meetings')->update(['kind' => 'meeting', 'status' => 'scheduled', 'scheduled_at' => now()->addDays(1)]);
    expect($svc->entryState(($this->user)(12), 'testroom')['status'])->toBe('scheduled');
    DB::table('meetings')->update(['status' => 'live', 'scheduled_at' => null]);
    DB::table('meeting_participants')->update(['last_seen_at' => now()->subMinutes(2)]);
    expect($svc->entryState(($this->user)(12), 'testroom')['can_join'])->toBeFalse();
});

test('dismissing an invitation does not remove an ongoing group from the session directory', function () {
    DB::table('meeting_invitees')->insert(['meeting_id' => 1, 'user_id' => 14]);
    $svc = app(MeetingService::class);
    $rooms = $svc->resumableFor(($this->user)(14));
    expect($rooms)->toHaveCount(1);
    expect($rooms[0]['rejoin'])->toBeFalse();
    DB::table('meeting_participants')->insert(['meeting_id' => 1, 'user_id' => 14, 'name' => 'Denied member', 'status' => 'denied']);
    expect($svc->resumableFor(($this->user)(14)))->toBeEmpty();
});


test('the original unanswered invitation cannot tear down a room after other friends join', function (string $action) {
    DB::table('friend_calls')->where('id', 1)->update(['status' => 'ringing', 'created_at' => $action === 'expire' ? now()->subMinutes(2) : now()]);
    $svc = app(FriendCallService::class);
    if ($action === 'decline') $svc->decline(($this->user)(12), 1);
    if ($action === 'expire') $svc->poll(($this->user)(11));
    if ($action === 'hangup') $svc->end(($this->user)(11), 1);
    expect(DB::table('meetings')->value('status'))->toBe('live');
    expect(DB::table('meeting_participants')->where('status', 'joined')->count())->toBe($action === 'hangup' ? 2 : 3);
    expect(app(MeetingService::class)->entryState(($this->user)(11), 'testroom')['can_join'])->toBeTrue();
})->with(['decline', 'expire', 'hangup']);


test('lightweight call polling reports acceptance without loading media signals or chat', function () {
    $participant = DB::table('meeting_participants')->find(11);
    $service = app(MeetingService::class);
    $state = $service->poll($participant, 0, 0, true);
    expect($state['meeting']['call_status'])->toBe('active')
        ->and($state['me']['status'])->toBe('joined')
        ->and($state)->not->toHaveKey('signals')->not->toHaveKey('chat');
    DB::table('friend_calls')->where('id', 1)->update(['status' => 'ringing']);
    expect($service->poll($participant, 0, 0, true)['meeting']['call_status'])->toBe('ringing');
});
