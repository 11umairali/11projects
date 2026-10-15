<?php
use App\Http\Controllers\InboxApiController;
use App\Models\User;
use App\Services\Email\EmailSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:']); DB::purge('sqlite');
    DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, active_workspace_id INTEGER)');
    DB::table('users')->insert(['id'=>1,'active_workspace_id'=>7]); $this->actingAs(User::find(1));
    DB::statement('CREATE TABLE conversations (id INTEGER PRIMARY KEY, workspace_id INTEGER, is_read INTEGER, messages_count INTEGER, updated_at TEXT, deleted_at TEXT, channel TEXT, status TEXT)');
    DB::table('conversations')->insert(['id'=>1,'workspace_id'=>7,'is_read'=>0,'messages_count'=>1,'updated_at'=>'2026-10-15 12:00:00','channel'=>'email']);
});

test('poll revision changes for another message in an already unread thread', function () {
    $controller = new InboxApiController;
    $first = $controller->poll(Request::create('/'))->getData(true);
    DB::table('conversations')->update(['messages_count'=>2]);
    $second = $controller->poll(Request::create('/'))->getData(true);
    expect($second['latest_id'])->toBe($first['latest_id'])->and($second['unread'])->toBe($first['unread'])->and($second['revision'])->not->toBe($first['revision']);
});

test('foreground sync retries failed accounts but never disconnected or other workspace accounts', function () {
    DB::statement('CREATE TABLE email_accounts (id INTEGER PRIMARY KEY, workspace_id INTEGER, provider TEXT, status TEXT, deleted_at TEXT)');
    DB::table('email_accounts')->insert([['id'=>1,'workspace_id'=>7,'provider'=>'imap','status'=>'error'],['id'=>2,'workspace_id'=>7,'provider'=>'imap','status'=>'disconnected'],['id'=>3,'workspace_id'=>8,'provider'=>'imap','status'=>'connected']]);
    $this->mock(EmailSyncService::class, function ($mock) { $mock->shouldReceive('syncBatch')->once()->withArgs(fn ($account, $limit) => $account->id === 1 && $limit === 25)->andReturn([0,false]); });
    expect((new InboxApiController)->syncNow(Request::create('/', 'POST'))->getData(true)['synced'])->toBe(0);
});

test('email mute is private and rejects a mixed unauthorized selection atomically', function () {
    (require database_path('migrations/2026_10_15_000002_create_email_notification_mutes.php'))->up();
    DB::table('conversations')->insert(['id'=>2,'workspace_id'=>8,'channel'=>'email']);
    $controller = new InboxApiController;
    expect(fn () => $controller->bulk(Request::create('/', 'POST', ['ids'=>[1,2], 'action'=>'mute'])))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(DB::table('email_notification_mutes')->count())->toBe(0);
    $controller->bulk(Request::create('/', 'POST', ['ids'=>[1], 'action'=>'mute']));
    expect(DB::table('email_notification_mutes')->value('user_id'))->toBe(1);
    $controller->bulk(Request::create('/', 'POST', ['ids'=>[1], 'action'=>'unmute']));
    expect(DB::table('email_notification_mutes')->count())->toBe(0);
});

test('foreground sync reports provider failure instead of a successful empty refresh', function () {
    DB::statement('CREATE TABLE email_accounts (id INTEGER PRIMARY KEY, workspace_id INTEGER, provider TEXT, status TEXT, deleted_at TEXT)');
    DB::table('email_accounts')->insert(['id' => 1, 'workspace_id' => 7, 'provider' => 'imap', 'status' => 'connected']);
    $this->mock(EmailSyncService::class, function ($mock) {
        $mock->shouldReceive('syncBatch')->once()->andThrow(new RuntimeException('Synthetic provider failure'));
    });
    $result = (new InboxApiController)->syncNow(Request::create('/', 'POST'))->getData(true);
    expect($result['failed_accounts'])->toBe(1)->and($result['synced'])->toBe(0);
});

test('sync reports a safe actionable account failure and can continue healthy accounts separately', function () {
    DB::statement('CREATE TABLE email_accounts (id INTEGER PRIMARY KEY, workspace_id INTEGER, email TEXT, provider TEXT, status TEXT, deleted_at TEXT)');
    DB::table('email_accounts')->insert([
        ['id' => 1, 'workspace_id' => 7, 'email' => 'broken@example.test', 'provider' => 'imap', 'status' => 'connected'],
        ['id' => 2, 'workspace_id' => 7, 'email' => 'healthy@example.test', 'provider' => 'imap', 'status' => 'connected'],
    ]);
    $this->mock(EmailSyncService::class, function ($mock) {
        $mock->shouldReceive('syncBatch')->once()->withArgs(fn ($a) => $a->id === 1)->andThrow(new Webklex\PHPIMAP\Exceptions\AuthFailedException('Secret provider response password=do-not-expose'));
        $mock->shouldReceive('syncBatch')->twice()->withArgs(fn ($a) => $a->id === 2)->andReturn([0, false]);
    });
    $controller = new InboxApiController;
    $first = $controller->syncNow(Request::create('/', 'POST'))->getData(true);
    expect($first['failures'])->toBe([['account_id' => 1, 'email' => 'broken@example.test', 'code' => 'authentication_failed',
        'message' => 'Mail server sign-in failed. Reconnect this account in Connected accounts.']]);
    expect(json_encode($first))->not->toContain('do-not-expose');
    $next = $controller->syncNow(Request::create('/', 'POST', ['skip_accounts' => [1]]))->getData(true);
    expect($next['failed_accounts'])->toBe(0);
});
