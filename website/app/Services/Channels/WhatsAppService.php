<?php

namespace App\Services\Channels;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Traits\NormalizesPhoneNumbers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppService
{
    use NormalizesPhoneNumbers;

    protected Client $httpClient;

    protected string $apiVersion = 'v21.0';

    protected string $baseUrl = 'https://graph.facebook.com';

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Send a text message via WhatsApp Cloud API.
     */
    public function sendText(string $phoneNumberId, string $accessToken, string $to, string $text): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        try {
            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $text,
                    ],
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp: Text message sent', [
                'to' => $to,
                'message_id' => $result['messages'][0]['id'] ?? null,
            ]);

            return $result;
        } catch (GuzzleException $e) {
            // FIX-058: Handle rate limiting (429) with retry-after
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 429) {
                $retryAfter = (int) ($e->getResponse()->getHeaderLine('Retry-After') ?: 60);
                Log::warning('WhatsApp: Rate limited, retry after {seconds}s', [
                    'to' => $to,
                    'retry_after' => $retryAfter,
                ]);
                throw new \RuntimeException("WhatsApp rate limited. Retry after {$retryAfter} seconds.", 429, $e);
            }

            Log::error('WhatsApp: Failed to send text message', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send a template message via WhatsApp Cloud API.
     */
    public function sendTemplate(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $templateName,
        string $lang = 'en_US',
        array $components = []
    ): array {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        $templatePayload = [
            'name' => $templateName,
            'language' => ['code' => $lang],
        ];

        if (! empty($components)) {
            $templatePayload['components'] = $components;
        }

        try {
            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'template',
                    'template' => $templatePayload,
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp: Template message sent', [
                'to' => $to,
                'template' => $templateName,
                'message_id' => $result['messages'][0]['id'] ?? null,
            ]);

            return $result;
        } catch (GuzzleException $e) {
            Log::error('WhatsApp: Failed to send template message', [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send a media message via WhatsApp Cloud API (image, video, audio, document).
     */
    public function sendMedia(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $type,
        string $mediaUrl,
        ?string $caption = null
    ): array {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        $mediaPayload = ['link' => $mediaUrl];
        if ($caption && in_array($type, ['image', 'video', 'document'])) {
            $mediaPayload['caption'] = $caption;
        }

        try {
            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => $type,
                    $type => $mediaPayload,
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info("WhatsApp: {$type} message sent", [
                'to' => $to,
                'message_id' => $result['messages'][0]['id'] ?? null,
            ]);

            return $result;
        } catch (GuzzleException $e) {
            Log::error("WhatsApp: Failed to send {$type} message", [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead(string $phoneNumberId, string $accessToken, string $messageId): void
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        try {
            $this->httpClient->post($url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId,
                ],
            ]);

            Log::debug('WhatsApp: Message marked as read', ['message_id' => $messageId]);
        } catch (GuzzleException $e) {
            Log::warning('WhatsApp: Failed to mark message as read', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verify webhook subscription (Meta verification challenge).
     */
    public function verifyWebhook(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // Check .env first, then check all active WhatsApp integrations
        $verifyToken = config('services.whatsapp.verify_token');

        if (!$verifyToken || $token !== $verifyToken) {
            // Try matching from channel_integrations table (user-saved verify token)
            $integrations = \App\Models\ChannelIntegration::where('channel', 'whatsapp')
                ->where('status', 'active')
                ->get();

            foreach ($integrations as $integration) {
                $savedToken = $integration->credentials['verify_token'] ?? null;
                if ($savedToken && $token === $savedToken) {
                    $verifyToken = $savedToken;
                    break;
                }
            }
        }

        if ($mode === 'subscribe' && $token && $token === $verifyToken) {
            Log::info('WhatsApp: Webhook verified successfully');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp: Webhook verification failed', [
            'mode' => $mode,
            'received_token' => $token,
            'expected_token' => $verifyToken,
        ]);

        return response('Verification failed', 403);
    }

    /**
     * Process incoming webhook payload from WhatsApp Cloud API.
     */
    public function processWebhook(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // Process incoming messages
                foreach ($value['messages'] ?? [] as $waMessage) {
                    $this->processIncomingMessage($waMessage, $value);
                }

                // Process status updates (sent, delivered, read, failed)
                foreach ($value['statuses'] ?? [] as $status) {
                    $this->processStatusUpdate($status);
                }
            }
        }
    }

    /**
     * Process a single incoming WhatsApp message.
     */
    protected function processIncomingMessage(array $waMessage, array $value): void
    {
        try {
            $from = $this->normalizePhone($waMessage['from'] ?? '');
            $messageType = $waMessage['type'] ?? 'text';
            $timestamp = isset($waMessage['timestamp']) ? \Carbon\Carbon::createFromTimestamp($waMessage['timestamp']) : now();
            $waMessageId = $waMessage['id'] ?? '';

            // Skip if already processed
            if ($waMessageId && Message::where('channel_message_id', $waMessageId)->exists()) {
                return;
            }

            // Extract sender profile info
            $contacts = $value['contacts'] ?? [];
            $senderName = $contacts[0]['profile']['name'] ?? $from;

            // Get the phone number ID this message was received on
            $phoneNumberId = $value['metadata']['phone_number_id'] ?? config('services.whatsapp.phone_number_id');

            // Extract message body
            $bodyText = match ($messageType) {
                'text' => $waMessage['text']['body'] ?? '',
                'image' => $waMessage['image']['caption'] ?? '[Image]',
                'video' => $waMessage['video']['caption'] ?? '[Video]',
                'audio' => '[Audio Message]',
                'document' => $waMessage['document']['filename'] ?? '[Document]',
                'location' => "[Location: {$waMessage['location']['latitude']},{$waMessage['location']['longitude']}]",
                'contacts' => '[Contact Card]',
                'sticker' => '[Sticker]',
                'reaction' => "[Reaction: {$waMessage['reaction']['emoji']}]",
                default => "[{$messageType}]",
            };

            // Find or create the workspace from the phone number ID
            $workspaceId = $this->resolveWorkspaceFromPhoneNumber($phoneNumberId);

            if (! $workspaceId) {
                Log::warning("WhatsApp: No workspace found for phone number {$phoneNumberId}");
                return;
            }

            // Wrap contact/conversation/message creation in a transaction to prevent
            // orphaned records (e.g., contact created but message insert fails).
            $message = DB::transaction(function () use ($workspaceId, $from, $senderName, $messageType, $bodyText, $waMessageId, $timestamp) {
                // Find or create contact
                $contact = Contact::firstOrCreate(
                    ['workspace_id' => $workspaceId, 'phone' => $from],
                    [
                        'first_name' => $senderName,
                        'status' => 'active',
                        'last_contacted_at' => now(),
                    ]
                );
                $contact->update(['last_contacted_at' => now()]);

                // Find or create conversation
                $conversation = Conversation::where('workspace_id', $workspaceId)
                    ->where('contact_id', $contact->id)
                    ->where('channel', 'whatsapp')
                    ->where('status', '!=', 'closed')
                    ->orderBy('last_message_at', 'desc')
                    ->first();

                if (! $conversation) {
                    $conversation = Conversation::create([
                        'workspace_id' => $workspaceId,
                        'contact_id' => $contact->id,
                        'channel' => 'whatsapp',
                        'channel_conversation_id' => $from,
                        'status' => 'open',
                        'priority' => 'normal',
                        'subject' => "WhatsApp: {$senderName}",
                        'is_read' => false,
                        'messages_count' => 0,
                        'last_message_at' => $timestamp,
                    ]);
                }

                // Create message.
                // NOTE: `type` is the Message category enum (message|note|ai_draft|system_event),
                // NOT the WhatsApp content type. The WA content kind ($messageType:
                // text/image/audio/...) is captured in the log below and available
                // via attachments if needed.
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'workspace_id' => $workspaceId,
                    'uuid' => Str::uuid(),
                    'direction' => 'inbound',
                    'sender_type' => 'contact',
                    'type' => 'message',
                    'body_text' => $bodyText,
                    'body_html' => e($bodyText),
                    'from_name' => $senderName,
                    'channel_message_id' => $waMessageId,
                    'delivery_status' => 'delivered',
                    'sent_at' => $timestamp,
                    'delivered_at' => $timestamp,
                ]);

                // Update conversation
                $conversation->update([
                    'last_message_at' => $timestamp,
                    'preview' => Str::limit($bodyText, 200),
                    'is_read' => false,
                ]);
                $conversation->increment('messages_count');

                return $message;
            });

            // Auto mark as read (outside transaction -- non-critical external API call).
            // The access token lives in the integration credentials, not in config —
            // users configure WhatsApp via the Settings UI, which writes to
            // channel_integrations.credentials.access_token.
            $integration = \App\Models\ChannelIntegration::where('workspace_id', $workspaceId)
                ->where('channel', 'whatsapp')
                ->where('status', 'active')
                ->first();
            $waAccessToken = $integration->credentials['access_token']
                ?? config('services.whatsapp.access_token');

            if ($waAccessToken) {
                $this->markAsRead($phoneNumberId, $waAccessToken, $waMessageId);
            } else {
                Log::warning('WhatsApp: No access token available, skipping mark-as-read', [
                    'workspace_id' => $workspaceId,
                    'wa_message_id' => $waMessageId,
                ]);
            }

            Log::info('WhatsApp: Incoming message processed', [
                'from' => $from,
                'type' => $messageType,
                'message_id' => $message->id,
            ]);

            // Fire MessageReceived event for workflows and real-time notifications
            $conversation = $message->conversation;
            if ($conversation) {
                event(new \App\Events\MessageReceived($message, $conversation));
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp: Failed to process incoming message', [
                'error' => $e->getMessage(),
                'wa_message' => $waMessage,
            ]);
        }
    }

    /**
     * Process a status update from WhatsApp.
     */
    protected function processStatusUpdate(array $status): void
    {
        try {
            $waMessageId = $status['id'] ?? '';
            $statusType = $status['status'] ?? '';
            $timestamp = isset($status['timestamp']) ? \Carbon\Carbon::createFromTimestamp($status['timestamp']) : now();

            $message = Message::where('channel_message_id', $waMessageId)->first();

            if (! $message) {
                return;
            }

            // The `messages.delivery_status` column is an ENUM accepting
            // queued/sent/delivered/failed/bounced — "read" is not a valid
            // value. "Read" is a stronger signal than "delivered", so we keep
            // delivery_status at 'delivered' and record the read moment in
            // opened_at, which is the source of truth for read receipts.
            $updates = match ($statusType) {
                'sent' => ['delivery_status' => 'sent', 'sent_at' => $timestamp],
                'delivered' => ['delivery_status' => 'delivered', 'delivered_at' => $timestamp],
                'read' => ['delivery_status' => 'delivered', 'opened_at' => $timestamp],
                'failed' => [
                    'delivery_status' => 'failed',
                    'delivery_error' => \Illuminate\Support\Str::limit(
                        $status['errors'][0]['message'] ?? 'Unknown error',
                        240
                    ),
                ],
                default => [],
            };

            if (! empty($updates)) {
                $message->update($updates);
                Log::debug("WhatsApp: Status updated to '{$statusType}' for message {$message->id}");
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp: Failed to process status update', [
                'error' => $e->getMessage(),
                'status' => $status,
            ]);
        }
    }

    /**
     * Resolve workspace ID from a WhatsApp phone number ID.
     * Looks up the channel_integrations table to find which workspace owns this phone number.
     */
    protected function resolveWorkspaceFromPhoneNumber(string $phoneNumberId): ?int
    {
        // Look up the workspace that owns this WhatsApp phone number ID.
        // Credentials are encrypted at rest — cannot use whereJsonContains.
        // First try the dedicated phone_number column, then fall back to PHP filtering.
        $integration = \App\Models\ChannelIntegration::where('channel', 'whatsapp')
            ->where('status', 'active')
            ->where('phone_number', $phoneNumberId)
            ->first()
            ?? \App\Models\ChannelIntegration::where('channel', 'whatsapp')
                ->where('status', 'active')
                ->get()
                ->first(fn ($i) => ($i->credentials['phone_number_id'] ?? null) === $phoneNumberId);

        if (!$integration) {
            Log::warning('WhatsApp: No active integration found for phone_number_id', [
                'phone_number_id' => $phoneNumberId,
            ]);
            return null;
        }

        return $integration->workspace_id;
    }
}
