<?php

namespace App\Services\Integrations;

use App\Models\ChannelIntegration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class GoogleCalendarIntegrationService
{
    protected Client $httpClient;

    protected string $baseUrl = 'https://www.googleapis.com/calendar/v3';

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Resolve the active Google Calendar integration for a workspace.
     */
    protected function getIntegration(int $workspaceId): ?ChannelIntegration
    {
        return ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'google_calendar')
            ->where('status', 'active')
            ->first();
    }

    /**
     * Build authorization headers for Google API calls.
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
     * Refresh the Google OAuth access token if expired.
     * Google access tokens expire after 1 hour.
     */
    public function refreshTokenIfNeeded(ChannelIntegration $integration): void
    {
        if ($integration->token_expires_at && $integration->token_expires_at->isFuture()) {
            return;
        }

        $refreshToken = $integration->refresh_token;
        if (! $refreshToken) {
            Log::warning('GoogleCalendarIntegration: No refresh token available', [
                'workspace_id' => $integration->workspace_id,
            ]);
            $integration->update([
                'status' => 'error',
                'error_message' => 'OAuth session expired. Please reconnect Google Calendar.',
            ]);
            return;
        }

        try {
            $response = $this->httpClient->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'client_id' => config('services.google_calendar.client_id'),
                    'client_secret' => config('services.google_calendar.client_secret'),
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $newAccessToken = $data['access_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? 3600;

            if (! $newAccessToken) {
                throw new \RuntimeException('No access_token in Google refresh response');
            }

            $creds = $integration->credentials;
            $creds['access_token'] = $newAccessToken;

            $integration->update([
                'credentials' => $creds,
                'token_expires_at' => now()->addSeconds($expiresIn - 60), // Subtract 60s buffer
                'status' => 'active',
                'error_message' => null,
            ]);

            Log::info('GoogleCalendarIntegration: Access token refreshed', [
                'workspace_id' => $integration->workspace_id,
            ]);
        } catch (GuzzleException $e) {
            Log::error('GoogleCalendarIntegration: Token refresh failed', [
                'workspace_id' => $integration->workspace_id,
                'error' => $e->getMessage(),
            ]);

            $integration->update([
                'status' => 'error',
                'error_message' => 'Token refresh failed. Please reconnect Google Calendar.',
            ]);
        }
    }

    /**
     * Create a calendar event.
     *
     * @param int   $workspaceId The workspace ID.
     * @param array $eventData   Event details: [
     *    'summary'     => string,        // Required: event title
     *    'description' => string|null,   // Optional: event description
     *    'start'       => string,        // Required: ISO 8601 datetime (e.g. 2026-03-20T10:00:00)
     *    'end'         => string,        // Required: ISO 8601 datetime
     *    'timezone'    => string|null,   // Optional: timezone (e.g. 'America/New_York')
     *    'attendees'   => string[]|null, // Optional: array of email addresses
     *    'location'    => string|null,   // Optional: physical location or meeting URL
     * ]
     * @return string|null Google Calendar event ID on success, null on failure.
     */
    public function createEvent(int $workspaceId, array $eventData): ?string
    {
        try {
            $integration = $this->getIntegration($workspaceId);
            if (! $integration) {
                return null;
            }

            $this->refreshTokenIfNeeded($integration);
            $integration->refresh();

            $accessToken = $integration->credentials['access_token'] ?? null;
            if (! $accessToken) {
                return null;
            }

            $timezone = $eventData['timezone'] ?? 'UTC';

            $calendarEvent = [
                'summary' => $eventData['summary'] ?? 'Untitled Event',
                'description' => $eventData['description'] ?? '',
                'start' => [
                    'dateTime' => $eventData['start'],
                    'timeZone' => $timezone,
                ],
                'end' => [
                    'dateTime' => $eventData['end'],
                    'timeZone' => $timezone,
                ],
            ];

            if (! empty($eventData['location'])) {
                $calendarEvent['location'] = $eventData['location'];
            }

            if (! empty($eventData['attendees'])) {
                $calendarEvent['attendees'] = array_map(
                    fn (string $email) => ['email' => $email],
                    $eventData['attendees']
                );
            }

            $response = $this->httpClient->post(
                "{$this->baseUrl}/calendars/primary/events",
                [
                    'headers' => $this->headers($accessToken),
                    'json' => $calendarEvent,
                    'query' => ['sendUpdates' => 'all'], // Notify attendees
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $eventId = $data['id'] ?? null;

            Log::info('GoogleCalendarIntegration: Event created', [
                'workspace_id' => $workspaceId,
                'event_id' => $eventId,
                'summary' => $eventData['summary'] ?? '',
            ]);

            return $eventId;
        } catch (GuzzleException $e) {
            Log::error('GoogleCalendarIntegration: Failed to create event', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * List upcoming calendar events.
     *
     * @param int $workspaceId The workspace ID.
     * @param int $days        How many days ahead to look.
     * @return array Array of event data.
     */
    public function listUpcomingEvents(int $workspaceId, int $days = 7): array
    {
        try {
            $integration = $this->getIntegration($workspaceId);
            if (! $integration) {
                return [];
            }

            $this->refreshTokenIfNeeded($integration);
            $integration->refresh();

            $accessToken = $integration->credentials['access_token'] ?? null;
            if (! $accessToken) {
                return [];
            }

            $timeMin = now()->toRfc3339String();
            $timeMax = now()->addDays($days)->toRfc3339String();

            $response = $this->httpClient->get(
                "{$this->baseUrl}/calendars/primary/events",
                [
                    'headers' => $this->headers($accessToken),
                    'query' => [
                        'timeMin' => $timeMin,
                        'timeMax' => $timeMax,
                        'maxResults' => 50,
                        'singleEvents' => 'true',
                        'orderBy' => 'startTime',
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $items = $data['items'] ?? [];

            return array_map(function (array $event) {
                $start = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;
                $end = $event['end']['dateTime'] ?? $event['end']['date'] ?? null;

                return [
                    'id' => $event['id'],
                    'summary' => $event['summary'] ?? '(no title)',
                    'description' => $event['description'] ?? '',
                    'start' => $start,
                    'end' => $end,
                    'location' => $event['location'] ?? null,
                    'html_link' => $event['htmlLink'] ?? null,
                    'status' => $event['status'] ?? 'confirmed',
                    'attendees' => array_map(
                        fn ($a) => ['email' => $a['email'], 'status' => $a['responseStatus'] ?? 'needsAction'],
                        $event['attendees'] ?? []
                    ),
                ];
            }, $items);
        } catch (GuzzleException $e) {
            Log::error('GoogleCalendarIntegration: Failed to list events', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check if Google Calendar integration is active for a workspace.
     */
    public function isActive(int $workspaceId): bool
    {
        return $this->getIntegration($workspaceId) !== null;
    }
}
