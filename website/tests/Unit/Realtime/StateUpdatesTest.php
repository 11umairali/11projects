<?php

use App\Events\StateChanged;
use App\Models\Message;
use App\Models\User;
use App\Notifications\InAppNotification;
use App\Services\RealtimeUpdates;
use App\Services\Friends\FriendChatService;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false, 'broadcasting.default' => 'log']);
    DB::purge('sqlite');
    DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, notification_preferences TEXT)');
    DB::statement('CREATE TABLE conversations (id INTEGER PRIMARY KEY, workspace_id INTEGER, email_account_id INTEGER, channel TEXT, deleted_at TEXT)');
    DB::statement('CREATE TABLE email_accounts (id INTEGER PRIMARY KEY, workspace_id INTEGER, user_id INTEGER, last_synced_at TEXT, deleted_at TEXT)');
    DB::statement('CREATE TABLE messages (id INTEGER PRIMARY KEY, uuid TEXT, workspace_id INTEGER, conversation_id INTEGER, sender_id INTEGER, direction TEXT, type TEXT, delivery_status TEXT, subject TEXT, from_email TEXT, body_text TEXT, sent_at TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
    DB::table('users')->insert(['id' => 1, 'name' => 'Synthetic owner']);
    DB::table('email_accounts')->insert(['id' => 2, 'workspace_id' => 7, 'user_id' => 1, 'last_synced_at' => now()->subMinute()]);
    DB::table('conversations')->insert(['id' => 3, 'workspace_id' => 7, 'email_account_id' => 2, 'channel' => 'email']);
    Notification::fake();
    Event::fake([StateChanged::class]);
    $this->mail = ['workspace_id' => 7, 'conversation_id' => 3, 'sender_id' => 1, 'direction' => 'inbound', 'type' => 'message', 'delivery_status' => 'delivered', 'subject' => 'Synthetic email', 'from_email' => 'sender@example.test', 'sent_at' => now()];
});

test('private user channels reject other users', function () {
    require base_path('routes/channels.php');
    $channel = Broadcast::getChannels()['user.{userId}'];
    $user = new User(); $user->id = 1;
    expect($channel($user, '1'))->toBeTrue()->and($channel($user, '2'))->toBeFalse();
});

test('workspace broadcasts require membership in that exact workspace', function () {
    DB::statement('CREATE TABLE workspaces (id INTEGER PRIMARY KEY, deleted_at TEXT)');
    DB::statement('CREATE TABLE workspace_members (workspace_id INTEGER, user_id INTEGER)');
    DB::table('workspaces')->insert([['id' => 7], ['id' => 8]]);
    DB::table('workspace_members')->insert(['workspace_id' => 7, 'user_id' => 1]);
    require base_path('routes/channels.php');
    $channel = Broadcast::getChannels()['workspace.{workspaceId}'];
    expect($channel(User::find(1), 7))->toBeTrue()->and($channel(User::find(1), 8))->toBeFalse();
});

test('realtime invalidation waits for commit and does not broadcast rolled back changes', function () {
    DB::beginTransaction();
    RealtimeUpdates::users([1, 1, 2], ['type' => 'friends']);
    Event::assertNotDispatched(StateChanged::class);
    DB::rollBack();
    Event::assertNotDispatched(StateChanged::class);
    DB::transaction(fn () => RealtimeUpdates::users([1, 1, 2], ['type' => 'friends']));
    Event::assertDispatched(StateChanged::class, function ($event) {
        return array_map(fn ($channel) => $channel->name, $event->broadcastOn()) === ['private-user.1', 'private-user.2'];
    });
});

test('new mail notifies the account owner once and body hydration does not repeat alerts', function () {
    $message = Message::create($this->mail);
    $message->update(['body_text' => 'Loaded after header sync']);
    Notification::assertSentTo(User::find(1), InAppNotification::class, fn ($n) => $n->type === 'email_received' && $n->actionUrl === '/inbox?cid=3' && $n->senderName === 'sender@example.test');
    Notification::assertCount(1);
    Event::assertDispatched(StateChanged::class);
});

test('mail received since the last sync still alerts after a long server outage', function () {
    DB::table('email_accounts')->update(['last_synced_at' => now()->subDay()]);
    Message::create(array_replace($this->mail, ['sent_at' => now()->subHours(2)]));
    Notification::assertSentTo(User::find(1), InAppNotification::class, fn ($n) => $n->type === 'email_received');
});

test('historical import and initial mailbox sync do not flood notifications', function () {
    Message::create(array_replace($this->mail, ['sent_at' => now()->subDay()]));
    Message::create(array_replace($this->mail, ['direction' => 'outbound']));
    DB::table('email_accounts')->update(['last_synced_at' => null]);
    Message::create($this->mail);
    Notification::assertNothingSent();
});

test('SMTP acceptance failure and bounce status changes each notify without repeated unchanged saves', function () {
    $message = Message::create(array_replace($this->mail, ['direction' => 'outbound', 'delivery_status' => 'queued']));
    foreach (['sent', 'delivered', 'failed', 'bounced'] as $status) {
        $message->update(['delivery_status' => $status]);
        $message->update(['delivery_status' => $status]);
    }
    Notification::assertCount(4);
    Notification::assertSentTo(User::find(1), InAppNotification::class, fn ($n) => $n->type === 'email_failed');
    Notification::assertSentTo(User::find(1), InAppNotification::class, fn ($n) => $n->type === 'email_bounced');
});

test('inbound delivery failure email creates a bounce alert and respects notification preferences', function () {
    Message::create(array_replace($this->mail, ['from_email' => 'mailer-daemon@example.test', 'subject' => 'Mail delivery failed']));
    Notification::assertSentTo(User::find(1), InAppNotification::class, fn ($n) => $n->type === 'email_bounced');
    DB::table('users')->update(['notification_preferences' => json_encode(['inAppNotifs' => false])]);
    Message::create($this->mail);
    Notification::assertCount(1);
});

test('friend preview hides cleared messages and another users private recording', function () {
    DB::statement('CREATE TABLE friend_messages (id INTEGER PRIMARY KEY, sender_id INTEGER, recipient_id INTEGER, visible_to INTEGER, body TEXT, kind TEXT, file_name TEXT, deleted_at TEXT)');
    DB::statement('CREATE TABLE friend_chat_clears (user_id INTEGER, other_id INTEGER, before_id INTEGER)');
    DB::table('friend_messages')->insert([
        ['id' => 1, 'sender_id' => 1, 'recipient_id' => 2, 'visible_to' => null, 'body' => 'visible', 'kind' => 'text'],
        ['id' => 2, 'sender_id' => 2, 'recipient_id' => 1, 'visible_to' => 2, 'body' => 'private recording', 'kind' => 'text'],
    ]);
    $user = User::find(1);
    expect(app(FriendChatService::class)->previews($user, [2]))->toBe([2 => 'visible']);
    DB::table('friend_chat_clears')->insert(['user_id' => 1, 'other_id' => 2, 'before_id' => 1]);
    expect(app(FriendChatService::class)->previews($user, [2]))->toBe([]);
});

test('oversized SDP emits immediate replay invalidation without truncating the offer', function () {
    RealtimeUpdates::users([1], ['type' => 'call_signal', 'call_id' => 42, 'signal' => ['payload' => str_repeat('s', 12000)]]);
    Event::assertDispatched(StateChanged::class, fn ($e) => $e->broadcastWith() === ['type' => 'call_signal', 'call_id' => 42]);
});

test('HTTP call actions defer broadcaster IO until after the response and commit', function () {
    $property = new ReflectionProperty(app(), 'isRunningInConsole');
    $original = $property->getValue(app());
    $property->setValue(app(), false);
    try {
        DB::transaction(fn () => RealtimeUpdates::users([1], ['type' => 'call']));
        Event::assertNotDispatched(StateChanged::class);
        app()->terminate();
        Event::assertDispatched(StateChanged::class);
    } finally {
        $property->setValue(app(), $original);
    }
});


test('call decisions carry room status to both participants without another API lookup', function () {
    $call = (object) ['id' => 42, 'caller_id' => 1, 'callee_id' => 2, 'meeting_code' => 'syntheticroom', 'group_invite' => 0];
    \App\Services\Friends\FriendPush::stopRinging($call, 'answered');
    Event::assertDispatched(StateChanged::class, fn ($e) => $e->broadcastWith() === [
        'type' => 'call_cancel', 'call_id' => 42, 'reason' => 'answered',
        'code' => 'syntheticroom', 'group' => false, 'status' => 'active',
    ] && count($e->broadcastOn()) === 2);
    \App\Services\Friends\FriendPush::stopRinging($call, 'declined');
    Event::assertDispatched(StateChanged::class, fn ($e) => $e->broadcastWith()['status'] === 'declined');
});


test('email refresh reaches the mobile owner even with alerts disabled and carries message identity', function () {
    DB::table('users')->where('id', 1)->update(['notification_preferences' => json_encode(['inAppNotifs' => false])]);
    $m = Message::create($this->mail);
    Notification::assertNothingSent();
    Event::assertDispatched(StateChanged::class, fn ($event) => $event->broadcastWith()['type'] === 'email'
        && array_map(fn ($channel) => $channel->name, $event->broadcastOn()) === ['private-user.1']);
    $alert = new InAppNotification('Email', messageId: $m->id);
    expect($alert->toArray(User::find(1))['message_id'])->toBe($m->id);
});

test('muting email suppresses alerts but preserves realtime list updates', function () {
    (require database_path('migrations/2026_10_15_000002_create_email_notification_mutes.php'))->up();
    DB::table('email_notification_mutes')->insert(['user_id' => 1, 'conversation_id' => $this->mail['conversation_id']]);
    Message::create($this->mail);
    Notification::assertNothingSent();
    Event::assertDispatched(StateChanged::class);
    DB::table('email_notification_mutes')->delete();
    Message::create($this->mail);
    Notification::assertSentTo(User::find(1), InAppNotification::class);
});
