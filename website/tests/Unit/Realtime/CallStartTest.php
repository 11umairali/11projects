<?php

use App\Http\Controllers\Friends\FriendCallController;
use App\Http\Controllers\Meetings\MeetingController;
use App\Models\User;
use App\Services\FcmPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
        'broadcasting.default' => 'null', 'services.fcm.credentials' => null, 'services.apns.key_path' => null]);
    DB::purge('sqlite');
    DB::unprepared(file_get_contents(base_path('tests/Fixtures/realtime.sqlite.sql')));
    DB::statement('CREATE TABLE friend_requests (id INTEGER PRIMARY KEY, requester_id INTEGER, addressee_id INTEGER, status TEXT, unfriended_at TEXT)');
    DB::statement('CREATE TABLE workspace_members (workspace_id INTEGER, user_id INTEGER)');
    DB::statement('CREATE TABLE meeting_invitees (meeting_id INTEGER, user_id INTEGER)');
    foreach ([1 => 'Caller', 2 => 'Recipient'] as $id => $name) {
        DB::table('users')->insert(['id' => $id, 'uuid' => 'synthetic-'.$id, 'name' => $name, 'email' => $id.'@example.test', 'notification_preferences' => '{}']);
    }
    DB::table('friend_requests')->insert(['requester_id' => 1, 'addressee_id' => 2, 'status' => 'accepted']);
    $this->actingAs(User::find(1));
    $this->assets = sys_get_temp_dir().'/dahimail-call-assets-'.bin2hex(random_bytes(8));
    File::makeDirectory($this->assets.'/build', 0755, true);
    app()->usePublicPath($this->assets);
    Vite::useHotFile($this->assets.'/hot');
});

afterEach(function () { File::deleteDirectory($this->assets); });

test('website call creation dispatches the recipient push after the response', function (bool $audioOnly) {
    $push = Mockery::mock(FcmPush::class);
    $push->shouldReceive('sendCall')->once()->withArgs(fn ($user, $data, $ttl) =>
        $user->id === 2 && $data['call_id'] > 0 && $data['audio_only'] === $audioOnly && $ttl > 0);
    app()->instance(FcmPush::class, $push);
    $request = Request::create('/friends/api/calls/video/2'.($audioOnly ? '?audio=1' : ''), 'POST');
    $request->setUserResolver(fn () => User::find(1));
    $response = app(FriendCallController::class)->startVideo($request, 2);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true)['data']['meeting'])->not->toBeEmpty();
    expect(DB::table('friend_calls')->value('status'))->toBe('ringing');
    $push->shouldNotHaveReceived('sendCall');
    app()->terminate();
})->with([true, false]);

test('a call room remains usable when deployment lacks the optional realtime entry', function (bool $manifestExists) {
    if ($manifestExists) File::put($this->assets.'/build/manifest.json', json_encode([
        'resources/js/app.js' => ['file' => 'assets/app-old.js', 'isEntry' => true],
    ]));
    DB::table('meetings')->insert(['code' => 'testroom', 'host_id' => 1, 'title' => 'Audio call', 'kind' => 'call']);
    $request = Request::create('/meet/testroom');
    $request->setUserResolver(fn () => User::find(1));
    $html = app(MeetingController::class)->room($request, 'testroom')->render();
    expect($html)->toContain('id="mt"')->toContain('data-code="testroom"');
})->with([true, false]);


test('a built call room still loads its realtime module', function () {
    File::put($this->assets.'/build/manifest.json', json_encode([
        'resources/js/realtime.js' => ['file' => 'assets/realtime-test.js', 'src' => 'resources/js/realtime.js', 'isEntry' => true],
    ]));
    DB::table('meetings')->insert(['code' => 'testroom', 'host_id' => 1, 'title' => 'Audio call', 'kind' => 'call']);
    $request = Request::create('/meet/testroom');
    $request->setUserResolver(fn () => User::find(1));
    $html = app(MeetingController::class)->room($request, 'testroom')->render();
    expect($html)->toContain('type="module"')->toContain('/build/assets/realtime-test.js');
});

test('answer commits immediately and cancellation pushes are sent after the response', function () {
    $id = DB::table('friend_calls')->insertGetId(['caller_id' => 1, 'callee_id' => 2, 'status' => 'ringing', 'created_at' => now(), 'updated_at' => now()]);
    $push = Mockery::mock(FcmPush::class);
    $push->shouldReceive('sendSilent')->once()->withArgs(fn ($user, $data) => $user->id === 2 && $data['reason'] === 'answered');
    app()->instance(FcmPush::class, $push);
    expect(app(App\Services\Friends\FriendCallService::class)->answer(User::find(2), $id)[0])->toBeTrue();
    expect(DB::table('friend_calls')->find($id)->status)->toBe('active');
    $push->shouldNotHaveReceived('sendSilent');
    app()->terminate();
});

test('rolled back call cancellation never reaches push delivery', function () {
    $push = Mockery::mock(FcmPush::class);
    $push->shouldNotReceive('sendSilent');
    app()->instance(FcmPush::class, $push);
    DB::beginTransaction();
    App\Services\Friends\FriendPush::stopRinging((object) ['id' => 42, 'caller_id' => 1, 'callee_id' => 2], 'cancelled');
    DB::rollBack();
    app()->terminate();
});

test('dialing does not sweep unrelated calls and the scheduler still expires them', function () {
    DB::table('friend_calls')->insert(['id' => 99, 'caller_id' => 8, 'callee_id' => 9, 'status' => 'ringing', 'created_at' => now()->subMinutes(2), 'updated_at' => now()->subMinutes(2)]);
    $service = app(App\Services\Friends\FriendCallService::class);
    expect($service->start(User::find(1), 2)[0])->toBeTrue();
    expect(DB::table('friend_calls')->find(99)->status)->toBe('ringing');
    $service->sweep();
    expect(DB::table('friend_calls')->find(99)->status)->toBe('missed');
});

test('private socket invitation has enough data to ring without polling and retains the original deadline', function () {
    config(['broadcasting.default' => 'log']);
    Illuminate\Support\Facades\Event::fake([App\Events\StateChanged::class]);
    $service = app(App\Services\Friends\FriendCallService::class);
    [$ok, , $call] = $service->start(User::find(1), 2, true, true);
    expect($ok)->toBeTrue();
    Illuminate\Support\Facades\Event::assertDispatched(App\Events\StateChanged::class, function ($event) use ($call) {
        $data = $event->broadcastWith();
        return ($data['type'] ?? '') === 'call' && ($data['incoming']['id'] ?? null) === $call['id']
            && $data['incoming']['meeting'] === $call['meeting'] && $data['incoming']['audio_only'] === true
            && $data['incoming']['sent_at'] === $call['sent_at']
            && array_map(fn ($channel) => $channel->name, $event->broadcastOn()) === ['private-user.2'];
    });
});
