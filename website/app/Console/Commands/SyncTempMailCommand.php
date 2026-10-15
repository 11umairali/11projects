<?php
namespace App\Console\Commands;

use App\Jobs\SyncTempMailJob;
use App\Models\TempMailDomain;
use Illuminate\Console\Command;

class SyncTempMailCommand extends Command
{
    protected $signature = 'mailtrixy:sync-temp-mail {--domain= : Sync a specific domain ID} {--force : Skip throttle check}';
    protected $description = 'Sync incoming emails for all active temp mail domains';

    public function handle(): int
    {
        $query = TempMailDomain::where('status', 'active');

        if ($domainId = $this->option('domain')) {
            $query->where('id', $domainId);
        }

        $domains = $query->get();

        if ($domains->isEmpty()) {
            $this->info('No active temp mail domains found.');
            return 0;
        }

        $dispatched = 0;
        foreach ($domains as $domain) {
            // Skip if synced within last 2 minutes unless --force
            if (!$this->option('force') && $domain->last_synced_at && $domain->last_synced_at->diffInSeconds(now()) < 120) {
                $this->line("  Skipping {$domain->domain} (synced {$domain->last_synced_at->diffForHumans()})");
                continue;
            }

            SyncTempMailJob::dispatch($domain);
            $this->info("  Dispatched sync for: {$domain->domain}");
            $dispatched++;
        }

        $this->info("Done. {$dispatched} domain(s) queued for sync.");
        return 0;
    }
}
