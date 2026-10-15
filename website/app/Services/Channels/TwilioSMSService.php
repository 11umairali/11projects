<?php

namespace App\Services\Channels;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Traits\NormalizesPhoneNumbers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Security\RequestValidator;

class TwilioSMSService
{
    use NormalizesPhoneNumbers;

    /**
     * Build a Twilio client from workspace credentials or .env fallback.
     */
    protected function client(?string $sid = null, ?string $token = null): TwilioClient
    {
        $sid = $sid ?: config('services.twilio.sid');
        $token = $token ?: config('services.twilio.auth_token');

        if (!$sid || !$token) {
            throw new \RuntimeException('Twilio credentials not configured. Add them in Settings → Channels → SMS or set TWILIO_SID/TWILIO_AUTH_TOKEN in .env.');
        }

        return new TwilioClient($sid, $token);
    }

    /**
     * Send an SMS using workspace-stored credentials.
     *
     * @param string $to Recipient phone number
     * @param string $body Message text
     * @param string|null $from From phone number (from workspace channel config)
     * @param string|null $sid Twilio Account SID (from workspace channel config)
     * @param string|null $token Twilio Auth Token (from workspace channel config)
     * @return \Twilio\Rest\Api\V2010\Account\MessageInstance
     */
    public function sendSMS(string $to, string $body, ?string $from = null, ?string $sid = null, ?string $token = null): \Twilio\Rest\Api\V2010\Account\MessageInstance
    {
        $fromNumber = $from ?: config('services.twilio.phone_number');

        if (!$fromNumber) {
            throw new \RuntimeException('Twilio phone number not configured. Add it in Settings → Channels → SMS.');
        }

        try {
            $client = $this->client($sid, $token);
            $message = $client->messages->create($to, [
                'from' => $fromNumber,
                'body' => $body,
                'statusCallback' => url('/api/webhooks/twilio/status'),
            ]);

            Log::info('Twilio: SMS sent', [
                'to' => $to,
                'from' => $fromNumber,
                'sid' => $message->sid,
                'status' => $message->status,
            ]);

            return $message;
        } catch (\Throwable $e) {
            Log::error('Twilio: Failed to send SMS', [
                'to' => $to,
                'from' => $fromNumber,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send an MMS message with media via Twilio.
     *
     * @param  array<string>  $mediaUrls
     * @return \Twilio\Rest\Api\V2010\Account\MessageInstance
     */
    public function sendMMS(string $to, string $body, array $mediaUrls, ?string $from = null): \Twilio\Rest\Api\V2010\Account\MessageInstance
    {
        $fromNumber = $from ?? config('services.twilio.phone_number');

        try {
            $message = $this->client()->messages->create($to, [
                'from' => $fromNumber,
                'body' => $body,
                'mediaUrl' => $mediaUrls,
                'statusCallback' => route('webhooks.twilio.status'),
            ]);

            Log::info('Twilio: MMS sent', [
                'to' => $to,
                'sid' => $message->sid,
                'media_count' => count($mediaUrls),
            ]);

            return $message;
        } catch (\Throwable $e) {
            Log::error('Twilio: Failed to send MMS', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process incoming SMS/MMS from Twilio webhook.
     */
    public function processIncoming(array $payload): void
    {
        try {
            $from = $this->normalizePhone($payload['From'] ?? '');
            $to = $this->normalizePhone($payload['To'] ?? '');
            $body = $payload['Body'] ?? '';
            $messageSid = $payload['MessageSid'] ?? '';
            $numMedia = (int) ($payload['NumMedia'] ?? 0);

            // Skip if already processed
            if ($messageSid && Message::where('channel_message_id', $messageSid)->exists()) {
                return;
            }

            // Resolve the workspace that owns the receiving phone number
            $workspaceId = $this->resolveWorkspaceFromNumber($to);
            if (! $workspaceId) {
                Log::warning("Twilio: No workspace found for number {$to}");
                return;
            }

            // Find or create contact
            $contact = Contact::firstOrCreate(
                ['workspace_id' => $workspaceId, 'phone' => $from],
                [
                    'first_name' => $from,
                    'status' => 'active',
                    'last_contacted_at' => now(),
                ]
            );
            $contact->update(['last_contacted_at' => now()]);

            // Find or create conversation
            $conversation = Conversation::where('workspace_id', $workspaceId)
                ->where('contact_id', $contact->id)
                ->where('channel', 'sms')
                ->where('status', '!=', 'closed')
                ->orderBy('last_message_at', 'desc')
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'workspace_id' => $workspaceId,
                    'contact_id' => $contact->id,
                    'channel' => 'sms',
                    'channel_conversation_id' => $from,
                    'status' => 'open',
                    'priority' => 'normal',
                    'subject' => "SMS: {$from}",
                    'is_read' => false,
                    'messages_count' => 0,
                    'last_message_at' => now(),
                ]);
            }

            // Build body text with media URLs
            $fullBody = $body;
            $mediaUrls = [];
            for ($i = 0; $i < $numMedia; $i++) {
                $mediaUrl = $payload["MediaUrl{$i}"] ?? null;
                $mediaType = $payload["MediaContentType{$i}"] ?? 'unknown';
                if ($mediaUrl) {
                    $mediaUrls[] = $mediaUrl;
                    $fullBody .= "\n[Media: {$mediaType}]";
                }
            }

            // Create message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'workspace_id' => $workspaceId,
                'uuid' => Str::uuid(),
                'direction' => 'inbound',
                'sender_type' => 'contact',
                'type' => $numMedia > 0 ? 'mms' : 'sms',
                'body_text' => $fullBody,
                'body_html' => nl2br(e($fullBody)),
                'from_name' => $from,
                'from_email' => $from,
                'channel_message_id' => $messageSid,
                'delivery_status' => 'delivered',
                'sent_at' => now(),
                'delivered_at' => now(),
            ]);

            // Update conversation
            $conversation->update([
                'last_message_at' => now(),
                'preview' => Str::limit($fullBody, 200),
                'is_read' => false,
            ]);
            $conversation->increment('messages_count');

            Log::info('Twilio: Incoming SMS processed', [
                'from' => $from,
                'to' => $to,
                'message_id' => $message->id,
                'has_media' => $numMedia > 0,
            ]);

            // Fire MessageReceived event for workflows and real-time notifications
            event(new \App\Events\MessageReceived($message, $conversation));
        } catch (\Throwable $e) {
            Log::error('Twilio: Failed to process incoming message', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }

    /**
     * Process a status callback from Twilio.
     */
    public function processStatusCallback(array $payload): void
    {
        try {
            $messageSid = $payload['MessageSid'] ?? '';
            $status = $payload['MessageStatus'] ?? '';
            $errorCode = $payload['ErrorCode'] ?? null;
            $errorMessage = $payload['ErrorMessage'] ?? null;

            $message = Message::where('channel_message_id', $messageSid)->first();

            if (! $message) {
                return;
            }

            $updates = match ($status) {
                'queued', 'accepted' => ['delivery_status' => 'queued'],
                'sending' => ['delivery_status' => 'sending'],
                'sent' => ['delivery_status' => 'sent', 'sent_at' => now()],
                'delivered' => ['delivery_status' => 'delivered', 'delivered_at' => now()],
                'undelivered', 'failed' => [
                    'delivery_status' => 'failed',
                    'delivery_error' => $errorMessage ?? "Error code: {$errorCode}",
                ],
                default => [],
            };

            if (! empty($updates)) {
                $message->update($updates);
                Log::debug("Twilio: Status updated to '{$status}' for message {$message->id}");
            }
        } catch (\Throwable $e) {
            Log::error('Twilio: Failed to process status callback', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate that an incoming request is genuinely from Twilio using signature validation.
     */
    public function validateRequest(Request $request): bool
    {
        $authToken = config('services.twilio.auth_token');
        if (! $authToken) {
            Log::warning('Twilio: Cannot validate request - no auth token configured');
            return false;
        }

        $validator = new RequestValidator($authToken);

        $url = $request->fullUrl();
        $params = $request->all();
        $signature = $request->header('X-Twilio-Signature', '');

        $isValid = $validator->validate($signature, $url, $params);

        if (! $isValid) {
            Log::warning('Twilio: Request signature validation failed', [
                'url' => $url,
                'ip' => $request->ip(),
            ]);
        }

        return $isValid;
    }

    /**
     * Resolve workspace from the receiving Twilio phone number.
     * Looks up the channel_integrations table to find which workspace owns this number.
     *
     * NOTE: The channel_integrations table should have a unique constraint on
     * (channel, phone_number) WHERE status = 'active' to prevent two workspaces
     * from claiming the same Twilio number. Without this constraint, the first
     * matching integration is used and a warning is logged.
     */
    protected function resolveWorkspaceFromNumber(string $phoneNumber): ?int
    {
        $phoneNumber = $this->normalizePhone($phoneNumber);

        // Credentials are encrypted at rest — cannot use whereJsonContains.
        // First try the dedicated phone_number column, then fall back to PHP filtering.
        $integrations = \App\Models\ChannelIntegration::where('channel', 'sms')
            ->where('status', 'active')
            ->where('phone_number', $phoneNumber)
            ->get();

        if ($integrations->isEmpty()) {
            // Fall back: check encrypted credentials for phone_number match
            $integrations = \App\Models\ChannelIntegration::where('channel', 'sms')
                ->where('status', 'active')
                ->get()
                ->filter(fn ($i) => ($i->credentials['phone_number'] ?? null) === $phoneNumber);
        }

        if ($integrations->isEmpty()) {
            Log::warning('Twilio: No active integration found for phone number', [
                'phone_number' => $phoneNumber,
            ]);
            return null;
        }

        if ($integrations->count() > 1) {
            Log::warning('Twilio: Multiple active integrations found for the same phone number. Using the first match. Add a unique constraint on (channel, phone_number) to prevent this.', [
                'phone_number' => $phoneNumber,
                'workspace_ids' => $integrations->pluck('workspace_id')->toArray(),
                'integration_ids' => $integrations->pluck('id')->toArray(),
            ]);
        }

        return $integrations->first()->workspace_id;
    }
}
