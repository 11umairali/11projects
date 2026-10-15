<?php

use App\Http\Controllers\InboxApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, active_workspace_id INTEGER, timezone TEXT)');
    DB::statement('CREATE TABLE workspaces (id INTEGER PRIMARY KEY, timezone TEXT, deleted_at TEXT)');
    DB::statement('CREATE TABLE conversations (id INTEGER PRIMARY KEY, workspace_id INTEGER, is_read INTEGER DEFAULT 0, channel TEXT, deleted_at TEXT, updated_at TEXT)');
    DB::statement('CREATE TABLE messages (id INTEGER PRIMARY KEY, workspace_id INTEGER, conversation_id INTEGER, type TEXT, body_text TEXT, body_html TEXT, imap_uid INTEGER, ai_status TEXT, created_at TEXT, deleted_at TEXT)');
    DB::statement('CREATE TABLE attachments (id INTEGER PRIMARY KEY, message_id INTEGER)');
    DB::statement('CREATE TABLE tags (id INTEGER PRIMARY KEY, workspace_id INTEGER)');
    DB::statement('CREATE TABLE conversation_tag (conversation_id INTEGER, tag_id INTEGER)');
    DB::table('users')->insert(['id' => 1, 'active_workspace_id' => 7, 'timezone' => 'UTC']);
    DB::table('conversations')->insert([['id' => 3, 'workspace_id' => 7, 'channel' => 'chat'], ['id' => 4, 'workspace_id' => 8, 'channel' => 'chat']]);
    DB::table('messages')->insert(['id' => 1, 'workspace_id' => 7, 'conversation_id' => 3, 'type' => 'message', 'body_text' => 'Visible message', 'created_at' => now()]);
    $this->actingAs(User::find(1));
});

test('mobile inbox fetch leaves conversation unread', function () {
    $response = app(InboxApiController::class)->messages(3, Request::create('/api/mobile/inbox/conversations/3/messages'));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true)['messages'])->toHaveCount(1);
    expect((int) DB::table('conversations')->where('id', 3)->value('is_read'))->toBe(0);
});

test('bounded inbox acknowledgement leaves a later message unread', function () {
    DB::table('messages')->insert(['id' => 2, 'workspace_id' => 7, 'conversation_id' => 3, 'type' => 'message', 'body_text' => 'Arrived later', 'created_at' => now()]);
    $controller = app(InboxApiController::class);
    $controller->action(3, Request::create('/api/mobile/inbox/conversations/3/action', 'POST', ['action' => 'mark_read', 'through_message_id' => 1]));
    expect((int) DB::table('conversations')->where('id', 3)->value('is_read'))->toBe(0);
    $controller->action(3, Request::create('/api/mobile/inbox/conversations/3/action', 'POST', ['action' => 'mark_read', 'through_message_id' => 2]));
    expect((int) DB::table('conversations')->where('id', 3)->value('is_read'))->toBe(1);
});

test('inbox acknowledgement cannot cross conversation or workspace boundaries', function () {
    DB::table('messages')->insert(['id' => 2, 'workspace_id' => 8, 'conversation_id' => 4, 'type' => 'message']);
    $controller = app(InboxApiController::class);
    try {
        $controller->action(3, Request::create('/api/mobile/inbox/conversations/3/action', 'POST', ['action' => 'mark_read', 'through_message_id' => 2]));
        $this->fail('Accepted foreign message');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { expect($e->getStatusCode())->toBe(422); }
    expect(fn () => $controller->action(4, Request::create('/api/mobile/inbox/conversations/4/action', 'POST', ['action' => 'mark_read', 'through_message_id' => 2])))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect(DB::table('conversations')->where('is_read', 1)->count())->toBe(0);
});
