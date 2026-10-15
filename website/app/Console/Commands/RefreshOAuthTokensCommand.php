<?php

namespace App\Console\Commands;

use App\Jobs\RefreshOAuthTokenJob;
use App\Models\EmailAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshOAuthTokensCommand extends Command
{
    protected $signature = 'mailtrixy:refresh-oauth-tokens
                            {--account= : Refresh a specific account by ID}';

    protected $description = 'Refresh OAuth tokens for Gmail and Outlook email accounts';

    public function handle(): int
    {
        $query = EmailAccount::whereIn('status', ['connected', 'error'])
            ->whereIn('provider', ['gmail', 'outlook'])
            ->whereNotNull('oauth_refresh_token');

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        $query->where(function ($q) {
            $q->whereNull('oauth_token_expires_at')
                ->orWhere('oauth_token_expires_at', '<=', now()->addMinutes(15));
        });

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            Log::debug('[refresh-oauth] No tokens need refreshing');
            $this->info('No OAuth tokens need refreshing.');
            return self::SUCCESS;
        }

        Log::info("[refresh-oauth] Refreshing {$accounts->count()} token(s)");
        $this->info("Refreshing tokens for {$accounts->count()} account(s)...");

        foreach ($accounts as $account) {
            RefreshOAuthTokenJob::dispatch($account);
            Log::info("[refresh-oauth] Dispatched: {$account->email} ({$account->provider})");
            $this->line("  -> Dispatched refresh for: {$account->email} ({$account->provider})");
        }

        Log::info('[refresh-oauth] Done');
        $this->info('Done.');

        return self::SUCCESS;
    }
}
