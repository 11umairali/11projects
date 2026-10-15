<?php

namespace App\Console\Commands;

use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncEmailsCommand extends Command
{
    protected $signature = 'mailtrixy:sync-emails
                            {--account= : Sync a specific account by ID}
                            {--workspace= : Sync all accounts in a workspace}
                            {--force : Force sync even if recently synced}';

    protected $description = 'Sync emails from all connected email accounts';

    public function handle(): int
    {
        $query = EmailAccount::whereIn('status', ['connected', 'error']);

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        if ($workspaceId = $this->option('workspace')) {
            $query->where('workspace_id', $workspaceId);
        }

        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<=', now()->subSeconds(20));
            });
        }

        $totalCount = $query->count();

        if ($totalCount === 0) {
            Log::debug('[sync-emails] No accounts to sync');
            $this->info('No email accounts to sync.');
            return self::SUCCESS;
        }

        Log::info("[sync-emails] Dispatching sync for {$totalCount} account(s)");
        $this->info("Dispatching sync jobs for {$totalCount} account(s)...");

        $dispatched = 0;
        $query->chunkById(50, function ($accounts) use (&$dispatched) {
            foreach ($accounts as $account) {
                SyncEmailAccountJob::dispatch($account);
                $dispatched++;
                Log::info("[sync-emails] Dispatched: {$account->email} (ID:{$account->id}, provider:{$account->provider})");
                $this->line("  -> Dispatched sync for: {$account->email} (ID: {$account->id}, Provider: {$account->provider})");
            }
        });

        Log::info("[sync-emails] Done. {$dispatched} job(s) dispatched");
        $this->info("Done. {$dispatched} sync job(s) dispatched to queue.");

        return self::SUCCESS;
    }
}
