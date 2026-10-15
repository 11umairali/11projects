<?php

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use App\Services\Friends\FriendCallService;
use App\Services\Recording\RecordingService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
    DB::unprepared(file_get_contents(__DIR__.'/../../Fixtures/realtime.sqlite.sql'));
    Cache::flush();
    Storage::fake('local');
    DB::table('meetings')->insert(['id' => 1, 'code' => 'test-room', 'host_id' => 11, 'kind' => 'call', 'status' => 'ended']);
    DB::table('friend_calls')->insert(['id' => 1, 'caller_id' => 11, 'callee_id' => 12, 'meeting_code' => 'test-room', 'status' => 'ended']);
    DB::table('recording_sessions')->insert(['id' => 1, 'meeting_id' => 1, 'mode' => 5, 'status' => 'stopped', 'private' => 1, 'started_by_user' => 11, 'started_by_pid' => 21, 'recorder_pid' => 21, 'started_at' => now()->subMinute(), 'stopped_at' => now()]);
    $this->meeting = DB::table('meetings')->find(1);
    $this->participant = (object) ['id' => 21, 'user_id' => 11];
    $this->upload = fn () => UploadedFile::fake()->create('recording.webm', 10, 'video/webm');
});

test('recording retry delivers exactly once to the correct private chat', function () {
    $service = app(RecordingService::class);
    expect($service->store($this->meeting, $this->participant, ($this->upload)(), 30, true, 1)[0])->toBeTrue();
    expect($service->store($this->meeting, $this->participant, ($this->upload)(), 30, true, 1)[0])->toBeTrue();
    expect(DB::table('recording_files')->count())->toBe(1);
    expect(DB::table('friend_messages')->count())->toBe(1);
    $message = DB::table('friend_messages')->first();
    expect((int) $message->visible_to)->toBe(11)->and((int) $message->call_id)->toBe(1);
    expect($message->kind)->toBe('file')->and($message->file_mime)->toBe('video/webm');
    $owner = new User(); $owner->id = 11;
    $other = new User(); $other->id = 12;
    expect($service->fileForUser($owner, 1))->not->toBeNull();
    expect($service->fileForUser($other, 1))->toBeNull();
});

test('failed chat delivery rolls back metadata and allows a successful retry', function () {
    DB::statement("CREATE TRIGGER fail_delivery BEFORE INSERT ON friend_messages BEGIN SELECT RAISE(ABORT, 'synthetic delivery failure'); END");
    $service = app(RecordingService::class);
    expect($service->store($this->meeting, $this->participant, ($this->upload)(), 30, true, 1)[0])->toBeFalse();
    expect(DB::table('recording_files')->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    DB::statement('DROP TRIGGER fail_delivery');
    expect($service->store($this->meeting, $this->participant, ($this->upload)(), 30, true, 1)[0])->toBeTrue();
    expect(DB::table('friend_messages')->count())->toBe(1);
});

test('delayed upload stays bound to its recording session', function () {
    DB::table('recording_sessions')->insert(['id' => 2, 'meeting_id' => 1, 'mode' => 5, 'status' => 'recording', 'private' => 1, 'started_by_user' => 11, 'recorder_pid' => 21, 'started_at' => now()]);
    $service = app(RecordingService::class);
    expect($service->store($this->meeting, $this->participant, ($this->upload)(), 30, true, 1)[0])->toBeTrue();
    expect((int) DB::table('recording_files')->value('session_id'))->toBe(1);
    expect(DB::table('recording_sessions')->where('id', 2)->value('status'))->toBe('recording');
    expect($service->store($this->meeting, (object) ['id' => 99, 'user_id' => 12], ($this->upload)(), 30, true, 1)[0])->toBeFalse();
});

test('expired incoming calls cannot be answered', function () {
    DB::table('friend_calls')->where('id', 1)->update(['status' => 'ringing', 'created_at' => now()->subMinutes(2), 'meeting_code' => null]);
    $user = new User(); $user->id = 12;
    expect(app(FriendCallService::class)->answer($user, 1)[0])->toBeFalse();
    expect(DB::table('friend_calls')->where('id', 1)->value('status'))->toBe('missed');
});

test('media permissions allow same origin camera and microphone', function () {
    $response = (new SecurityHeaders())->handle(Request::create('/meet/test-room'), fn () => response('room'));
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=(self)', 'microphone=(self)', 'geolocation=()');
});

test('missed audio rooms are described as audio rather than video in chat history', function () {
    DB::table('friend_calls')->where('id', 1)->update(['status' => 'ringing', 'video' => 1, 'audio_only' => 1, 'meeting_code' => null]);
    $caller = new User(); $caller->id = 11;
    app(FriendCallService::class)->end($caller, 1);
    expect(DB::table('friend_messages')->where('call_id', 1)->value('body'))->toBe('Missed audio call');
});

test('polled call metadata keeps the original expiry when the app moves to background', function () {
    $sent = now()->subSeconds(10)->startOfSecond();
    DB::table('friend_calls')->where('id', 1)->update(['status' => 'ringing', 'created_at' => $sent, 'meeting_code' => null]);
    $method = new ReflectionMethod(FriendCallService::class, 'shape');
    $call = $method->invoke(app(FriendCallService::class), DB::table('friend_calls')->find(1), 12);
    expect($call['sent_at'])->toBe($sent->timestamp)->and($call['ttl'])->toBe(45);
});
