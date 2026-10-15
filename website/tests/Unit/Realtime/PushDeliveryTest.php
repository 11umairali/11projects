<?php

use App\Models\User;
use App\Services\FcmPush;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
    DB::statement('CREATE TABLE device_tokens (id INTEGER PRIMARY KEY, user_id INTEGER, token TEXT, platform TEXT)');
    DB::table('device_tokens')->insert([
        ['user_id' => 1, 'token' => 'synthetic-a', 'platform' => 'android'],
        ['user_id' => 1, 'token' => 'synthetic-b', 'platform' => 'android'],
    ]);
    Storage::fake('local');
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);
    Storage::disk('local')->put('fcm-test.json', json_encode(['project_id' => 'synthetic-project', 'client_email' => 'test@example.test', 'private_key' => $pem]));
    config(['services.fcm.credentials' => Storage::disk('local')->path('fcm-test.json'), 'services.apns.key_path' => null]);
    Cache::put('fcm_access_token', 'synthetic-access', 60);
    $this->user = new User(); $this->user->id = 1;
});

test('a failed device does not prevent high priority call delivery to another', function () {
    $sent = [];
    Http::fake(function ($request) use (&$sent) {
        $message = $request['message'];
        $sent[] = $message;
        if ($message['token'] === 'synthetic-a') throw new RuntimeException('synthetic timeout');
        return Http::response(['name' => 'delivered'], 200);
    });
    app(FcmPush::class)->sendCall($this->user, ['call_id' => 42, 'sent_at' => time()], 20);
    expect($sent)->toHaveCount(2);
    expect($sent[1]['android'])->toBe(['priority' => 'HIGH', 'ttl' => '20s']);
    expect($sent[1]['data']['call_id'])->toBe('42');
    expect($sent[1]['data']['recipient_user_id'])->toBe('1');
    expect($sent[1])->not->toHaveKey('notification');
});

test('configuration 404 does not delete registered devices but unregistered tokens are removed', function () {
    $unregistered = false;
    Http::fake(function () use (&$unregistered) {
        return Http::response(['error' => $unregistered ? ['details' => [['errorCode' => 'UNREGISTERED']]] : ['status' => 'NOT_FOUND']], 404);
    });
    app(FcmPush::class)->sendSilent($this->user, ['type' => 'call_cancel']);
    expect(DB::table('device_tokens')->count())->toBe(2);
    $unregistered = true;
    app(FcmPush::class)->sendSilent($this->user, ['type' => 'call_cancel']);
    expect(DB::table('device_tokens')->count())->toBe(0);
});

test('mail and social pushes carry visible high priority alerts and foreground data', function () {
    DB::table('device_tokens')->insert(['user_id' => 1, 'token' => 'synthetic-ios', 'platform' => 'ios']);
    $sent = [];
    Http::fake(function ($request) use (&$sent) { $sent[] = $request['message']; return Http::response(['name' => 'delivered'], 200); });
    app(FcmPush::class)->sendToUser($this->user, 'Email delivery failed', 'Synthetic subject', ['type' => 'email_bounced', 'notification_id' => 'synthetic-notification']);
    expect($sent)->toHaveCount(3);
    expect($sent[0]['notification']['title'])->toBe('Email delivery failed');
    expect($sent[0]['android']['notification']['icon'])->toBe('ic_stat_email');
    expect($sent[0]['android']['priority'])->toBe('HIGH');
    expect($sent[0]['data']['title'])->toBe('Email delivery failed');
    expect($sent[0]['data']['notification_id'])->toBe('synthetic-notification');
    expect($sent[2]['apns']['headers']['apns-push-type'])->toBe('alert');
    expect($sent[2]['apns']['headers']['apns-priority'])->toBe('10');
});

test('missing or invalid service-account configuration cannot mark push as enabled', function () {
    $push = app(FcmPush::class);
    config(['services.fcm.credentials' => null]);
    expect($push->enabled())->toBeFalse();
    $path = Storage::disk('local')->path('fcm-test.json');
    config(['services.fcm.credentials' => $path]);
    foreach (['not json', '{"project_id":"test"}', '{"project_id":"test","client_email":"test@example.test","private_key":"invalid"}'] as $json) {
        file_put_contents($path, $json);
        expect($push->enabled())->toBeFalse()->and($push->configurationError())->not->toBeNull();
    }
});

test('device registration retains the token but reports disabled server delivery truthfully', function () {
    foreach (['voip_token TEXT', 'app_version TEXT', 'apns_sandbox INTEGER', 'created_at TEXT', 'updated_at TEXT'] as $column) DB::statement('ALTER TABLE device_tokens ADD COLUMN '.$column);
    config(['services.fcm.credentials' => null]);
    $request = Illuminate\Http\Request::create('/api/v1/me/devices', 'POST', ['token' => 'new-synthetic-token', 'platform' => 'android', 'app_version' => '1.0.0+2']);
    $request->setUserResolver(fn () => $this->user);
    $response = app(App\Http\Controllers\Api\Mobile\MeController::class)->registerDevice($request);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true)['push_configured'])->toBeFalse();
    expect(DB::table('device_tokens')->where('token', 'new-synthetic-token')->value('app_version'))->toBe('1.0.0+2');
    config(['services.fcm.credentials' => Storage::disk('local')->path('fcm-test.json')]);
    $response = app(App\Http\Controllers\Api\Mobile\MeController::class)->registerDevice($request);
    expect($response->getData(true)['push_configured'])->toBeTrue();
});

test('push status diagnoses configuration and counts devices without exposing tokens', function () {
    DB::statement('ALTER TABLE device_tokens ADD COLUMN app_version TEXT');
    DB::statement('ALTER TABLE device_tokens ADD COLUMN voip_token TEXT');
    config(['services.fcm.credentials' => null]);
    $this->artisan('push:status', ['--user' => '1'])
        ->expectsOutputToContain('FIREBASE_CREDENTIALS')
        ->doesntExpectOutputToContain('synthetic-a')
        ->assertExitCode(1);
    config(['services.fcm.credentials' => Storage::disk('local')->path('fcm-test.json')]);
    $this->artisan('push:status', ['--user' => '1'])
        ->expectsOutputToContain('FCM: local credentials valid')
        ->expectsOutputToContain('Local checks only.')
        ->assertExitCode(0);
});

test('background phone alerts identify chat, video calls and meetings with labels and native icons', function () {
    $sent = [];
    Http::fake(function ($request) use (&$sent) { $sent[] = $request['message']; return Http::response(['name' => 'accepted'], 200); });
    foreach ([['type' => 'chat'], ['type' => 'missed_call', 'video' => true], ['type' => 'friend_meeting']] as $data) {
        app(FcmPush::class)->sendToUser($this->user, 'Alice', 'Synthetic alert', $data);
    }
    expect($sent[0]['notification']['title'])->toBe('Chat · Alice');
    expect($sent[0]['android']['notification']['icon'])->toBe('ic_stat_chat');
    expect($sent[2]['notification']['title'])->toBe('Missed video call · Alice');
    expect($sent[2]['android']['notification']['icon'])->toBe('ic_stat_video');
    expect($sent[4]['notification']['title'])->toBe('Meeting · Alice');
    expect($sent[4]['android']['notification']['icon'])->toBe('ic_stat_meeting');
});

test('sender identity survives database notifications through FCM while using a brand icon and action text', function () {
    $this->freezeTime();
    $sent = [];
    Http::fake(function ($request) use (&$sent) { $sent[] = $request['message']; return Http::response(['name' => 'accepted'], 200); });
    $notification = App\Notifications\FriendNotification::request('Alice Example', 'https://example.test/avatar.jpg');
    $notification->id = 'synthetic-request';
    app(App\Listeners\SendPushForNotification::class)->handle(new Illuminate\Notifications\Events\NotificationSent($this->user, $notification, 'database'));
    expect($sent)->toHaveCount(2);
    expect($sent[0]['data']['sender_name'])->toBe('Alice Example');
    expect($sent[0]['data']['created_at_iso'])->toBe(now()->toIso8601String());
    expect((int) $sent[0]['data']['sent_at'])->toBe(now()->timestamp);
    expect($sent[0]['notification']['title'])->toBe('Alice Example');
    expect($sent[0]['notification']['body'])->toBe('Sent you a friend request');
    expect($sent[0]['android']['notification']['image'])->toBe('https://example.test/avatar.jpg');
    expect($sent[0]['android']['notification']['icon'])->toBe('ic_stat_notify');
});

test('notification API retains sender identity for mobile and browser consumers', function () {
    DB::statement('CREATE TABLE meetings (id INTEGER PRIMARY KEY, code TEXT)');
    DB::statement('CREATE TABLE notifications (id TEXT PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT)');
    $data = App\Notifications\MeetingNotification::invited('Alice Example', 'Planning', 'Tomorrow', 'synthetic-room', 'https://example.test/avatar.jpg')->toArray($this->user);
    DB::table('notifications')->insert(['id' => 'synthetic', 'type' => App\Notifications\MeetingNotification::class, 'notifiable_type' => User::class, 'notifiable_id' => 1, 'data' => json_encode($data), 'created_at' => now()]);
    Illuminate\Support\Facades\Auth::shouldReceive('user')->once()->andReturn($this->user);
    $response = app(App\Http\Controllers\NotificationController::class)->index(new Illuminate\Http\Request())->getData(true);
    expect($response['unread_count'])->toBe(1);
    expect($response['notifications'][0]['sender_name'])->toBe('Alice Example');
    expect($response['notifications'][0]['created_at_iso'])->not->toBeEmpty();
    expect($response['notifications'][0]['sender_avatar'])->toBe('https://example.test/avatar.jpg');
    expect($response['notifications'][0]['action_url'])->toBe('/meet/synthetic-room');
    expect($response['notifications'][0]['session_entry']['can_join'])->toBeFalse();
    expect($response['notifications'][0]['body'])->toBe('Session unavailable');
});


test('meeting updates describe the update rather than a new invitation', function () {
    $data = (new App\Notifications\MeetingNotification('Meeting changed', 'Tomorrow', '/meet/test', 'Alice', '', 'meeting_updated'))->toArray($this->user);
    expect($data['type'])->toBe('meeting_updated');
    expect(App\Support\PushPresentation::action($data))->toContain('updated the meeting');
});

test('Firebase project mismatch is exposed instead of enabling push fallback suppression', function () {
    foreach (['voip_token TEXT', 'app_version TEXT', 'apns_sandbox INTEGER', 'created_at TEXT', 'updated_at TEXT'] as $column) DB::statement('ALTER TABLE device_tokens ADD COLUMN '.$column);
    $request = Illuminate\Http\Request::create('/api/v1/me/devices', 'POST', ['token' => 'synthetic-mismatch', 'platform' => 'android', 'firebase_project_id' => 'other-project']);
    $request->setUserResolver(fn () => $this->user);
    $response = app(App\Http\Controllers\Api\Mobile\MeController::class)->registerDevice($request)->getData(true);
    expect($response['push_configured'])->toBeFalse()->and($response['push_status'])->toBe('project_mismatch');
    expect(DB::table('device_tokens')->where('token', 'synthetic-mismatch')->exists())->toBeTrue();
});

test('provider checks validate without delivery or deleting token records and reveal sender mismatch', function () {
    $requests = [];
    Http::fake(function ($request) use (&$requests) {
        $requests[] = $request->data();
        return Http::response(['error' => ['details' => [['errorCode' => 'SENDER_ID_MISMATCH']]]], 403);
    });
    $result = app(FcmPush::class)->probeToken('synthetic-a', 'android');
    expect($result)->toBe(['accepted' => false, 'code' => 'SENDER_ID_MISMATCH']);
    expect($requests[0]['validate_only'])->toBeTrue();
    expect($requests[0]['message'])->not->toHaveKey('notification');
    expect(DB::table('device_tokens')->count())->toBe(2);
});

test('expired access token retries the same incoming call with refreshed authentication', function () {
    $push = new class extends FcmPush {
        public int $authCalls = 0;
        protected function auth(): ?array { return ['project' => 'synthetic-project', 'token' => ++$this->authCalls === 1 ? 'old-access' : 'new-access']; }
    };
    $requests = [];
    Http::fake(function ($request) use (&$requests) {
        $requests[] = $request;
        return $request->hasHeader('Authorization', 'Bearer old-access') ? Http::response(['error' => ['status' => 'UNAUTHENTICATED']], 401) : Http::response(['name' => 'accepted'], 200);
    });
    $push->sendCall($this->user, ['call_id' => '55', 'uuid' => 'synthetic-call'], 20);
    expect($requests)->toHaveCount(4);
    expect($requests[0]->data()['message'])->toBe($requests[1]->data()['message']);
    expect($requests[1]->hasHeader('Authorization', 'Bearer new-access'))->toBeTrue();
});

test('push status probe reports provider result without exposing device tokens', function () {
    foreach (['app_version TEXT', 'voip_token TEXT', 'apns_sandbox INTEGER'] as $column) DB::statement('ALTER TABLE device_tokens ADD COLUMN '.$column);
    Http::fake(fn () => Http::response(['name' => 'accepted'], 200));
    $this->artisan('push:status', ['--user' => '1', '--probe' => true])
        ->expectsOutputToContain('accepted')
        ->doesntExpectOutputToContain('synthetic-a')
        ->expectsOutputToContain('no notification was sent')
        ->assertExitCode(0);
});

test('relative credential paths work when an HTTP worker runs outside the Laravel root', function () {
    $absolute = Storage::disk('local')->path('fcm-test.json');
    expect(str_starts_with($absolute, base_path().DIRECTORY_SEPARATOR))->toBeTrue();
    config(['services.fcm.credentials' => substr($absolute, strlen(base_path()) + 1)]);
    $previous = getcwd();
    try {
        chdir(sys_get_temp_dir());
        expect(app(FcmPush::class)->enabled())->toBeTrue();
        expect(app(FcmPush::class)->projectId())->toBe('synthetic-project');
    } finally {
        chdir($previous);
    }
});


test('Google login rejection is reported as authentication instead of unreachable', function () {
    Cache::forget('fcm_access_token');
    Http::preventStrayRequests();
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'private-response'], 400)]);
    $result = app(FcmPush::class)->probeToken('synthetic-a', 'android');
    expect($result['code'])->toBe('authentication_rejected');
    expect($result['stage'])->toBe('authentication');
    expect(json_encode($result))->not->toContain('private-response');
    Http::assertSentCount(1);
});

test('a cold token cache authenticates before validating the phone without sending an alert', function () {
    Cache::forget('fcm_access_token');
    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fresh-access', 'expires_in' => 3600, 'token_type' => 'Bearer']),
        'fcm.googleapis.com/*' => Http::response(['name' => 'accepted']),
    ]);
    expect(app(FcmPush::class)->probeToken('synthetic-a', 'android')['accepted'])->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com') && $r->method() === 'POST');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com') && $r['validate_only'] === true && $r->hasHeader('Authorization', 'Bearer fresh-access'));
    Http::assertSentCount(2);
});

test('token cache failures do not prevent fresh authentication and incoming call delivery', function () {
    Cache::shouldReceive('get')->andThrow(new RuntimeException('private cache connection details'));
    Cache::shouldReceive('put')->andThrow(new RuntimeException('private cache connection details'));
    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fresh-access', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::response(['name' => 'accepted']),
    ]);
    app(FcmPush::class)->sendCall($this->user, ['call_id' => 42, 'sent_at' => time()], 20);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com') && $r['message']['android']['priority'] === 'HIGH');
    Http::assertSentCount(3); // One login, two registered phones.
});

test('failed cache invalidation cannot reuse a rejected access token for the retry', function () {
    Cache::shouldReceive('get')->andReturn('old-access');
    Cache::shouldReceive('forget')->andThrow(new RuntimeException('cache unavailable'));
    Cache::shouldReceive('put')->andThrow(new RuntimeException('cache unavailable'));
    Http::preventStrayRequests();
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'oauth2.googleapis.com')) return Http::response(['access_token' => 'fresh-access', 'expires_in' => 3600]);
        return $request->hasHeader('Authorization', 'Bearer old-access')
            ? Http::response(['error' => ['status' => 'UNAUTHENTICATED']], 401)
            : Http::response(['name' => 'accepted']);
    });
    expect(app(FcmPush::class)->probeToken('synthetic-a', 'android')['accepted'])->toBeTrue();
    Http::assertSentCount(3);
});

test('transport failures identify DNS TLS and timeout errors without disclosing requests', function ($errno, $expected) {
    $request = new GuzzleHttp\Psr7\Request('POST', 'https://fcm.googleapis.com');
    $cause = new GuzzleHttp\Exception\ConnectException('private transport details', $request, null, ['errno' => $errno]);
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('private outer details', 0, $cause));
    $result = app(FcmPush::class)->probeToken('synthetic-a', 'android');
    expect($result['code'])->toBe($expected);
    expect($result['stage'])->toBe('delivery');
    expect(json_encode($result))->not->toContain('private');
})->with([[6, 'provider_dns_error'], [7, 'provider_connection_refused'], [28, 'provider_timeout'], [60, 'provider_tls_error']]);

test('unexpected server exceptions are not misreported as network failure', function () {
    Http::fake(fn () => throw new RuntimeException('private application details'));
    $result = app(FcmPush::class)->probeToken('synthetic-a', 'android');
    expect($result['code'])->toBe('push_server_error');
    expect(json_encode($result))->not->toContain('private application details');
});

test('administrator probe shows the failed stage and repair hint', function () {
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('private details'));
    $this->artisan('push:status', ['--user' => '1', '--probe' => true])
        ->expectsOutputToContain('provider_unreachable')
        ->expectsOutputToContain('delivery: The hosting server could not contact Google')
        ->doesntExpectOutputToContain('private details')
        ->doesntExpectOutputToContain('synthetic-a')
        ->assertExitCode(1);
});


test('new Android builds get one native alert payload with category identity and safe reply target', function () {
    DB::statement('ALTER TABLE device_tokens ADD COLUMN app_version TEXT');
    DB::table('device_tokens')->where('token', 'synthetic-a')->update(['app_version' => '1.0.2+3']);
    $sent = [];
    Http::fake(function ($r) use (&$sent) { $sent[] = $r['message']; return Http::response(['name' => 'accepted']); });
    app(FcmPush::class)->sendToUser($this->user, 'Alice Example', 'Hello', ['type' => 'chat', 'from_id' => 2, 'sender_name' => 'Alice Example']);
    expect($sent[0])->not->toHaveKey('notification');
    expect($sent[0]['data'])->toMatchArray(['notification_layout' => 'v2', 'category_label' => 'Chat', 'reply_kind' => 'friend', 'reply_id' => '2', 'recipient_user_id' => '1']);
    expect($sent[0]['android']['priority'])->toBe('HIGH');
    expect($sent[1])->toHaveKey('notification'); // Older clients still receive an OS alert.
});

test('the shipped Android version selects native delivery for every ordinary alert category', function () {
    preg_match('/^version:\s*(\S+)/m', file_get_contents(base_path('../flutter_app/pubspec.yaml')), $version);
    DB::statement('ALTER TABLE device_tokens ADD COLUMN app_version TEXT');
    DB::table('device_tokens')->update(['app_version' => $version[1]]);
    Http::fake(['*' => Http::response(['name' => 'accepted'])]);
    foreach (['chat', 'email_received', 'friend_request', 'friend_accepted', 'meeting_invited', 'notification'] as $type) {
        app(FcmPush::class)->sendToUser($this->user, 'Alice Example', 'New update', ['type' => $type]);
    }
    Http::assertSentCount(12);
    Http::assertNotSent(fn ($request) => isset($request['message']['notification'])
        || ($request['message']['data']['notification_layout'] ?? null) !== 'v2'
        || ($request['message']['android']['priority'] ?? null) !== 'HIGH');
});

test('only incoming message notifications expose a reply target', function () {
    expect(App\Support\PushPresentation::replyTarget(['type' => 'email_received', 'action_url' => '/inbox?cid=7']))->toBe(['reply_kind' => 'conversation', 'reply_id' => '7']);
    foreach (['missed_call', 'email_failed', 'friend_request'] as $type) {
        expect(App\Support\PushPresentation::replyTarget(['type' => $type, 'from_id' => 2, 'action_url' => '/inbox?cid=7']))->toBe([]);
    }
});


test('new iOS notifications carry the action envelope and an account-bound reply payload', function () {
    DB::statement('ALTER TABLE device_tokens ADD COLUMN app_version TEXT');
    Http::fake(fn () => Http::response(['name' => 'delivered'], 200));
    DB::table('device_tokens')->insert(['user_id' => 1, 'token' => 'synthetic-ios-actions', 'platform' => 'ios', 'app_version' => '1.0.2+3']);
    app(App\Services\FcmPush::class)->sendToUser($this->user, 'New email', 'Subject and preview', [
        'type' => 'email_received', 'sender_name' => 'Alice Example', 'action_url' => '/inbox?cid=7',
    ]);
    Http::assertSent(function ($request) {
        $message = $request->data()['message'] ?? [];
        if (($message['token'] ?? '') !== 'synthetic-ios-actions') return false;
        $payload = $message['apns']['payload'];
        $reply = json_decode($payload['payload'], true);
        return $payload['dm_local_actions'] === '1' && $payload['aps']['category'] === 'MESSAGE_REPLY'
            && $payload['aps']['alert']['title'] === 'Alice Example'
            && $reply['recipient_user_id'] === '1' && $reply['reply_id'] === '7';
    });
});

test('real push test sends a native high priority alert rather than validation only', function () {
    Http::fake(fn () => Http::response(['name' => 'accepted'], 200));
    $this->artisan('push:status', ['--user' => '1', '--test' => true])
        ->expectsOutputToContain('Accepted does not confirm phone receipt')
        ->doesntExpectOutputToContain('synthetic-a')->assertExitCode(0);
    Http::assertSentCount(2);
    Http::assertSent(function ($request) {
        $data = $request->data();
        return empty($data['validate_only']) && !isset($data['message']['notification'])
            && $data['message']['android']['priority'] === 'HIGH'
            && $data['message']['data']['notification_layout'] === 'v2'
            && isset($data['message']['data']['notification_id']);
    });
});

test('real push test requires an explicit user and cannot be combined with probe', function () {
    Http::fake();
    $this->artisan('push:status', ['--test' => true])->assertExitCode(2);
    $this->artisan('push:status', ['--user' => '1', '--test' => true, '--probe' => true])->assertExitCode(2);
    Http::assertNothingSent();
});


test('system friend notifications use category full name and concise action on both phones', function () {
    DB::statement('ALTER TABLE device_tokens ADD COLUMN app_version TEXT');
    DB::table('device_tokens')->update(['app_version' => '1.0.5+6']);
    DB::table('device_tokens')->where('token', 'synthetic-b')->update(['platform' => 'ios']);
    Http::fake(fn () => Http::response(['name' => 'accepted']));
    app(FcmPush::class)->sendToUser($this->user, 'Alice accepted your friend request', 'You can now message each other.', [
        'type' => 'friend_accepted', 'sender_name' => 'Alice Example', 'notification_id' => 'friend-1',
    ]);
    Http::assertSent(fn ($r) => $r['message']['token'] === 'synthetic-a'
        && $r['message']['data']['category_label'] === 'Friend request accepted'
        && $r['message']['data']['sender_name'] === 'Alice Example'
        && $r['message']['data']['body'] === 'Accepted your friend request');
    Http::assertSent(fn ($r) => $r['message']['token'] === 'synthetic-b'
        && $r['message']['apns']['payload']['aps']['alert'] === [
            'title' => 'Alice Example', 'body' => 'Accepted your friend request',
        ]);
});

test('current registrations receive calls before stale device timeouts', function () {
    DB::statement('ALTER TABLE device_tokens ADD COLUMN updated_at TEXT');
    DB::table('device_tokens')->where('token', 'synthetic-a')->update(['updated_at' => '2026-01-01 00:00:00']);
    DB::table('device_tokens')->where('token', 'synthetic-b')->update(['updated_at' => '2026-10-08 00:00:00']);
    $sent = [];
    Http::fake(function ($r) use (&$sent) { $sent[] = $r['message']['token']; return Http::response(['name' => 'accepted']); });
    app(FcmPush::class)->sendCall($this->user, ['call_id' => 42, 'sent_at' => time()]);
    expect($sent)->toBe(['synthetic-b', 'synthetic-a']);
});

test('native iPhone calls do not wait on Firebase authentication', function () {
    DB::statement('ALTER TABLE device_tokens ADD COLUMN voip_token TEXT');
    DB::table('device_tokens')->where('token', 'synthetic-a')->delete();
    DB::table('device_tokens')->update(['platform' => 'ios', 'voip_token' => 'synthetic-voip']);
    Cache::forget('fcm_access_token');
    Http::fake();
    $this->mock(App\Services\ApnsVoip::class, function ($mock) {
        $mock->shouldReceive('enabled')->andReturn(true);
        $mock->shouldReceive('send')->once()->with('synthetic-voip', Mockery::on(fn ($data) => $data['call_id'] === '42'), false, 45)->andReturn(true);
    });
    app(FcmPush::class)->sendCall($this->user, ['call_id' => 42, 'sent_at' => time()]);
    Http::assertNothingSent();
});

test('device check sends a real data-only alert only on explicit request', function () {
    Http::fake(['*' => Http::response(['name' => 'synthetic-message'], 200)]);
    foreach ([false, true] as $send) {
        $request = Illuminate\Http\Request::create('/api/v1/me/devices/check-push', 'POST', ['token' => 'synthetic-a', 'send_test' => $send]);
        $request->setUserResolver(fn () => $this->user);
        $response = app(App\Http\Controllers\Api\Mobile\MeController::class)->checkPush($request)->getData(true);
        expect($response['test_sent'])->toBe($send);
        Http::assertSent(fn ($out) => $out['message']['token'] === 'synthetic-a'
            && ($send ? !isset($out['validate_only']) : $out['validate_only'] === true)
            && $out['message']['data']['type'] === ($send ? 'push_test' : 'push_connection_check')
            && !isset($out['message']['notification'])
            && $out['message']['android']['priority'] === 'HIGH');
    }
});

test('real test cannot target another users device or claim rejected delivery', function () {
    Http::fake(['*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503)]);
    $request = Illuminate\Http\Request::create('/api/v1/me/devices/check-push', 'POST', ['token' => 'synthetic-a', 'send_test' => true]);
    $request->setUserResolver(fn () => $this->user);
    $controller = app(App\Http\Controllers\Api\Mobile\MeController::class);
    expect($controller->checkPush($request)->getData(true)['test_sent'])->toBeFalse();
    Http::fake();
    $this->user->id = 2;
    try {
        $controller->checkPush($request);
        $this->fail('Expected device ownership denial');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }
    Http::assertNothingSent();
});

// Exercise actual event hooks through fake HTTP, without mocking the push service.
test('stored app events and saved chat reach recipient devices', function (string $type, bool $staleEventCache) {
    if ($staleEventCache) {
        // Reproduce a deployed event cache generated before the push listener existed.
        Illuminate\Support\Facades\Event::forget(Illuminate\Notifications\Events\NotificationSent::class);
        app()->getProvider(App\Providers\AppServiceProvider::class)->boot();
    }
    DB::unprepared(file_get_contents(base_path('tests/Fixtures/realtime.sqlite.sql')));
    DB::statement('CREATE TABLE notifications (id TEXT PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT)');
    foreach ([1 => 'Recipient', 2 => 'Sender'] as $id => $name) {
        DB::table('users')->insert(['id' => $id, 'uuid' => 'synthetic-'.$id, 'name' => $name, 'email' => $id.'@example.test', 'notification_preferences' => '{}']);
    }
    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'synthetic-delivery'], 200)]);
    if ($type === 'chat') {
        DB::statement('CREATE TABLE friend_requests (id INTEGER PRIMARY KEY, requester_id INTEGER, addressee_id INTEGER, status TEXT, unfriended_at TEXT)');
        DB::table('friend_requests')->insert(['requester_id' => 2, 'addressee_id' => 1, 'status' => 'accepted']);
        [$sent] = app(App\Services\Friends\FriendChatService::class)->send(User::findOrFail(2), 1, 'Synthetic chat', null);
        expect($sent)->toBeTrue();
        $id = DB::table('friend_messages')->value('id');
        app()->terminate();
    } else {
        $notification = match ($type) {
            'friend_request' => App\Notifications\FriendNotification::request('Sender'),
            'friend_accepted' => App\Notifications\FriendNotification::accepted('Sender'),
            'friend_declined' => App\Notifications\FriendNotification::declined('Sender'),
            default => new App\Notifications\InAppNotification('Synthetic alert', 'Synthetic body', '/inbox', type: $type),
        };
        User::findOrFail(1)->notify($notification);
        expect(DB::table('notifications')->count())->toBe(1);
        $id = DB::table('notifications')->value('id');
    }
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request['message']['data']['type'] === $type
        && $request['message']['data']['recipient_user_id'] === '1'
        && $request['message']['data'][$type === 'chat' ? 'message_id' : 'notification_id'] === (string) $id);
})->with(['chat', 'friend_request', 'friend_accepted', 'friend_declined', 'email_received', 'email_sent', 'workflow'])->with([false, true]);


test('new iOS chat category adds read action without breaking older clients', function () {
    DB::statement('ALTER TABLE device_tokens ADD COLUMN app_version TEXT');
    DB::table('device_tokens')->delete();
    foreach (['1.0.12+1', '1.0.13+2'] as $version) DB::table('device_tokens')->insert(['user_id' => 1, 'token' => 'synthetic-'.$version, 'platform' => 'ios', 'app_version' => $version]);
    Http::fake(fn () => Http::response(['name' => 'accepted'], 200));
    app(FcmPush::class)->sendToUser($this->user, 'Alice', 'Hello', ['type' => 'chat', 'from_id' => 2, 'message_id' => 42]);
    foreach (['1.0.12+1' => 'MESSAGE_REPLY', '1.0.13+2' => 'CHAT_REPLY'] as $version => $category) {
        Http::assertSent(fn ($request) => ($request['message']['token'] ?? '') === 'synthetic-'.$version && $request['message']['apns']['payload']['aps']['category'] === $category);
    }
});

test('persisted inbound email pushes once through the chat transport even without the notification event hook', function (bool $missingHook) {
    config(['activitylog.enabled' => false]);
    if ($missingHook) Illuminate\Support\Facades\Event::forget(Illuminate\Notifications\Events\NotificationSent::class);
    Illuminate\Support\Facades\Event::fake([App\Events\StateChanged::class]);
    DB::unprepared(file_get_contents(base_path('tests/Fixtures/realtime.sqlite.sql')));
    DB::statement('CREATE TABLE notifications (id TEXT PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT)');
    DB::statement('CREATE TABLE email_accounts (id INTEGER PRIMARY KEY, workspace_id INTEGER, user_id INTEGER, last_synced_at TEXT, deleted_at TEXT)');
    DB::statement('CREATE TABLE conversations (id INTEGER PRIMARY KEY, workspace_id INTEGER, email_account_id INTEGER, contact_id INTEGER, channel TEXT, deleted_at TEXT)');
    DB::statement('CREATE TABLE messages (id INTEGER PRIMARY KEY, uuid TEXT, workspace_id INTEGER, conversation_id INTEGER, direction TEXT, type TEXT, from_email TEXT, from_name TEXT, subject TEXT, body_text TEXT, sent_at TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
    DB::table('users')->insert(['id' => 1, 'uuid' => 'recipient', 'name' => 'Recipient', 'email' => 'recipient@example.test', 'notification_preferences' => '{}']);
    DB::table('email_accounts')->insert(['id' => 1, 'workspace_id' => 7, 'user_id' => 1, 'last_synced_at' => now()->subHour()]);
    DB::table('conversations')->insert(['id' => 4, 'workspace_id' => 7, 'email_account_id' => 1, 'channel' => 'email']);
    DB::statement('ALTER TABLE device_tokens ADD COLUMN app_version TEXT');
    DB::table('device_tokens')->update(['app_version' => '1.0.22+11']);
    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'accepted'], 200)]);
    $message = App\Models\Message::create(['workspace_id' => 7, 'conversation_id' => 4, 'direction' => 'inbound', 'type' => 'message',
        'from_email' => 'alice@example.test', 'from_name' => 'Alice Example', 'subject' => 'New email', 'sent_at' => now()]);
    expect(DB::table('notifications')->count())->toBe(1);
    $notificationId = DB::table('notifications')->value('id');
    Http::assertSentCount(2); // exactly once per registered device
    Http::assertSent(fn ($r) => $r['message']['data']['notification_id'] === $notificationId
        && $r['message']['data']['message_id'] === (string) $message->id
        && $r['message']['data']['sender_name'] === 'Alice Example'
        && $r['message']['data']['reply_kind'] === 'conversation'
        && $r['message']['data']['reply_id'] === '4'
        && $r['message']['android']['priority'] === 'HIGH' && !isset($r['message']['notification']));
    $message->update(['body_text' => 'Later body hydration']);
    Http::assertSentCount(2);
})->with([false, true]);
