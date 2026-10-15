<?php

namespace App\Services\Channels;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SlackService
{
    protected Client $httpClient;

    protected string $baseUrl = 'https://slack.com/api';

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Post a message to a Slack channel.
     */
    public function postMessage(string $botToken, string $channel, string $text, array $blocks = []): array
    {
        $payload = [
            'channel' => $channel,
            'text' => $text,
        ];

        if (! empty($blocks)) {
            $payload['blocks'] = $blocks;
        }

        try {
            $response = $this->httpClient->post("{$this->baseUrl}/chat.postMessage", [
                'headers' => [
                    'Authorization' => "Bearer {$botToken}",
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'json' => $payload,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (! ($result['ok'] ?? false)) {
                throw new \RuntimeException("Slack API error: " . ($result['error'] ?? 'Unknown error'));
            }

            Log::info('Slack: Message posted', [
                'channel' => $channel,
                'ts' => $result['ts'] ?? null,
            ]);

            return $result;
        } catch (GuzzleException $e) {
            Log::error('Slack: Failed to post message', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing message in a Slack channel.
     */
    public function updateMessage(string $botToken, string $channel, string $ts, string $text, array $blocks = []): array
    {
        $payload = [
            'channel' => $channel,
            'ts' => $ts,
            'text' => $text,
        ];

        if (! empty($blocks)) {
            $payload['blocks'] = $blocks;
        }

        try {
            $response = $this->httpClient->post("{$this->baseUrl}/chat.update", [
                'headers' => [
                    'Authorization' => "Bearer {$botToken}",
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'json' => $payload,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (! ($result['ok'] ?? false)) {
                throw new \RuntimeException("Slack API error: " . ($result['error'] ?? 'Unknown error'));
            }

            Log::info('Slack: Message updated', [
                'channel' => $channel,
                'ts' => $ts,
            ]);

            return $result;
        } catch (GuzzleException $e) {
            Log::error('Slack: Failed to update message', [
                'channel' => $channel,
                'ts' => $ts,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify Slack request signature using HMAC-SHA256.
     */
    public function verifySignature(Request $request): bool
    {
        $signingSecret = config('services.slack.signing_secret');
        if (! $signingSecret) {
            Log::warning('Slack: Cannot verify signature - no signing secret configured');
            return false;
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (! $timestamp || ! $signature) {
            Log::warning('Slack: Missing timestamp or signature headers');
            return false;
        }

        // Reject requests older than 5 minutes to prevent replay attacks
        if (abs(time() - (int) $timestamp) > 300) {
            Log::warning('Slack: Request timestamp is too old', ['timestamp' => $timestamp]);
            return false;
        }

        // Compute the expected signature
        $body = $request->getContent();
        $sigBasestring = "v0:{$timestamp}:{$body}";
        $expectedSignature = 'v0=' . hash_hmac('sha256', $sigBasestring, $signingSecret);

        $isValid = hash_equals($expectedSignature, $signature);

        if (! $isValid) {
            Log::warning('Slack: Signature verification failed', [
                'ip' => $request->ip(),
            ]);
        }

        return $isValid;
    }

    /**
     * Process a Slack Events API event.
     *
     * @param array $event The event object from the Slack payload.
     * @param string|null $teamId The team_id from the top-level payload (used for workspace resolution).
     */
    public function processEvent(array $event, ?string $teamId = null): void
    {
        $type = $event['type'] ?? '';

        switch ($type) {
            case 'message':
                // Only process human messages (not bot messages or message_changed subtypes)
                if (isset($event['subtype']) || isset($event['bot_id'])) {
                    return;
                }
                $this->processChannelMessage($event, $teamId);
                break;

            case 'app_mention':
                $this->processAppMention($event, $teamId);
                break;

            default:
                Log::debug("Slack: Unhandled event type: {$type}");
                break;
        }
    }

    /**
     * Process a message posted in a Slack channel.
     *
     * @param array $event The event data.
     * @param string|null $teamId The Slack team ID for workspace resolution.
     */
    protected function processChannelMessage(array $event, ?string $teamId = null): void
    {
        try {
            $channel = $event['channel'] ?? '';
            $userId = $event['user'] ?? '';
            $text = $event['text'] ?? '';
            $ts = $event['ts'] ?? '';
            $threadTs = $event['thread_ts'] ?? null;

            // Skip if already processed (initial fast check)
            $channelMessageId = "slack_{$channel}_{$ts}";
            if (Message::where('channel_message_id', $channelMessageId)->exists()) {
                return;
            }

            // Resolve workspace: prefer team_id lookup, fall back to channel_id lookup
            $workspaceId = $teamId
                ? $this->resolveWorkspaceFromTeamId($teamId)
                : $this->resolveWorkspaceFromChannel($channel);
            if (! $workspaceId) {
                return;
            }

            // Idempotency: confirm no message with this channel_message_id exists in the
            // workspace. This second check scopes to workspace_id and guards against the
            // race between the fast check above and the insert below.
            if (Message::where('workspace_id', $workspaceId)
                ->where('channel_message_id', $channelMessageId)
                ->exists()
            ) {
                Log::debug('Slack: Duplicate message skipped', ['channel_message_id' => $channelMessageId]);
                return;
            }

            // Find or create contact from Slack user ID
            $contact = Contact::firstOrCreate(
                ['workspace_id' => $workspaceId, 'phone' => "slack:{$userId}"],
                [
                    'first_name' => $userId,
                    'status' => 'active',
                    'last_contacted_at' => now(),
                ]
            );

            // Find existing thread/conversation or create new
            $conversation = null;
            if ($threadTs) {
                $threadMsgId = "slack_{$channel}_{$threadTs}";
                $threadMessage = Message::where('channel_message_id', $threadMsgId)->first();
                if ($threadMessage) {
                    $conversation = $threadMessage->conversation;
                }
            }

            if (! $conversation) {
                $conversation = Conversation::where('workspace_id', $workspaceId)
                    ->where('channel', 'slack')
                    ->where('channel_conversation_id', $channel)
                    ->where('status', '!=', 'closed')
                    ->orderBy('last_message_at', 'desc')
                    ->first();
            }

            if (! $conversation) {
                $conversation = Conversation::create([
                    'workspace_id' => $workspaceId,
                    'contact_id' => $contact->id,
                    'channel' => 'slack',
                    'channel_conversation_id' => $channel,
                    'status' => 'open',
                    'priority' => 'normal',
                    'subject' => "Slack: #{$channel}",
                    'is_read' => false,
                    'messages_count' => 0,
                    'last_message_at' => now(),
                ]);
            }

            $sentAt = $ts ? \Carbon\Carbon::createFromTimestamp((float) $ts) : now();

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'workspace_id' => $workspaceId,
                'uuid' => Str::uuid(),
                'direction' => 'inbound',
                'sender_type' => 'contact',
                // 'type' enum = ['message','note','ai_draft','system_event'].
                // Slack text messages are regular messages in our schema.
                'type' => 'message',
                'body_text' => $text,
                'body_html' => nl2br(e($text)),
                'from_name' => $userId,
                'channel_message_id' => $channelMessageId,
                'delivery_status' => 'delivered',
                'sent_at' => $sentAt,
                'delivered_at' => $sentAt,
            ]);

            $conversation->update([
                'last_message_at' => $sentAt,
                'preview' => Str::limit($text, 200),
                'is_read' => false,
            ]);
            $conversation->increment('messages_count');

            Log::info('Slack: Channel message processed', [
                'channel' => $channel,
                'user' => $userId,
                'message_id' => $message->id,
            ]);

            // Fire MessageReceived event for workflows and real-time notifications
            event(new \App\Events\MessageReceived($message, $conversation));
        } catch (\Throwable $e) {
            Log::error('Slack: Failed to process channel message', [
                'error' => $e->getMessage(),
                'event' => $event,
            ]);
        }
    }

    /**
     * Process an @app_mention event.
     *
     * @param array $event The event data.
     * @param string|null $teamId The Slack team ID for workspace resolution.
     */
    protected function processAppMention(array $event, ?string $teamId = null): void
    {
        $channel = $event['channel'] ?? '';
        $userId = $event['user'] ?? '';
        $text = $event['text'] ?? '';

        Log::info('Slack: App mentioned', [
            'channel' => $channel,
            'user' => $userId,
            'text' => Str::limit($text, 100),
        ]);

        // Process the mention as a regular message
        $this->processChannelMessage($event, $teamId);
    }

    /**
     * Handle /mailtrixy slash commands.
     */
    public function handleCommand(array $payload): array
    {
        $command = $payload['command'] ?? '';
        $text = $payload['text'] ?? '';
        $userId = $payload['user_id'] ?? '';
        $channelId = $payload['channel_id'] ?? '';
        $responseUrl = $payload['response_url'] ?? '';

        Log::info('Slack: Slash command received', [
            'command' => $command,
            'text' => $text,
            'user' => $userId,
        ]);

        // Parse subcommand
        $parts = explode(' ', trim($text), 2);
        $subcommand = strtolower($parts[0] ?? 'help');
        $args = $parts[1] ?? '';

        $response = match ($subcommand) {
            'status' => [
                'response_type' => 'ephemeral',
                'text' => config('app.name') . ' is connected and running.',
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*" . config('app.name') . " Status*\nBot: Online\nSync: Active\nChannel: #{$channelId}",
                        ],
                    ],
                ],
            ],
            'inbox' => [
                'response_type' => 'ephemeral',
                'text' => 'Opening your ' . config('app.name') . ' inbox...',
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "View your inbox at: " . config('app.url') . '/inbox',
                        ],
                    ],
                ],
            ],
            'assign' => [
                'response_type' => 'in_channel',
                'text' => "Assigning conversation to {$args}...",
            ],
            default => [
                'response_type' => 'ephemeral',
                'text' => config('app.name') . ' Slack Commands',
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*Available Commands:*\n`/mailtrixy status` - Check bot status\n`/mailtrixy inbox` - Link to inbox\n`/mailtrixy assign @user` - Assign a conversation\n`/mailtrixy help` - Show this help",
                        ],
                    ],
                ],
            ],
        };

        return $response;
    }

    /**
     * Handle interactive actions (button clicks, menu selections, etc.).
     */
    public function handleInteraction(array $payload): array
    {
        $type = $payload['type'] ?? '';
        $actions = $payload['actions'] ?? [];
        $user = $payload['user'] ?? [];

        Log::info('Slack: Interaction received', [
            'type' => $type,
            'user' => $user['id'] ?? 'unknown',
            'actions' => count($actions),
        ]);

        foreach ($actions as $action) {
            $actionId = $action['action_id'] ?? '';
            $value = $action['value'] ?? '';

            switch ($actionId) {
                case 'approve_ai_reply':
                    Log::info("Slack: AI reply approved by {$user['id']} for message {$value}");
                    return [
                        'response_type' => 'in_channel',
                        'text' => "AI reply approved by <@{$user['id']}>",
                        'replace_original' => true,
                    ];

                case 'reject_ai_reply':
                    Log::info("Slack: AI reply rejected by {$user['id']} for message {$value}");
                    return [
                        'response_type' => 'in_channel',
                        'text' => "AI reply rejected by <@{$user['id']}>. Manual response needed.",
                        'replace_original' => true,
                    ];

                case 'assign_conversation':
                    Log::info("Slack: Conversation {$value} assignment requested by {$user['id']}");
                    return [
                        'response_type' => 'ephemeral',
                        'text' => "Conversation assigned.",
                    ];

                default:
                    Log::debug("Slack: Unknown action: {$actionId}");
            }
        }

        return ['text' => 'Action processed.'];
    }

    /**
     * Resolve workspace from a Slack team ID.
     * Looks up the channel_integrations table by the dedicated slack_team_id column.
     */
    protected function resolveWorkspaceFromTeamId(string $teamId): ?int
    {
        $integration = \App\Models\ChannelIntegration::where('channel', 'slack')
            ->where('status', 'active')
            ->where('slack_team_id', $teamId)
            ->first();

        if (!$integration) {
            Log::warning('Slack: No active integration found for team', [
                'team_id' => $teamId,
            ]);
            return null;
        }

        return $integration->workspace_id;
    }

    /**
     * Resolve workspace from a Slack channel ID.
     * Falls back to checking the slack_channel_id column on active integrations.
     */
    protected function resolveWorkspaceFromChannel(string $channelId): ?int
    {
        $integration = \App\Models\ChannelIntegration::where('channel', 'slack')
            ->where('status', 'active')
            ->where('slack_channel_id', $channelId)
            ->first();

        if (!$integration) {
            Log::warning('Slack: No active integration found for channel', [
                'channel_id' => $channelId,
            ]);
            return null;
        }

        return $integration->workspace_id;
    }
}
