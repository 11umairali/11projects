<?php

namespace App\Http\Controllers\Webhooks;

use App\Events\ContactCreated;
use App\Http\Controllers\Controller;
use App\Models\ChannelIntegration;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Message;
use App\Services\Integrations\ZapierIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ZapierWebhookController extends Controller
{
    /**
     * Handle incoming Zapier webhook.
     *
     * POST /api/webhooks/zapier/{webhookToken}
     *
     * Authentication: Bearer token in Authorization header must match the
     * zapier_api_token stored for this workspace's Zapier integration.
     */
    public function handle(Request $request, string $webhookToken): JsonResponse
    {
        // Look up integration by webhook token
        $integration = ChannelIntegration::where('channel', 'zapier')
            ->where('zapier_webhook_token', $webhookToken)
            ->where('status', 'active')
            ->first();

        if (! $integration) {
            Log::warning('Zapier webhook: unknown or inactive token', [
                'token_prefix' => substr($webhookToken, 0, 8) . '...',
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid webhook token.'], 404);
        }

        // Verify Bearer token
        $authHeader = $request->header('Authorization', '');
        $bearerToken = str_starts_with($authHeader, 'Bearer ')
            ? substr($authHeader, 7)
            : null;

        if (! $bearerToken) {
            return response()->json(['error' => 'Missing Authorization header.'], 401);
        }

        // The 'encrypted' cast on the model auto-decrypts
        $storedToken = $integration->zapier_api_token;

        if (! $storedToken || ! hash_equals($storedToken, $bearerToken)) {
            Log::warning('Zapier webhook: invalid bearer token', [
                'workspace_id' => $integration->workspace_id,
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid API token.'], 401);
        }

        // Process the webhook payload
        $payload = $request->all();
        $action = $payload['action'] ?? null;
        $workspaceId = $integration->workspace_id;

        Log::info('Zapier webhook received', [
            'workspace_id' => $workspaceId,
            'action' => $action,
            'payload_keys' => array_keys($payload),
        ]);

        if (! $action) {
            return response()->json([
                'status' => 'received',
                'message' => 'No action specified. Payload acknowledged.',
                'workspace_id' => $workspaceId,
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        return match ($action) {
            'create_contact' => $this->createContact($workspaceId, $payload),
            'create_conversation' => $this->createConversation($workspaceId, $payload),
            'update_deal_stage' => $this->updateDealStage($workspaceId, $payload),
            'trigger_workflow' => $this->triggerWorkflow($workspaceId, $payload),
            'register_hook' => $this->registerHook($workspaceId, $payload),
            'unregister_hook' => $this->unregisterHook($workspaceId, $payload),
            default => response()->json([
                'status' => 'error',
                'message' => "Unknown action: {$action}",
                'supported_actions' => [
                    'create_contact',
                    'create_conversation',
                    'update_deal_stage',
                    'trigger_workflow',
                    'register_hook',
                    'unregister_hook',
                ],
            ], 400),
        };
    }

    /**
     * Create a contact from Zapier data.
     */
    protected function createContact(int $workspaceId, array $payload): JsonResponse
    {
        $data = $payload['data'] ?? $payload;

        $email = $data['email'] ?? null;
        if (! $email) {
            return response()->json(['status' => 'error', 'message' => 'Email is required.'], 422);
        }

        $contact = Contact::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'email' => $email,
            ],
            [
                'first_name' => $data['first_name'] ?? $data['name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'status' => 'active',
                'last_contacted_at' => now(),
            ]
        );

        // Fire event for workflow triggers and other integrations
        if ($contact->wasRecentlyCreated) {
            event(new ContactCreated($contact));
        }

        Log::info('Zapier: Contact created/updated', [
            'workspace_id' => $workspaceId,
            'contact_id' => $contact->id,
            'email' => $email,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $contact->wasRecentlyCreated ? 'Contact created.' : 'Contact updated.',
            'data' => [
                'id' => $contact->id,
                'email' => $contact->email,
                'name' => $contact->full_name,
            ],
        ], $contact->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Create a conversation (with an initial message) from Zapier data.
     */
    protected function createConversation(int $workspaceId, array $payload): JsonResponse
    {
        $data = $payload['data'] ?? $payload;

        $email = $data['email'] ?? $data['contact_email'] ?? null;
        $subject = $data['subject'] ?? 'Zapier Conversation';
        $body = $data['body'] ?? $data['message'] ?? '';

        if (! $email) {
            return response()->json(['status' => 'error', 'message' => 'Contact email is required.'], 422);
        }

        // Find or create contact
        $contact = Contact::firstOrCreate(
            ['workspace_id' => $workspaceId, 'email' => $email],
            [
                'first_name' => $data['first_name'] ?? $data['name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'status' => 'active',
                'last_contacted_at' => now(),
            ]
        );

        $conversation = Conversation::create([
            'workspace_id' => $workspaceId,
            'contact_id' => $contact->id,
            'channel' => $data['channel'] ?? 'email',
            'status' => 'open',
            'priority' => $data['priority'] ?? 'normal',
            'subject' => $subject,
            'preview' => Str::limit($body, 200),
            'is_read' => false,
            'messages_count' => 1,
            'last_message_at' => now(),
        ]);

        if ($body) {
            Message::create([
                'conversation_id' => $conversation->id,
                'workspace_id' => $workspaceId,
                'uuid' => Str::uuid(),
                'direction' => 'inbound',
                'sender_type' => 'contact',
                'type' => 'text',
                'body_text' => $body,
                'body_html' => nl2br(e($body)),
                'from_email' => $email,
                'from_name' => $contact->full_name,
                'delivery_status' => 'delivered',
                'sent_at' => now(),
                'delivered_at' => now(),
            ]);
        }

        Log::info('Zapier: Conversation created', [
            'workspace_id' => $workspaceId,
            'conversation_id' => $conversation->id,
            'contact_email' => $email,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Conversation created.',
            'data' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'contact_id' => $contact->id,
            ],
        ], 201);
    }

    /**
     * Update a deal's stage from Zapier data.
     */
    protected function updateDealStage(int $workspaceId, array $payload): JsonResponse
    {
        $data = $payload['data'] ?? $payload;

        $dealId = $data['deal_id'] ?? null;
        $stageName = $data['stage_name'] ?? $data['stage'] ?? null;
        $stageId = $data['stage_id'] ?? null;

        if (! $dealId) {
            return response()->json(['status' => 'error', 'message' => 'deal_id is required.'], 422);
        }

        $deal = Deal::where('workspace_id', $workspaceId)->find($dealId);
        if (! $deal) {
            return response()->json(['status' => 'error', 'message' => 'Deal not found.'], 404);
        }

        // Resolve target stage
        $newStage = null;
        if ($stageId) {
            $newStage = DealStage::where('pipeline_id', $deal->pipeline_id)->find($stageId);
        } elseif ($stageName) {
            $newStage = DealStage::where('pipeline_id', $deal->pipeline_id)
                ->where('name', $stageName)
                ->first();
        }

        if (! $newStage) {
            return response()->json(['status' => 'error', 'message' => 'Target stage not found.'], 404);
        }

        $previousStage = $deal->dealStage;

        $deal->update(['deal_stage_id' => $newStage->id]);

        // Handle won/lost status updates
        if (isset($data['status'])) {
            if ($data['status'] === 'won') {
                $deal->markAsWon();
            } elseif ($data['status'] === 'lost') {
                $deal->markAsLost($data['lost_reason'] ?? null);
            }
        }

        // Fire event if stage actually changed
        if ($previousStage && $previousStage->id !== $newStage->id) {
            event(new \App\Events\DealStageChanged($deal, $previousStage, $newStage));
        }

        Log::info('Zapier: Deal stage updated', [
            'workspace_id' => $workspaceId,
            'deal_id' => $deal->id,
            'new_stage' => $newStage->name,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Deal stage updated.',
            'data' => [
                'deal_id' => $deal->id,
                'title' => $deal->title,
                'new_stage' => $newStage->name,
                'previous_stage' => $previousStage?->name,
            ],
        ]);
    }

    /**
     * Trigger a workflow by name or ID from Zapier.
     */
    protected function triggerWorkflow(int $workspaceId, array $payload): JsonResponse
    {
        $data = $payload['data'] ?? $payload;

        $workflowId = $data['workflow_id'] ?? null;
        $workflowName = $data['workflow_name'] ?? null;
        $contactId = $data['contact_id'] ?? null;
        $contactEmail = $data['contact_email'] ?? null;

        // Resolve workflow
        $workflow = null;
        if ($workflowId) {
            $workflow = \App\Models\Workflow::where('workspace_id', $workspaceId)->find($workflowId);
        } elseif ($workflowName) {
            $workflow = \App\Models\Workflow::where('workspace_id', $workspaceId)
                ->where('name', $workflowName)
                ->where('status', 'active')
                ->first();
        }

        if (! $workflow) {
            return response()->json(['status' => 'error', 'message' => 'Workflow not found.'], 404);
        }

        // Resolve contact
        $contact = null;
        if ($contactId) {
            $contact = Contact::where('workspace_id', $workspaceId)->find($contactId);
        } elseif ($contactEmail) {
            $contact = Contact::where('workspace_id', $workspaceId)
                ->where('email', $contactEmail)
                ->first();
        }

        \App\Jobs\ExecuteWorkflowJob::dispatch(
            workflowId: $workflow->id,
            contactId: $contact?->id,
            triggerData: array_merge($data, ['source' => 'zapier']),
        )->onQueue('workflows');

        Log::info('Zapier: Workflow triggered', [
            'workspace_id' => $workspaceId,
            'workflow_id' => $workflow->id,
            'contact_id' => $contact?->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Workflow triggered.',
            'data' => [
                'workflow_id' => $workflow->id,
                'workflow_name' => $workflow->name,
                'contact_id' => $contact?->id,
            ],
        ]);
    }

    /**
     * Register a Zapier catch hook URL (for outgoing triggers from MailTrixy to Zapier).
     */
    protected function registerHook(int $workspaceId, array $payload): JsonResponse
    {
        $hookUrl = $payload['hook_url'] ?? $payload['data']['hook_url'] ?? null;

        if (! $hookUrl || ! filter_var($hookUrl, FILTER_VALIDATE_URL)) {
            return response()->json(['status' => 'error', 'message' => 'Valid hook_url is required.'], 422);
        }

        $zapierService = app(ZapierIntegrationService::class);
        $registered = $zapierService->registerWebhookUrl($workspaceId, $hookUrl);

        if (! $registered) {
            return response()->json(['status' => 'error', 'message' => 'Failed to register hook.'], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook URL registered.',
        ]);
    }

    /**
     * Unregister a Zapier catch hook URL.
     */
    protected function unregisterHook(int $workspaceId, array $payload): JsonResponse
    {
        $hookUrl = $payload['hook_url'] ?? $payload['data']['hook_url'] ?? null;

        if (! $hookUrl) {
            return response()->json(['status' => 'error', 'message' => 'hook_url is required.'], 422);
        }

        $zapierService = app(ZapierIntegrationService::class);
        $zapierService->unregisterWebhookUrl($workspaceId, $hookUrl);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook URL unregistered.',
        ]);
    }
}
