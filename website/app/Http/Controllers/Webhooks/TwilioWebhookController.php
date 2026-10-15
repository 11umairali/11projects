<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ChannelIntegration;
use App\Models\Workspace;
use App\Services\Channels\TwilioSMSService;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioWebhookController extends Controller
{
    public function __construct(
        protected TwilioSMSService $twilioService
    ) {}

    /**
     * Handle incoming SMS/MMS from Twilio.
     * Twilio sends POST with From, To, Body, MessageSid, etc.
     */
    public function incoming(Request $request): Response
    {
        // Validate Twilio signature in production
        if (! $this->twilioService->validateRequest($request)) {
            Log::warning('Twilio: Invalid request signature on incoming webhook', [
                'ip' => $request->ip(),
            ]);

            return response('Unauthorized', 403);
        }

        $payload = $request->all();

        Log::debug('Twilio: Incoming message received', [
            'from' => $payload['From'] ?? '',
            'to' => $payload['To'] ?? '',
            'sid' => $payload['MessageSid'] ?? '',
        ]);

        // Check if workspace with active SMS/Twilio integration has the sms feature enabled
        $smsIntegration = ChannelIntegration::where('channel', 'sms')
            ->where('status', 'active')
            ->first();

        if ($smsIntegration) {
            $workspace = Workspace::find($smsIntegration->workspace_id);
            if ($workspace && !app(PlanLimitService::class)->hasFeature($workspace, 'sms')) {
                Log::info('Twilio: Incoming message ignored — sms feature not enabled on workspace plan', [
                    'workspace_id' => $workspace->id,
                ]);
                return response(
                    '<?xml version="1.0" encoding="UTF-8"?><Response></Response>',
                    200,
                    ['Content-Type' => 'application/xml']
                );
            }
        }

        try {
            $this->twilioService->processIncoming($payload);
        } catch (\Throwable $e) {
            Log::error('Twilio: Failed to process incoming message', [
                'error' => $e->getMessage(),
                'sid' => $payload['MessageSid'] ?? '',
            ]);
        }

        // Return TwiML empty response (Twilio expects XML)
        return response(
            '<?xml version="1.0" encoding="UTF-8"?><Response></Response>',
            200,
            ['Content-Type' => 'application/xml']
        );
    }

    /**
     * Handle delivery status callbacks from Twilio.
     * Twilio sends POST with MessageSid, MessageStatus, ErrorCode, etc.
     */
    public function status(Request $request): Response
    {
        // Validate Twilio signature in production
        if (! $this->twilioService->validateRequest($request)) {
            Log::warning('Twilio: Invalid request signature on status webhook', [
                'ip' => $request->ip(),
            ]);

            return response('Unauthorized', 403);
        }

        $payload = $request->all();

        Log::debug('Twilio: Status callback received', [
            'sid' => $payload['MessageSid'] ?? '',
            'status' => $payload['MessageStatus'] ?? '',
        ]);

        try {
            $this->twilioService->processStatusCallback($payload);
        } catch (\Throwable $e) {
            Log::error('Twilio: Failed to process status callback', [
                'error' => $e->getMessage(),
                'sid' => $payload['MessageSid'] ?? '',
            ]);
        }

        return response('', 204);
    }

    /**
     * Generic handle endpoint (alias for incoming).
     */
    public function handle(Request $request): Response
    {
        return $this->incoming($request);
    }

    /**
     * Events endpoint (alias for status).
     */
    public function events(Request $request): Response
    {
        return $this->status($request);
    }

    /**
     * Commands endpoint (not used by Twilio, reserved).
     */
    public function commands(Request $request): Response
    {
        return response('', 204);
    }

    /**
     * Interactions endpoint (not used by Twilio, reserved).
     */
    public function interactions(Request $request): Response
    {
        return response('', 204);
    }
}
