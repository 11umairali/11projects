<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ChannelIntegration;
use App\Models\Workspace;
use App\Services\Channels\TelegramService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    /**
     * Handle incoming Telegram webhook updates.
     * Telegram sends POST with an Update object containing message, callback_query, etc.
     *
     * Security: Verifies the X-Telegram-Bot-Api-Secret-Token header against the
     * webhook_secret stored in the ChannelIntegration credentials. Telegram sends
     * this header on every webhook delivery when a secret_token was provided
     * during setWebhook registration.
     */
    public function handle(Request $request): JsonResponse
    {
        // --- Signature verification ---
        // Telegram supports a secret_token parameter when registering the webhook.
        // It sends it back as X-Telegram-Bot-Api-Secret-Token on every request.
        if (!$this->verifySecretToken($request)) {
            Log::warning('Telegram: Webhook secret token verification failed', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['ok' => false], 403);
        }

        $update = $request->all();

        Log::debug('Telegram: Webhook update received', [
            'update_id' => $update['update_id'] ?? null,
            'has_message' => isset($update['message']),
            'has_callback' => isset($update['callback_query']),
        ]);

        $integration = ChannelIntegration::where('channel', 'telegram')
            ->where('status', 'active')
            ->first();

        if (!$integration) {
            Log::warning('Telegram: Webhook ignored — no active integration');
            return response()->json(['ok' => true]);
        }

        $workspace = Workspace::find($integration->workspace_id);
        if ($workspace && !app(PlanLimitService::class)->hasFeature($workspace, 'telegram')) {
            Log::info('Telegram: Webhook ignored — telegram feature not enabled on workspace plan', [
                'workspace_id' => $workspace->id,
            ]);
            return response()->json(['ok' => true]);
        }

        $botToken = $integration->credentials['bot_token'] ?? null;
        if (!$botToken) {
            Log::error('Telegram: Active integration has no bot_token in credentials', [
                'integration_id' => $integration->id,
            ]);
            return response()->json(['ok' => true]);
        }

        try {
            // Build a service instance bound to THIS integration's bot token so
            // outbound replies and workspace resolution use the correct bot.
            // The constructor-injected service falls back to config and may have
            // no token, which would send requests to /bot/sendMessage (404).
            $service = new TelegramService($botToken);
            $service->processUpdate($update);
        } catch (\Throwable $e) {
            Log::error('Telegram: Webhook processing failed', [
                'update_id' => $update['update_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // Return 200 to acknowledge receipt (Telegram requirement)
        return response()->json(['ok' => true]);
    }

    /**
     * Verify the webhook secret token from the request header.
     *
     * Returns true if:
     *   - No secret is configured (backwards-compatible, but logs a warning).
     *   - The header matches the configured secret.
     *
     * Returns false if:
     *   - A secret is configured but the header is missing or wrong.
     */
    private function verifySecretToken(Request $request): bool
    {
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        // Find the active Telegram integration to get the expected secret.
        // Cache this in a real high-throughput scenario, but for webhook
        // volume (< 100 req/s typically) a single query is fine.
        $integration = ChannelIntegration::where('channel', 'telegram')
            ->where('status', 'active')
            ->first();

        $expectedToken = $integration?->credentials['webhook_secret'] ?? null;

        // If no secret configured, allow but warn (backwards compatible)
        if (!$expectedToken) {
            Log::warning('Telegram: No webhook_secret configured — accepting without verification. Set credentials.webhook_secret for security.', [
                'integration_id' => $integration?->id,
            ]);
            return true;
        }

        // Secret is configured -- header MUST match.
        if (!$secretToken || !hash_equals($expectedToken, $secretToken)) {
            return false;
        }

        return true;
    }

    /**
     * Alias: incoming messages.
     */
    public function incoming(Request $request): JsonResponse
    {
        return $this->handle($request);
    }

    /**
     * Alias: status updates.
     */
    public function status(Request $request): JsonResponse
    {
        return $this->handle($request);
    }

    /**
     * Alias: events.
     */
    public function events(Request $request): JsonResponse
    {
        return $this->handle($request);
    }

    /**
     * Alias: commands.
     */
    public function commands(Request $request): JsonResponse
    {
        return $this->handle($request);
    }

    /**
     * Alias: interactions (callback queries).
     */
    public function interactions(Request $request): JsonResponse
    {
        return $this->handle($request);
    }
}
