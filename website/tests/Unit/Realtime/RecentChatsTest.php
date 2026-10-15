<?php

use App\Livewire\Friends\RecentChats;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::unprepared(file_get_contents(base_path('tests/Fixtures/realtime.sqlite.sql')));
    DB::statement('CREATE TABLE friend_requests (id INTEGER PRIMARY KEY, requester_id INTEGER, addressee_id INTEGER, status TEXT, responded_at TEXT, unfriended_at TEXT, unfriended_by INTEGER, created_at TEXT, updated_at TEXT)');
    DB::statement('CREATE TABLE workspace_members (workspace_id INTEGER, user_id INTEGER, role TEXT, status TEXT, last_active_at TEXT)');
    DB::statement('CREATE TABLE user_presence (user_id INTEGER, last_seen_at TEXT, show_presence INTEGER)');
    DB::statement('CREATE TABLE friend_chat_clears (user_id INTEGER, other_id INTEGER, before_id INTEGER)');
    foreach ([1 => 'Viewer', 2 => 'Alice', 3 => 'Bob', 4 => 'Unrelated'] as $id => $name) {
        DB::table('users')->insert(['id' => $id, 'uuid' => 'synthetic-'.$id, 'name' => $name, 'email' => $id.'@example.test', 'notification_preferences' => '{}']);
    }
    DB::table('friend_requests')->insert([
        ['requester_id' => 1, 'addressee_id' => 2, 'status' => 'accepted'],
        ['requester_id' => 3, 'addressee_id' => 1, 'status' => 'accepted'],
    ]);
    $this->actingAs(User::find(1));
});

test('an empty visible chats component receives its first conversation and unread badge without navigation', function () {
    $component = Livewire::test(RecentChats::class)
        ->assertSee('Your conversations will appear here.')
        ->assertSeeHtml('wire:poll.5s.visible');
    DB::table('friend_messages')->insert(['sender_id' => 2, 'recipient_id' => 1, 'body' => 'First incoming message', 'created_at' => now()]);
    $component->dispatch('realtime-refresh')
        ->assertSee('Alice')->assertSee('First incoming message')
        ->assertViewHas('recentChats', fn ($rows) => count($rows) === 1 && $rows[0]['unread'] === 1);
    DB::table('friend_messages')->update(['read_at' => now()]);
    $component->call('$refresh')->assertViewHas('recentChats', fn ($rows) => $rows[0]['unread'] === 0);
});

test('polling updates previews and ordering while preserving viewer privacy', function () {
    DB::table('friend_messages')->insert([
        ['sender_id' => 2, 'recipient_id' => 1, 'body' => 'Older Alice message', 'created_at' => now()],
        ['sender_id' => 3, 'recipient_id' => 1, 'body' => 'Latest Bob message', 'created_at' => now()],
    ]);
    $component = Livewire::test(RecentChats::class)->assertSeeInOrder(['Bob', 'Alice']);
    DB::table('friend_messages')->insert(['sender_id' => 2, 'recipient_id' => 1, 'body' => 'New Alice message', 'created_at' => now()]);
    DB::table('friend_messages')->insert(['sender_id' => 2, 'recipient_id' => 1, 'body' => 'Private recording', 'visible_to' => 2, 'created_at' => now()]);
    DB::table('friend_messages')->insert(['sender_id' => 4, 'recipient_id' => 3, 'body' => 'Other conversation', 'created_at' => now()]);
    $component->call('$refresh')->assertSeeInOrder(['Alice', 'Bob'])
        ->assertSee('New Alice message')->assertDontSee('Older Alice message')
        ->assertDontSee('Private recording')->assertDontSee('Other conversation');
});

test('people directory renders friends with realtime previews', function () {
    DB::table('friend_messages')->insert(['sender_id' => 2, 'recipient_id' => 1, 'body' => 'People preview', 'created_at' => now()]);
    Livewire::test(App\Livewire\Friends\FriendsHub::class)->assertSee('Alice')->assertSee('People preview');
});

test('missed call history has no delivery status or ticks in the recent conversation list', function () {
    DB::table('friend_messages')->insert(['sender_id' => 1, 'recipient_id' => 2, 'kind' => 'call', 'body' => 'Missed call', 'read_at' => now(), 'created_at' => now()]);
    Livewire::test(RecentChats::class)->assertSee('Missed call')->assertDontSee('✓')
        ->assertViewHas('recentChats', fn ($rows) => $rows[0]['status'] === null);
});


test('unfriending keeps history visible and blocks messaging even between teammates', function () {
    DB::table('friend_messages')->insert(['sender_id' => 2, 'recipient_id' => 1, 'body' => 'Keep this message', 'created_at' => now()]);
    DB::table('workspace_members')->insert([['workspace_id' => 1, 'user_id' => 1], ['workspace_id' => 1, 'user_id' => 2]]);
    app(App\Services\Friends\FriendService::class)->remove(User::find(1), 2);
    $chat = app(App\Services\Friends\FriendChatService::class);
    foreach ([[1, 2], [2, 1]] as [$me, $other]) {
        expect($chat->areFriends($me, $other))->toBeFalse();
        expect($chat->relation($me, $other)['read_only'])->toBeTrue();
        expect($chat->viewableOrFail(User::find($me), $other)->id)->toBe($other);
        expect(collect($chat->recent(User::find($me)))->pluck('id')->all())->toContain($other);
        expect($chat->send(User::find($me), $other, 'Must not send', null)[0])->toBeFalse();
    }
    expect(DB::table('friend_messages')->where('body', 'Keep this message')->exists())->toBeTrue();
    expect(DB::table('friend_messages')->where('body', 'Must not send')->exists())->toBeFalse();
});

test('a renewed request and its cancellation preserve former-friend history access', function () {
    DB::table('friend_requests')->where('requester_id', 1)->where('addressee_id', 2)->update(['status' => 'unfriended', 'unfriended_at' => now(), 'unfriended_by' => 1]);
    DB::table('friend_messages')->insert(['sender_id' => 2, 'recipient_id' => 1, 'body' => 'Old conversation', 'created_at' => now()]);
    Illuminate\Support\Facades\Notification::fake();
    $service = app(App\Services\Friends\FriendService::class);
    expect($service->send(User::find(1), 2)[0])->toBeTrue();
    $chat = app(App\Services\Friends\FriendChatService::class);
    expect($chat->wasFriends(1, 2))->toBeTrue();
    expect($chat->areFriends(1, 2))->toBeFalse();
    DB::statement('CREATE TABLE friendship_events (id INTEGER PRIMARY KEY, user_low_id INTEGER, user_high_id INTEGER, actor_id INTEGER, event TEXT, created_at TEXT)');
    $profile = app(App\Services\Friends\PeopleService::class)->profile(User::find(1), 2);
    expect($profile['is_former'])->toBeTrue();
    expect($profile['can_talk'])->toBeFalse();
    $id = DB::table('friend_requests')->where('requester_id', 1)->where('addressee_id', 2)->value('id');
    $service->cancel(User::find(1), $id);
    expect($chat->wasFriends(1, 2))->toBeTrue();
    expect(DB::table('friend_messages')->where('body', 'Old conversation')->exists())->toBeTrue();
    expect(collect($chat->recent(User::find(1)))->pluck('id')->all())->toContain(2);
});

test('mobile fetching never marks seen and explicit receipts exclude later arrivals', function () {
    $id = DB::table('friend_messages')->insertGetId(['sender_id' => 2, 'recipient_id' => 1, 'body' => 'Visible', 'created_at' => now()]);
    $request = Illuminate\Http\Request::create('/api/mobile/friends/2/messages', 'GET');
    $request->setUserResolver(fn () => User::find(1));
    app(App\Http\Controllers\Friends\FriendChatController::class)->messages($request, 2);
    expect(DB::table('friend_messages')->where('id', $id)->value('read_at'))->toBeNull();
    $later = DB::table('friend_messages')->insertGetId(['sender_id' => 2, 'recipient_id' => 1, 'body' => 'Arrived later', 'created_at' => now()]);
    $chat = app(App\Services\Friends\FriendChatService::class);
    expect($chat->markRead(User::find(1), 2, $id))->toBe(1);
    expect(DB::table('friend_messages')->where('id', $id)->value('read_at'))->not->toBeNull();
    expect(DB::table('friend_messages')->where('id', $later)->value('read_at'))->toBeNull();
    expect($chat->markRead(User::find(1), 2, $id))->toBe(0);
});

test('read acknowledgements reject messages outside visible authorized history', function () {
    $chat = app(App\Services\Friends\FriendChatService::class);
    foreach ([['sender_id' => 3, 'recipient_id' => 1], ['sender_id' => 2, 'recipient_id' => 1, 'visible_to' => 2]] as $row) {
        $id = DB::table('friend_messages')->insertGetId($row + ['body' => 'Private', 'created_at' => now()]);
        try { $chat->markRead(User::find(1), 2, $id); $this->fail('Accepted private read boundary'); }
        catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { expect($e->getStatusCode())->toBe(422); }
    }
    expect(DB::table('friend_messages')->whereNotNull('read_at')->count())->toBe(0);
});

test('read receipt returns remaining unread count and clears only the viewed chat', function () {
    $id = DB::table('friend_messages')->insertGetId(['sender_id' => 2, 'recipient_id' => 1, 'body' => 'Viewed', 'created_at' => now()]);
    DB::table('friend_messages')->insert(['sender_id' => 3, 'recipient_id' => 1, 'body' => 'Other chat', 'created_at' => now()]);
    $request = Illuminate\Http\Request::create('/friends/api/messages/2/read', 'POST', ['through_message_id' => $id]);
    $request->setUserResolver(fn () => User::find(1));
    $response = app(App\Http\Controllers\Friends\FriendChatController::class)->read($request, 2);
    expect($response->getData(true)['unread'])->toBe(1);
    expect(app(App\Services\Friends\FriendChatService::class)->unreadCounts(User::find(1)))->toBe([3 => 1]);
});

test('unfriending keeps both users conversation positions and last message previews', function () {
    DB::table('friend_requests')->insert(['requester_id' => 2, 'addressee_id' => 3, 'status' => 'accepted']);
    DB::table('friend_messages')->insert([
        ['sender_id' => 2, 'recipient_id' => 1, 'body' => 'Old shared conversation', 'created_at' => now()->subDays(2)],
        ['sender_id' => 3, 'recipient_id' => 1, 'body' => 'Newer for viewer', 'created_at' => now()],
        ['sender_id' => 3, 'recipient_id' => 2, 'body' => 'Newer for Alice', 'created_at' => now()],
    ]);
    $chat = app(App\Services\Friends\FriendChatService::class);
    $before = [];
    foreach ([1, 2] as $id) $before[$id] = $chat->recent(User::find($id));
    app(App\Services\Friends\FriendService::class)->remove(User::find(1), 2);
    foreach ([1 => 2, 2 => 1] as $id => $other) {
        $after = $chat->recent(User::find($id));
        expect(array_column($after, 'id'))->toBe(array_column($before[$id], 'id'));
        expect(array_column($after, 'at'))->toBe(array_column($before[$id], 'at'));
        expect(array_column($after, 'preview'))->toBe(array_column($before[$id], 'preview'));
        expect(collect($after)->firstWhere('id', $other)['former'])->toBeTrue();
    }
});
