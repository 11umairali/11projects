<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ChannelIntegration;
use App\Models\Workspace;
use App\Services\Channels\WhatsAppService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    /**
     * Handle GET (verification) and POST (incoming) webhook requests.
     * Meta sends GET for challenge verification and POST for message/status events.
     */
    public function handle(Request $request): Response|JsonResponse
    {
        // GET = Meta webhook verification challenge
        if ($request->isMethod('get')) {
            return $this->whatsAppService->verifyWebhook($request);
        }

        // POST = Incoming webhook payload

        // Verify webhook signature from Meta (X-Hub-Signature-256 header)
        $signature = $request->header('X-Hub-Signature-256');
        $rawPayload = $request->getContent();
        $appSecret = config('services.whatsapp.app_secret');

        // If not in config, try to find from active WhatsApp integrations
        // Use all active integrations to verify — the webhook could be from any workspace's number
        if (!$appSecret) {
            $integrations = ChannelIntegration::where('channel', 'whatsapp')
                ->where('status', 'active')
                ->get();

            // Try each integration's app_secret until one matches
            foreach ($integrations as $integration) {
                $candidateSecret = $integration->credentials['app_secret'] ?? null;
                if ($candidateSecret && $signature) {
                    $expectedSig = 'sha256=' . hash_hmac('sha256', $rawPayload, $candidateSecret);
                    if (hash_equals($expectedSig, $signature)) {
                        $appSecret = $candidateSecret;
                        break;
                    }
                }
            }

            if (!$appSecret && $integrations->isNotEmpty()) {
                // No match found across any integration
                Log::warning('WhatsApp webhook: Signature did not match any active integration secret', [
                    'ip' => $request->ip(),
                    'integration_count' => $integrations->count(),
                ]);
                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        if (!$appSecret) {
            Log::warning('WhatsApp webhook: No app_secret configured — rejecting unverified request', [
                'ip' => $request->ip(),
            ]);
            return response('Webhook verification not configured', 403);
        }

        if (!$signature) {
            Log::warning('WhatsApp webhook: Missing X-Hub-Signature-256 header — rejecting request', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Missing signature'], 403);
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('WhatsApp: Webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->all();

        // FIX-046: Validate webhook timestamp — reject payloads older than 5 minutes
        $entries = $payload['entry'] ?? [];
        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $timestamps = collect($change['value']['messages'] ?? [])
                    ->pluck('timestamp')
                    ->filter();
                foreach ($timestamps as $ts) {
                    if (abs(time() - (int) $ts) > 300) {
                        Log::warning('WhatsApp: Rejecting stale webhook payload', [
                            'message_timestamp' => $ts,
                            'age_seconds' => abs(time() - (int) $ts),
                        ]);
                        return response()->json(['status' => 'stale'], 200);
                    }
                }
            }
        }

        Log::debug('WhatsApp: Webhook payload received', [
            'object' => $payload['object'] ?? 'unknown',
            'entry_count' => count($entries),
        ]);

        // Only process whatsapp_business_account payloads
        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Check if any workspace with active WhatsApp integration has the feature enabled
        $whatsappIntegrations = ChannelIntegration::where('channel', 'whatsapp')
            ->where('status', 'active')
            ->get();

        $featureBlocked = true;
        foreach ($whatsappIntegrations as $integration) {
            $workspace = Workspace::find($integration->workspace_id);
            if ($workspace && app(PlanLimitService::class)->hasFeature($workspace, 'whatsapp')) {
                $featureBlocked = false;
                break;
            }
        }

        if ($featureBlocked && $whatsappIntegrations->isNotEmpty()) {
            Log::info('WhatsApp: Webhook ignored — whatsapp feature not enabled on any workspace plan');
            return response()->json(['status' => 'ok'], 200);
        }

        // Process entries: each entry contains changes with messages and statuses
        try {
            $this->whatsAppService->processWebhook($payload);
        } catch (\Throwable $e) {
            Log::error('WhatsApp: Webhook processing failed', [
                'error' => $e->getMessage(),
            ]);
            // Return 200 to prevent Meta from retrying (we handle errors internally)
        }

        // Always return 200 to acknowledge receipt (Meta requirement)
        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Alias for incoming messages (if routed separately).
     */
    public function incoming(Request $request): JsonResponse
    {
        return $this->handle($request);
    }

    /**
     * Status updates (sent, delivered, read, failed).
     */
    public function status(Request $request): JsonResponse
    {
        return $this->handle($request);
    }

    /**
     * Events endpoint (alias).
     */
    public function events(Request $request): JsonResponse
    {
        return $this->handle($request);
    }

    /**
     * Commands endpoint (not used by WhatsApp, reserved).
     */
    public function commands(Request $request): JsonResponse
    {
        return response()->json(['status' => 'not_supported'], 200);
    }

    /**
     * Interactions endpoint (not used by WhatsApp, reserved).
     */
    public function interactions(Request $request): JsonResponse
    {
        return response()->json(['status' => 'not_supported'], 200);
    }
}
