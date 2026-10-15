<?php
namespace App\Console\Commands;

use App\Services\TempMailService;
use Illuminate\Console\Command;

class CleanupExpiredTempMailCommand extends Command
{
    protected $signature = 'mailtrixy:cleanup-temp-mail {--dry-run : Show what would be cleaned without deleting}';
    protected $description = 'Deactivate expired temp mail addresses and clean up their messages';

    public function handle(TempMailService $service): int
    {
        if ($this->option('dry-run')) {
            $expired = \App\Models\TempMailAddress::where('is_active', true)
                ->where('expires_at', '<=', now())
                ->count();
            $this->info("Dry run: {$expired} expired address(es) would be cleaned.");
            return 0;
        }

        $result = $service->cleanupExpired();

        $this->info("Cleanup complete:");
        $this->info("  Addresses deactivated: {$result['addresses_cleaned']}");
        $this->info("  Messages deleted: {$result['messages_deleted']}");

        return 0;
    }
}
