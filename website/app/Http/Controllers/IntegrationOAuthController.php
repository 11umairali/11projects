<?php

namespace App\Http\Controllers;

use App\Models\ChannelIntegration;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IntegrationOAuthController extends Controller
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);
    }

    // =====================================================================
    // SLACK
    // =====================================================================

    /**
     * Redirect to Slack's OAuth authorization page.
     * This is an alternative entry point to the Livewire redirect for cases
     * where a direct link is needed (e.g., from onboarding).
     */
    public function slackRedirect(Request $request): RedirectResponse
    {
        $state = \Illuminate\Support\Str::random(40);
        session()->put('oauth_slack_state', $state);

        $params = http_build_query([
            'client_id' => config('services.slack.client_id'),
            'scope' => \App\Services\Integrations\SlackIntegrationService::oauthScopeString(),
            'redirect_uri' => config('services.slack.redirect'),
            'state' => $state,
        ]);

        return redirect("https://slack.com/oauth/v2/authorize?{$params}");
    }

    /**
     * Handle Slack OAuth callback.
     * Exchanges the authorization code for an access token, stores it encrypted.
     */
    public function slackCallback(Request $request): RedirectResponse
    {
        // Validate state to prevent CSRF
        if (! $this->validateOAuthState($request, 'slack')) {
            return redirect(url('/settings/integrations'))
                ->with('error', 'Invalid OAuth state. Please try connecting again.');
        }

        if ($request->has('error')) {
            Log::warning('Slack OAuth denied', ['error' => $request->input('error')]);
            return redirect(url('/settings/integrations'))
                ->with('error', 'Slack authorization was denied. Please try again or contact support.');
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect(url('/settings/integrations'))
                ->with('error', 'No authorization code received from Slack.');
        }

        try {
            $response = $this->http->post('https://slack.com/api/oauth.v2.access', [
                'form_params' => [
                    'client_id' => config('services.slack.client_id'),
                    'client_secret' => config('services.slack.client_secret'),
                    'code' => $code,
                    'redirect_uri' => config('services.slack.redirect'),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! ($data['ok'] ?? false)) {
                throw new \RuntimeException('Slack token exchange failed: ' . ($data['error'] ?? 'unknown'));
            }

            $workspaceId = auth()->user()->active_workspace_id;

            ChannelIntegration::updateOrCreate(
                ['workspace_id' => $workspaceId, 'channel' => 'slack'],
                [
                    'credentials' => [
                        'access_token' => $data['access_token'] ?? null,
                        'bot_token' => $data['access_token'] ?? null,
                        'bot_user_id' => $data['bot_user_id'] ?? null,
                        'app_id' => $data['app_id'] ?? null,
                    ],
                    'status' => 'active',
                    'error_message' => null,
                    'slack_team_id' => $data['team']['id'] ?? null,
                    'account_name' => $data['team']['name'] ?? 'Slack Workspace',
                ]
            );

            Log::info('Slack OAuth completed', [
                'workspace_id' => $workspaceId,
                'team_id' => $data['team']['id'] ?? null,
                'team_name' => $data['team']['name'] ?? null,
            ]);

            return redirect(url('/settings/integrations'))
                ->with('success', 'Slack connected successfully to ' . ($data['team']['name'] ?? 'your workspace') . '.');

        } catch (\Throwable $e) {
            Log::error('Slack OAuth callback failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect(url('/settings/integrations'))
                ->with('error', 'Failed to connect Slack: ' . $e->getMessage());
        }
    }

    // =====================================================================
    // SALESFORCE
    // =====================================================================

    /**
     * Redirect to Salesforce OAuth authorization.
     */
    public function salesforceRedirect(Request $request): RedirectResponse
    {
        $state = \Illuminate\Support\Str::random(40);
        session()->put('oauth_salesforce_state', $state);

        $workspaceId = auth()->user()->active_workspace_id;
        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'salesforce')
            ->first();

        $clientId = $integration?->credentials['client_id'] ?? config('services.salesforce.client_id');
        $instanceUrl = $integration?->salesforce_instance_url ?? 'https://login.salesforce.com';

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => config('services.salesforce.redirect'),
            'state' => $state,
        ]);

        return redirect("{$instanceUrl}/services/oauth2/authorize?{$params}");
    }

    /**
     * Handle Salesforce OAuth callback.
     */
    public function salesforceCallback(Request $request): RedirectResponse
    {
        if (! $this->validateOAuthState($request, 'salesforce')) {
            return redirect(url('/settings/integrations'))
                ->with('error', 'Invalid OAuth state. Please try connecting Salesforce again.');
        }

        if ($request->has('error')) {
            Log::warning('Salesforce OAuth denied', ['error' => $request->input('error_description')]);
            return redirect(url('/settings/integrations'))
                ->with('error', 'Salesforce authorization was denied. Please try again or contact support.');
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect(url('/settings/integrations'))
                ->with('error', 'No authorization code received from Salesforce.');
        }

        try {
            $workspaceId = auth()->user()->active_workspace_id;
            $integration = ChannelIntegration::where('workspace_id', $workspaceId)
                ->where('channel', 'salesforce')
                ->first();

            $clientId = $integration?->credentials['client_id'] ?? config('services.salesforce.client_id');
            $clientSecret = $integration?->credentials['client_secret'] ?? config('services.salesforce.client_secret');
            $instanceUrl = $integration?->salesforce_instance_url ?? 'https://login.salesforce.com';

            $response = $this->http->post("{$instanceUrl}/services/oauth2/token", [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => config('services.salesforce.redirect'),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! isset($data['access_token'])) {
                throw new \RuntimeException('Salesforce token exchange returned no access token.');
            }

            // The instance_url from the token response is the canonical one
            $canonicalInstanceUrl = $data['instance_url'] ?? $instanceUrl;

            // Retrieve user identity for the account name
            $identityResponse = $this->http->get($data['id'], [
                'headers' => ['Authorization' => 'Bearer ' . $data['access_token']],
            ]);
            $identity = json_decode($identityResponse->getBody()->getContents(), true);

            ChannelIntegration::updateOrCreate(
                ['workspace_id' => $workspaceId, 'channel' => 'salesforce'],
                [
                    'credentials' => [
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'access_token' => $data['access_token'],
                    ],
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'salesforce_instance_url' => $canonicalInstanceUrl,
                    'status' => 'active',
                    'error_message' => null,
                    'account_name' => ($identity['display_name'] ?? 'Salesforce') . ' (' . ($identity['organization_id'] ?? '') . ')',
                    'token_expires_at' => isset($data['issued_at'])
                        ? \Carbon\Carbon::createFromTimestampMs($data['issued_at'])->addHour()
                        : now()->addHour(),
                ]
            );

            Log::info('Salesforce OAuth completed', [
                'workspace_id' => $workspaceId,
                'instance_url' => $canonicalInstanceUrl,
            ]);

            return redirect(url('/settings/integrations'))
                ->with('success', 'Salesforce connected successfully.');

        } catch (\Throwable $e) {
            Log::error('Salesforce OAuth callback failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect(url('/settings/integrations'))
                ->with('error', 'Failed to connect Salesforce: ' . $e->getMessage());
        }
    }

    // =====================================================================
    // GOOGLE CALENDAR
    // =====================================================================

    /**
     * Redirect to Google OAuth for Calendar access.
     */
    public function googleCalendarRedirect(Request $request): RedirectResponse
    {
        $state = \Illuminate\Support\Str::random(40);
        session()->put('oauth_google_calendar_state', $state);

        $params = http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => config('services.google_calendar.redirect'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.readonly https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return redirect("https://accounts.google.com/o/oauth2/v2/auth?{$params}");
    }

    /**
     * Handle Google Calendar OAuth callback.
     */
    public function googleCalendarCallback(Request $request): RedirectResponse
    {
        if (! $this->validateOAuthState($request, 'google_calendar')) {
            return redirect(url('/settings/integrations'))
                ->with('error', 'Invalid OAuth state. Please try connecting Google Calendar again.');
        }

        if ($request->has('error')) {
            Log::warning('Google Calendar OAuth denied', ['error' => $request->input('error')]);
            return redirect(url('/settings/integrations'))
                ->with('error', 'Google Calendar authorization denied.');
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect(url('/settings/integrations'))
                ->with('error', 'No authorization code received from Google.');
        }

        try {
            $response = $this->http->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'code' => $code,
                    'client_id' => config('services.google_calendar.client_id'),
                    'client_secret' => config('services.google_calendar.client_secret'),
                    'redirect_uri' => config('services.google_calendar.redirect'),
                    'grant_type' => 'authorization_code',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! isset($data['access_token'])) {
                throw new \RuntimeException('Google token exchange returned no access token.');
            }

            // Get user info for account name
            $userInfoResponse = $this->http->get('https://www.googleapis.com/oauth2/v2/userinfo', [
                'headers' => ['Authorization' => 'Bearer ' . $data['access_token']],
            ]);
            $userInfo = json_decode($userInfoResponse->getBody()->getContents(), true);

            $workspaceId = auth()->user()->active_workspace_id;

            ChannelIntegration::updateOrCreate(
                ['workspace_id' => $workspaceId, 'channel' => 'google_calendar'],
                [
                    'credentials' => [
                        'access_token' => $data['access_token'],
                    ],
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'status' => 'active',
                    'error_message' => null,
                    'account_name' => $userInfo['email'] ?? 'Google Calendar',
                    'token_expires_at' => isset($data['expires_in'])
                        ? now()->addSeconds($data['expires_in'])
                        : now()->addHour(),
                ]
            );

            Log::info('Google Calendar OAuth completed', [
                'workspace_id' => $workspaceId,
                'email' => $userInfo['email'] ?? null,
            ]);

            return redirect(url('/settings/integrations'))
                ->with('success', 'Google Calendar connected for ' . ($userInfo['email'] ?? 'your account') . '.');

        } catch (\Throwable $e) {
            Log::error('Google Calendar OAuth callback failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect(url('/settings/integrations'))
                ->with('error', 'Failed to connect Google Calendar: ' . $e->getMessage());
        }
    }

    // =====================================================================
    // HELPERS
    // =====================================================================

    /**
     * Validate the OAuth state parameter to prevent CSRF attacks.
     */
    private function validateOAuthState(Request $request, string $service): bool
    {
        $expected = session()->pull("oauth_{$service}_state");
        $received = $request->input('state');

        if (! $expected || ! $received) {
            Log::warning("OAuth state validation failed for {$service}: missing state", [
                'has_expected' => (bool) $expected,
                'has_received' => (bool) $received,
            ]);
            return false;
        }

        if (! hash_equals($expected, $received)) {
            Log::warning("OAuth state validation failed for {$service}: mismatch", [
                'ip' => $request->ip(),
            ]);
            return false;
        }

        return true;
    }
}
