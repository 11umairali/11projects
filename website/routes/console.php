<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Server-friendly scheduling — max 3 PHP processes at any time.
| Run the scheduler via: php artisan schedule:run
| Production: add `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1` to cron.
|
*/

// Hostinger / shared-host friendly worker pattern:
//   --stop-when-empty: worker exits as soon as the queue is drained (usually
//     within 1-2s) so it never holds a long-running PHP process. The next
//     cron tick fires a fresh worker that picks up any new jobs.
//   --max-time=45:    hard upper bound well below the 60s shared-host kill
//     window, so even a backed-up queue never leaves a zombie process.
//   --tries=3:        matches the jobs' own retry count.
// The key fix for "scheduled email not sending" was removing the 10-min
// jobs-table wipe below, NOT keeping workers alive longer.

// ── Worker 1: user-facing send/reply/undo-send ──
// NO withoutOverlapping() — on shared hosts the PHP process gets killed before
// Laravel's schedule:finish fires, which leaves the mutex lock held for a full
// 24 hours (its default expiry) and no subsequent worker can ever start. The
// database queue driver already locks individual job rows with FOR UPDATE so
// two concurrent workers can't double-process the same job. Safer to let them
// run in parallel than to risk a silent 24h outage.
Schedule::command('queue:work --stop-when-empty --max-time=45 --tries=3 --queue=campaigns,default')
    ->everyMinute()
    ->runInBackground()
    ->before(fn () => Log::info('[Scheduler] Worker1 (campaigns,default) — STARTED'))
    ->after(fn () => Log::info('[Scheduler] Worker1 — FINISHED'));

// ── Worker 2: sync + system jobs. email-sync listed FIRST so sync gets priority
//    during backfill; other queues are drained once sync is idle. ──
Schedule::command('queue:work --stop-when-empty --max-time=45 --tries=3 --queue=email-sync,workflows,integrations,processing,oauth-refresh,billing')
    ->everyMinute()
    ->runInBackground()
    ->before(fn () => Log::info('[Scheduler] Worker2 (email-sync,workflows,...) — STARTED'))
    ->after(fn () => Log::info('[Scheduler] Worker2 — FINISHED'));

// ── Email sync — every minute ──
Schedule::command('mailtrixy:sync-emails')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->runInBackground()
    ->before(fn () => Log::info('[Scheduler] sync-emails — STARTED'))
    ->after(fn () => Log::info('[Scheduler] sync-emails — FINISHED'));

// ── Send scheduled emails (undo-send, scheduled sends) — every minute ──
Schedule::command('emails:send-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->before(fn () => Log::info('[Scheduler] send-scheduled — STARTED'))
    ->after(fn () => Log::info('[Scheduler] send-scheduled — FINISHED'));

// ── Start any campaigns whose "schedule for later" time has arrived ──
Schedule::command('campaigns:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->before(fn () => Log::info('[Scheduler] process-scheduled-campaigns — STARTED'))
    ->after(fn () => Log::info('[Scheduler] process-scheduled-campaigns — FINISHED'));

// ── Fire workflows with trigger=scheduled whose config matches this minute ──
Schedule::command('workflows:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->before(fn () => Log::info('[Scheduler] process-scheduled-workflows — STARTED'))
    ->after(fn () => Log::info('[Scheduler] process-scheduled-workflows — FINISHED'));

// ── Drip sequences — every 2 minutes ──
Schedule::command('mailtrixy:process-drips')
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->before(fn () => Log::info('[Scheduler] process-drips — STARTED'))
    ->after(fn () => Log::info('[Scheduler] process-drips — FINISHED'));

// ── OAuth token refresh — every 30 minutes ──
Schedule::command('mailtrixy:refresh-oauth-tokens')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->before(fn () => Log::info('[Scheduler] refresh-oauth — STARTED'))
    ->after(fn () => Log::info('[Scheduler] refresh-oauth — FINISHED'));

// ── Temp mail sync — every 5 minutes ──
Schedule::command('mailtrixy:sync-temp-mail')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->before(fn () => Log::info('[Scheduler] sync-temp-mail — STARTED'))
    ->after(fn () => Log::info('[Scheduler] sync-temp-mail — FINISHED'));

// ── Catch-all queue worker (every 2 minutes) ──────────────────────────
// The two queue:work tasks above only consume named queues. Any job that
// gets dispatched to a queue not listed there (legacy code paths, custom
// extensions, future feature flags) would pile up forever. This catch-all
// drains "every queue" every 2 minutes as a safety net.
//
// On shared hosts where a long-lived `php artisan queue:work` daemon
// can't run, this is the difference between "AI replies fire" / "KB
// scraping completes" / "notifications arrive" — and silent failure.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --memory=256 --queue=default,scraping,ai,notifications')
    ->everyTwoMinutes()
    ->withoutOverlapping(60)
    ->runInBackground()
    ->before(fn () => Log::info('[Scheduler] queue:work catch-all — STARTED'))
    ->after(fn () => Log::info('[Scheduler] queue:work catch-all — FINISHED'));

// ── Cleanup tasks — low frequency, off-peak ──
Schedule::command('mailtrixy:cleanup-temp')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('mailtrixy:cleanup-temp-mail')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('queue:prune-batches --hours=48')->daily()->withoutOverlapping();

// ── Prune only jobs that have truly exhausted their retries + trim old failed_jobs ──
// NOTE: do NOT delete by `created_at` age on the `jobs` table — delayed/scheduled
// jobs and backlogged jobs both live there long before they run. Deleting "old"
// rows would silently wipe legitimate pending work (scheduled emails, sync, AI,
// campaigns). For `failed_jobs` the story is different: once a job is in there,
// it has already exhausted its retries and is inert — age-based pruning is safe
// and desirable. We keep 24 h of history for post-mortem debugging and cap the
// total row count at 500 so a buggy listener can't balloon the table into the
// thousands.
Schedule::call(function () {
    $exhausted = DB::table('jobs')->where('attempts', '>=', 5)->delete();

    // Age-based prune: anything older than 24 h goes.
    $oldFailed = DB::table('failed_jobs')->where('failed_at', '<', now()->subDay())->delete();

    // Hard cap: keep at most 500 rows (newest). Over-cap rows are deleted
    // oldest-first so you still have recent context for debugging.
    $over = DB::table('failed_jobs')->count() - 500;
    $capFailed = 0;
    if ($over > 0) {
        $ids = DB::table('failed_jobs')->orderBy('failed_at')->limit($over)->pluck('id');
        $capFailed = DB::table('failed_jobs')->whereIn('id', $ids)->delete();
    }

    $remaining = DB::table('jobs')->count();
    $failedRemaining = DB::table('failed_jobs')->count();
    Log::info("[Scheduler] Job cleanup — exhausted: {$exhausted}, old failed: {$oldFailed}, over-cap failed: {$capFailed} | pending: {$remaining} | failed kept: {$failedRemaining}");
})->hourly();

// Health check — every 5 minutes so you can see scheduler is alive
// Friends: a ring nobody answered becomes "Missed call" (+ notification) even when no phone is polling any more
Schedule::call(fn () => app(\App\Services\Friends\FriendCallService::class)->sweep())->name('friend-calls-sweep')->everyMinute()->withoutOverlapping();
// Recording: expire unanswered consent requests, end sessions of finished meetings, fail sessions no device can capture
Schedule::call(fn () => app(\App\Services\Recording\RecordingService::class)->sweep())->name('recording-sweep')->everyMinute()->withoutOverlapping();

Schedule::call(fn () => Log::info('[Scheduler] heartbeat — OK | jobs: ' . DB::table('jobs')->count()))->everyFiveMinutes();

Schedule::call(function () {
    if (!\Illuminate\Support\Facades\Schema::hasTable('admin_broadcast_deliveries')) return;
    \Illuminate\Support\Facades\DB::table('admin_broadcast_deliveries')
        ->where(fn ($q) => $q->where('push_status', 'pending')->orWhere('email_status', 'pending'))
        ->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) \App\Jobs\DeliverAdminBroadcast::dispatch($row->id);
        });
})->name('admin-broadcast-dispatch')->everyMinute()->withoutOverlapping();
