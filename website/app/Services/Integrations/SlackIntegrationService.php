<?php

namespace App\Services\Integrations;

use App\Models\ChannelIntegration;
use App\Models\Conversation;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SlackIntegrationService
{
    protected Client $httpClient;

    protected string $baseUrl = 'https://slack.com/api';

    /**
     * Single source of truth for bot-level OAuth scopes MailTrixy requests
     * when a workspace admin clicks Connect. Both the Livewire Connect flow
     * (IntegrationManager) and the raw-URL entry point (IntegrationOAuthController)
     * read from here so they can never drift out of sync.
     *
     * When adding a new scope: add it here, then reinstall the Slack app in
     * api.slack.com to match, then users must reconnect from MailTrixy.
     */
    public const BOT_SCOPES = [
        'chat:write',        // post notifications to channels
        'channels:read',     // list public channels in Select Channel dropdown
        'channels:history',  // read public channel history (future inbound)
        'groups:read',       // list private channels
        'groups:history',    // read private channel history
        'commands',          // slash commands
        'team:read',         // workspace name / icon
    ];

    public static function oauthScopeString(): string
    {
        return implode(',', self::BOT_SCOPES);
    }

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Resolve the active Slack integration for a workspace.
     * Returns null if Slack is not connected or inactive.
     */
    protected function getIntegration(int $workspaceId): ?ChannelIntegration
    {
        return ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'slack')
            ->where('status', 'active')
            ->first();
    }

    /**
     * Extract the bot token from a Slack integration's credentials.
     */
    protected function getBotToken(ChannelIntegration $integration): ?string
    {
        return $integration->credentials['access_token']
            ?? $integration->credentials['bot_token']
            ?? null;
    }

    /**
     * Post a plain-text notification to the workspace's configured Slack channel.
     *
     * @param int         $workspaceId  The workspace ID.
     * @param string      $message      The message text.
     * @param string|null $channel      Override channel (defaults to integration's configured channel).
     * @return bool Whether the notification was sent successfully.
     */
    public function sendNotification(int $workspaceId, string $message, ?string $channel = null): bool
    {
        try {
            $integration = $this->getIntegration($workspaceId);
            if (! $integration) {
                return false;
            }

            $token = $this->getBotToken($integration);
            if (! $token) {
                Log::warning('SlackIntegration: No bot token for workspace', ['workspace_id' => $workspaceId]);
                return false;
            }

            $targetChannel = $channel ?? $integration->slack_channel_id;
            if (! $targetChannel) {
                Log::warning('SlackIntegration: No channel configured for workspace', ['workspace_id' => $workspaceId]);
                return false;
            }

            $response = $this->httpClient->post("{$this->baseUrl}/chat.postMessage", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'json' => [
                    'channel' => $targetChannel,
                    'text' => $message,
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (! ($result['ok'] ?? false)) {
                Log::error('SlackIntegration: API error posting notification', [
                    'workspace_id' => $workspaceId,
                    'error' => $result['error'] ?? 'unknown',
                ]);
                return false;
            }

            Log::info('SlackIntegration: Notification sent', [
                'workspace_id' => $workspaceId,
                'channel' => $targetChannel,
            ]);

            return true;
        } catch (GuzzleException $e) {
            Log::error('SlackIntegration: Failed to send notification', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Fetch the list of Slack channels accessible to the bot.
     *
     * @param int $workspaceId The workspace ID.
     * @return array Array of channels: [['id' => 'C01...', 'name' => 'general', ...], ...]
     */
    public function syncChannels(int $workspaceId): array
    {
        try {
            $integration = $this->getIntegration($workspaceId);
            if (! $integration) {
                return [];
            }

            $token = $this->getBotToken($integration);
            if (! $token) {
                return [];
            }

            $channels = [];

            // Try public + private first. If Slack returns missing_scope
            // (means groups:read wasn't granted), fall back to public-only
            // so users with the minimum scope set still see SOMETHING and
            // can pick a public channel. Previously the whole dropdown stayed
            // empty on any scope mismatch, making the integration look broken.
            $channels = $this->fetchChannelsList($token, 'public_channel,private_channel', $workspaceId);

            if ($channels === null) {
                Log::info('SlackIntegration: falling back to public-only channel list', [
                    'workspace_id' => $workspaceId,
                ]);
                $channels = $this->fetchChannelsList($token, 'public_channel', $workspaceId) ?? [];
            }

            Log::info('SlackIntegration: Channels synced', [
                'workspace_id' => $workspaceId,
                'count' => count($channels),
            ]);

            return $channels;
        } catch (GuzzleException $e) {
            Log::error('SlackIntegration: Failed to sync channels', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Paginated fetch of conversations.list for the given types string.
     * Returns null if Slack responds with missing_scope (caller should retry
     * with a reduced type set); returns [] if any other error.
     */
    private function fetchChannelsList(string $token, string $types, int $workspaceId): ?array
    {
        $channels = [];
        $cursor = null;

        try {
            do {
                $query = ['types' => $types, 'limit' => 200, 'exclude_archived' => true];
                if ($cursor) {
                    $query['cursor'] = $cursor;
                }

                $response = $this->httpClient->get("{$this->baseUrl}/conversations.list", [
                    'headers' => ['Authorization' => "Bearer {$token}"],
                    'query' => $query,
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                if (! ($data['ok'] ?? false)) {
                    $err = $data['error'] ?? 'unknown';
                    Log::error('SlackIntegration: Failed to list channels', [
                        'workspace_id' => $workspaceId,
                        'types' => $types,
                        'error' => $err,
                    ]);
                    return $err === 'missing_scope' ? null : [];
                }

                foreach ($data['channels'] ?? [] as $ch) {
                    $channels[] = [
                        'id' => $ch['id'],
                        'name' => $ch['name'],
                        'is_private' => $ch['is_private'] ?? false,
                        'num_members' => $ch['num_members'] ?? 0,
                    ];
                }

                $cursor = $data['response_metadata']['next_cursor'] ?? null;
            } while ($cursor);
        } catch (GuzzleException $e) {
            Log::error('SlackIntegration: HTTP error on conversations.list', [
                'workspace_id' => $workspaceId,
                'types' => $types,
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        return $channels;
    }

    /**
     * Post a richly-formatted conversation update to the Slack channel.
     *
     * @param Conversation $conversation The conversation model.
     * @param string       $action       The action (e.g., 'new_message', 'ai_reply_generated', 'assigned', 'closed').
     * @return bool
     */
    public function postConversationUpdate(Conversation $conversation, string $action): bool
    {
        try {
            $integration = $this->getIntegration($conversation->workspace_id);
            if (! $integration) {
                return false;
            }

            // User preference: don't forward inbound activity to Slack unless
            // the workspace has explicitly opted in. Default is OFF — an active
            // Slack integration alone is not consent to mirror every inbound
            // message into a Slack channel. Toggle lives in Settings →
            // Integrations → Slack → "Forward new messages to Slack".
            $forwardEnabled = (bool) ($integration->credentials['forward_new_messages'] ?? false);
            if (! $forwardEnabled && $action === 'new_message') {
                return false;
            }

            $token = $this->getBotToken($integration);
            $channel = $integration->slack_channel_id;
            if (! $token || ! $channel) {
                return false;
            }

            $contact = $conversation->contact;
            $senderName = $contact?->full_name ?? 'Unknown';
            $subject = $conversation->subject ?? '(no subject)';
            $preview = Str::limit($conversation->preview ?? '', 200);
            $inboxUrl = config('app.url') . '/inbox?conversation=' . $conversation->id;

            $actionLabel = match ($action) {
                'new_message' => 'New message received',
                'ai_reply_generated' => 'AI reply generated',
                'assigned' => 'Conversation assigned',
                'closed' => 'Conversation closed',
                'reopened' => 'Conversation reopened',
                default => ucfirst(str_replace('_', ' ', $action)),
            };

            $actionEmoji = match ($action) {
                'new_message' => ':envelope:',
                'ai_reply_generated' => ':robot_face:',
                'assigned' => ':bust_in_silhouette:',
                'closed' => ':white_check_mark:',
                'reopened' => ':arrows_counterclockwise:',
                default => ':bell:',
            };

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "{$actionEmoji} *{$actionLabel}*",
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*From:*\n{$senderName}",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Channel:*\n" . ucfirst($conversation->channel ?? 'email'),
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Subject:*\n{$subject}",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Priority:*\n" . ucfirst($conversation->priority ?? 'normal'),
                        ],
                    ],
                ],
            ];

            if ($preview) {
                $blocks[] = [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Preview:*\n> {$preview}",
                    ],
                ];
            }

            $blocks[] = [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Open in ' . config('app.name')],
                        'url' => $inboxUrl,
                        'style' => 'primary',
                    ],
                ],
            ];

            $fallbackText = "{$actionLabel}: {$subject} from {$senderName}";

            $response = $this->httpClient->post("{$this->baseUrl}/chat.postMessage", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'json' => [
                    'channel' => $channel,
                    'text' => $fallbackText,
                    'blocks' => $blocks,
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (! ($result['ok'] ?? false)) {
                Log::error('SlackIntegration: Failed to post conversation update', [
                    'workspace_id' => $conversation->workspace_id,
                    'conversation_id' => $conversation->id,
                    'error' => $result['error'] ?? 'unknown',
                ]);
                return false;
            }

            return true;
        } catch (GuzzleException $e) {
            Log::error('SlackIntegration: postConversationUpdate failed', [
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update the selected notification channel for a workspace's Slack integration.
     *
     * @param int    $workspaceId The workspace ID.
     * @param string $channelId   The Slack channel ID to use for notifications.
     * @return bool
     */
    public function setNotificationChannel(int $workspaceId, string $channelId): bool
    {
        $integration = $this->getIntegration($workspaceId);
        if (! $integration) {
            return false;
        }

        $integration->update(['slack_channel_id' => $channelId]);

        Log::info('SlackIntegration: Notification channel updated', [
            'workspace_id' => $workspaceId,
            'channel_id' => $channelId,
        ]);

        return true;
    }

    /**
     * Send a test notification to verify the Slack configuration.
     */
    public function sendTestNotification(int $workspaceId): bool
    {
        return $this->sendNotification(
            $workspaceId,
            ':wave: *' . config('app.name') . ' Test Notification*'
            . "\nYour Slack integration is working correctly."
            . "\nNotifications for new conversations, AI replies, and deal updates will appear in this channel."
        );
    }

    /**
     * Check if Slack integration is active for a workspace.
     */
    public function isActive(int $workspaceId): bool
    {
        return $this->getIntegration($workspaceId) !== null;
    }
}
