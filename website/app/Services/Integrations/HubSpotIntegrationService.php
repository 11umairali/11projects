<?php

namespace App\Services\Integrations;

use App\Models\ChannelIntegration;
use App\Models\Contact;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class HubSpotIntegrationService
{
    protected Client $httpClient;

    protected string $baseUrl = 'https://api.hubapi.com';

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Resolve the active HubSpot integration for a workspace.
     */
    protected function getIntegration(int $workspaceId): ?ChannelIntegration
    {
        return ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'hubspot')
            ->where('status', 'active')
            ->first();
    }

    /**
     * Extract the API token from a HubSpot integration.
     */
    protected function getApiKey(ChannelIntegration $integration): ?string
    {
        return $integration->credentials['api_key'] ?? null;
    }

    /**
     * Build authorization headers for HubSpot API calls.
     */
    protected function headers(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Push a MailTrixy contact to HubSpot CRM.
     * Creates or updates (by email) via the HubSpot Contacts v3 API.
     *
     * @param Contact $contact The MailTrixy contact.
     * @return string|null HubSpot contact ID on success, null on failure.
     */
    public function syncContactToHubSpot(Contact $contact): ?string
    {
        try {
            $integration = $this->getIntegration($contact->workspace_id);
            if (! $integration) {
                return null;
            }

            $token = $this->getApiKey($integration);
            if (! $token) {
                return null;
            }

            if (! $contact->email) {
                Log::warning('HubSpotIntegration: Cannot sync contact without email', [
                    'contact_id' => $contact->id,
                ]);
                return null;
            }

            $properties = [
                'email' => $contact->email,
                'firstname' => $contact->first_name ?? '',
                'lastname' => $contact->last_name ?? '',
                'phone' => $contact->phone ?? '',
                'company' => $contact->company ?? '',
                'jobtitle' => $contact->job_title ?? '',
                'city' => $contact->city ?? '',
                'country' => $contact->country ?? '',
            ];

            // First try to find existing contact by email
            $existingId = $this->findContactByEmail($token, $contact->email);

            if ($existingId) {
                // Update existing contact
                $this->httpClient->patch(
                    "{$this->baseUrl}/crm/v3/objects/contacts/{$existingId}",
                    [
                        'headers' => $this->headers($token),
                        'json' => ['properties' => $properties],
                    ]
                );

                Log::info('HubSpotIntegration: Contact updated', [
                    'contact_id' => $contact->id,
                    'hubspot_id' => $existingId,
                ]);

                return $existingId;
            }

            // Create new contact
            $response = $this->httpClient->post(
                "{$this->baseUrl}/crm/v3/objects/contacts",
                [
                    'headers' => $this->headers($token),
                    'json' => ['properties' => $properties],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $hubspotId = $data['id'] ?? null;

            Log::info('HubSpotIntegration: Contact created', [
                'contact_id' => $contact->id,
                'hubspot_id' => $hubspotId,
            ]);

            return $hubspotId;
        } catch (GuzzleException $e) {
            Log::error('HubSpotIntegration: Failed to sync contact to HubSpot', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Pull a HubSpot contact into MailTrixy by HubSpot contact ID.
     *
     * @param int    $workspaceId The workspace to create the contact in.
     * @param string $hubspotId   The HubSpot contact ID.
     * @return Contact|null The created/updated MailTrixy contact, or null on failure.
     */
    public function syncContactFromHubSpot(int $workspaceId, string $hubspotId): ?Contact
    {
        try {
            $integration = $this->getIntegration($workspaceId);
            if (! $integration) {
                return null;
            }

            $token = $this->getApiKey($integration);
            if (! $token) {
                return null;
            }

            $response = $this->httpClient->get(
                "{$this->baseUrl}/crm/v3/objects/contacts/{$hubspotId}",
                [
                    'headers' => $this->headers($token),
                    'query' => [
                        'properties' => 'email,firstname,lastname,phone,company,jobtitle,city,country',
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $props = $data['properties'] ?? [];

            $email = $props['email'] ?? null;
            if (! $email) {
                Log::warning('HubSpotIntegration: HubSpot contact has no email', [
                    'hubspot_id' => $hubspotId,
                ]);
                return null;
            }

            $contact = Contact::updateOrCreate(
                [
                    'workspace_id' => $workspaceId,
                    'email' => $email,
                ],
                [
                    'first_name' => $props['firstname'] ?? null,
                    'last_name' => $props['lastname'] ?? null,
                    'phone' => $props['phone'] ?? null,
                    'company' => $props['company'] ?? null,
                    'job_title' => $props['jobtitle'] ?? null,
                    'city' => $props['city'] ?? null,
                    'country' => $props['country'] ?? null,
                    'status' => 'active',
                ]
            );

            // Store the HubSpot ID in custom_fields for future reference
            $customFields = $contact->custom_fields ?? [];
            $customFields['hubspot_id'] = $hubspotId;
            $contact->update(['custom_fields' => $customFields]);

            Log::info('HubSpotIntegration: Contact imported from HubSpot', [
                'contact_id' => $contact->id,
                'hubspot_id' => $hubspotId,
            ]);

            return $contact;
        } catch (GuzzleException $e) {
            Log::error('HubSpotIntegration: Failed to sync contact from HubSpot', [
                'workspace_id' => $workspaceId,
                'hubspot_id' => $hubspotId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Search HubSpot contacts by query string.
     *
     * @param int    $workspaceId The workspace ID.
     * @param string $query       The search query (name, email, etc.).
     * @return array Array of HubSpot contact data.
     */
    public function searchContacts(int $workspaceId, string $query): array
    {
        try {
            $integration = $this->getIntegration($workspaceId);
            if (! $integration) {
                return [];
            }

            $token = $this->getApiKey($integration);
            if (! $token) {
                return [];
            }

            $response = $this->httpClient->post(
                "{$this->baseUrl}/crm/v3/objects/contacts/search",
                [
                    'headers' => $this->headers($token),
                    'json' => [
                        'query' => $query,
                        'limit' => 20,
                        'properties' => ['email', 'firstname', 'lastname', 'company', 'phone'],
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            return array_map(function (array $result) {
                $props = $result['properties'] ?? [];
                return [
                    'id' => $result['id'],
                    'email' => $props['email'] ?? null,
                    'first_name' => $props['firstname'] ?? null,
                    'last_name' => $props['lastname'] ?? null,
                    'company' => $props['company'] ?? null,
                    'phone' => $props['phone'] ?? null,
                ];
            }, $data['results'] ?? []);
        } catch (GuzzleException $e) {
            Log::error('HubSpotIntegration: Search contacts failed', [
                'workspace_id' => $workspaceId,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Bulk sync all MailTrixy contacts to HubSpot (chunked, max 100 at a time).
     *
     * @param int $workspaceId The workspace ID.
     * @return array Sync result stats: ['synced' => int, 'failed' => int, 'skipped' => int]
     */
    public function syncAllContacts(int $workspaceId): array
    {
        $integration = $this->getIntegration($workspaceId);
        if (! $integration) {
            return ['synced' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $token = $this->getApiKey($integration);
        if (! $token) {
            return ['synced' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $stats = ['synced' => 0, 'failed' => 0, 'skipped' => 0];

        // Process contacts in chunks to avoid memory issues and rate limits
        Contact::where('workspace_id', $workspaceId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('status', 'active')
            ->chunk(100, function ($contacts) use ($token, &$stats) {
                $inputs = [];

                foreach ($contacts as $contact) {
                    $inputs[] = [
                        'properties' => [
                            'email' => $contact->email,
                            'firstname' => $contact->first_name ?? '',
                            'lastname' => $contact->last_name ?? '',
                            'phone' => $contact->phone ?? '',
                            'company' => $contact->company ?? '',
                            'jobtitle' => $contact->job_title ?? '',
                            'city' => $contact->city ?? '',
                            'country' => $contact->country ?? '',
                        ],
                    ];
                }

                if (empty($inputs)) {
                    return;
                }

                try {
                    $response = $this->httpClient->post(
                        "{$this->baseUrl}/crm/v3/objects/contacts/batch/create",
                        [
                            'headers' => $this->headers($token),
                            'json' => ['inputs' => $inputs],
                        ]
                    );

                    $data = json_decode($response->getBody()->getContents(), true);
                    $statusCode = $response->getStatusCode();

                    if ($statusCode === 201 || $statusCode === 200) {
                        $stats['synced'] += count($data['results'] ?? []);
                    }

                    // Handle partial failures (409 conflicts = already exists)
                    if (isset($data['errors'])) {
                        $stats['failed'] += count($data['errors']);
                    }
                } catch (GuzzleException $e) {
                    // If batch create fails (e.g. duplicates), fall back to individual upserts
                    foreach ($contacts as $contact) {
                        try {
                            $existingId = $this->findContactByEmail($token, $contact->email);
                            if ($existingId) {
                                $this->httpClient->patch(
                                    "{$this->baseUrl}/crm/v3/objects/contacts/{$existingId}",
                                    [
                                        'headers' => $this->headers($token),
                                        'json' => [
                                            'properties' => [
                                                'email' => $contact->email,
                                                'firstname' => $contact->first_name ?? '',
                                                'lastname' => $contact->last_name ?? '',
                                                'phone' => $contact->phone ?? '',
                                                'company' => $contact->company ?? '',
                                            ],
                                        ],
                                    ]
                                );
                                $stats['synced']++;
                            } else {
                                $this->httpClient->post(
                                    "{$this->baseUrl}/crm/v3/objects/contacts",
                                    [
                                        'headers' => $this->headers($token),
                                        'json' => [
                                            'properties' => [
                                                'email' => $contact->email,
                                                'firstname' => $contact->first_name ?? '',
                                                'lastname' => $contact->last_name ?? '',
                                                'phone' => $contact->phone ?? '',
                                                'company' => $contact->company ?? '',
                                            ],
                                        ],
                                    ]
                                );
                                $stats['synced']++;
                            }
                        } catch (GuzzleException $innerException) {
                            $stats['failed']++;
                            Log::debug('HubSpotIntegration: Individual contact sync failed', [
                                'contact_id' => $contact->id,
                                'error' => $innerException->getMessage(),
                            ]);
                        }
                    }
                }

                // Rate limit protection: brief pause between chunks
                usleep(200_000); // 200ms
            });

        // Update the integration's last sync metadata
        $integration->update([
            'config' => array_merge($integration->config ?? [], [
                'last_sync_at' => now()->toIso8601String(),
                'last_sync_stats' => $stats,
            ]),
        ]);

        Log::info('HubSpotIntegration: Bulk sync completed', [
            'workspace_id' => $workspaceId,
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * Find a HubSpot contact ID by email address.
     */
    protected function findContactByEmail(string $token, string $email): ?string
    {
        try {
            $response = $this->httpClient->post(
                "{$this->baseUrl}/crm/v3/objects/contacts/search",
                [
                    'headers' => $this->headers($token),
                    'json' => [
                        'filterGroups' => [
                            [
                                'filters' => [
                                    [
                                        'propertyName' => 'email',
                                        'operator' => 'EQ',
                                        'value' => $email,
                                    ],
                                ],
                            ],
                        ],
                        'limit' => 1,
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $results = $data['results'] ?? [];

            return $results[0]['id'] ?? null;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    /**
     * Fetch a HubSpot contact profile by email so the inbox right panel can
     * show who they are on the HubSpot side (lifecycle stage, owner, recent
     * activity) without leaving the conversation. Returns null when the
     * integration is inactive, the email isn't in HubSpot, or the API fails.
     *
     * Shape intentionally matches Stripe's getCustomerProfile() so the
     * Alpine template can render it the same way.
     */
    public function getContactProfile(int $workspaceId, string $email): ?array
    {
        try {
            $integration = $this->getIntegration($workspaceId);
            if (! $integration) {
                return null;
            }
            $token = $this->getApiKey($integration);
            if (! $token) {
                return null;
            }

            // Search-by-email is an exact-match filter, not the free-text query,
            // so we hit the dedicated /search endpoint with a structured filter.
            $response = $this->httpClient->post(
                "{$this->baseUrl}/crm/v3/objects/contacts/search",
                [
                    'headers' => $this->headers($token),
                    'json' => [
                        'filterGroups' => [[
                            'filters' => [[
                                'propertyName' => 'email',
                                'operator' => 'EQ',
                                'value' => $email,
                            ]],
                        ]],
                        'properties' => [
                            'email', 'firstname', 'lastname', 'company', 'phone',
                            'lifecyclestage', 'hs_lead_status', 'hubspot_owner_id',
                            'num_associated_deals', 'createdate', 'lastmodifieddate',
                            'jobtitle', 'city', 'country',
                        ],
                        'limit' => 1,
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $hit = ($data['results'] ?? [])[0] ?? null;
            if (! $hit) {
                return null;
            }

            $p = $hit['properties'] ?? [];
            $contactId = $hit['id'] ?? null;

            // Best-effort fetch of associated deals. Failures here shouldn't
            // hide the whole card — the lifecycle/owner info is still useful.
            $deals = [];
            try {
                $dealsResp = $this->httpClient->get(
                    "{$this->baseUrl}/crm/v4/objects/contacts/{$contactId}/associations/deals",
                    ['headers' => $this->headers($token)]
                );
                $dealsData = json_decode($dealsResp->getBody()->getContents(), true);
                $dealIds = array_slice(array_map(fn ($r) => $r['toObjectId'] ?? null, $dealsData['results'] ?? []), 0, 5);
                foreach (array_filter($dealIds) as $dealId) {
                    try {
                        $dResp = $this->httpClient->get(
                            "{$this->baseUrl}/crm/v3/objects/deals/{$dealId}?properties=dealname,amount,dealstage,closedate",
                            ['headers' => $this->headers($token)]
                        );
                        $dProps = json_decode($dResp->getBody()->getContents(), true)['properties'] ?? [];
                        $deals[] = [
                            'id' => $dealId,
                            'name' => $dProps['dealname'] ?? 'Untitled',
                            'amount' => $dProps['amount'] !== null ? (float) $dProps['amount'] : null,
                            'stage' => $dProps['dealstage'] ?? null,
                            'close_date' => $dProps['closedate'] ?? null,
                        ];
                    } catch (GuzzleException $e) {
                        // ignore single-deal failures
                    }
                }
            } catch (GuzzleException $e) {
                // ignore associations lookup failure
            }

            $portalId = $this->getPortalId($integration);

            return [
                'id' => $contactId,
                'email' => $p['email'] ?? $email,
                'full_name' => trim(($p['firstname'] ?? '') . ' ' . ($p['lastname'] ?? '')) ?: null,
                'company' => $p['company'] ?? null,
                'job_title' => $p['jobtitle'] ?? null,
                'phone' => $p['phone'] ?? null,
                'city' => $p['city'] ?? null,
                'country' => $p['country'] ?? null,
                'lifecycle_stage' => $p['lifecyclestage'] ?? null,
                'lead_status' => $p['hs_lead_status'] ?? null,
                'owner_id' => $p['hubspot_owner_id'] ?? null,
                'num_associated_deals' => isset($p['num_associated_deals']) ? (int) $p['num_associated_deals'] : 0,
                'created_at' => $p['createdate'] ?? null,
                'last_modified_at' => $p['lastmodifieddate'] ?? null,
                'deals' => $deals,
                'url' => $portalId && $contactId
                    ? "https://app.hubspot.com/contacts/{$portalId}/contact/{$contactId}"
                    : null,
            ];
        } catch (GuzzleException $e) {
            Log::warning('HubSpotIntegration: getContactProfile failed', [
                'workspace_id' => $workspaceId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract the HubSpot portal/account id from stored credentials if the
     * OAuth flow captured it. Used to build deep-links back to HubSpot.
     */
    protected function getPortalId(\App\Models\ChannelIntegration $integration): ?int
    {
        $creds = $integration->credentials ?? [];
        return isset($creds['portal_id']) ? (int) $creds['portal_id'] : null;
    }

    /**
     * Check if HubSpot integration is active for a workspace.
     */
    public function isActive(int $workspaceId): bool
    {
        $integration = $this->getIntegration($workspaceId);
        return $integration !== null;
    }

    /**
     * Get the last sync metadata for a workspace's HubSpot integration.
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
