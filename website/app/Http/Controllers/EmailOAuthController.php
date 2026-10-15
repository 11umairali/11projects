<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailOAuthController extends Controller
{
    /**
     * Redirect user to Google OAuth for Gmail access.
     */
    public function redirectGoogle(Request $request): RedirectResponse
    {
        $state = encrypt(json_encode([
            'user_id' => Auth::id(),
            'workspace_id' => Auth::user()->active_workspace_id,
            'account_id' => $request->query('account_id'),
        ]));

        $params = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => url('/email-oauth/google/callback'),
            'response_type' => 'code',
            'scope' => 'https://mail.google.com/ https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/userinfo.email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return redirect("https://accounts.google.com/o/oauth2/v2/auth?{$params}");
    }

    /**
     * Handle Google OAuth callback — store tokens on EmailAccount.
     */
    public function callbackGoogle(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect(url('/settings/email-accounts'))
                ->with('error', 'Google authorization was denied. Please try again or contact support.');
        }

        try {
            $state = json_decode(decrypt($request->input('state')), true);
        } catch (\Exception $e) {
            return redirect(url('/settings/email-accounts'))->with('error', 'Invalid OAuth state.');
        }

        if ((int) $state['user_id'] !== Auth::id()) {
            return redirect(url('/settings/email-accounts'))->with('error', 'OAuth session mismatch.');
        }

        // Exchange code for tokens
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $request->input('code'),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => url('/email-oauth/google/callback'),
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            Log::error('Google OAuth token exchange failed', ['response' => $response->body()]);
            return redirect(url('/settings/email-accounts'))->with('error', 'Failed to connect Gmail. Please try again.');
        }

        $tokens = $response->json();

        // Get user email
        $userInfo = Http::withToken($tokens['access_token'])
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');
        $email = $userInfo->json('email', '');

        // Find the target account: by explicit ID first, then fall back to pending_oauth
        $account = null;

        if (! empty($state['account_id'])) {
            $account = EmailAccount::where('id', $state['account_id'])
                ->where('workspace_id', $state['workspace_id'])
                ->first();
        }

        // Fallback: look for the most recent pending_oauth placeholder (onboarding flow)
        if (! $account) {
            $account = EmailAccount::where('workspace_id', $state['workspace_id'])
                ->where('provider', 'gmail')
                ->where('status', 'pending_oauth')
                ->latest()
                ->first();
        }

        if ($account) {
            $account->update([
                'email' => $email ?: $account->email,
                'oauth_token' => $tokens['access_token'],
                'oauth_refresh_token' => $tokens['refresh_token'] ?? $account->oauth_refresh_token,
                'oauth_token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                'status' => 'connected',
            ]);
        } else {
            $account = EmailAccount::create([
                'workspace_id' => $state['workspace_id'],
                'user_id' => Auth::id(),
                'email' => $email,
                'provider' => 'gmail',
                'oauth_token' => $tokens['access_token'],
                'oauth_refresh_token' => $tokens['refresh_token'] ?? null,
                'oauth_token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                'status' => 'connected',
                'is_default' => ! EmailAccount::where('workspace_id', $state['workspace_id'])->where('is_default', true)->exists(),
            ]);
        }

        // Dispatch initial sync immediately
        try {
            // Uses the job's own email-sync queue (see SyncEmailAccountJob::__construct)
            // so the initial backfill rides the dedicated worker and doesn't starve sends.
            \App\Jobs\SyncEmailAccountJob::dispatch($account);
            \Log::info("Email sync dispatched for account: {$account->email}");
        } catch (\Exception $e) {
            \Log::warning("Email sync dispatch failed: {$e->getMessage()}");
        }

        return redirect(url('/settings/email'))->with('success', 'Gmail connected successfully!');
    }

    /**
     * Redirect user to Microsoft OAuth for Outlook access.
     */
    public function redirectMicrosoft(Request $request): RedirectResponse
    {
        $state = encrypt(json_encode([
            'user_id' => Auth::id(),
            'workspace_id' => Auth::user()->active_workspace_id,
            'account_id' => $request->query('account_id'),
        ]));

        $tenant = config('services.microsoft.tenant_id', 'common');
        $params = http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'redirect_uri' => url('/email-oauth/microsoft/callback'),
            'response_type' => 'code',
            'scope' => 'https://graph.microsoft.com/Mail.ReadWrite https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read offline_access',
            'state' => $state,
        ]);

        return redirect("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?{$params}");
    }

    /**
     * Handle Microsoft OAuth callback — store tokens on EmailAccount.
     */
    public function callbackMicrosoft(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect(url('/settings/email-accounts'))
                ->with('error', 'Microsoft authorization was denied. Please try again or contact support.');
        }

        try {
            $state = json_decode(decrypt($request->input('state')), true);
        } catch (\Exception $e) {
            return redirect(url('/settings/email-accounts'))->with('error', 'Invalid OAuth state.');
        }

        if ((int) $state['user_id'] !== Auth::id()) {
            return redirect(url('/settings/email-accounts'))->with('error', 'OAuth session mismatch.');
        }

        $tenant = config('services.microsoft.tenant_id', 'common');

        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'code' => $request->input('code'),
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'redirect_uri' => url('/email-oauth/microsoft/callback'),
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            Log::error('Microsoft OAuth token exchange failed', ['response' => $response->body()]);
            return redirect(url('/settings/email-accounts'))->with('error', 'Failed to connect Outlook. Please try again.');
        }

        $tokens = $response->json();

        // Get user email from Microsoft Graph
        $profile = Http::withToken($tokens['access_token'])
            ->get('https://graph.microsoft.com/v1.0/me');
        $email = $profile->json('mail') ?? $profile->json('userPrincipalName', '');

        // Find the target account: by explicit ID first, then fall back to pending_oauth
        $account = null;

        if (! empty($state['account_id'])) {
            $account = EmailAccount::where('id', $state['account_id'])
                ->where('workspace_id', $state['workspace_id'])
                ->first();
        }

        // Fallback: look for the most recent pending_oauth placeholder (onboarding flow)
        if (! $account) {
            $account = EmailAccount::where('workspace_id', $state['workspace_id'])
                ->where('provider', 'outlook')
                ->where('status', 'pending_oauth')
                ->latest()
                ->first();
        }

        if ($account) {
            $account->update([
                'email' => $email ?: $account->email,
                'oauth_token' => $tokens['access_token'],
                'oauth_refresh_token' => $tokens['refresh_token'] ?? $account->oauth_refresh_token,
                'oauth_token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                'status' => 'connected',
            ]);
        } else {
            $account = EmailAccount::create([
                'workspace_id' => $state['workspace_id'],
                'user_id' => Auth::id(),
                'email' => $email,
                'provider' => 'outlook',
                'oauth_token' => $tokens['access_token'],
                'oauth_refresh_token' => $tokens['refresh_token'] ?? null,
                'oauth_token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                'status' => 'connected',
                'is_default' => ! EmailAccount::where('workspace_id', $state['workspace_id'])->where('is_default', true)->exists(),
            ]);
        }

        // Dispatch initial sync immediately
        try {
            // Uses the job's own email-sync queue (see SyncEmailAccountJob::__construct)
            // so the initial backfill rides the dedicated worker and doesn't starve sends.
            \App\Jobs\SyncEmailAccountJob::dispatch($account);
            \Log::info("Email sync dispatched for account: {$account->email}");
        } catch (\Exception $e) {
            \Log::warning("Email sync dispatch failed: {$e->getMessage()}");
        }

        return redirect(url('/settings/email'))->with('success', 'Outlook connected successfully!');
    }
}
