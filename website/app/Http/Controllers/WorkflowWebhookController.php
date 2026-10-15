<?php

namespace App\Http\Controllers;

use App\Events\WebhookReceived;
use App\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Receives inbound HTTP calls that fire workflows with
 * subtype='webhook_received'. Each workflow has a unique token stored on
 * the workflows.webhook_token column (auto-generated on save). The token
 * IS the auth — keep it secret. Payload (JSON body + query string) becomes
 * the trigger's triggerData.
 */
class WorkflowWebhookController extends Controller
{
    /**
     * POST /workflows/hook/{token}
     */
    public function receive(string $token, Request $request): JsonResponse
    {
        // Rate-limit per token to block a single misconfigured upstream from
        // hammering the queue. 120/min is generous for genuine integrations
        // (Zapier polls, Make.com scenarios, etc.) but catches runaway loops.
        $rlKey = "workflow-hook:{$token}";
        if (RateLimiter::tooManyAttempts($rlKey, 120)) {
            return response()->json(['error' => 'Too many requests'], 429);
        }
        RateLimiter::hit($rlKey, 60);

        $workflow = Workflow::where('webhook_token', $token)
            ->where('status', 'active')
            ->whereHas('workflowNodes', fn ($q) => $q->where('type', 'trigger')->where('subtype', 'webhook_received'))
            ->first();

        if (!$workflow) {
            return response()->json(['error' => 'Unknown webhook'], 404);
        }

        // Merge query string + JSON body into trigger payload; body wins on
        // collision since that's where richer third-party data lives.
        $payload = array_merge(
            $request->query->all(),
            $request->all(),
        );

        // Strip anything that isn't safe JSON-serializable so the payload
        // survives DB storage on the jobs table.
        $payload = json_decode(json_encode($payload), true) ?? [];

        try {
            event(new WebhookReceived($workflow, $payload));
        } catch (\Throwable $e) {
            Log::warning("WorkflowWebhook: dispatch failed for workflow={$workflow->id}: {$e->getMessage()}");
            return response()->json(['error' => 'Internal error'], 500);
        }

        return response()->json(['ok' => true, 'workflow_id' => $workflow->id]);
    }
}
