<?php

namespace App\Livewire\Settings;

use App\Models\ChannelIntegration;
use App\Services\Integrations\HubSpotIntegrationService;
use App\Services\Integrations\SalesforceIntegrationService;
use App\Services\Integrations\SlackIntegrationService;
use App\Traits\AuthorizesWorkspaceActions;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;

class IntegrationManager extends Component
{
    use AuthorizesWorkspaceActions;

    /** Currently loaded integrations keyed by service name. */
    public array $integrations = [];

    /** Which service's config form is currently open (null = none). */
    public ?string $configuringService = null;

    /** Inline form data for API-key-based integrations. */
    public array $configForm = [];

    /** Status message after an action. */
    public string $statusMessage = '';
    public string $statusType = 'success'; // success, error, info

    /** Zapier-specific display values (non-sensitive). */
    public string $zapierWebhookUrl = '';
    public string $zapierApiToken = '';

    /** Whether a test is currently running. */
    public bool $testing = false;

    /** Whether a sync operation is currently running. */
    public bool $syncing = false;

    /** Sync result stats to display after a sync completes. */
    public ?array $syncResult = null;

    /** Slack channels for channel selection dropdown. */
    public array $slackChannels = [];

    /** Currently selected Slack channel for notifications. */
    public string $selectedSlackChannel = '';

    /**
     * Integration registry: defines all supported third-party integrations.
     * This is the single source of truth for what appears on the page.
     */
    private const INTEGRATION_DEFINITIONS = [
        'slack' => [
            'name' => 'Slack',
            'category' => 'Communication',
            'description' => 'Get notifications and manage conversations in Slack.',
            'bg' => 'bg-purple-50',
            'text' => 'text-purple-600',
            'type' => 'oauth', // oauth | api_key | webhook_token
        ],
        'stripe' => [
            'name' => 'Stripe',
            'category' => 'Payments',
            'description' => 'View customer payment data alongside conversations.',
            'bg' => 'bg-indigo-50',
            'text' => 'text-indigo-600',
            'type' => 'api_key',
        ],
        'zapier' => [
            'name' => 'Zapier',
            'category' => 'Automation',
            'description' => 'Connect with 5,000+ apps via Zapier.',
            'bg' => 'bg-orange-50',
            'text' => 'text-orange-600',
            'type' => 'webhook_token',
        ],
        'salesforce' => [
            'name' => 'Salesforce',
            'category' => 'CRM',
            'description' => 'Sync contacts and deals with Salesforce CRM.',
            'bg' => 'bg-blue-50',
            'text' => 'text-blue-600',
            'type' => 'oauth',
        ],
        'hubspot' => [
            'name' => 'HubSpot',
            'category' => 'CRM',
            'description' => 'Two-way sync with HubSpot contacts and companies.',
            'bg' => 'bg-orange-50',
            'text' => 'text-orange-600',
            'type' => 'api_key',
        ],
        'google_calendar' => [
            'name' => 'Google Calendar',
            'category' => 'Productivity',
            'description' => 'Schedule meetings and sync calendar events.',
            'bg' => 'bg-blue-50',
            'text' => 'text-blue-600',
            'type' => 'oauth',
        ],
    ];

    public function mount(): void
    {
        $this->loadIntegrations();
    }

    /**
     * Load existing integration records from the database and merge with definitions.
     */
    public function loadIntegrations(): void
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $dbIntegrations = ChannelIntegration::where('workspace_id', $workspaceId)
            ->whereIn('channel', array_keys(self::INTEGRATION_DEFINITIONS))
            ->get()
            ->keyBy('channel');

        $result = [];

        foreach (self::INTEGRATION_DEFINITIONS as $key => $def) {
            $record = $dbIntegrations->get($key);
            $config = $record?->config ?? [];

            $result[$key] = array_merge($def, [
                'key' => $key,
                'status' => $record?->status ?? 'not_connected',
                'account_name' => $record?->account_name ?? null,
                'error_message' => $record?->error_message ?? null,
                'connected_at' => $record?->updated_at?->diffForHumans() ?? null,
                'last_sync_at' => isset($config['last_sync_at'])
                    ? \Carbon\Carbon::parse($config['last_sync_at'])->diffForHumans()
                    : null,
                'last_sync_stats' => $config['last_sync_stats'] ?? null,
                'slack_channel_id' => $record?->slack_channel_id ?? null,
                'forward_new_messages' => (bool) (($record?->credentials ?? [])['forward_new_messages'] ?? false),
            ]);
        }

        $this->integrations = $result;
    }

    /**
     * Initiate connection flow for a given service.
     * OAuth services redirect; API-key services open an inline form.
     */
    public function connect(string $service): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $def = self::INTEGRATION_DEFINITIONS[$service] ?? null;
        if (! $def) {
            return;
        }

        match ($def['type']) {
            'oauth' => $this->startOAuth($service),
            'api_key' => $this->openConfigForm($service),
            'webhook_token' => $this->generateWebhookToken($service),
        };
    }

    /**
     * Open the inline config form for API-key-based integrations.
     */
    public function openConfigForm(string $service): void
    {
        $this->configuringService = $service;
        $this->statusMessage = '';
        $this->configForm = [];

        // Pre-fill from existing integration if present
        $workspaceId = auth()->user()->active_workspace_id;
        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', $service)
            ->first();

        if ($integration && $integration->status === 'active') {
            $creds = $integration->credentials;

            if ($service === 'stripe') {
                // Show masked version of the key
                $key = $creds['secret_key'] ?? '';
                $this->configForm['secret_key'] = $key ? (str_repeat('*', max(0, strlen($key) - 8)) . substr($key, -8)) : '';
            } elseif ($service === 'hubspot') {
                $key = $creds['api_key'] ?? '';
                $this->configForm['api_key'] = $key ? (str_repeat('*', max(0, strlen($key) - 8)) . substr($key, -8)) : '';
            } elseif ($service === 'salesforce') {
                $this->configForm['client_id'] = $creds['client_id'] ?? '';
                $this->configForm['client_secret'] = $creds['client_secret'] ?? '' ? '********' : '';
                $this->configForm['instance_url'] = $integration->salesforce_instance_url ?? '';
            }
        } else {
            // Initialize empty form fields
            if ($service === 'stripe') {
                $this->configForm = ['secret_key' => ''];
            } elseif ($service === 'hubspot') {
                $this->configForm = ['api_key' => ''];
            } elseif ($service === 'salesforce') {
                $this->configForm = ['client_id' => '', 'client_secret' => '', 'instance_url' => ''];
            }
        }
    }

    /**
     * Close the config modal/form.
     */
    public function closeConfigForm(): void
    {
        $this->configuringService = null;
        $this->configForm = [];
        $this->statusMessage = '';
        $this->zapierWebhookUrl = '';
        $this->zapierApiToken = '';
    }

    /**
     * Save an API key for Stripe or HubSpot.
     */
    public function saveApiKey(string $service): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        if ($service === 'stripe') {
            $key = $this->configForm['secret_key'] ?? '';

            // Skip if the user submitted the masked placeholder
            if (empty($key) || str_starts_with($key, '***')) {
                $this->statusMessage = 'Please enter a valid Stripe secret key.';
                $this->statusType = 'error';
                return;
            }

            // Validate key format (sk_live_ or sk_test_)
            if (! preg_match('/^sk_(test|live)_[a-zA-Z0-9]+$/', $key)) {
                $this->statusMessage = 'Invalid Stripe key format. Expected sk_test_... or sk_live_...';
                $this->statusType = 'error';
                return;
            }

            // Save to system_settings (Stripe is global, not per-workspace channel)
            // AND to channel_integrations for the integrations page status tracking
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'stripe_secret_key'],
                ['value' => encrypt($key), 'group' => 'integrations', 'updated_at' => now()]
            );

            ChannelIntegration::updateOrCreate(
                ['workspace_id' => $workspaceId, 'channel' => 'stripe'],
                [
                    'credentials' => ['secret_key' => $key],
                    'status' => 'active',
                    'account_name' => str_starts_with($key, 'sk_live_') ? 'Live Mode' : 'Test Mode',
                    'error_message' => null,
                ]
            );
        } elseif ($service === 'hubspot') {
            $key = $this->configForm['api_key'] ?? '';

            if (empty($key) || str_starts_with($key, '***')) {
                $this->statusMessage = 'Please enter a valid HubSpot API key.';
                $this->statusType = 'error';
                return;
            }

            // HubSpot private app tokens start with pat-
            if (! str_starts_with($key, 'pat-')) {
                $this->statusMessage = 'Invalid HubSpot key format. Private app tokens start with pat-...';
                $this->statusType = 'error';
                return;
            }

            ChannelIntegration::updateOrCreate(
                ['workspace_id' => $workspaceId, 'channel' => 'hubspot'],
                [
                    'credentials' => ['api_key' => $key],
                    'status' => 'active',
                    'account_name' => 'HubSpot',
                    'error_message' => null,
                ]
            );
        }

        $this->statusMessage = ucfirst($service) . ' connected successfully.';
        $this->statusType = 'success';
        $this->loadIntegrations();

        // Keep the form open to show success
    }

    /**
     * Save Salesforce credentials and redirect to OAuth.
     */
    public function saveSalesforceCredentials(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $clientId = $this->configForm['client_id'] ?? '';
        $clientSecret = $this->configForm['client_secret'] ?? '';
        $instanceUrl = $this->configForm['instance_url'] ?? '';

        if (empty($clientId) || empty($instanceUrl)) {
            $this->statusMessage = 'Client ID and Instance URL are required.';
            $this->statusType = 'error';
            return;
        }

        // Skip masked secret if Salesforce is already connected
        if (empty($clientSecret) || $clientSecret === '********') {
            $workspaceId = auth()->user()->active_workspace_id;
            $existing = ChannelIntegration::where('workspace_id', $workspaceId)
                ->where('channel', 'salesforce')
                ->first();

            if (! $existing || ! ($existing->credentials['client_secret'] ?? null)) {
                $this->statusMessage = 'Client Secret is required for first-time setup.';
                $this->statusType = 'error';
                return;
            }
            // Reuse existing secret
            $clientSecret = $existing->credentials['client_secret'];
        }

        // Clean instance URL
        $instanceUrl = rtrim($instanceUrl, '/');
        if (! str_starts_with($instanceUrl, 'https://')) {
            $instanceUrl = 'https://' . $instanceUrl;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        ChannelIntegration::updateOrCreate(
            ['workspace_id' => $workspaceId, 'channel' => 'salesforce'],
            [
                'credentials' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
                'salesforce_instance_url' => $instanceUrl,
                'status' => 'inactive', // Will become active after OAuth
                'error_message' => null,
            ]
        );

        // Now redirect to Salesforce OAuth
        $this->startOAuth('salesforce');
    }

    /**
     * Generate a unique webhook URL and API token for Zapier.
     */
    public function generateWebhookToken(string $service): void
    {
        if ($service !== 'zapier') {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        $existing = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'zapier')
            ->first();

        if ($existing && $existing->status === 'active') {
            // Show existing tokens
            $this->configuringService = 'zapier';
            $this->zapierWebhookUrl = url("/api/webhooks/zapier/{$existing->zapier_webhook_token}");
            $this->zapierApiToken = $existing->zapier_api_token ?: 'Token unavailable';
            $this->statusMessage = '';
            return;
        }

        // Generate new tokens
        $webhookToken = Str::random(48);
        $apiToken = 'zap_' . Str::random(40);

        ChannelIntegration::updateOrCreate(
            ['workspace_id' => $workspaceId, 'channel' => 'zapier'],
            [
                'credentials' => ['api_token' => $apiToken],
                'zapier_webhook_token' => $webhookToken,
                'zapier_api_token' => $apiToken,
                'status' => 'active',
                'account_name' => 'Zapier',
                'error_message' => null,
            ]
        );

        $this->configuringService = 'zapier';
        $this->zapierWebhookUrl = url("/api/webhooks/zapier/{$webhookToken}");
        $this->zapierApiToken = $apiToken;
        $this->statusMessage = 'Zapier integration created. Copy the webhook URL and API token below.';
        $this->statusType = 'success';
        $this->loadIntegrations();
    }

    /**
     * Regenerate Zapier tokens (revokes the old ones).
     */
    public function regenerateZapierTokens(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        $webhookToken = Str::random(48);
        $apiToken = 'zap_' . Str::random(40);

        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'zapier')
            ->first();

        if ($integration) {
            $integration->zapier_webhook_token = $webhookToken;
            $integration->zapier_api_token = $apiToken;
            $integration->credentials = ['api_token' => $apiToken]; // mutator encrypts
            $integration->save();
        }

        $this->zapierWebhookUrl = url("/api/webhooks/zapier/{$webhookToken}");
        $this->zapierApiToken = $apiToken;
        $this->statusMessage = 'Tokens regenerated. Update your Zapier zaps with the new credentials.';
        $this->statusType = 'info';
    }

    /**
     * Start an OAuth redirect for Slack, Salesforce, or Google Calendar.
     */
    private function startOAuth(string $service): void
    {
        // Generate and store a CSRF state token
        $state = Str::random(40);
        session()->put("oauth_{$service}_state", $state);

        $redirectUrl = match ($service) {
            'slack' => $this->buildSlackOAuthUrl($state),
            'salesforce' => $this->buildSalesforceOAuthUrl($state),
            'google_calendar' => $this->buildGoogleCalendarOAuthUrl($state),
            default => null,
        };

        if ($redirectUrl) {
            $this->redirect($redirectUrl);
        }
    }

    private function buildSlackOAuthUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => config('services.slack.client_id'),
            'scope' => \App\Services\Integrations\SlackIntegrationService::oauthScopeString(),
            'redirect_uri' => config('services.slack.redirect'),
            'state' => $state,
        ]);

        return "https://slack.com/oauth/v2/authorize?{$params}";
    }

    private function buildSalesforceOAuthUrl(string $state): string
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'salesforce')
            ->first();

        $instanceUrl = $integration?->salesforce_instance_url ?? 'https://login.salesforce.com';

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $integration?->credentials['client_id'] ?? config('services.salesforce.client_id'),
            'redirect_uri' => config('services.salesforce.redirect'),
            'state' => $state,
        ]);

        return "{$instanceUrl}/services/oauth2/authorize?{$params}";
    }

    private function buildGoogleCalendarOAuthUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => config('services.google_calendar.redirect'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.readonly https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
    }

    /**
     * Disconnect a service: remove credentials and deactivate.
     */
    public function disconnect(string $service): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', $service)
            ->first();

        if (! $integration) {
            return;
        }

        // Clear all credentials and tokens
        $integration->update([
            'credentials' => null,
            'status' => 'inactive',
            'error_message' => null,
            'account_name' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'zapier_webhook_token' => null,
            'zapier_api_token' => null,
        ]);

        // If Stripe, also remove from system_settings
        if ($service === 'stripe') {
            DB::table('system_settings')->where('key', 'stripe_secret_key')->delete();
        }

        $this->statusMessage = self::INTEGRATION_DEFINITIONS[$service]['name'] . ' disconnected.';
        $this->statusType = 'info';
        $this->configuringService = null;
        $this->loadIntegrations();
    }

    /**
     * Test the connection for a given service by making a real API call.
     */
    public function testConnection(string $service): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->testing = true;
        $workspaceId = auth()->user()->active_workspace_id;

        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', $service)
            ->first();

        if (! $integration || $integration->status !== 'active') {
            $this->statusMessage = 'Integration is not active. Connect it first.';
            $this->statusType = 'error';
            $this->testing = false;
            return;
        }

        $client = new Client(['timeout' => 120, 'connect_timeout' => 10]);

        try {
            $result = match ($service) {
                'slack' => $this->testSlack($client, $integration),
                'stripe' => $this->testStripe($client, $integration),
                'hubspot' => $this->testHubSpot($client, $integration),
                'salesforce' => $this->testSalesforce($client, $integration),
                'google_calendar' => $this->testGoogleCalendar($client, $integration),
                'zapier' => ['ok' => true, 'message' => 'Zapier is ready to receive webhooks.'],
                default => ['ok' => false, 'message' => 'Unknown service.'],
            };

            if ($result['ok']) {
                $integration->update(['status' => 'active', 'error_message' => null]);
                $this->statusMessage = $result['message'];
                $this->statusType = 'success';
            } else {
                $integration->update(['status' => 'error', 'error_message' => $result['message']]);
                $this->statusMessage = $result['message'];
                $this->statusType = 'error';
            }

            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error("Integration test failed for {$service}", [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            $integration->update(['status' => 'error', 'error_message' => 'Connection test failed.']);
            $this->statusMessage = 'Connection test failed: ' . Str::limit($e->getMessage(), 100);
            $this->statusType = 'error';
            $this->loadIntegrations();
        }

        $this->testing = false;
    }

    private function testSlack(Client $client, ChannelIntegration $integration): array
    {
        $token = $integration->credentials['access_token']
            ?? $integration->credentials['bot_token']
            ?? null;

        if (! $token) {
            return ['ok' => false, 'message' => 'No Slack access token found.'];
        }

        $response = $client->post('https://slack.com/api/auth.test', [
            'headers' => ['Authorization' => "Bearer {$token}"],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if ($data['ok'] ?? false) {
            $teamName = $data['team'] ?? 'Unknown';
            $integration->update(['account_name' => $teamName]);
            return ['ok' => true, 'message' => "Connected to Slack workspace: {$teamName}"];
        }

        return ['ok' => false, 'message' => 'Slack auth failed: ' . ($data['error'] ?? 'unknown error')];
    }

    private function testStripe(Client $client, ChannelIntegration $integration): array
    {
        $key = $integration->credentials['secret_key'] ?? null;
        if (! $key) {
            return ['ok' => false, 'message' => 'No Stripe secret key found.'];
        }

        $response = $client->get('https://api.stripe.com/v1/balance', [
            'headers' => ['Authorization' => "Bearer {$key}"],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (isset($data['available'])) {
            $mode = str_starts_with($key, 'sk_live_') ? 'Live' : 'Test';
            return ['ok' => true, 'message' => "Stripe connected ({$mode} mode). Balance accessible."];
        }

        return ['ok' => false, 'message' => 'Could not retrieve Stripe balance.'];
    }

    private function testHubSpot(Client $client, ChannelIntegration $integration): array
    {
        $key = $integration->credentials['api_key'] ?? null;
        if (! $key) {
            return ['ok' => false, 'message' => 'No HubSpot API key found.'];
        }

        $response = $client->get('https://api.hubapi.com/crm/v3/objects/contacts', [
            'headers' => ['Authorization' => "Bearer {$key}"],
            'query' => ['limit' => 1],
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode === 200) {
            return ['ok' => true, 'message' => 'HubSpot connected. CRM API accessible.'];
        }

        return ['ok' => false, 'message' => "HubSpot returned HTTP {$statusCode}."];
    }

    private function testSalesforce(Client $client, ChannelIntegration $integration): array
    {
        $token = $integration->credentials['access_token'] ?? null;
        $instanceUrl = $integration->salesforce_instance_url;

        if (! $token || ! $instanceUrl) {
            return ['ok' => false, 'message' => 'Missing Salesforce access token or instance URL.'];
        }

        $response = $client->get("{$instanceUrl}/services/data/v59.0/", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
            ],
        ]);

        if ($response->getStatusCode() === 200) {
            return ['ok' => true, 'message' => 'Salesforce connected. API v59.0 accessible.'];
        }

        return ['ok' => false, 'message' => 'Salesforce API returned an error.'];
    }

    private function testGoogleCalendar(Client $client, ChannelIntegration $integration): array
    {
        $token = $integration->credentials['access_token'] ?? null;
        if (! $token) {
            return ['ok' => false, 'message' => 'No Google Calendar access token found.'];
        }

        $response = $client->get('https://www.googleapis.com/calendar/v3/users/me/calendarList', [
            'headers' => ['Authorization' => "Bearer {$token}"],
            'query' => ['maxResults' => 1],
        ]);

        if ($response->getStatusCode() === 200) {
            return ['ok' => true, 'message' => 'Google Calendar connected. Calendar list accessible.'];
        }

        return ['ok' => false, 'message' => 'Google Calendar API returned an error.'];
    }

    // ======================================================================
    // Integration sync / action methods
    // ======================================================================

    /**
     * Sync contacts from MailTrixy to HubSpot (bulk).
     */
    public function syncHubSpotContacts(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->syncing = true;
        $this->syncResult = null;
        $this->statusMessage = '';

        try {
            $hubspot = app(HubSpotIntegrationService::class);
            $workspaceId = auth()->user()->active_workspace_id;
            $stats = $hubspot->syncAllContacts($workspaceId);

            $this->syncResult = $stats;
            $this->statusMessage = "HubSpot sync complete: {$stats['synced']} synced, {$stats['failed']} failed.";
            $this->statusType = $stats['failed'] > 0 ? 'info' : 'success';
            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('IntegrationManager: HubSpot sync failed', ['error' => $e->getMessage()]);
            $this->statusMessage = 'HubSpot sync failed: ' . Str::limit($e->getMessage(), 100);
            $this->statusType = 'error';
        }

        $this->syncing = false;
    }

    /**
     * Sync contacts from MailTrixy to Salesforce (push).
     */
    public function syncSalesforceContacts(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->syncing = true;
        $this->syncResult = null;
        $this->statusMessage = '';

        try {
            $salesforce = app(SalesforceIntegrationService::class);
            $workspaceId = auth()->user()->active_workspace_id;

            // Pull contacts from Salesforce into MailTrixy
            $stats = $salesforce->pullContacts($workspaceId, 100);

            $this->syncResult = $stats;
            $this->statusMessage = "Salesforce contacts sync: {$stats['synced']} imported, {$stats['failed']} failed.";
            $this->statusType = $stats['failed'] > 0 ? 'info' : 'success';
            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('IntegrationManager: Salesforce contacts sync failed', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Salesforce sync failed: ' . Str::limit($e->getMessage(), 100);
            $this->statusType = 'error';
        }

        $this->syncing = false;
    }

    /**
     * Sync deals from MailTrixy to Salesforce (push all open deals).
     */
    public function syncSalesforceDeals(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->syncing = true;
        $this->syncResult = null;
        $this->statusMessage = '';

        try {
            $salesforce = app(SalesforceIntegrationService::class);
            $workspaceId = auth()->user()->active_workspace_id;

            $deals = \App\Models\Deal::where('workspace_id', $workspaceId)
                ->where('status', 'open')
                ->with(['dealStage', 'contact'])
                ->get();

            $synced = 0;
            $failed = 0;

            foreach ($deals as $deal) {
                try {
                    $sfId = $salesforce->syncDealToSalesforce($deal);
                    if ($sfId) {
                        $synced++;
                    } else {
                        $failed++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                }
            }

            $this->syncResult = ['synced' => $synced, 'failed' => $failed];
            $this->statusMessage = "Salesforce deals sync: {$synced} synced, {$failed} failed.";
            $this->statusType = $failed > 0 ? 'info' : 'success';
            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('IntegrationManager: Salesforce deals sync failed', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Salesforce deals sync failed: ' . Str::limit($e->getMessage(), 100);
            $this->statusType = 'error';
        }

        $this->syncing = false;
    }

    /**
     * Fetch Slack channels for the channel selection dropdown.
     */
    public function loadSlackChannels(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        // Clear any leftover banner from a previous test/save attempt so the
        // newly-opened modal doesn't inherit a red "Failed to send test
        // notification" message the user already acknowledged.
        $this->statusMessage = '';
        $this->statusType = 'success';

        try {
            $slack = app(SlackIntegrationService::class);
            $workspaceId = auth()->user()->active_workspace_id;
            $this->slackChannels = $slack->syncChannels($workspaceId);

            // Pre-select the currently configured channel
            $integration = ChannelIntegration::where('workspace_id', $workspaceId)
                ->where('channel', 'slack')
                ->first();
            $this->selectedSlackChannel = $integration?->slack_channel_id ?? '';

            $this->configuringService = 'slack_channels';
        } catch (\Throwable $e) {
            Log::error('IntegrationManager: Failed to load Slack channels', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Failed to load Slack channels.';
            $this->statusType = 'error';
        }
    }

    /**
     * Save the selected Slack notification channel.
     */
    public function saveSlackChannel(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        if (! $this->selectedSlackChannel) {
            $this->statusMessage = 'Please select a channel.';
            $this->statusType = 'error';
            return;
        }

        try {
            $slack = app(SlackIntegrationService::class);
            $workspaceId = auth()->user()->active_workspace_id;
            $slack->setNotificationChannel($workspaceId, $this->selectedSlackChannel);

            $channelName = collect($this->slackChannels)
                ->firstWhere('id', $this->selectedSlackChannel)['name'] ?? $this->selectedSlackChannel;

            $this->statusMessage = "Notifications will be sent to #{$channelName}.";
            $this->statusType = 'success';
            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('IntegrationManager: Failed to save Slack channel', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Failed to save channel selection.';
            $this->statusType = 'error';
        }
    }

    /**
     * Toggle the "forward every new inbound message to Slack" preference.
     * Default is OFF — Slack stays quiet unless the user opts in. The gate
     * lives in SlackIntegrationService::postConversationUpdate so disabling
     * here stops notifications from every call site at once.
     */
    public function toggleSlackForward(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;
        $integration = \App\Models\ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'slack')
            ->where('status', 'active')
            ->first();

        if (! $integration) {
            $this->statusMessage = 'Connect Slack first.';
            $this->statusType = 'error';
            return;
        }

        $creds = $integration->credentials ?? [];
        $creds['forward_new_messages'] = ! ($creds['forward_new_messages'] ?? false);
        $integration->update(['credentials' => $creds]);

        $this->statusMessage = $creds['forward_new_messages']
            ? 'Slack forwarding enabled — new inbound messages will post to your Slack channel.'
            : 'Slack forwarding disabled — new inbound messages stay inside the app.';
        $this->statusType = 'success';
        $this->loadIntegrations();
    }

    /**
     * Send a test notification to the configured Slack channel.
     */
    public function sendSlackTestNotification(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        try {
            $slack = app(SlackIntegrationService::class);
            $workspaceId = auth()->user()->active_workspace_id;
            $sent = $slack->sendTestNotification($workspaceId);

            if ($sent) {
                $this->statusMessage = 'Test notification sent to Slack.';
                $this->statusType = 'success';
            } else {
                $this->statusMessage = 'Failed to send test notification. Check channel configuration.';
                $this->statusType = 'error';
            }
        } catch (\Throwable $e) {
            Log::error('IntegrationManager: Slack test notification failed', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Test notification failed.';
            $this->statusType = 'error';
        }
    }

    /**
     * Get the status class for a given service.
     */
    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'active' => 'text-green-600',
            'error' => 'text-red-600',
            default => 'text-gray-400',
        };
    }

    public function getStatusDotColor(string $status): string
    {
        return match ($status) {
            'active' => 'bg-green-500',
            'error' => 'bg-red-500',
            default => 'bg-gray-300',
        };
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Connected',
            'error' => 'Error',
            default => 'Not connected',
        };
    }

    public function render()
    {
        return view('livewire.settings.integration-manager');
    }
}
