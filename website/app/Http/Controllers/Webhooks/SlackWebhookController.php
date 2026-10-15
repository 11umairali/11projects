<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ChannelIntegration;
use App\Models\Workspace;
use App\Services\Channels\SlackService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SlackWebhookController extends Controller
{
    public function __construct(
        protected SlackService $slackService
    ) {}

    /**
     * Handle Slack Events API.
     * Handles url_verification challenge and event callbacks.
     */
    public function events(Request $request): JsonResponse
    {
        // Verify Slack request signature FIRST — before processing any payload
        if (! $this->slackService->verifySignature($request)) {
            Log::warning('Slack: Events webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->all();
        $type = $payload['type'] ?? '';

        // Handle url_verification challenge (Slack sends this to verify endpoint)
        if ($type === 'url_verification') {
            Log::info('Slack: URL verification challenge received');
            return response()->json([
                'challenge' => $payload['challenge'] ?? '',
            ]);
        }

        // Handle event callbacks
        if ($type === 'event_callback') {
            $event = $payload['event'] ?? [];
            $eventType = $event['type'] ?? 'unknown';
            $teamId = $payload['team_id'] ?? null;

            Log::debug('Slack: Event received', [
                'type' => $eventType,
                'team_id' => $teamId ?? '',
            ]);

            // Check if workspace with active Slack integration has the slack feature enabled
            if ($teamId) {
                // Credentials are encrypted at rest — cannot use whereJsonContains.
                // Load all active slack integrations and filter in PHP after decryption.
                $slackIntegration = ChannelIntegration::where('channel', 'slack')
                    ->where('status', 'active')
                    ->where(function ($q) use ($teamId) {
                        $q->where('slack_team_id', $teamId);
                    })
                    ->first()
                    ?? ChannelIntegration::where('channel', 'slack')
                        ->where('status', 'active')
                        ->get()
                        ->first(fn ($i) => ($i->credentials['team_id'] ?? null) === $teamId);

                if ($slackIntegration) {
                    $workspace = Workspace::find($slackIntegration->workspace_id);
                    if ($workspace && !app(PlanLimitService::class)->hasFeature($workspace, 'slack')) {
                        Log::info('Slack: Event ignored — slack feature not enabled on workspace plan', [
                            'workspace_id' => $workspace->id,
                            'team_id' => $teamId,
                        ]);
                        return response()->json(['status' => 'ok']);
                    }
                }
            }

            try {
                $this->slackService->processEvent($event, $teamId);
            } catch (\Throwable $e) {
                Log::error('Slack: Failed to process event', [
                    'type' => $eventType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle Slack slash commands (e.g., /mailtrixy).
     */
    public function commands(Request $request): JsonResponse
    {
        // Verify Slack request signature
        if (! $this->slackService->verifySignature($request)) {
            Log::warning('Slack: Commands webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->all();

        Log::debug('Slack: Slash command received', [
            'command' => $payload['command'] ?? '',
            'text' => $payload['text'] ?? '',
            'user' => $payload['user_id'] ?? '',
        ]);

        try {
            $response = $this->slackService->handleCommand($payload);
            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('Slack: Failed to process slash command', [
                'command' => $payload['command'] ?? '',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'response_type' => 'ephemeral',
                'text' => 'Sorry, an error occurred while processing your command.',
            ]);
        }
    }

    /**
     * Handle Slack interactive actions (button clicks, menu selections, modals).
     * Slack sends these as application/x-www-form-urlencoded with a "payload" JSON string.
     */
    public function interactions(Request $request): JsonResponse
    {
        // Verify Slack request signature
        if (! $this->slackService->verifySignature($request)) {
            Log::warning('Slack: Interactions webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Slack sends interactive payloads as a URL-encoded "payload" field
        $rawPayload = $request->input('payload');
        $payload = json_decode($rawPayload, true);

        if (! $payload) {
            Log::warning('Slack: Could not parse interaction payload');
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $type = $payload['type'] ?? 'unknown';

        Log::debug('Slack: Interaction received', [
            'type' => $type,
            'user' => $payload['user']['id'] ?? 'unknown',
        ]);

        try {
            $response = $this->slackService->handleInteraction($payload);
            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('Slack: Failed to process interaction', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'text' => 'An error occurred.',
                'replace_original' => false,
            ]);
        }
    }

    /**
     * Generic handle endpoint.
     */
    public function handle(Request $request): JsonResponse
    {
        return $this->events($request);
    }

    /**
     * Incoming endpoint (alias for events).
     */
    public function incoming(Request $request): JsonResponse
    {
        return $this->events($request);
    }

    /**
     * Status endpoint (not used by Slack directly).
     */
    public function status(Request $request): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
