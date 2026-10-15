<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RefreshOAuthTokenJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public int $timeout = 120;

    public function __construct(
        public EmailAccount $emailAccount
    ) {
        $this->onQueue('oauth-refresh');
    }

    /**
     * The unique ID for this job (prevents duplicate refreshes).
     */
    public function uniqueId(): string
    {
        return "oauth-refresh-{$this->emailAccount->id}";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $account = $this->emailAccount;

        if (! $account->isOAuth()) {
            Log::info("RefreshOAuthToken: Account {$account->id} is not OAuth, skipping.");
            return;
        }

        if (! $account->oauth_refresh_token) {
            Log::warning("RefreshOAuthToken: Account {$account->id} has no refresh token.");
            $account->update([
                'status' => 'error',
                'error_message' => 'OAuth refresh token is missing. Please reconnect the account.',
            ]);
            return;
        }

        // Skip if token is still valid for more than 10 minutes
        if ($account->oauth_token_expires_at && $account->oauth_token_expires_at->isAfter(now()->addMinutes(10))) {
            Log::debug("RefreshOAuthToken: Token for account {$account->id} is still valid.");
            return;
        }

        try {
            $result = match ($account->provider) {
                'gmail' => $this->refreshGmailToken($account),
                'outlook' => $this->refreshOutlookToken($account),
                default => throw new \InvalidArgumentException("Unsupported OAuth provider: {$account->provider}"),
            };

            $account->update([
                'oauth_token' => $result['access_token'],
                'oauth_token_expires_at' => now()->addSeconds($result['expires_in'] ?? 3600),
                'status' => 'connected',
                'error_message' => null,
            ]);

            // Update refresh token if a new one was issued
            if (! empty($result['refresh_token'])) {
                $account->update(['oauth_refresh_token' => $result['refresh_token']]);
            }

            Log::info("RefreshOAuthToken: Successfully refreshed token for account {$account->id} ({$account->email})");
        } catch (\Throwable $e) {
            Log::error("RefreshOAuthToken: Failed for account {$account->id}: {$e->getMessage()}", [
                'provider' => $account->provider,
                'email' => $account->email,
            ]);

            $account->update([
                'status' => 'error',
                'error_message' => "OAuth refresh failed: {$e->getMessage()}",
            ]);

            if ($this->attempts() >= $this->tries) {
                throw $e;
            }
        }
    }

    /**
     * Refresh a Gmail OAuth2 token.
     */
    protected function refreshGmailToken(EmailAccount $account): array
    {
        $response = Http::asForm()->timeout(120)->connectTimeout(10)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $account->oauth_refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            $error = $response->json('error_description') ?? $response->json('error') ?? 'Unknown error';
            throw new \RuntimeException("Gmail token refresh failed: {$error}");
        }

        $data = $response->json();

        if (empty($data['access_token'])) {
            throw new \RuntimeException('Gmail token refresh returned no access_token');
        }

        return $data;
    }

    /**
     * Refresh a Microsoft/Outlook OAuth2 token.
     */
    protected function refreshOutlookToken(EmailAccount $account): array
    {
        $response = Http::asForm()->timeout(120)->connectTimeout(10)->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'refresh_token' => $account->oauth_refresh_token,
            'grant_type' => 'refresh_token',
            'scope' => 'https://graph.microsoft.com/Mail.ReadWrite https://graph.microsoft.com/Mail.Send offline_access',
        ]);

        if (! $response->successful()) {
            $error = $response->json('error_description') ?? $response->json('error') ?? 'Unknown error';
            throw new \RuntimeException("Outlook token refresh failed: {$error}");
        }

        $data = $response->json();

        if (empty($data['access_token'])) {
            throw new \RuntimeException('Outlook token refresh returned no access_token');
        }

        return $data;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("RefreshOAuthToken: Permanently failed for account {$this->emailAccount->id}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
