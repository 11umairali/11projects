<?php

use App\Models\EmailAccount;
use App\Services\Email\EmailSyncService;
use Illuminate\Support\Facades\DB;

test('mail identity normalizes provider header formatting and isolates missing IDs by account and provider UID', function () {
    $service = new class extends EmailSyncService {
        public function identity($header, $account, $uid) { return $this->messageIdentity($header, $account, $uid); }
    };
    $account = new EmailAccount(); $account->id = 1;
    expect($service->identity('abc@example.test', $account, 'gmail:1'))->toBe('<abc@example.test>');
    expect($service->identity(" <abc@example.test>\r\n", $account, 'imap:INBOX:1'))->toBe('<abc@example.test>');
    $id = $service->identity(null, $account, 'imap:INBOX:9:1');
    expect($id)->toBe($service->identity('', $account, 'imap:INBOX:9:1'));
    expect($id)->not->toBe($service->identity(null, $account, 'imap:INBOX:10:1'));
    expect($id)->not->toBe($service->identity(null, $account, 'imap:Sent:9:1'));
    $account->id = 2;
    expect($id)->not->toBe($service->identity(null, $account, 'imap:INBOX:9:1'));
});

test('overview sync skips both existing ID forms including deleted mail before creating a conversation', function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
    DB::statement('CREATE TABLE messages (id INTEGER PRIMARY KEY, workspace_id INTEGER, message_id_header TEXT, deleted_at TEXT)');
    $service = new class extends EmailSyncService {
        public function overview($header, $account) { return $this->processImapOverview((object) ['message_id' => $header, 'uid' => 1], $account); }
    };
    $account = new EmailAccount(); $account->id = 1; $account->workspace_id = 7;
    foreach (['abc@example.test', '<abc@example.test>'] as $stored) {
        DB::table('messages')->delete();
        DB::table('messages')->insert(['workspace_id' => 7, 'message_id_header' => $stored, 'deleted_at' => now()]);
        expect($service->overview('<abc@example.test>', $account))->toBeFalse();
    }
});

test('sync watermark is the start time so mail arriving during sync is not skipped', function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::statement('CREATE TABLE email_accounts (id INTEGER PRIMARY KEY, workspace_id INTEGER, provider TEXT, status TEXT, last_synced_at TEXT, error_message TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)');
    DB::table('email_accounts')->insert(['id' => 1, 'workspace_id' => 7, 'provider' => 'imap', 'status' => 'connected', 'last_synced_at' => '2026-10-01 10:00:00']);
    $service = new class extends EmailSyncService {
        protected function syncImap(EmailAccount $account): int {
            expect($account->last_synced_at->format('H:i:s'))->toBe('10:00:00');
            \Carbon\Carbon::setTestNow('2026-10-01 10:06:00');
            return 1;
        }
    };
    \Carbon\Carbon::setTestNow('2026-10-01 10:05:00');
    try {
        $account = EmailAccount::findOrFail(1);
        expect($service->syncAccount($account))->toBe(1);
        expect($account->fresh()->last_synced_at->format('H:i:s'))->toBe('10:05:00');
    } finally {
        \Carbon\Carbon::setTestNow();
    }
});

test('foreground IMAP import uses the header path and anchors sync before the request', function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::statement('CREATE TABLE email_accounts (id INTEGER PRIMARY KEY, workspace_id INTEGER, provider TEXT, status TEXT, last_synced_at TEXT, error_message TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)');
    DB::table('email_accounts')->insert(['id' => 1, 'workspace_id' => 7, 'provider' => 'imap', 'status' => 'connected']);
    $service = new class extends EmailSyncService {
        protected function syncImap(EmailAccount $account): int { throw new RuntimeException('HTTP refresh must not run the full body backfill.'); }
        protected function syncImapMailbox(EmailAccount $account, bool $foreground = false): int {
            expect($foreground)->toBeTrue();
            \Carbon\Carbon::setTestNow('2026-10-15 12:01:00');
            return 2;
        }
    };
    \Carbon\Carbon::setTestNow('2026-10-15 12:00:00');
    try {
        $account = EmailAccount::findOrFail(1);
        expect($service->syncBatch($account, 25))->toBe([2, false]);
        expect($account->fresh()->last_synced_at->format('H:i:s'))->toBe('12:00:00');
    } finally { \Carbon\Carbon::setTestNow(); }
});
