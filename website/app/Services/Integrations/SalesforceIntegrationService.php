<?php

namespace App\Services\Integrations;

use App\Models\ChannelIntegration;
use App\Models\Contact;
use App\Models\Deal;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class SalesforceIntegrationService
{
    protected Client $httpClient;

    protected string $apiVersion = 'v59.0';

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Resolve the active Salesforce integration for a workspace.
     */
    protected function getIntegration(int $workspaceId): ?ChannelIntegration
    {
        return ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'salesforce')
            ->where('status', 'active')
            ->first();
    }

    /**
     * Build authorization headers for Salesforce API calls.
     */
    protected function headers(string $accessToken): array
    {
        return [
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Check if the OAuth access token is expired and refresh if needed.
     * Salesforce access tokens typically expire after 2 hours.
     *
     * @param ChannelIntegration $integration The integration record.
     */
    public function refreshTokenIfNeeded(ChannelIntegration $integration): void
    {
        // If token hasn't expired yet, skip refresh
        if ($integration->token_expires_at && $integration->token_expires_at->isFuture()) {
            return;
        }

        $refreshToken = $integration->refresh_token;
        if (! $refreshToken) {
            Log::warning('SalesforceIntegration: No refresh token available', [
                'workspace_id' => $integration->workspace_id,
            ]);
            $integration->update([
                'status' => 'error',
                'error_message' => 'OAuth session expired. Please reconnect Salesforce.',
            ]);
            return;
        }

        try {
            $clientId = $integration->credentials['client_id'] ?? config('services.salesforce.client_id');
            $clientSecret = $integration->credentials['client_secret'] ?? config('services.salesforce.client_secret');

            $loginUrl = $integration->salesforce_instance_url ?? 'https://login.salesforce.com';

            $response = $this->httpClient->post("{$loginUrl}/services/oauth2/token", [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            $newAccessToken = $data['access_token'] ?? null;
            $newInstanceUrl = $data['instance_url'] ?? $integration->salesforce_instance_url;

            if (! $newAccessToken) {
                throw new \RuntimeException('No access_token in refresh response');
            }

            // Update stored credentials with the new access token
            $creds = $integration->credentials;
            $creds['access_token'] = $newAccessToken;

            $integration->update([
                'credentials' => $creds,
                'salesforce_instance_url' => $newInstanceUrl,
                'token_expires_at' => now()->addHours(2),
                'status' => 'active',
                'error_message' => null,
            ]);

            Log::info('SalesforceIntegration: Access token refreshed', [
                'workspace_id' => $integration->workspace_id,
            ]);
        } catch (GuzzleException $e) {
            Log::error('SalesforceIntegration: Token refresh failed', [
                'workspace_id' => $integration->workspace_id,
                'error' => $e->getMessage(),
            ]);

            $integration->update([
                'status' => 'error',
                'error_message' => 'Token refresh failed. Please reconnect Salesforce.',
            ]);
        }
    }

    /**
     * Push a MailTrixy contact to Salesforce as a Contact object.
     * Creates or updates (matched by email).
     *
     * @param Contact $contact The MailTrixy contact.
     * @return string|null Salesforce Contact ID on success, null on failure.
     */
    public function syncContactToSalesforce(Contact $contact): ?string
    {
        try {
            $integration = $this->getIntegration($contact->workspace_id);
            if (! $integration) {
                return null;
            }

            $this->refreshTokenIfNeeded($integration);
            $integration->refresh(); // Reload after potential token update

            $accessToken = $integration->credentials['access_token'] ?? null;
            $instanceUrl = $integration->salesforce_instance_url;

            if (! $accessToken || ! $instanceUrl) {
                return null;
            }

            if (! $contact->email) {
                Log::warning('SalesforceIntegration: Cannot sync contact without email', [
                    'contact_id' => $contact->id,
                ]);
                return null;
            }

            $sfData = [
                'Email' => $contact->email,
                'FirstName' => $contact->first_name ?? '',
                'LastName' => $contact->last_name ?: ($contact->first_name ?: 'Unknown'),
                'Phone' => $contact->phone ?? '',
                'Title' => $contact->job_title ?? '',
                'MailingCity' => $contact->city ?? '',
                'MailingCountry' => $contact->country ?? '',
            ];

            if ($contact->company) {
                $sfData['Department'] = $contact->company;
            }

            // Search for existing contact by email
            $existingId = $this->findContactByEmail($accessToken, $instanceUrl, $contact->email);

            if ($existingId) {
                // Update existing
                $this->httpClient->patch(
                    "{$instanceUrl}/services/data/{$this->apiVersion}/sobjects/Contact/{$existingId}",
                    [
                        'headers' => $this->headers($accessToken),
                        'json' => $sfData,
                    ]
                );

                Log::info('SalesforceIntegration: Contact updated', [
                    'contact_id' => $contact->id,
                    'salesforce_id' => $existingId,
                ]);

                return $existingId;
            }

            // Create new
            $response = $this->httpClient->post(
                "{$instanceUrl}/services/data/{$this->apiVersion}/sobjects/Contact",
                [
                    'headers' => $this->headers($accessToken),
                    'json' => $sfData,
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $sfId = $data['id'] ?? null;

            Log::info('SalesforceIntegration: Contact created', [
                'contact_id' => $contact->id,
                'salesforce_id' => $sfId,
            ]);

            // Store SF ID in custom_fields
            $customFields = $contact->custom_fields ?? [];
            $customFields['salesforce_id'] = $sfId;
            $contact->update(['custom_fields' => $customFields]);

            return $sfId;
        } catch (GuzzleException $e) {
            Log::error('SalesforceIntegration: Failed to sync contact', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Push a MailTrixy deal to Salesforce as an Opportunity.
     *
     * @param Deal $deal The MailTrixy deal.
     * @return string|null Salesforce Opportunity ID on success, null on failure.
     */
    public function syncDealToSalesforce(Deal $deal): ?string
    {
        try {
            $integration = $this->getIntegration($deal->workspace_id);
            if (! $integration) {
                return null;
            }

            $this->refreshTokenIfNeeded($integration);
            $integration->refresh();

            $accessToken = $integration->credentials['access_token'] ?? null;
            $instanceUrl = $integration->salesforce_instance_url;

            if (! $accessToken || ! $instanceUrl) {
                return null;
            }

            $stageName = $deal->dealStage?->name ?? 'Prospecting';
            $closeDate = $deal->expected_close_date?->format('Y-m-d')
                ?? now()->addDays(30)->format('Y-m-d');

            $sfData = [
                'Name' => $deal->title ?: 'Untitled Deal',
                'StageName' => $stageName,
                'Amount' => (float) ($deal->value ?? 0),
                'CloseDate' => $closeDate,
                'Description' => $deal->notes ?? '',
            ];

            // Check for existing SF opportunity ID in custom_fields
            $customFields = $deal->custom_fields ?? [];
            $existingId = $customFields['salesforce_opportunity_id'] ?? null;

            if ($existingId) {
                $this->httpClient->patch(
                    "{$instanceUrl}/services/data/{$this->apiVersion}/sobjects/Opportunity/{$existingId}",
                    [
                        'headers' => $this->headers($accessToken),
                        'json' => $sfData,
                    ]
                );

                Log::info('SalesforceIntegration: Deal updated in Salesforce', [
                    'deal_id' => $deal->id,
                    'salesforce_id' => $existingId,
                ]);

                return $existingId;
            }

            // Create new Opportunity
            $response = $this->httpClient->post(
                "{$instanceUrl}/services/data/{$this->apiVersion}/sobjects/Opportunity",
                [
                    'headers' => $this->headers($accessToken),
                    'json' => $sfData,
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $sfId = $data['id'] ?? null;

            // Store SF Opportunity ID for future updates
            $customFields['salesforce_opportunity_id'] = $sfId;
            $deal->update(['custom_fields' => $customFields]);

            Log::info('SalesforceIntegration: Deal created in Salesforce', [
                'deal_id' => $deal->id,
                'salesforce_id' => $sfId,
            ]);

            return $sfId;
        } catch (GuzzleException $e) {
            Log::error('SalesforceIntegration: Failed to sync deal', [
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Pull recent contacts from Salesforce into MailTrixy.
     *
     * @param int $workspaceId The workspace ID.
     * @param int $limit       Max contacts to pull.
     * @return array Sync result stats: ['synced' => int, 'failed' => int]
     */
    public function pullContacts(int $workspaceId, int $limit = 100): array
    {
        $stats = ['synced' => 0, 'failed' => 0];

        try {
            $integration = $this->getIntegration($workspaceId);
            if (! $integration) {
                return $stats;
            }

            $this->refreshTokenIfNeeded($integration);
            $integration->refresh();

            $accessToken = $integration->credentials['access_token'] ?? null;
            $instanceUrl = $integration->salesforce_instance_url;

            if (! $accessToken || ! $instanceUrl) {
                return $stats;
            }

            $soql = urlencode(
                "SELECT Id, Email, FirstName, LastName, Phone, Title, MailingCity, MailingCountry "
                . "FROM Contact WHERE Email != null ORDER BY CreatedDate DESC LIMIT {$limit}"
            );

            $response = $this->httpClient->get(
                "{$instanceUrl}/services/data/{$this->apiVersion}/query",
                [
                    'headers' => $this->headers($accessToken),
                    'query' => ['q' => "SELECT Id, Email, FirstName, LastName, Phone, Title, MailingCity, MailingCountry FROM Contact WHERE Email != null ORDER BY CreatedDate DESC LIMIT {$limit}"],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $records = $data['records'] ?? [];

            foreach ($records as $record) {
                try {
                    $email = $record['Email'] ?? null;
                    if (! $email) {
                        continue;
                    }

                    $contact = Contact::updateOrCreate(
                        [
                            'workspace_id' => $workspaceId,
                            'email' => $email,
                        ],
                        [
                            'first_name' => $record['FirstName'] ?? null,
                            'last_name' => $record['LastName'] ?? null,
                            'phone' => $record['Phone'] ?? null,
                            'job_title' => $record['Title'] ?? null,
                            'city' => $record['MailingCity'] ?? null,
                            'country' => $record['MailingCountry'] ?? null,
                            'status' => 'active',
                        ]
                    );

                    // Store SF ID reference
                    $customFields = $contact->custom_fields ?? [];
                    $customFields['salesforce_id'] = $record['Id'];
                    $contact->update(['custom_fields' => $customFields]);

                    $stats['synced']++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    Log::debug('SalesforceIntegration: Individual contact import failed', [
                        'salesforce_id' => $record['Id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update last sync metadata
            $integration->update([
                'config' => array_merge($integration->config ?? [], [
                    'last_sync_at' => now()->toIso8601String(),
                    'last_sync_stats' => $stats,
                ]),
            ]);

            Log::info('SalesforceIntegration: Contact pull completed', [
                'workspace_id' => $workspaceId,
                'stats' => $stats,
            ]);
        } catch (GuzzleException $e) {
            Log::error('SalesforceIntegration: Failed to pull contacts', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    /**
     * Search for a Salesforce Contact by email using SOQL.
     */
    protected function findContactByEmail(string $accessToken, string $instanceUrl, string $email): ?string
    {
        try {
            $escapedEmail = str_replace(["'", "\\"], ["\\'", "\\\\"], $email);

            $response = $this->httpClient->get(
                "{$instanceUrl}/services/data/{$this->apiVersion}/query",
                [
                    'headers' => $this->headers($accessToken),
                    'query' => ['q' => "SELECT Id FROM Contact WHERE Email = '{$escapedEmail}' LIMIT 1"],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $records = $data['records'] ?? [];

            return $records[0]['Id'] ?? null;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    /**
     * Fetch a Salesforce contact profile by email so the inbox right panel
     * can show who they are on the Salesforce side (account, open deals,
     * title) next to the conversation. Returns null when the integration is
     * inactive, the email isn't in Salesforce, or the API fails.
     *
     * One SOQL call resolves the contact; a second pulls their recent
     * opportunities via AccountId. Shape mirrors Stripe/HubSpot profiles so
     * the Alpine template can render it consistently.
     */
    public function getContactProfile(int $workspaceId, string $email): ?array
    {
        try {
            $integration = $this->getIntegration($workspaceId);
            if (! $integration) {
                return null;
            }

            $this->refreshTokenIfNeeded($integration);
            $integration->refresh();

            $accessToken = $integration->credentials['access_token'] ?? null;
            $instanceUrl = $integration->salesforce_instance_url;
            if (! $accessToken || ! $instanceUrl) {
                return null;
            }

            // Escape single quotes per SOQL rules — simple, unambiguous.
            $emailSoql = str_replace("'", "\\'", $email);

            $contactResp = $this->httpClient->get(
                "{$instanceUrl}/services/data/{$this->apiVersion}/query",
                [
                    'headers' => $this->headers($accessToken),
                    'query' => [
                        'q' => "SELECT Id, FirstName, LastName, Email, Phone, Title, "
                            . "MailingCity, MailingCountry, LeadSource, AccountId, Account.Name "
                            . "FROM Contact WHERE Email = '{$emailSoql}' LIMIT 1",
                    ],
                ]
            );
            $contactData = json_decode($contactResp->getBody()->getContents(), true);
            $contact = ($contactData['records'] ?? [])[0] ?? null;
            if (! $contact) {
                return null;
            }

            $opportunities = [];
            if (! empty($contact['AccountId'])) {
                try {
                    $accId = str_replace("'", "\\'", $contact['AccountId']);
                    $oppResp = $this->httpClient->get(
                        "{$instanceUrl}/services/data/{$this->apiVersion}/query",
                        [
                            'headers' => $this->headers($accessToken),
                            'query' => [
                                'q' => "SELECT Id, Name, StageName, Amount, CloseDate, IsClosed, IsWon "
                                    . "FROM Opportunity WHERE AccountId = '{$accId}' "
                                    . "ORDER BY CloseDate DESC LIMIT 5",
                            ],
                        ]
                    );
                    $oppData = json_decode($oppResp->getBody()->getContents(), true);
                    foreach (($oppData['records'] ?? []) as $opp) {
                        $opportunities[] = [
                            'id' => $opp['Id'] ?? null,
                            'name' => $opp['Name'] ?? 'Untitled',
                            'stage' => $opp['StageName'] ?? null,
                            'amount' => isset($opp['Amount']) ? (float) $opp['Amount'] : null,
                            'close_date' => $opp['CloseDate'] ?? null,
                            'is_closed' => (bool) ($opp['IsClosed'] ?? false),
                            'is_won' => (bool) ($opp['IsWon'] ?? false),
                        ];
                    }
                } catch (GuzzleException $e) {
                    // Opportunities are a nice-to-have — don't fail the card.
                }
            }

            return [
                'id' => $contact['Id'] ?? null,
                'email' => $contact['Email'] ?? $email,
                'full_name' => trim(($contact['FirstName'] ?? '') . ' ' . ($contact['LastName'] ?? '')) ?: null,
                'title' => $contact['Title'] ?? null,
                'phone' => $contact['Phone'] ?? null,
                'city' => $contact['MailingCity'] ?? null,
                'country' => $contact['MailingCountry'] ?? null,
                'lead_source' => $contact['LeadSource'] ?? null,
                'account_id' => $contact['AccountId'] ?? null,
                'account_name' => $contact['Account']['Name'] ?? null,
                'opportunities' => $opportunities,
                'url' => $contact['Id']
                    ? rtrim($instanceUrl, '/') . '/' . $contact['Id']
                    : null,
            ];
        } catch (GuzzleException $e) {
            Log::warning('SalesforceIntegration: getContactProfile failed', [
                'workspace_id' => $workspaceId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if Salesforce integration is active for a workspace.
     */
    public function isActive(int $workspaceId): bool
    {
        return $this->getIntegration($workspaceId) !== null;
    }

    /**
     * Get the last sync metadata for a workspace's Salesforce integration.
     */
    public function getLastSyncInfo(int $workspaceId): ?array
    {
        $integration = $this->getIntegration($workspaceId);
        if (! $integration) {
            return null;
        }

        $config = $integration->config ?? [];

        return [
            'last_sync_at' => $config['last_sync_at'] ?? null,
            'last_sync_stats' => $config['last_sync_stats'] ?? null,
        ];
    }
}
