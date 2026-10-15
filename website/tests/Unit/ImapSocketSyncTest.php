<?php

use App\Http\Controllers\Api\Mobile\InboxExtrasController;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Models\User;
use App\Services\Email\EmailSyncService;
use App\Services\Email\SyncImapMailbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Webklex\PHPIMAP\ClientManager;

// Opt-in, loopback-only integration against a disposable IMAP server. Never use
// a real mailbox: the test appends synthetic mail and creates INBOX.Sent.
test('socket IMAP imports arrivals without ext-imap, skips absent Sent, hydrates bodies and deduplicates', function () {
    if (!getenv('IMAP_TEST_PASSWORD_FILE')) $this->markTestSkipped('Requires a disposable loopback IMAP fixture; see realtime-notifications.md.');
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::statement('PRAGMA foreign_keys = OFF');
    DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, active_workspace_id INTEGER, notification_preferences TEXT)');
    DB::statement('CREATE TABLE workspaces (id INTEGER PRIMARY KEY, settings TEXT, deleted_at TEXT)');
    DB::statement('CREATE TABLE workspace_members (workspace_id INTEGER, user_id INTEGER, role TEXT)');
    foreach (['0001_01_01_000005_create_email_accounts_table.php', '0001_01_01_000006_create_contacts_and_crm_tables.php', '0001_01_01_000007_create_conversations_and_messages_tables.php'] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    DB::statement('ALTER TABLE email_accounts ADD COLUMN deleted_at TEXT');
    DB::table('users')->insert(['id' => 1, 'name' => 'Fixture Owner', 'active_workspace_id' => 7]);
    DB::table('workspaces')->insert(['id' => 7, 'settings' => '{"contact_auto_create":false}']);
    DB::table('workspace_members')->insert(['workspace_id' => 7, 'user_id' => 1, 'role' => 'owner']);
    $this->actingAs(User::findOrFail(1));
    Notification::fake();
    Event::fake([App\Events\StateChanged::class, App\Events\ContactCreated::class]);
    $password = file_get_contents(getenv('IMAP_TEST_PASSWORD_FILE'));
    $account = EmailAccount::create(['workspace_id' => 7, 'user_id' => 1, 'email' => 'sync@example.test', 'display_name' => 'Fixture', 'provider' => 'imap',
        'imap_host' => '127.0.0.1', 'imap_port' => (int) (getenv('IMAP_TEST_PORT') ?: 1143), 'imap_encryption' => 'none',
        'imap_username' => 'sync@example.test', 'imap_password' => $password, 'sync_folders' => ['INBOX', 'Sent'], 'status' => 'connected']);
    expect(SyncImapMailbox::password($account))->toBe($password);
    $client = (new ClientManager())->make(['host' => '127.0.0.1', 'port' => $account->imap_port, 'encryption' => false,
        'username' => 'sync@example.test', 'password' => $password, 'protocol' => 'imap', 'authentication' => null]);
    $client->connect();
    $protocol = $client->getConnection();
    $tag = bin2hex(random_bytes(8));
    $append = function ($folder, $suffix) use ($protocol, $tag) {
        $protocol->appendMessage($folder, "From: Alice Example <alice@example.test>\r\nTo: sync@example.test\r\nSubject: Socket $tag $suffix\r\nDate: ".date(DATE_RFC2822)."\r\nMessage-ID: <$tag-$suffix@example.test>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nFull socket email $suffix.\r\n")->validatedData();
    };
    $service = app(EmailSyncService::class);
    $append('INBOX', 'first');
    [$count, $more] = $service->syncBatch($account);
    expect($count)->toBeGreaterThanOrEqual(1)->and($more)->toBeFalse();
    expect($account->fresh()->sync_folders['Sent']['missing_folder'] ?? false)->toBeTrue();
    expect($service->syncBatch($account))->toBe([0, false]);
    $append('INBOX', 'second');
    expect($service->syncBatch($account))->toBe([1, false]);
    expect($service->syncBatch($account))->toBe([0, false]);
    expect(Message::where('message_id_header', '<'.$tag.'-second@example.test>')->count())->toBe(1);
    $message = Message::where('message_id_header', '<'.$tag.'-second@example.test>')->firstOrFail();
    expect($message->body_text)->toBeNull();
    $request = Request::create('/');
    $request->setUserResolver(fn () => User::findOrFail(1));
    $result = (new InboxExtrasController())->messageBody($request, $message->id);
    expect($result->getStatusCode())->toBe(200)->and($result->getData(true)['data']['text'])->toBe('Full socket email second.');
    expect($message->fresh()->conversation->is_read)->toBeFalse();
    $client->openFolder('INBOX', true);
    $remote = $client->getFolder('INBOX')->query()->getMessageByUid($message->imap_uid);
    expect($remote->getFlags()->has('seen'))->toBeFalse();
    // The default Sent entry must resolve the server's namespace alias when it appears.
    $client->createFolder('INBOX.Sent');
    try {
        $append('INBOX.Sent', 'sent');
        expect($service->syncBatch($account))->toBe([1, false]);
        expect($account->fresh()->sync_folders)->toHaveKey('INBOX.Sent');
        expect(Message::where('message_id_header', '<'.$tag.'-sent@example.test>')->value('imap_folder'))->toBe('INBOX.Sent');
    } finally { $client->deleteFolder('INBOX.Sent'); }
    $older = Message::where('message_id_header', '<'.$tag.'-first@example.test>')->firstOrFail();
    DB::table('conversations')->where('id', $older->conversation_id)->update(['preview' => 'Newer preview', 'last_message_at' => now()->addDay()]);
    expect((new InboxExtrasController())->messageBody($request, $older->id)->getStatusCode())->toBe(200);
    expect($older->conversation->preview)->toBe('Newer preview');
    // A UID reused after a reset must never hydrate an unrelated email.
    $firstUid = Message::where('message_id_header', '<'.$tag.'-first@example.test>')->value('imap_uid');
    $message->refresh()->update(['imap_uid' => $firstUid, 'body_text' => null, 'body_html' => null]);
    $stale = (new InboxExtrasController())->messageBody($request, $message->id);
    expect($stale->getStatusCode())->toBe(503)->and($message->fresh()->body_text)->toBeNull();
    $this->mock(EmailSyncService::class, fn ($mock) => $mock->shouldNotReceive('fetchBodyForMessage'));
    $otherRequest = Request::create('/');
    $otherUser = new User(['active_workspace_id' => 8]);
    $otherUser->id = 1;
    $otherRequest->setUserResolver(fn () => $otherUser);
    expect(fn () => (new InboxExtrasController())->messageBody($otherRequest, $message->id))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    $account->imap_password = 'incorrect synthetic password';
    try {
        SyncImapMailbox::connect($account, 5);
        $this->fail('Incorrect credentials must be rejected.');
    } catch (Webklex\PHPIMAP\Exceptions\AuthFailedException | Webklex\PHPIMAP\Exceptions\ImapServerErrorException $error) {
        expect(App\Services\Email\MailSyncFailure::describe($error)['code'])->toBe('authentication_failed');
    }
    $client->disconnect();
});
