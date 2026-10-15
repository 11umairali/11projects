<?php

namespace App\Listeners;

use App\Events\ContactCreated;
use App\Events\DealStageChanged;
use App\Events\MessageReceived;
use App\Services\Integrations\HubSpotIntegrationService;
use App\Services\Integrations\SalesforceIntegrationService;
use App\Services\Integrations\SlackIntegrationService;
use App\Services\Integrations\ZapierIntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches notifications to connected integrations (Slack, Zapier)
 * when domain events fire. Runs on the queue so the main request is not blocked.
 */
class IntegrationNotificationListener implements ShouldQueue
{
    public string $connection = 'database';

    public string $queue = 'integrations';

    /**
     * Number of seconds to wait before retrying on failure.
     */
    public int $backoff = 10;

    /**
     * Maximum retry attempts.
     */
    public int $tries = 2;

    public function __construct(
        protected SlackIntegrationService $slack,
        protected ZapierIntegrationService $zapier,
        protected SalesforceIntegrationService $salesforce,
        protected HubSpotIntegrationService $hubspot,
    ) {}

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            MessageReceived::class => 'handleMessageReceived',
            ContactCreated::class => 'handleContactCreated',
            DealStageChanged::class => 'handleDealStageChanged',
        ];
    }

    /**
     * New inbound message received -- notify Slack and Zapier.
     */
    public function handleMessageReceived(MessageReceived $event): void
    {
        // When this listener is queued, Laravel re-hydrates the Message and
        // Conversation models from the DB before running. If either was
        // deleted in the meantime (trash, workspace wipe, etc.) the implicit
        // findOrFail throws ModelNotFoundException, the job fails, retries
        // twice, then lands in failed_jobs forever. With high message
        // churn that fills the failed_jobs table with thousands of rows.
        // Guard both models explicitly so stale events short-circuit
        // cleanly instead of failing the whole job.
        $message = $event->message ?? null;
        $conversation = $event->conversation ?? null;
        if (! $message || ! $message->exists || ! $conversation || ! $conversation->exists) {
            return;
        }

        // Shared dedupe: the inline path in InboxApiController /
        // SyncEmailAccountJob uses the SAME cache keys, so if either already
        // fired the notification for this message, we skip here.
        $slackKey = "int_slack_fired:{$message->id}";
        $zapierKey = "int_zapier_fired:{$message->id}";
        $workspaceId = $conversation->workspace_id;

        // Only notify on inbound messages (not our own outbound replies)
        if ($message->direction !== 'inbound') {
            return;
        }

        // NOTE: Auto-reply is handled by the SYNCHRONOUS AutoReplyListener
        // so it fires the instant the message arrives, not after the
        // integrations-queue worker runs on its minute tick. This class
        // stays queued because Slack/Zapier posts are external, slower, and
        // acceptable to delay.

        // --- Slack: post conversation update ---
        // Cache::add returns true only if the key didn't exist — so if the
        // inline path already fired for this message, add() returns false and
        // we skip. Otherwise we claim the slot and fire.
        try {
            if (\Illuminate\Support\Facades\Cache::add($slackKey, true, 3600)
                && $this->slack->isActive($workspaceId)) {
                $this->slack->postConversationUpdate($conversation, 'new_message');
            }
        } catch (\Throwable $e) {
            Log::error('IntegrationNotification: Slack notification failed for MessageReceived', [
                'workspace_id' => $workspaceId,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }

        // --- Zapier: trigger conversation.new webhook ---
        try {
            if (\Illuminate\Support\Facades\Cache::add($zapierKey, true, 3600)
                && $this->zapier->isActive($workspaceId)) {
                $contact = $conversation->contact;

                $this->zapier->triggerWebhook($workspaceId, 'conversation.new', [
                    'conversation_id' => $conversation->id,
                    'subject' => $conversation->subject,
                    'channel' => $conversation->channel ?? 'email',
                    'contact_email' => $contact?->email,
                    'contact_name' => $contact?->full_name,
                    'message_preview' => \Illuminate\Support\Str::limit($message->body_text ?? '', 300),
                    'priority' => $conversation->priority,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('IntegrationNotification: Zapier webhook failed for MessageReceived', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * New contact created -- notify Zapier AND auto-push to Salesforce/HubSpot
     * if their integration is active. Each CRM is fire-and-forget (independent
     * try/catch) so one failing vendor never blocks the others.
     */
    public function handleContactCreated(ContactCreated $event): void
    {
        $contact = $event->contact;
        $workspaceId = $contact->workspace_id;

        // --- Zapier webhook ---
        try {
            if ($this->zapier->isActive($workspaceId)) {
                $this->zapier->triggerWebhook($workspaceId, 'contact.created', [
                    'contact_id' => $contact->id,
                    'email' => $contact->email,
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'company' => $contact->company,
                    'phone' => $contact->phone,
                    'source' => $contact->source ?? 'manual',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('IntegrationNotification: Zapier webhook failed for ContactCreated', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);
        }

        // --- Salesforce: upsert as Contact object ---
        try {
            if ($this->salesforce->isActive($workspaceId)) {
                $this->salesforce->syncContactToSalesforce($contact);
            }
        } catch (\Throwable $e) {
            Log::warning('IntegrationNotification: Salesforce push failed for ContactCreated', [
                'workspace_id' => $workspaceId,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
        }

        // --- HubSpot: upsert to HubSpot CRM ---
        try {
            if ($this->hubspot->isActive($workspaceId)) {
                $this->hubspot->syncContactToHubSpot($contact);
            }
        } catch (\Throwable $e) {
            Log::warning('IntegrationNotification: HubSpot push failed for ContactCreated', [
                'workspace_id' => $workspaceId,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Deal stage changed -- notify Slack and Zapier.
     */
    public function handleDealStageChanged(DealStageChanged $event): void
    {
        $deal = $event->deal;
        $workspaceId = $deal->workspace_id;
        $previousStage = $event->previousStage;
        $newStage = $event->newStage;

        // --- Slack: post deal update ---
        try {
            if ($this->slack->isActive($workspaceId)) {
                $contact = $deal->contact;
                $contactName = $contact?->full_name ?? 'Unknown';
                $message = ":moneybag: *Deal Stage Changed*"
                    . "\n*Deal:* {$deal->title}"
                    . "\n*Contact:* {$contactName}"
                    . "\n*Stage:* {$previousStage->name} -> {$newStage->name}"
                    . "\n*Value:* $" . number_format((float) $deal->value, 2);

                $this->slack->sendNotification($workspaceId, $message);
            }
        } catch (\Throwable $e) {
            Log::error('IntegrationNotification: Slack notification failed for DealStageChanged', [
                'workspace_id' => $workspaceId,
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);
        }

        // --- Zapier: trigger deal event ---
        try {
            if ($this->zapier->isActive($workspaceId)) {
                // Determine if this is a won/lost/general stage change
                $zapierEvent = match ($deal->status) {
                    'won' => 'deal.won',
                    'lost' => 'deal.lost',
                    default => 'deal.stage_changed',
                };

                $this->zapier->triggerWebhook($workspaceId, $zapierEvent, [
                    'deal_id' => $deal->id,
                    'deal_title' => $deal->title,
                    'deal_value' => $deal->value,
                    'deal_currency' => $deal->currency ?? 'USD',
                    'deal_status' => $deal->status,
                    'previous_stage' => $previousStage->name,
                    'new_stage' => $newStage->name,
                    'contact_email' => $deal->contact?->email,
                    'contact_name' => $deal->contact?->full_name,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('IntegrationNotification: Zapier webhook failed for DealStageChanged', [
                'workspace_id' => $workspaceId,
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
