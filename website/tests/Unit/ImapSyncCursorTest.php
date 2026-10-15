<?php

use App\Models\EmailAccount;
use App\Services\Email\EmailSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::statement('CREATE TABLE email_accounts (id INTEGER PRIMARY KEY, workspace_id INTEGER, provider TEXT, status TEXT, sync_folders TEXT, last_synced_at TEXT, error_message TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)');
    DB::table('email_accounts')->insert(['id' => 1, 'workspace_id' => 7, 'provider' => 'imap', 'status' => 'connected']);
    $this->account = EmailAccount::findOrFail(1);
    $this->service = new class extends EmailSyncService {
        public array $queries = [], $seen = [], $batches = [], $results = [];
        public bool $fail = false;
        public function headers($account, &$folders, $validity = 9, $deadline = null) {
            return $this->syncImapHeaders(null, $account, 'INBOX', $folders, $validity, $deadline ?? microtime(true) + 10);
        }
        protected function imapSearchUids($mbox, string $criteria): array {
            $this->queries[] = $criteria;
            return $this->results[$criteria] ?? [];
        }
        protected function imapOverviews($mbox, array $uids): array {
            $this->batches[] = $uids;
            return array_map(fn ($uid) => (object) ['uid' => $uid, 'from' => 'Alice', 'message_id' => "<$uid@example.test>"], $uids);
        }
        protected function processImapOverview(object $overview, EmailAccount $account, string $folderName = 'INBOX'): bool {
            if ($this->fail && $overview->uid === 102) throw new RuntimeException('Synthetic import failure');
            $this->seen[] = $overview->uid;
            return true;
        }
    };
});

test('an incremental mailbox fetches only arrivals and rejects IMAPs reversed UID range result', function () {
    $folders = ['INBOX' => ['uidvalidity' => 9, 'last_uid' => 100, 'oldest_uid' => 1, 'backfill_done' => true]];
    $this->service->results = ['UID 101:*' => [103, 100, 102, 101]];
    expect($this->service->headers($this->account, $folders))->toBe(3);
    expect($this->service->seen)->toBe([101, 102, 103]);
    expect($this->service->queries)->toBe(['UID 101:*']);
    expect($folders['INBOX']['last_uid'])->toBe(103)->and($folders['INBOX']['oldest_uid'])->toBe(1);
});

test('arrivals are imported before history and old mail resumes below the saved UID', function () {
    $folders = ['INBOX' => ['last_uid' => 100, 'oldest_uid' => 50, 'backfill_done' => false]];
    $this->service->results = ['UID 101:*' => [102, 101], 'UID 1:49' => [1, 49, 48]];
    $this->service->headers($this->account, $folders);
    expect($this->service->seen)->toBe([101, 102, 49, 48, 1]);
    expect($folders['INBOX']['last_uid'])->toBe(102)->and($folders['INBOX']['oldest_uid'])->toBe(1)->and($folders['INBOX']['backfill_done'])->toBeTrue();
});

test('provider UID reset discards the old cursor', function () {
    $folders = ['INBOX' => ['uidvalidity' => 8, 'last_uid' => 100, 'backfill_done' => true]];
    $this->service->results = ['ALL' => [1, 2]];
    $this->service->headers($this->account, $folders);
    expect($this->service->seen)->toBe([2, 1]);
    expect($folders['INBOX']['uidvalidity'])->toBe(9)->and($folders['INBOX']['last_uid'])->toBe(2);
});

test('failed batch leaves the cursor before the failed message so it is retried', function () {
    $folders = ['INBOX' => ['last_uid' => 100, 'backfill_done' => true]];
    $this->service->results = ['UID 101:*' => [101, 102, 103]];
    $this->service->fail = true;
    expect(fn () => $this->service->headers($this->account, $folders))->toThrow(RuntimeException::class);
    expect($folders['INBOX']['last_uid'])->toBe(100);
});

test('foreground and worker imports cannot enter the same IMAP or Outlook mailbox concurrently', function () {
    foreach (['imap', 'custom', 'outlook'] as $provider) {
        $this->account->update(['provider' => $provider]);
        $lock = Cache::lock('email-import:1', 180);
        expect($lock->get())->toBeTrue();
        try {
            expect($this->service->syncBatch($this->account))->toBe([0, true]);
            expect($this->service->syncAccount($this->account))->toBe(0);
        } finally { $lock->release(); }
    }
});

test('foreground Outlook sync advances its watermark from the start of the request', function () {
    $service = new class extends EmailSyncService {
        protected function syncOutlook(EmailAccount $account): int {
            \Carbon\Carbon::setTestNow('2026-10-15 12:01:00');
            return 2;
        }
    };
    $this->account->update(['provider' => 'outlook']);
    \Carbon\Carbon::setTestNow('2026-10-15 12:00:00');
    try {
        expect($service->syncBatch($this->account))->toBe([2, false]);
        expect($this->account->fresh()->last_synced_at->format('H:i:s'))->toBe('12:00:00');
    } finally { \Carbon\Carbon::setTestNow(); }
});


test('time-limited incremental import leaves pending work visible to the next refresh', function () {
    $folders = ['INBOX' => ['last_uid' => 100, 'backfill_done' => true]];
    $this->service->results = ['UID 101:*' => [101]];
    expect($this->service->headers($this->account, $folders, deadline: microtime(true) - 1))->toBe(0);
    expect($folders['INBOX']['headers_pending'])->toBeTrue();
    expect($folders['INBOX']['last_uid'])->toBe(100);
    $this->service->headers($this->account, $folders);
    expect($folders['INBOX']['headers_pending'])->toBeFalse();
    expect($this->service->seen)->toBe([101]);
});
