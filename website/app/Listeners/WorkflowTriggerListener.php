<?php

namespace App\Listeners;

use App\Events\CampaignLinkClicked;
use App\Events\CampaignOpened;
use App\Events\ContactCreated;
use App\Events\ContactUpdated;
use App\Events\DealStageChanged;
use App\Events\FormSubmitted;
use App\Events\MessageReceived;
use App\Events\TagAdded;
use App\Events\TagRemoved;
use App\Events\WebhookReceived;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\Workflow;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

// Runs SYNCHRONOUSLY (not ShouldQueue). The actual workflow execution is
// dispatched to the `workflows` queue via ExecuteWorkflowJob below, so this
// listener only does a cheap DB lookup. Running synchronously avoids the
// SerializesModels round-trip that was throwing ModelNotFoundException when
// a Message/Conversation got deleted between sync and listener execution.
class WorkflowTriggerListener
{
    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            MessageReceived::class => 'handleMessageReceived',
            ContactCreated::class => 'handleContactCreated',
            ContactUpdated::class => 'handleContactUpdated',
            TagAdded::class => 'handleTagAdded',
            TagRemoved::class => 'handleTagRemoved',
            DealStageChanged::class => 'handleDealStageChanged',
            CampaignOpened::class => 'handleCampaignOpened',
            CampaignLinkClicked::class => 'handleCampaignLinkClicked',
            FormSubmitted::class => 'handleFormSubmitted',
            WebhookReceived::class => 'handleWebhookReceived',
        ];
    }

    /**
     * Form submissions target a SPECIFIC workflow (the one whose form was
     * submitted) — don't broadcast to every form_submitted workflow in the
     * workspace. Dispatch a single ExecuteWorkflowJob for that workflow.
     */
    public function handleFormSubmitted(FormSubmitted $event): void
    {
        $workflow = $event->workflow;
        if (!$workflow->isActive()) return;

        \App\Jobs\ExecuteWorkflowJob::dispatch(
            workflowId: $workflow->id,
            contactId: $event->contact?->id,
            triggerData: [
                'form_fields' => $event->fields,
                'contact_id' => $event->contact?->id,
                'email' => $event->contact?->email,
            ],
        )->onQueue('workflows');

        Log::info('FormSubmitted: dispatched workflow', [
            'workflow_id' => $workflow->id,
            'contact_id' => $event->contact?->id,
        ]);
    }

    /**
     * Inbound webhook targets a specific workflow by token — same one-shot
     * dispatch pattern as FormSubmitted.
     */
    public function handleWebhookReceived(WebhookReceived $event): void
    {
        $workflow = $event->workflow;
        if (!$workflow->isActive()) return;

        \App\Jobs\ExecuteWorkflowJob::dispatch(
            workflowId: $workflow->id,
            contactId: null,
            triggerData: $event->payload,
        )->onQueue('workflows');

        Log::info('WebhookReceived: dispatched workflow', [
            'workflow_id' => $workflow->id,
        ]);
    }

    public function handleCampaignOpened(CampaignOpened $event): void
    {
        $campaign = $event->campaign;

        $this->findAndDispatchWorkflows(
            workspaceId: $campaign->workspace_id,
            triggerSubtype: 'campaign_opened',
            contactId: $event->contact?->id,
            triggerData: [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'recipient_id' => $event->recipient->id,
                'contact_id' => $event->contact?->id,
            ],
            matchConfig: function (array $config) use ($campaign) {
                // Optional filter: only fire for a specific campaign id
                $requiredCampaignId = $config['campaign_id'] ?? null;
                return !$requiredCampaignId || (int) $requiredCampaignId === $campaign->id;
            }
        );
    }

    public function handleCampaignLinkClicked(CampaignLinkClicked $event): void
    {
        $campaign = $event->campaign;

        $this->findAndDispatchWorkflows(
            workspaceId: $campaign->workspace_id,
            triggerSubtype: 'campaign_clicked',
            contactId: $event->contact?->id,
            triggerData: [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'recipient_id' => $event->recipient->id,
                'contact_id' => $event->contact?->id,
                'url' => $event->url,
            ],
            matchConfig: function (array $config) use ($campaign, $event) {
                $requiredCampaignId = $config['campaign_id'] ?? null;
                if ($requiredCampaignId && (int) $requiredCampaignId !== $campaign->id) {
                    return false;
                }
                // Optional filter: URL must contain a substring
                $urlContains = $config['url_contains'] ?? null;
                if ($urlContains && !str_contains(strtolower($event->url), strtolower($urlContains))) {
                    return false;
                }
                return true;
            }
        );
    }

    public function handleContactUpdated(ContactUpdated $event): void
    {
        $contact = $event->contact;

        $this->findAndDispatchWorkflows(
            workspaceId: $contact->workspace_id,
            triggerSubtype: 'contact_updated',
            contactId: $contact->id,
            triggerData: [
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'changed_fields' => $event->changedFields,
            ],
            matchConfig: function (array $config) use ($event) {
                // If the trigger node has a "watch only these fields" filter,
                // require at least one of them to be in the changed set.
                $watched = $config['fields'] ?? [];
                if (empty($watched)) return true;
                return (bool) array_intersect($watched, $event->changedFields);
            }
        );
    }

    public function handleTagRemoved(TagRemoved $event): void
    {
        $contact = $event->contact;
        $tag = $event->tag;

        $this->findAndDispatchWorkflows(
            workspaceId: $contact->workspace_id,
            triggerSubtype: 'tag_removed',
            contactId: $contact->id,
            triggerData: [
                'contact_id' => $contact->id,
                'tag_id' => $tag->id,
                'tag_name' => $tag->name,
            ],
            matchConfig: function (array $config) use ($tag) {
                $triggerTagId = $config['tag_id'] ?? null;
                $triggerTagName = $config['tag_name'] ?? null;
                if ($triggerTagId && $triggerTagId != $tag->id) return false;
                if ($triggerTagName && strtolower($triggerTagName) !== strtolower($tag->name)) return false;
                return true;
            }
        );
    }

    /**
     * Handle a new message received event.
     */
    public function handleMessageReceived(MessageReceived $event): void
    {
        $conversation = $event->conversation;
        $message = $event->message;
        $workspaceId = $conversation->workspace_id;

        Log::info('WorkflowTriggerListener: handleMessageReceived entered', [
            'workspace_id' => $workspaceId,
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'from_email' => $message->from_email,
        ]);

        $this->findAndDispatchWorkflows(
            workspaceId: $workspaceId,
            triggerSubtype: 'email_received',
            contactId: $conversation->contact_id,
            triggerData: [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'subject' => $message->subject,
                'from_email' => $message->from_email,
                'channel' => $conversation->channel ?? 'email',
            ],
            matchConfig: function (array $config) use ($message, $conversation) {
                // Check if trigger config has filters (e.g., subject contains, from domain)
                return $this->matchesTriggerFilters($config, [
                    'subject' => $message->subject,
                    'from_email' => $message->from_email,
                    'channel' => $conversation->channel ?? 'email',
                ]);
            }
        );
    }

    /**
     * Handle a contact created event.
     */
    public function handleContactCreated(ContactCreated $event): void
    {
        $contact = $event->contact;

        $this->findAndDispatchWorkflows(
            workspaceId: $contact->workspace_id,
            triggerSubtype: 'contact_created',
            contactId: $contact->id,
            triggerData: [
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'source' => $contact->source ?? 'manual',
            ],
            matchConfig: function (array $config) use ($contact) {
                return $this->matchesTriggerFilters($config, [
                    'email' => $contact->email,
                    'source' => $contact->source ?? 'manual',
                ]);
            }
        );
    }

    /**
     * Handle a tag added event.
     */
    public function handleTagAdded(TagAdded $event): void
    {
        $contact = $event->contact;
        $tag = $event->tag;

        $this->findAndDispatchWorkflows(
            workspaceId: $contact->workspace_id,
            triggerSubtype: 'tag_added',
            contactId: $contact->id,
            triggerData: [
                'contact_id' => $contact->id,
                'tag_id' => $tag->id,
                'tag_name' => $tag->name,
            ],
            matchConfig: function (array $config) use ($tag) {
                // Match if trigger is configured for a specific tag
                $triggerTagId = $config['tag_id'] ?? null;
                $triggerTagName = $config['tag_name'] ?? null;

                if ($triggerTagId && $triggerTagId != $tag->id) {
                    return false;
                }
                if ($triggerTagName && strtolower($triggerTagName) !== strtolower($tag->name)) {
                    return false;
                }
                return true;
            }
        );
    }

    /**
     * Handle a deal stage changed event.
     */
    public function handleDealStageChanged(DealStageChanged $event): void
    {
        $deal = $event->deal;

        $this->findAndDispatchWorkflows(
            workspaceId: $deal->workspace_id,
            triggerSubtype: 'deal_stage_changed',
            contactId: $deal->contact_id,
            triggerData: [
                'deal_id' => $deal->id,
                'deal_title' => $deal->title,
                'previous_stage_id' => $event->previousStage->id,
                'previous_stage_name' => $event->previousStage->name,
                'new_stage_id' => $event->newStage->id,
                'new_stage_name' => $event->newStage->name,
                'deal_value' => $deal->value,
            ],
            matchConfig: function (array $config) use ($event) {
                // Match specific stage transitions if configured
                $fromStageId = $config['from_stage_id'] ?? null;
                $toStageId = $config['to_stage_id'] ?? null;
                $pipelineId = $config['pipeline_id'] ?? null;

                if ($pipelineId && $pipelineId != $event->deal->pipeline_id) {
                    return false;
                }
                if ($fromStageId && $fromStageId != $event->previousStage->id) {
                    return false;
                }
                if ($toStageId && $toStageId != $event->newStage->id) {
                    return false;
                }
                return true;
            }
        );
    }

    /**
     * Find active workflows matching the trigger subtype and dispatch execution jobs.
     *
     * @param int      $workspaceId     The workspace to search for workflows.
     * @param string   $triggerSubtype   The trigger subtype to match (e.g., 'email_received').
     * @param int|null $contactId        The contact ID (if applicable).
     * @param array    $triggerData      Data to pass to the workflow execution.
     * @param callable $matchConfig      Callback to validate trigger node config matches the event.
     */
    private function findAndDispatchWorkflows(
        int $workspaceId,
        string $triggerSubtype,
        ?int $contactId,
        array $triggerData,
        callable $matchConfig
    ): void {
        // Eager load trigger nodes matching the subtype to avoid N+1 queries.
        // Each workflow's workflowNodes relation will only contain matching trigger nodes.
        $workflows = Workflow::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->with(['workflowNodes' => function ($query) use ($triggerSubtype) {
                $query->where('type', 'trigger')
                    ->where('subtype', $triggerSubtype);
            }])
            ->whereHas('workflowNodes', function ($query) use ($triggerSubtype) {
                $query->where('type', 'trigger')
                    ->where('subtype', $triggerSubtype);
            })
            ->get();

        Log::info('WorkflowTriggerListener: candidate workflows', [
            'workspace_id' => $workspaceId,
            'trigger_subtype' => $triggerSubtype,
            'candidate_count' => $workflows->count(),
        ]);

        foreach ($workflows as $workflow) {
            // Trigger node is already eager-loaded -- pull from the relation collection
            $triggerNode = $workflow->workflowNodes->first();

            if (!$triggerNode) {
                continue;
            }

            $config = $triggerNode->config ?? [];

            // Check if the trigger config matches the event data
            if (!$matchConfig($config)) {
                continue;
            }

            // Belt-and-suspenders dedupe: even if the same event fires twice
            // (e.g. AJAX sync + background SyncEmailAccountJob race), make sure
            // any given (workflow, message|contact) pair only dispatches once.
            // Uses Cache::add which is atomic; 1h TTL is plenty because messages
            // never get reprocessed hours later under any normal flow.
            $dedupeParts = [
                'wf:' . $workflow->id,
                'trg:' . $triggerSubtype,
                'msg:' . ($triggerData['message_id'] ?? '0'),
                'contact:' . ($contactId ?? '0'),
                'deal:' . ($triggerData['deal_id'] ?? '0'),
                'tag:' . ($triggerData['tag_id'] ?? '0'),
                'recip:' . ($triggerData['recipient_id'] ?? '0'),
            ];
            $dedupeKey = 'wf_dispatch:' . md5(implode('|', $dedupeParts));
            if (!\Illuminate\Support\Facades\Cache::add($dedupeKey, true, 3600)) {
                continue;
            }

            Log::info('Workflow trigger matched', [
                'workflow_id' => $workflow->id,
                'workflow_name' => $workflow->name,
                'trigger_subtype' => $triggerSubtype,
                'contact_id' => $contactId,
            ]);

            ExecuteWorkflowJob::dispatch(
                workflowId: $workflow->id,
                contactId: $contactId,
                triggerData: $triggerData,
            )->onQueue('workflows');
        }
    }

    /**
     * Check if trigger config filters match the event data.
     * Empty config = matches everything (no filters).
     */
    private function matchesTriggerFilters(array $config, array $eventData): bool
    {
        $filters = $config['filters'] ?? [];

        if (empty($filters)) {
            return true; // No filters = match all
        }

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? '';
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? '';

            $actual = $eventData[$field] ?? null;

            if ($actual === null) {
                continue;
            }

            $matched = match ($operator) {
                'equals' => (string) $actual === (string) $value,
                'not_equals' => (string) $actual !== (string) $value,
                'contains' => str_contains(strtolower((string) $actual), strtolower($value)),
                'starts_with' => str_starts_with(strtolower((string) $actual), strtolower($value)),
                'ends_with' => str_ends_with(strtolower((string) $actual), strtolower($value)),
                'regex' => $this->safeRegexMatch($value, (string) $actual),
                default => true,
            };

            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * Safely evaluate a regex pattern against a value.
     * Rejects patterns that are too long or syntactically invalid.
     */
    private function safeRegexMatch(string $pattern, string $actual): bool
    {
        if (strlen($pattern) > 500) {
            Log::warning('WorkflowTriggerListener: regex pattern too long, rejecting', [
                'pattern_length' => strlen($pattern),
            ]);
            return false;
        }

        if (@preg_match("/{$pattern}/i", '') === false) {
            Log::warning('WorkflowTriggerListener: invalid regex pattern', [
                'pattern' => $pattern,
            ]);
            return false;
        }

        return (bool) preg_match("/{$pattern}/i", $actual);
    }
}
