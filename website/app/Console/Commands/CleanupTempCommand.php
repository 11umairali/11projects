<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupTempCommand extends Command
{
    protected $signature = 'mailtrixy:cleanup-temp';

    protected $description = 'Delete old temp files, expired sessions, and stale conversation viewers';

    public function handle(): int
    {
        $this->info('Starting cleanup...');
        $totalCleaned = 0;

        // 1. Clean up temp files older than 24 hours
        try {
            $tempPath = storage_path('app/temp');
            if (is_dir($tempPath)) {
                $files = glob($tempPath . '/*');
                $cutoff = time() - (24 * 3600);
                $cleaned = 0;

                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < $cutoff) {
                        unlink($file);
                        $cleaned++;
                    }
                }

                $this->info("  Temp files removed: {$cleaned}");
                $totalCleaned += $cleaned;
            }
        } catch (\Throwable $e) {
            $this->error("  Temp file cleanup error: {$e->getMessage()}");
        }

        // 2. Clean up expired database sessions (older than 48 hours)
        try {
            if (config('session.driver') === 'database') {
                $deleted = DB::table('sessions')
                    ->where('last_activity', '<', time() - (48 * 3600))
                    ->delete();

                $this->info("  Expired sessions removed: {$deleted}");
                $totalCleaned += $deleted;
            }
        } catch (\Throwable $e) {
            // Sessions table may not exist or have different schema
            $this->warn("  Session cleanup skipped: {$e->getMessage()}");
        }

        // 3. Clean up stale conversation viewers (users viewing a conversation but idle > 10 mins)
        // These are tracked in cache/DB for real-time "who's viewing" indicators
        try {
            if (DB::getSchemaBuilder()->hasTable('conversation_viewers')) {
                $deleted = DB::table('conversation_viewers')
                    ->where('last_seen_at', '<', now()->subMinutes(10))
                    ->delete();

                $this->info("  Stale conversation viewers removed: {$deleted}");
                $totalCleaned += $deleted;
            }
        } catch (\Throwable $e) {
            // Table may not exist yet
            $this->warn("  Conversation viewer cleanup skipped: {$e->getMessage()}");
        }

        // 4. Clean up old workflow step logs (older than 90 days)
        try {
            $deleted = DB::table('workflow_step_logs')
                ->where('created_at', '<', now()->subDays(90))
                ->delete();

            $this->info("  Old workflow logs removed: {$deleted}");
            $totalCleaned += $deleted;
        } catch (\Throwable $e) {
            $this->warn("  Workflow log cleanup skipped: {$e->getMessage()}");
        }

        // 5. Clean up old audit logs (older than 365 days)
        try {
            $deleted = DB::table('audit_logs')
                ->where('created_at', '<', now()->subDays(365))
                ->delete();

            $this->info("  Old audit logs removed: {$deleted}");
            $totalCleaned += $deleted;
        } catch (\Throwable $e) {
            $this->warn("  Audit log cleanup skipped: {$e->getMessage()}");
        }

        $this->info("Cleanup complete. Total items cleaned: {$totalCleaned}");

        Log::info('mailtrixy:cleanup-temp completed', ['total_cleaned' => $totalCleaned]);

        return self::SUCCESS;
    }
}
