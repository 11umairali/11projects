<?php

namespace App\Jobs;

use App\Models\CampaignRecipient;
use App\Models\ChannelIntegration;
use App\Services\Channels\TwilioSMSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends a single SMS to one campaign recipient via Twilio.
 *
 * Mirrors the contract of SendCampaignEmailJob:
 *   - Atomic claim via DB UPDATE (`pending → processing`) to prevent
 *     duplicate sends when two workers race on the same recipient.
 *   - Variable substitution ({first_name}, {company}, etc.) before send.
 *   - Twilio credentials resolved from the workspace's active SMS
 *     channel_integrations row, falling back to .env defaults.
 *   - Honors campaign.from_number override; otherwise uses the integration's
 *     primary phone number.
 *   - Updates recipient.status to 'sent' on success or 'failed' with
 *     error_message when Twilio rejects the request.
 *
 * Throttling is handled by CampaignService at dispatch time (queue
 * delays); this job itself just sends one message.
 */
class SendCampaignSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];
    public int $timeout = 60;

    public function __construct(
        public CampaignRecipient $recipient
    ) {}

    public function handle(TwilioSMSService $twilio): void
    {
        // Atomic claim: only this worker proceeds
        $claimed = CampaignRecipient::where('id', $this->recipient->id)
            ->where('status', 'pending')
            ->update(['status' => 'processing']);

        if ($claimed === 0) {
            Log::info('Campaign SMS skipped — recipient already claimed/processed', [
                'recipient_id' => $this->recipient->id,
            ]);
            return;
        }

        $recipient = $this->recipient->fresh()->load(['contact', 'campaign']);
        $campaign = $recipient->campaign;
        $contact = $recipient->contact;

        if (!$campaign || !$contact) {
            $recipient->update(['status' => 'failed', 'error_message' => 'Campaign or contact missing.']);
            return;
        }

        // Phone number: prefer the snapshot stored on the recipient row,
        // fall back to the contact's current phone.
        $to = $recipient->phone ?: $contact->phone;
        if (!$to) {
            $recipient->update(['status' => 'failed', 'error_message' => 'No phone number for contact.']);
            return;
        }

        // Resolve Twilio credentials from the workspace's active SMS integration.
        // Each workspace can have its own SID/token/from-number stored in
        // channel_integrations.credentials (encrypted at rest).
        $integration = ChannelIntegration::where('workspace_id', $campaign->workspace_id)
            ->where('channel', 'sms')
            ->where('status', 'active')
            ->first();

        $sid = $integration?->credentials['account_sid'] ?? null;
        $token = $integration?->credentials['auth_token'] ?? null;

        // From-number priority:
        //   1. campaign.from_number  (user picked it during create)
        //   2. integration.phone_number  (default for the workspace)
        //   3. .env TWILIO_PHONE_NUMBER  (platform fallback)
        $fromNumber = $campaign->from_number
            ?: ($integration?->phone_number)
            ?: ($integration?->credentials['phone_number'] ?? null);

        // Render the SMS body with contact variables substituted
        $body = $this->renderBody($campaign->body_text ?? '', $contact);

        if (trim($body) === '') {
            $recipient->update(['status' => 'failed', 'error_message' => 'Empty SMS body.']);
            return;
        }

        try {
            $message = $twilio->sendSMS(
                to: $to,
                body: $body,
                from: $fromNumber,
                sid: $sid,
                token: $token,
            );

            $recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);

            // Bump campaign-level counters so the report panel reflects progress
            $campaign->increment('sent_count');

            Log::info('Campaign SMS sent', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'to' => $to,
                'twilio_sid' => $message->sid,
            ]);
        } catch (\Throwable $e) {
            // Truncate the error so a 1KB Twilio payload doesn't blow up the column
            $errorMessage = mb_substr($e->getMessage(), 0, 500);

            $recipient->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
            ]);

            Log::error('Campaign SMS send failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            // Re-throw so the queue retries (up to $tries) for transient failures
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        // After all retries exhausted, mark as failed and move on
        CampaignRecipient::where('id', $this->recipient->id)
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'failed',
                'error_message' => 'All retries exhausted: ' . mb_substr($exception?->getMessage() ?? '', 0, 400),
            ]);
    }

    /**
     * Resolve {first_name}, {last_name}, {full_name}, {company}, {phone}
     * placeholders against the recipient contact. Mirrors the substitution
     * surface of SendCampaignEmailJob so authors can use the same merge
     * tags in both channels.
     */
    private function renderBody(string $template, $contact): string
    {
        return str_replace(
            ['{first_name}', '{last_name}', '{full_name}', '{company}', '{phone}', '{email}'],
            [
                $contact->first_name ?? '',
                $contact->last_name ?? '',
                trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')),
                $contact->company ?? '',
                $contact->phone ?? '',
                $contact->email ?? '',
            ],
            $template
        );
    }
}
