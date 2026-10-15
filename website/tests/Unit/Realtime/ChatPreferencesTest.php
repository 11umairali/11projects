<?php
use App\Models\User;
use App\Services\Friends\{ChatPreferences, FriendChatService, PeopleService};
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::unprepared(file_get_contents(base_path('tests/Fixtures/realtime.sqlite.sql')));
    DB::statement('CREATE TABLE friend_requests (id INTEGER PRIMARY KEY, requester_id INTEGER, addressee_id INTEGER, status TEXT, responded_at TEXT, unfriended_at TEXT, unfriended_by INTEGER, created_at TEXT, updated_at TEXT)');
    DB::statement('CREATE TABLE workspace_members (workspace_id INTEGER, user_id INTEGER, role TEXT, status TEXT, last_active_at TEXT)');
    DB::statement('CREATE TABLE user_presence (user_id INTEGER, findable INTEGER, last_seen_at TEXT, show_presence INTEGER)');
    DB::statement('CREATE TABLE friend_chat_clears (user_id INTEGER, other_id INTEGER, before_id INTEGER)');
    (require database_path('migrations/2026_10_15_000001_create_friend_chat_preferences.php'))->up();
    Cache::flush();
    for ($id = 1; $id <= 8; $id++) {
        DB::table('users')->insert(['id' => $id, 'uuid' => 'synthetic-'.$id, 'name' => 'Person '.$id, 'email' => $id.'@example.test', 'notification_preferences' => '{}']);
        if ($id > 1 && $id < 8) {
            DB::table('friend_requests')->insert(['requester_id' => 1, 'addressee_id' => $id, 'status' => 'accepted']);
            DB::table('friend_messages')->insert(['sender_id' => $id, 'recipient_id' => 1, 'body' => 'Message '.$id, 'created_at' => now()->addSeconds($id)]);
        }
    }
    $this->actingAs(User::find(1));
});

test('five pins are enforced atomically and all selections are authorized before mutation', function () {
    $prefs = app(ChatPreferences::class); $me = User::find(1);
    $prefs->apply($me, [2, 3, 4, 5], 'pin');
    expect(fn () => $prefs->apply($me, [6, 7], 'pin'))->toThrow(ValidationException::class);
    expect(DB::table('friend_chat_preferences')->where('pinned', true)->count())->toBe(4);
    expect(fn () => $prefs->apply($me, [2, 8], 'archive'))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(DB::table('friend_chat_preferences')->where('archived', true)->count())->toBe(0);
    $prefs->apply($me, [6], 'pin'); $prefs->apply($me, [2], 'unpin'); $prefs->apply($me, [7], 'pin');
    expect(DB::table('friend_chat_preferences')->where('pinned', true)->count())->toBe(5);
});

test('archive mute and pin are private persistent state and pins sort first', function () {
    $prefs = app(ChatPreferences::class); $me = User::find(1); $chat = app(FriendChatService::class);
    foreach (['archive', 'mute', 'pin'] as $action) $prefs->apply($me, [2], $action);
    $first = $chat->recent($me)[0];
    expect($first['id'])->toBe(2)->and($first['archived'])->toBeTrue()->and($first['muted'])->toBeTrue()->and($first['pinned'])->toBeTrue();
    expect($prefs->muted(2, 1))->toBeFalse();
    foreach (['unarchive', 'unmute', 'unpin'] as $action) $prefs->apply($me, [2], $action);
    expect($prefs->muted(1, 2))->toBeFalse();
    expect($chat->recent($me)[0]['id'])->toBe(7);
});

test('delete hides until a new message while clear retains the row and peers copy', function () {
    $prefs = app(ChatPreferences::class); $me = User::find(1); $chat = app(FriendChatService::class);
    $prefs->apply($me, [2], 'delete');
    expect(array_column($chat->recent($me), 'id'))->not->toContain(2);
    expect($chat->messages(User::find(2), 1, null, markRead: false)['data'])->toHaveCount(1);
    DB::table('friend_messages')->insert(['sender_id' => 2, 'recipient_id' => 1, 'body' => 'New message', 'created_at' => now()->addMinute()]);
    expect(array_column($chat->recent($me), 'id'))->toContain(2);
    $prefs->apply($me, [2], 'clear');
    expect($chat->messages($me, 2, null, markRead: false)['data'])->toBeEmpty();
    expect(collect($chat->recent($me))->firstWhere('id', 2)['preview'])->toBe('');
    expect($chat->messages(User::find(2), 1, null, markRead: false)['data'])->toHaveCount(2);
    $prefs->apply(User::find(2), [1], 'clear');
    expect(array_column($chat->recent($me), 'id'))->toContain(2);
    expect(DB::table('friend_requests')->where('addressee_id', 2)->value('status'))->toBe('accepted');
});

test('muted chats stay unread without appearing as the latest alert', function () {
    $prefs = app(ChatPreferences::class); $me = User::find(1);
    $prefs->apply($me, [2, 3, 4, 5, 6, 7], 'mute');
    $summary = app(FriendChatService::class)->liveSummary($me);
    expect($summary['unread'])->toBe(6)->and($summary['latest'])->toBeNull();
});

test('web multiselection exposes clear chats and moves chats to archive', function () {
    Livewire::test(App\Livewire\Friends\ChatList::class)->call('toggle', 2)->call('toggle', 3)
        ->assertSee('Clear chats')->assertDontSee('View profile')->assertDontSee('Call history')
        ->call('organize', 'archive')->assertDontSee('Message 2')->call('folder', true)->assertSee('Message 2');
});

test('discovered nonfriends expose limited details and cannot send or bypass a block', function () {
    Cache::put('friend_candidates:1', [8], 60);
    $profile = app(PeopleService::class)->profile(User::find(1), 8);
    expect($profile['name'])->toBe('Person 8')->and($profile['can_talk'])->toBeFalse();
    foreach (['email', 'phone', 'timezone', 'history', 'status_label'] as $private) expect($profile)->not->toHaveKey($private);
    expect(fn () => app(FriendChatService::class)->send(User::find(1), 8, 'Denied', null))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    DB::table('friend_requests')->insert(['requester_id' => 8, 'addressee_id' => 1, 'status' => 'blocked']);
    expect(fn () => app(PeopleService::class)->profile(User::find(1), 8))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});


test('notification email endpoints require workspace access before loading or sending', function () {
    $request = Illuminate\Http\Request::create('/inbox/api/conversations/9/send', 'POST', ['body' => 'Do not send']);
    $request->setUserResolver(fn () => User::find(1));
    expect(app(App\Http\Controllers\Api\Mobile\SendController::class)->reply($request, 9)->getStatusCode())->toBe(403);
    expect(app(App\Http\Controllers\Api\Mobile\InboxExtrasController::class)->messageBody($request, 9)->getStatusCode())->toBe(403);
});


test('legacy cleared conversations remain listed and web search finds existing peers', function () {
    $mid = DB::table('friend_messages')->where('sender_id', 2)->value('id');
    DB::table('friend_chat_clears')->insert(['user_id' => 1, 'other_id' => 2, 'before_id' => $mid]);
    $rows = app(FriendChatService::class)->recent(User::find(1));
    expect(collect($rows)->firstWhere('id', 2)['preview'])->toBe('');
    $list = new \App\Livewire\Friends\ChatList;
    $list->search = 'Person 2';
    expect(array_column($list->render()->getData()['chats'], 'id'))->toBe([2]);
});

test('new chat picker lists accepted active friends alphabetically even without messages', function () {
    DB::table('users')->where('id', 2)->update(['name' => 'Zoe']);
    DB::table('users')->where('id', 3)->update(['name' => 'alice']);
    DB::table('users')->where('id', 4)->update(['name' => 'Bob']);
    DB::table('users')->where('id', 5)->update(['status' => 'suspended']);
    DB::table('friend_requests')->where('addressee_id', 6)->update(['status' => 'pending']);
    DB::table('friend_requests')->where('addressee_id', 7)->update(['status' => 'blocked']);
    DB::table('friend_messages')->where('sender_id', 3)->delete();
    Livewire::test(App\Livewire\Friends\ChatList::class)->call('startChat')
        ->assertSet('newChat', true)->assertSeeInOrder(['alice', 'Bob', 'Zoe'])
        ->assertDontSee('Person 5')->assertDontSee('Person 6')->assertDontSee('Person 7')->assertDontSee('Person 8')
        ->assertSee(User::find(3)->chatUrl(), false)
        ->set('search', 'zoe')->assertSee('Zoe')->assertDontSee('alice')
        ->call('closePicker')->assertSet('newChat', false)->assertSet('search', '')->assertSee('Message 2');
});

test('notification View fetches the exact older message without history read receipts or another peers data', function () {
    $service = app(FriendChatService::class);
    $me = User::find(1);
    $id = (int) DB::table('friend_messages')->where('sender_id', 2)->value('id');
    for ($i = 0; $i < 65; $i++) DB::table('friend_messages')->insert(['sender_id' => 2, 'recipient_id' => 1, 'body' => 'Newer message '.$i, 'created_at' => now()]);
    $result = $service->messages($me, 2, null, null, false, $id);
    expect(array_column($result['data'], 'id'))->toBe([$id]);
    expect(DB::table('friend_messages')->where('id', $id)->value('read_at'))->toBeNull();
    $otherId = (int) DB::table('friend_messages')->where('sender_id', 3)->value('id');
    expect($service->messages($me, 2, null, null, false, $otherId)['data'])->toBeEmpty();
    DB::table('friend_chat_clears')->insert(['user_id' => 1, 'other_id' => 2, 'before_id' => $id]);
    expect($service->messages($me, 2, null, null, false, $id)['data'])->toBeEmpty();
});
