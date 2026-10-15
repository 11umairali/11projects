<?php

use App\Models\User;
use App\Services\Friends\FriendFinder;
use App\Services\Friends\FriendService;
use App\Support\PhoneKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::unprepared(file_get_contents(base_path('tests/Fixtures/realtime.sqlite.sql')));
    DB::statement('CREATE TABLE friend_requests (id INTEGER PRIMARY KEY, requester_id INTEGER, addressee_id INTEGER, status TEXT, created_at TEXT, updated_at TEXT, responded_at TEXT, unfriended_at TEXT, unfriended_by INTEGER)');
    DB::statement('CREATE TABLE workspace_members (workspace_id INTEGER, user_id INTEGER, role TEXT)');
    DB::statement('CREATE TABLE user_presence (user_id INTEGER, findable INTEGER, show_presence INTEGER, last_seen_at TEXT)');
    Cache::flush();
    Notification::fake();
    foreach ([1 => 'Viewer', 2 => 'Alice Example', 3 => 'Numeric Person', 4 => 'Alice Other'] as $id => $name) {
        DB::table('users')->insert(['notification_preferences' => '{}', 'id' => $id, 'uuid' => 'synthetic-'.$id, 'name' => $name, 'email' => $id.'@example.test', 'username' => $id === 2 ? '@alice.example' : ($id === 3 ? '1234567890' : 'user'.$id)]);
    }
    DB::table('users')->where('id', 2)->update(['phone' => '+1 202 555 0199', 'phone_key' => PhoneKey::of('+1 202 555 0199'), 'phone_verified_at' => now(), 'discoverable' => 1]);
    $this->me = User::find(1);
});

test('discovery supports names usernames full email and formatted phone numbers', function (string $query, int $id) {
    $result = app(FriendFinder::class)->search($this->me, $query)['results'];
    expect(array_column($result, 'id'))->toContain($id);
    expect($result[0])->not->toHaveKeys(['email', 'phone']);
})->with([
    ['alice.example', 2], ['@ALICE.EXAMPLE', 2], ['Alice Example', 2], ['Example', 2],
    ['2@EXAMPLE.TEST', 2], ['+1 202 555 0199', 2], ['(202) 555-0199', 2],
    ['1234567890', 3], ['@1234567890', 3],
]);

test('discovery respects privacy settings and treats wildcard characters literally', function () {
    DB::table('user_presence')->insert(['user_id' => 2, 'findable' => 0]);
    expect(app(FriendFinder::class)->search($this->me, 'Alice Example')['results'])->toBe([]);
    DB::table('users')->where('id', 2)->update(['discoverable' => 0]);
    expect(app(FriendFinder::class)->search($this->me, '(202) 555-0199')['results'])->toBe([]);
    foreach (['ali%', 'ali_', '2@exam', 'user1'] as $q) expect(app(FriendFinder::class)->search($this->me, $q)['results'])->toBe([]);
});

test('discovered profiles can receive requests and return the updated relationship', function () {
    $finder = app(FriendFinder::class);
    $finder->search($this->me, 'alice.example');
    expect(app(FriendService::class)->send($this->me, 2)[0])->toBeTrue();
    expect($finder->search($this->me, 'alice.example')['results'][0]['relation'])->toBe('sent');
    expect(DB::table('friend_requests')->count())->toBe(1);
    app(FriendService::class)->send($this->me, 2);
    expect(DB::table('friend_requests')->count())->toBe(1);
});

test('device directory includes friends and pending requests but keeps hidden accounts private', function () {
    DB::table('friend_requests')->insert(['requester_id' => 1, 'addressee_id' => 2, 'status' => 'accepted']);
    $service = app(FriendService::class);
    $matches = $service->contactsForKeys($this->me, ['2025550199']);
    expect($matches)->toHaveCount(1)
        ->and($matches[0]['phone_key'])->toBe('2025550199')
        ->and($matches[0]['relation'])->toBe('accepted');
    DB::table('friend_requests')->update(['status' => 'pending']);
    expect($service->contactsForKeys($this->me, ['2025550199'])[0]['relation'])->toBe('pending');
    DB::table('friend_requests')->update(['status' => 'blocked']);
    expect($service->contactsForKeys($this->me, ['2025550199']))->toBe([]);
    DB::table('friend_requests')->delete();
    DB::table('users')->where('id', 2)->update(['discoverable' => 0]);
    expect($service->contactsForKeys($this->me, ['2025550199']))->toBe([]);
});

test('device directory matches authorize a friend request without exposing email', function () {
    $service = app(FriendService::class);
    $matches = $service->contactsForKeys($this->me, ['2025550199']);
    expect($matches[0])->not->toHaveKey('email');
    expect($service->send($this->me, 2)[0])->toBeTrue();
});

test('rejecting notifies the sender and allows another request without duplicating the relationship', function () {
    $service = app(FriendService::class);
    app(FriendFinder::class)->search($this->me, 'alice.example');
    expect($service->send($this->me, 2)[0])->toBeTrue();
    $id = DB::table('friend_requests')->value('id');
    expect($service->respond(User::find(2), $id, 'decline')[0])->toBeTrue();
    Notification::assertSentTo($this->me, \App\Notifications\FriendNotification::class, fn ($n) => $n->kind === 'friend_declined');
    expect($service->send($this->me, 2)[0])->toBeTrue();
    expect(DB::table('friend_requests')->count())->toBe(1);
    expect(DB::table('friend_requests')->value('status'))->toBe('pending');
    expect($service->cancel(User::find(2), $id)[0])->toBeFalse();
    expect($service->cancel($this->me, $id)[0])->toBeTrue();
    expect(DB::table('friend_requests')->count())->toBe(0);
});

test('blocked requests cannot be resent and acceptance notifies the sender', function () {
    $service = app(FriendService::class);
    app(FriendFinder::class)->search($this->me, 'alice.example');
    $service->send($this->me, 2);
    $id = DB::table('friend_requests')->value('id');
    expect($service->respond($this->me, $id, 'accept')[0])->toBeFalse();
    expect($service->respond(User::find(2), $id, 'accept')[0])->toBeTrue();
    Notification::assertSentTo($this->me, \App\Notifications\FriendNotification::class, fn ($n) => $n->kind === 'friend_accepted');
    expect(DB::table('friend_requests')->value('status'))->toBe('accepted');
    DB::table('friend_requests')->update(['status' => 'blocked']);
    expect($service->send($this->me, 2)[0])->toBeFalse();
});
