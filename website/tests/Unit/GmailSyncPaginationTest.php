<?php

use App\Models\EmailAccount;
use App\Services\Email\EmailSyncService;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GmailPaginationFixture extends EmailSyncService
{
    public array $requests = [];
    public array $processed = [];
    public array $missing = [];
    public bool $advanceClock = false;
    private GoogleClient $client;

    public function __construct(array $pages)
    {
        $handler = HandlerStack::create(new MockHandler(array_map(fn ($page) => new Response(200, ['Content-Type' => 'application/json'], json_encode($page)), $pages)));
        $handler->push(Middleware::history($this->requests));
        $this->client = new GoogleClient();
        $this->client->setHttpClient(new \GuzzleHttp\Client(['handler' => $handler]));
    }
    protected function gmailClient(EmailAccount $account): GoogleClient { return $this->client; }
    protected function refreshGmailTokenLocked(EmailAccount $account, int $httpTimeout = 15): EmailAccount { return $account->fresh(); }
    protected function gmailBatchGet(GoogleClient $client, Gmail $gmail, array $ids): array
    {
        $out = [];
        foreach ($ids as $id) if (!in_array($id, $this->missing)) $out[$id] = new Gmail\Message(['id' => $id]);
        return $out;
    }
    protected function processGmailMessage(object $message, EmailAccount $account): bool
    {
        $this->processed[] = $message->getId();
        if ($this->advanceClock) Carbon::setTestNow('2026-10-15 12:05:00');
        return true;
    }
}

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false, 'cache.default' => 'array']);
    DB::purge('sqlite'); Cache::flush();
    DB::statement('CREATE TABLE email_accounts (id INTEGER PRIMARY KEY, workspace_id INTEGER, provider TEXT, status TEXT, sync_folders TEXT, last_synced_at TEXT, error_message TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)');
    DB::table('email_accounts')->insert(['id' => 1, 'workspace_id' => 7, 'provider' => 'gmail', 'status' => 'connected',
        'sync_folders' => json_encode(['INBOX' => ['backfill_done' => true]]), 'last_synced_at' => '2026-10-15 11:00:00']);
    Carbon::setTestNow('2026-10-15 12:00:00');
});
afterEach(fn () => Carbon::setTestNow());

test('incremental Gmail sync keeps its query and cursor until all pages drain', function () {
    $service = new GmailPaginationFixture([
        ['messages' => [['id' => 'first']], 'nextPageToken' => 'next-page'],
        ['messages' => [['id' => 'second']]],
    ]);
    $account = EmailAccount::findOrFail(1);
    expect($service->syncBatch($account, 25))->toBe([1, true]);
    expect($account->fresh()->last_synced_at->format('H:i:s'))->toBe('11:00:00');
    Carbon::setTestNow('2026-10-15 12:03:00');
    expect($service->syncBatch($account, 25))->toBe([1, false]);
    expect($service->processed)->toBe(['first', 'second']);
    parse_str($service->requests[0]['request']->getUri()->getQuery(), $first);
    parse_str($service->requests[1]['request']->getUri()->getQuery(), $second);
    expect($second['pageToken'])->toBe('next-page')->and($second['q'])->toBe($first['q']);
    expect($account->fresh()->last_synced_at->format('H:i:s'))->toBe('12:00:00');
    expect($account->fresh()->sync_folders['INBOX'])->not->toHaveKey('incremental_page_token');
});

test('failed Gmail subrequests are retried without advancing past the message', function () {
    $service = new GmailPaginationFixture([['messages' => [['id' => 'retry-me']]], ['messages' => [['id' => 'retry-me']]]]);
    $service->missing = ['retry-me'];
    $account = EmailAccount::findOrFail(1);
    expect($service->syncBatch($account))->toBe([0, true]);
    expect($account->fresh()->last_synced_at->format('H:i:s'))->toBe('11:00:00');
    $service->missing = [];
    expect($service->syncBatch($account))->toBe([1, false]);
    expect($service->processed)->toBe(['retry-me']);
});

test('Gmail worker does not overwrite the sync start anchor with the completion time', function () {
    $service = new GmailPaginationFixture([['messages' => [['id' => 'slow-fetch']]]]);
    $service->advanceClock = true;
    $account = EmailAccount::findOrFail(1);
    expect($service->syncAccount($account))->toBe(1);
    expect($account->fresh()->last_synced_at->format('H:i:s'))->toBe('12:00:00');
});

test('foreground and worker Gmail imports cannot race their saved cursors', function () {
    $service = new GmailPaginationFixture([]);
    $lock = Cache::lock('email-import:1', 180); $lock->get();
    try {
        expect($service->syncBatch(EmailAccount::findOrFail(1)))->toBe([0, true]);
        expect($service->requests)->toBeEmpty();
    } finally { $lock->release(); }
});
