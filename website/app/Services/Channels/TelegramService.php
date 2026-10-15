<?php

namespace App\Services\Channels;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Traits\NormalizesPhoneNumbers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramService
{
    use NormalizesPhoneNumbers;

    protected Client $httpClient;

    protected string $baseUrl = 'https://api.telegram.org';

    protected ?string $botToken;

    public function __construct(?string $botToken = null)
    {
        $this->botToken = $botToken ?? config('services.telegram.bot_token');
        $this->httpClient = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Get the API URL for a given method.
     */
    protected function apiUrl(string $method): string
    {
        return "{$this->baseUrl}/bot{$this->botToken}/{$method}";
    }

    /**
     * Make a POST request to the Telegram Bot API.
     *
     * The Bot API returns `result` as either an array (e.g. sendMessage returns
     * the sent Message object) or a bool (e.g. setWebhook / deleteWebhook return
     * true on success), so the return type must accept both.
     */
    protected function apiPost(string $method, array $params = []): array|bool
    {
        try {
            $response = $this->httpClient->post($this->apiUrl($method), [
                'json' => $params,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (! ($result['ok'] ?? false)) {
                throw new \RuntimeException("Telegram API error: " . ($result['description'] ?? 'Unknown error'));
            }

            return $result['result'] ?? [];
        } catch (GuzzleException $e) {
            Log::error("Telegram: API call failed for {$method}", [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            throw $e;
        }
    }

    /**
     * Sanitize an HTML body into Telegram's restricted HTML subset.
     *
     * Telegram's parse_mode=HTML only accepts: b/strong, i/em, u/ins, s/strike/del,
     * a, code, pre, blockquote, tg-spoiler. Paragraphs and <br> must become real
     * newlines — anything else causes "can't parse entities" 400 errors.
     */
    public static function sanitizeHtmlForTelegram(string $html): string
    {
        $html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</\s*p\s*>#i', "\n\n", $html) ?? $html;
        $html = preg_replace('#<\s*p[^>]*>#i', '', $html) ?? $html;
        $html = preg_replace('#</\s*div\s*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<\s*div[^>]*>#i', '', $html) ?? $html;

        $allowed = '<b><strong><i><em><u><ins><s><strike><del><a><code><pre><blockquote>';
        $html = strip_tags($html, $allowed);

        // Decode the HTML entities Livewire/editors emit (&nbsp; etc.) but keep
        // &lt; &gt; &amp; which Telegram needs escaped for text content.
        $html = str_replace('&nbsp;', ' ', $html);
        $html = preg_replace("/[ \t]+\n/", "\n", $html) ?? $html;
        $html = preg_replace("/\n{3,}/", "\n\n", $html) ?? $html;

        return trim($html);
    }

    /**
     * Send a text message to a Telegram chat.
     */
    public function sendMessage(string $chatId, string $text, array $replyMarkup = []): array
    {
        $text = self::sanitizeHtmlForTelegram($text);
        if ($text === '') {
            $text = ' ';
        }

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if (! empty($replyMarkup)) {
            $params['reply_markup'] = $replyMarkup;
        }

        $result = $this->apiPost('sendMessage', $params);

        Log::info('Telegram: Message sent', [
            'chat_id' => $chatId,
            'message_id' => $result['message_id'] ?? null,
        ]);

        return $result;
    }

    /**
     * Send a photo to a Telegram chat.
     */
    public function sendPhoto(string $chatId, string $photoUrl, string $caption = ''): void
    {
        $params = [
            'chat_id' => $chatId,
            'photo' => $photoUrl,
        ];

        if ($caption) {
            $params['caption'] = $caption;
            $params['parse_mode'] = 'HTML';
        }

        $this->apiPost('sendPhoto', $params);

        Log::info('Telegram: Photo sent', ['chat_id' => $chatId]);
    }

    /**
     * Send a document to a Telegram chat.
     */
    public function sendDocument(string $chatId, string $filePath): void
    {
        // For file uploads, we need multipart form
        try {
            $this->httpClient->post($this->apiUrl('sendDocument'), [
                'multipart' => [
                    [
                        'name' => 'chat_id',
                        'contents' => $chatId,
                    ],
                    [
                        'name' => 'document',
                        'contents' => fopen($filePath, 'r'),
                        'filename' => basename($filePath),
                    ],
                ],
            ]);

            Log::info('Telegram: Document sent', [
                'chat_id' => $chatId,
                'file' => basename($filePath),
            ]);
        } catch (GuzzleException $e) {
            Log::error('Telegram: Failed to send document', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Register a webhook URL with Telegram.
     */
    public function setWebhook(string $url): void
    {
        $result = $this->apiPost('setWebhook', [
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query', 'edited_message'],
            'drop_pending_updates' => false,
        ]);

        Log::info('Telegram: Webhook set', [
            'url' => $url,
            'result' => $result,
        ]);
    }

    /**
     * Remove the webhook.
     */
    public function deleteWebhook(): void
    {
        $this->apiPost('deleteWebhook', ['drop_pending_updates' => false]);
        Log::info('Telegram: Webhook deleted');
    }

    /**
     * Get current webhook info.
     */
    public function getWebhookInfo(): array
    {
        return $this->apiPost('getWebhookInfo');
    }

    /**
     * Process an incoming Telegram Update.
     */
    public function processUpdate(array $update): void
    {
        try {
            if (isset($update['message'])) {
                $this->processMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->processCallbackQuery($update['callback_query']);
            } elseif (isset($update['edited_message'])) {
                Log::debug('Telegram: Edited message received (ignored)', [
                    'message_id' => $update['edited_message']['message_id'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Telegram: Failed to process update', [
                'update_id' => $update['update_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process an incoming Telegram message.
     */
    protected function processMessage(array $tgMessage): void
    {
        $chatId = (string) ($tgMessage['chat']['id'] ?? '');
        $fromUser = $tgMessage['from'] ?? [];
        $fromId = (string) ($fromUser['id'] ?? '');
        $firstName = $fromUser['first_name'] ?? '';
        $lastName = $fromUser['last_name'] ?? '';
        $username = $fromUser['username'] ?? '';
        $messageId = (string) ($tgMessage['message_id'] ?? '');
        $date = isset($tgMessage['date']) ? \Carbon\Carbon::createFromTimestamp($tgMessage['date']) : now();

        // Skip bot's own messages
        if ($fromUser['is_bot'] ?? false) {
            return;
        }

        // Skip if already processed
        $channelMessageId = "tg_{$chatId}_{$messageId}";
        if (Message::where('channel_message_id', $channelMessageId)->exists()) {
            return;
        }

        // Determine message type and extract body
        $bodyText = '';
        $messageType = 'text';

        if (isset($tgMessage['text'])) {
            $bodyText = $tgMessage['text'];

            // Handle commands
            if (str_starts_with($bodyText, '/')) {
                $this->handleCommand($bodyText, $chatId, $tgMessage);
                return;
            }
        } elseif (isset($tgMessage['photo'])) {
            $messageType = 'image';
            $bodyText = $tgMessage['caption'] ?? '[Photo]';
        } elseif (isset($tgMessage['document'])) {
            $messageType = 'document';
            $bodyText = $tgMessage['caption'] ?? ('[Document: ' . ($tgMessage['document']['file_name'] ?? 'file') . ']');
        } elseif (isset($tgMessage['video'])) {
            $messageType = 'video';
            $bodyText = $tgMessage['caption'] ?? '[Video]';
        } elseif (isset($tgMessage['voice'])) {
            $messageType = 'audio';
            $bodyText = '[Voice Message]';
        } elseif (isset($tgMessage['audio'])) {
            $messageType = 'audio';
            $bodyText = '[Audio: ' . ($tgMessage['audio']['title'] ?? 'audio') . ']';
        } elseif (isset($tgMessage['sticker'])) {
            $messageType = 'sticker';
            $bodyText = '[Sticker: ' . ($tgMessage['sticker']['emoji'] ?? '') . ']';
        } elseif (isset($tgMessage['location'])) {
            $messageType = 'location';
            $bodyText = "[Location: {$tgMessage['location']['latitude']}, {$tgMessage['location']['longitude']}]";
        } elseif (isset($tgMessage['contact'])) {
            $messageType = 'contact';
            $bodyText = '[Contact: ' . ($tgMessage['contact']['phone_number'] ?? '') . ']';
        }

        // Resolve workspace (first workspace or from channel config)
        $workspaceId = $this->resolveWorkspaceFromChat($chatId);
        if (! $workspaceId) {
            Log::warning("Telegram: No workspace found for chat {$chatId}");
            return;
        }

        $senderName = trim("{$firstName} {$lastName}");

        // Find or create contact — store username in last_name if available
        $contact = Contact::firstOrCreate(
            ['workspace_id' => $workspaceId, 'phone' => $fromId],
            [
                'first_name' => $firstName ?: $username ?: $fromId,
                'last_name' => $username ? "@{$username}" : ($lastName ?: null),
                'status' => 'active',
                'last_contacted_at' => now(),
            ]
        );
        // Update username if it changed or wasn't stored before
        if ($username && !str_contains($contact->last_name ?? '', $username)) {
            $contact->update(['last_name' => "@{$username}", 'last_contacted_at' => now()]);
        } else {
            $contact->update(['last_contacted_at' => now()]);
        }

        // Find or create conversation
        $conversation = Conversation::where('workspace_id', $workspaceId)
            ->where('contact_id', $contact->id)
            ->where('channel', 'telegram')
            ->where('channel_conversation_id', $chatId)
            ->where('status', '!=', 'closed')
            ->orderBy('last_message_at', 'desc')
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'workspace_id' => $workspaceId,
                'contact_id' => $contact->id,
                'channel' => 'telegram',
                'channel_conversation_id' => $chatId,
                'status' => 'open',
                'priority' => 'normal',
                'subject' => "Telegram: {$senderName}" . ($username ? " (@{$username})" : ''),
                'is_read' => false,
                'messages_count' => 0,
                'last_message_at' => $date,
            ]);
        }

        // Create message.
        // NOTE: `type` is the Message category enum (message|note|ai_draft|system_event),
        // NOT the Telegram content type. The content kind ($messageType: text/image/...)
        // is captured in the log below and can be inferred from attachments if needed.
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspaceId,
            'uuid' => Str::uuid(),
            'direction' => 'inbound',
            'sender_type' => 'contact',
            'type' => 'message',
            'body_text' => $bodyText,
            'body_html' => nl2br(e($bodyText)),
            'from_name' => $senderName,
            'channel_message_id' => $channelMessageId,
            'delivery_status' => 'delivered',
            'sent_at' => $date,
            'delivered_at' => $date,
        ]);

        // Update conversation
        $conversation->update([
            'last_message_at' => $date,
            'preview' => Str::limit($bodyText, 200),
            'is_read' => false,
        ]);
        $conversation->increment('messages_count');

        Log::info('Telegram: Message processed', [
            'chat_id' => $chatId,
            'from' => $senderName,
            'type' => $messageType,
            'message_id' => $message->id,
        ]);

        // Fire MessageReceived event for workflows and real-time notifications
        event(new \App\Events\MessageReceived($message, $conversation));
    }

    /**
     * Process a callback query (button click).
     */
    protected function processCallbackQuery(array $callbackQuery): void
    {
        $callbackId = $callbackQuery['id'] ?? '';
        $data = $callbackQuery['data'] ?? '';
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? '');

        Log::info('Telegram: Callback query received', [
            'callback_id' => $callbackId,
            'data' => $data,
            'chat_id' => $chatId,
        ]);

        // Answer the callback to remove loading indicator
        try {
            $this->apiPost('answerCallbackQuery', [
                'callback_query_id' => $callbackId,
                'text' => 'Received!',
            ]);
        } catch (\Throwable $e) {
            Log::warning("Telegram: Failed to answer callback query: {$e->getMessage()}");
        }

        // Process based on callback data
        if (str_starts_with($data, 'approve_ai:')) {
            $messageId = str_replace('approve_ai:', '', $data);
            // Dispatch AI approval job if available
            Log::info("Telegram: AI reply approved for message {$messageId}");
        }
    }

    /**
     * Handle bot commands (e.g., /start, /help, /status).
     */
    protected function handleCommand(string $text, string $chatId, array $tgMessage): void
    {
        $command = explode(' ', $text)[0];
        $args = trim(substr($text, strlen($command)));

        switch ($command) {
            case '/start':
                $this->registerUserFromStart($chatId, $tgMessage);
                $this->sendMessage($chatId, "Welcome to " . config('app.name') . "! I'll help you manage your conversations.\n\nAvailable commands:\n/help - Show help\n/status - Check connection status");
                break;

            case '/help':
                $this->sendMessage($chatId, "<b>" . config('app.name') . " Bot Commands:</b>\n\n/start - Initialize the bot\n/help - Show this help message\n/status - Check your connection status\n\nYou can send me messages and I'll route them to your " . config('app.name') . " inbox.");
                break;

            case '/status':
                $this->sendMessage($chatId, "Bot is online and connected.");
                break;

            default:
                $this->sendMessage($chatId, "Unknown command: {$command}\nType /help for available commands.");
                break;
        }
    }

    /**
     * Register a user who sent /start so compose lookup by @username works.
     */
    protected function registerUserFromStart(string $chatId, array $tgMessage): void
    {
        try {
            $fromUser = $tgMessage['from'] ?? [];
            $fromId = (string) ($fromUser['id'] ?? '');
            if (! $fromId) {
                return;
            }

            $firstName = $fromUser['first_name'] ?? '';
            $lastName = $fromUser['last_name'] ?? '';
            $username = $fromUser['username'] ?? '';

            $workspaceId = $this->resolveWorkspaceFromChat($chatId);
            if (! $workspaceId) {
                return;
            }

            $contact = Contact::firstOrCreate(
                ['workspace_id' => $workspaceId, 'phone' => $fromId],
                [
                    'first_name' => $firstName ?: $username ?: $fromId,
                    'last_name' => $username ? "@{$username}" : ($lastName ?: null),
                    'status' => 'active',
                    'last_contacted_at' => now(),
                ]
            );

            if ($username && ! str_contains($contact->last_name ?? '', $username)) {
                $contact->update(['last_name' => "@{$username}", 'last_contacted_at' => now()]);
            } else {
                $contact->update(['last_contacted_at' => now()]);
            }

            $senderName = trim("{$firstName} {$lastName}");
            Conversation::firstOrCreate(
                [
                    'workspace_id' => $workspaceId,
                    'contact_id' => $contact->id,
                    'channel' => 'telegram',
                    'channel_conversation_id' => $chatId,
                ],
                [
                    'status' => 'open',
                    'priority' => 'normal',
                    'subject' => "Telegram: " . ($senderName ?: ($username ? "@{$username}" : $fromId)),
                    'is_read' => true,
                    'messages_count' => 0,
                    'last_message_at' => now(),
                ]
            );

            Log::info('Telegram: /start registered user', [
                'chat_id' => $chatId,
                'username' => $username,
                'contact_id' => $contact->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram: Failed to register /start user', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve workspace from the bot token used for this service instance.
     *
     * Telegram webhooks are registered per bot, so the bot_token on this
     * service instance identifies which workspace the message belongs to.
     * If the token doesn't match any stored integration, an exception is thrown
     * rather than silently falling back to an arbitrary integration.
     *
     * @throws \RuntimeException If bot_token is set but doesn't match any integration.
     */
    protected function resolveWorkspaceFromChat(string $chatId): ?int
    {
        if (! $this->botToken) {
            Log::warning('Telegram: No bot token configured, cannot resolve workspace', [
                'chat_id' => $chatId,
            ]);
            return null;
        }

        // Look up which workspace owns this bot token.
        // Credentials are encrypted at rest — cannot use whereJsonContains.
        // Load all active telegram integrations and match in PHP after decryption.
        $integration = \App\Models\ChannelIntegration::where('channel', 'telegram')
            ->where('status', 'active')
            ->get()
            ->first(fn ($i) => ($i->credentials['bot_token'] ?? null) === $this->botToken);

        if ($integration) {
            return $integration->workspace_id;
        }

        // Bot token was provided but did not match any stored integration.
        // This is a configuration error -- do NOT silently fall back to a random
        // integration, as that would route messages to the wrong workspace.
        Log::error('Telegram: Bot token does not match any active integration. Messages cannot be routed.', [
            'chat_id' => $chatId,
            'bot_token_prefix' => substr($this->botToken, 0, 10) . '...',
        ]);

        throw new \RuntimeException(
            "Telegram bot token does not match any active integration. "
            . "Verify the token in Settings > Channels > Telegram matches the webhook configuration."
        );
    }
}
