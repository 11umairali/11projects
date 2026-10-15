<?php

namespace App\Livewire\Inbox;

use App\Models\ChannelIntegration;
use App\Models\Conversation;
use App\Models\ConversationViewer;
use App\Models\Message;
use App\Models\Tag;
use App\Models\User;
use App\Services\AI\AIManager;
use App\Services\Integrations\StripeIntegrationService;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ConversationDetail extends Component
{
    use AuthorizesWorkspaceActions;

    /** Maximum number of messages to load per conversation to prevent OOM on long threads. */
    private const MAX_MESSAGES = 100;

    public ?int $conversationId = null;

    public bool $showAssignDropdown = false;
    public bool $showTagDropdown = false;
    public bool $showPriorityDropdown = false;
    public bool $showMoreMenu = false;
    public bool $showSnoozeOptions = false;

    // AI draft state
    public bool $showAiDraft = false;
    public string $aiDraft = '';
    public ?int $aiDraftMessageId = null;
    public ?int $aiConfidence = null;
    public bool $aiLoading = false;

    // AI Recap (thread summarizer) state — separate from the reply-draft
    // flow so an agent can summarize without losing an in-progress draft.
    public ?string $aiSummary = null;
    public bool $summarizingThread = false;

    // Heartbeat throttle
    protected ?\Carbon\Carbon $lastHeartbeat = null;

    #[Computed]
    public function isAiConfigured(): bool
    {
        return \App\Models\AiConfig::where('workspace_id', auth()->user()->active_workspace_id)->exists();
    }

    #[On('conversation-selected')]
    public function loadConversation(int $conversationId): void
    {
        if (!$this->authorizeWorkspaceAction('view')) return;

        // Verify workspace ownership to prevent IDOR
        $exists = Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $conversationId)
            ->exists();

        if (!$exists) {
            $this->conversationId = null;
            return;
        }

        $this->conversationId = $conversationId;
        $this->resetDropdowns();
        $this->showAiDraft = false;
        $this->aiDraft = '';
        $this->aiDraftMessageId = null;
        $this->aiConfidence = null;
        $this->aiSummary = null;
        $this->summarizingThread = false;

        // Update viewer heartbeat for collision detection
        $this->updateViewerHeartbeat();

        // Check for pending AI drafts
        $this->checkPendingAiDrafts();
    }

    public function dehydrate(): void
    {
        // Update heartbeat on render cycle, throttled to every 30 seconds
        if ($this->conversationId) {
            if (!$this->lastHeartbeat || now()->diffInSeconds($this->lastHeartbeat) >= 30) {
                $this->updateViewerHeartbeat();
                $this->lastHeartbeat = now();
            }
        }
    }

    protected function updateViewerHeartbeat(): void
    {
        if (!$this->conversationId) return;

        ConversationViewer::updateOrCreate(
            [
                'conversation_id' => $this->conversationId,
                'user_id' => auth()->id(),
            ],
            [
                'workspace_id' => auth()->user()->active_workspace_id,
                'last_seen_at' => now(),
            ]
        );
    }

    protected function checkPendingAiDrafts(): void
    {
        if (!$this->conversationId) return;

        $aiDraftMessage = Message::where('conversation_id', $this->conversationId)
            ->where('type', 'ai_draft')
            ->where('ai_status', 'draft')
            ->latest()
            ->first();

        if ($aiDraftMessage) {
            $this->showAiDraft = true;
            $this->aiDraft = $aiDraftMessage->body_text ?? strip_tags($aiDraftMessage->body_html ?? '');
            $this->aiDraftMessageId = $aiDraftMessage->id;
            $this->aiConfidence = $aiDraftMessage->ai_confidence;
        }
    }

    protected function getConversation(): ?Conversation
    {
        if (!$this->conversationId) return null;

        return Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->with(['contact', 'assignedTo', 'tagModels', 'emailAccount'])
            ->find($this->conversationId);
    }

    protected function getActiveViewers(): \Illuminate\Support\Collection
    {
        if (!$this->conversationId) return collect();

        return ConversationViewer::where('conversation_id', $this->conversationId)
            ->where('user_id', '!=', auth()->id())
            ->where('last_seen_at', '>=', now()->subSeconds(15))
            ->with('user')
            ->get();
    }

    // Actions

    public function approveAiDraft(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (!$this->conversationId || !$this->aiDraftMessageId) return;

        // FIX-020: Lock scoped to message ID, not conversation, to prevent race conditions
        $this->withOperationLock("approve-ai-draft-{$this->aiDraftMessageId}", function () {
            // Verify draft belongs to this conversation AND workspace
            $message = Message::where('id', $this->aiDraftMessageId)
                ->where('workspace_id', auth()->user()->active_workspace_id)
                ->where('conversation_id', $this->conversationId)
                ->where('type', 'ai_draft')
                ->where('ai_status', 'draft')
                ->first();

            if (!$message) {
                session()->flash('error', 'AI draft not found or already processed.');
                return;
            }

            $conversation = $this->getConversation();
            $emailAccount = $conversation?->emailAccount;

            // First mark as approved message (so sendReply finds a real message, not a draft)
            $message->update([
                'ai_status' => 'approved',
                'type' => 'message',
                'sent_at' => now(),
                'delivery_status' => 'sending',
            ]);

            // Actually send the email via EmailSendService
            if ($conversation && $emailAccount && $conversation->channel === 'email') {
                try {
                    $sendService = app(\App\Services\Email\EmailSendService::class);
                    // sendReply() expects (Message $replyMessage, EmailAccount $account)
                    $sendService->sendReply($message, $emailAccount);
                    $message->update(['delivery_status' => 'sent']);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('AI draft send failed', [
                        'message_id' => $message->id,
                        'error' => $e->getMessage(),
                    ]);
                    $message->update(['delivery_status' => 'failed']);
                    session()->flash('error', 'Failed to send email: ' . $e->getMessage());
                    return;
                }
            } else {
                // Non-email channel or no email account — mark as sent (handled by channel service)
                $message->update(['delivery_status' => 'sent']);
            }

            if ($conversation) {
                $conversation->update([
                    'last_message_at' => now(),
                    'first_response_at' => $conversation->first_response_at ?? now(),
                ]);
                $conversation->increment('messages_count');
                $conversation->increment('ai_replies_count');
            }

            $this->showAiDraft = false;
            $this->aiDraft = '';
            $this->aiDraftMessageId = null;
            $this->aiConfidence = null;
            $this->dispatch('conversations-updated');
            session()->flash('success', 'AI draft approved and email sent.');
        });
    }

    public function rejectAiDraft(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (!$this->aiDraftMessageId) return;

        Message::where('id', $this->aiDraftMessageId)
            ->where('workspace_id', auth()->user()->active_workspace_id)
            ->update(['ai_status' => 'rejected']);

        $this->showAiDraft = false;
        $this->aiDraft = '';
        $this->aiDraftMessageId = null;
        $this->aiConfidence = null;
        session()->flash('success', 'AI draft rejected.');
    }

    public function editAiDraft(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        // Emit event to the reply composer with the draft content
        $this->dispatch('load-ai-draft-to-composer', body: $this->aiDraft);

        if ($this->aiDraftMessageId) {
            Message::where('id', $this->aiDraftMessageId)
                ->where('workspace_id', auth()->user()->active_workspace_id)
                ->update(['ai_status' => 'edited']);
        }

        $this->showAiDraft = false;
        $this->aiDraft = '';
        $this->aiDraftMessageId = null;
    }

    public function regenerateAiDraft(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        if ($this->aiDraftMessageId) {
            Message::where('id', $this->aiDraftMessageId)
                ->where('workspace_id', auth()->user()->active_workspace_id)
                ->update(['ai_status' => 'rejected']);
        }

        $this->showAiDraft = false;
        $this->aiDraft = '';
        $this->aiDraftMessageId = null;

        // Trigger AI suggestion
        $this->suggestAiReply();
    }

    public function suggestAiReply(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $conversation = $this->getConversation();
        if (!$conversation) return;

        $this->aiLoading = true;
        $this->showAiDraft = false;
        $this->aiDraft = '';

        $lastMessage = $conversation->messages()
            ->where('direction', 'inbound')
            ->latest()
            ->first();

        if (!$lastMessage) {
            $this->aiLoading = false;
            return;
        }

        try {
            $workspace = $conversation->workspace;
            $aiManager = app(AIManager::class);

            $history = $conversation->messages()
                ->where('type', '!=', 'ai_draft')
                ->orderBy('created_at')
                ->limit(10)
                ->get()
                ->map(fn ($m) => [
                    'role' => $m->direction === 'inbound' ? 'user' : 'assistant',
                    'content' => $m->body_text ?? strip_tags($m->body_html ?? ''),
                ])->toArray();

            $response = $aiManager->generateReply($workspace, $lastMessage->body_text ?? strip_tags($lastMessage->body_html ?? ''), [
                'conversation_history' => $history,
                'sender_name' => $conversation->contact?->full_name,
                'subject' => $conversation->subject,
                'agent_name' => auth()->user()->name,
            ]);

            // Create a draft message
            $draftMessage = Message::create([
                'conversation_id' => $conversation->id,
                'workspace_id' => $conversation->workspace_id,
                'direction' => 'outbound',
                'sender_type' => 'ai',
                'sender_id' => auth()->id(),
                'type' => 'ai_draft',
                'body_text' => $response->content,
                'body_html' => nl2br(e($response->content)),
                'from_name' => auth()->user()->name,
                'ai_confidence' => $response->confidence,
                'ai_model' => $response->model,
                'ai_provider' => $response->provider,
                'ai_tokens_in' => $response->tokens_in,
                'ai_tokens_out' => $response->tokens_out,
                'ai_cost' => $response->cost,
                'ai_response_time_ms' => $response->response_time_ms,
                'ai_sources_used' => $response->sources_used,
                'ai_status' => 'draft',
            ]);

            $this->aiDraft = $response->content;
            $this->aiDraftMessageId = $draftMessage->id;
            $this->aiConfidence = $response->confidence;
            $this->showAiDraft = true;
        } catch (\Exception $e) {
            session()->flash('error', 'AI generation failed: ' . $e->getMessage());
        }

        $this->aiLoading = false;
    }

    /**
     * AI Recap — generate a 2-line context summary for the current
     * conversation so an agent can pick up a long thread instantly.
     *
     * Reuses AIManager::generateCompose() with a custom system prompt;
     * no new tables, no new model attributes, no new routes.
     *
     * Cost control: cached for 30 minutes keyed by conversation id +
     * message count, so re-clicking the button on an unchanged thread
     * hits the cache and costs $0. The cache key changes the moment a
     * new message arrives, which auto-invalidates the stale summary.
     */
    public function summarizeThread(): void
    {
        if (!$this->authorizeWorkspaceAction('view')) return;
        if (!$this->conversationId) return;

        $conversation = $this->getConversation();
        if (!$conversation) return;

        $this->summarizingThread = true;
        $this->aiSummary = null;

        try {
            // Pull oldest-first, skip AI drafts/system events, cap at 50.
            $messages = $conversation->messages()
                ->where('type', '!=', 'ai_draft')
                ->where('type', '!=', 'system_event')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit(50)
                ->get(['direction', 'sender_type', 'body_text', 'body_html', 'from_name', 'created_at']);

            if ($messages->isEmpty()) {
                $this->aiSummary = __('No messages in this thread yet.');
                $this->summarizingThread = false;
                return;
            }

            $messageCount = $messages->count();
            $cacheKey = "conv-summary:{$this->conversationId}:{$messageCount}";

            $this->aiSummary = cache()->remember($cacheKey, now()->addMinutes(30), function () use ($conversation, $messages) {
                $transcript = $messages->map(function ($m) {
                    $who = $m->direction === 'inbound'
                        ? 'CUSTOMER (' . ($m->from_name ?: 'unknown') . ')'
                        : 'AGENT';
                    $body = trim(strip_tags($m->body_text ?: $m->body_html ?: ''));
                    // Trim very long messages so the prompt stays cost-efficient
                    if (mb_strlen($body) > 800) {
                        $body = mb_substr($body, 0, 800) . '…';
                    }
                    return "{$who} [{$m->created_at->format('M j, H:i')}]: {$body}";
                })->implode("\n\n");

                $totalMsgs = $messages->count();
                $inbound = $messages->where('direction', 'inbound')->count();
                $outbound = $totalMsgs - $inbound;

                $prompt = "You are summarizing the ENTIRE multi-message support thread below "
                    . "for an agent who is picking it up cold. The thread contains "
                    . "{$totalMsgs} messages ({$inbound} from the customer, {$outbound} from agents). "
                    . "Read EVERY message — do NOT just summarize the last one. Track how the "
                    . "issue evolved across the whole thread.\n\n"
                    . "Output EXACTLY 2 short lines, plain text, no Markdown, no bullets, no quotes:\n"
                    . "Line 1 — What the customer wants / the core issue across the whole thread (1 sentence).\n"
                    . "Line 2 — Current status: what has been done so far + what the agent should do next (1 sentence).\n\n"
                    . "===== THREAD ({$totalMsgs} messages) START =====\n"
                    . "{$transcript}\n"
                    . "===== THREAD END =====\n\n"
                    . "Two-line summary:";

                $response = app(AIManager::class)->generateCompose(
                    $conversation->workspace,
                    $prompt,
                    'professional',
                    [
                        'subject' => $conversation->subject,
                        'sender_name' => 'AI Recap',
                    ]
                );

                $clean = trim(strip_tags($response->content));
                // Some providers wrap the answer in quotes — strip them.
                $clean = trim($clean, "\"'`\u{201C}\u{201D}\u{2018}\u{2019}");
                return $clean !== '' ? $clean : __('AI could not summarize this thread.');
            });
        } catch (\Throwable $e) {
            \Log::warning('summarizeThread failed', [
                'conversation_id' => $this->conversationId,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', __('AI Recap failed: ') . $e->getMessage());
            $this->aiSummary = null;
        }

        $this->summarizingThread = false;
    }

    public function dismissAiSummary(): void
    {
        $this->aiSummary = null;
    }

    public function assignTo(?int $userId): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (!$this->conversationId) return;

        Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $this->conversationId)
            ->update(['assigned_to' => $userId]);

        $this->showAssignDropdown = false;
        $this->dispatch('conversations-updated');
        session()->flash('success', $userId ? 'Conversation assigned.' : 'Conversation unassigned.');
    }

    public function addTag(int $tagId): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $conversation = $this->getConversation();
        if (!$conversation) return;

        // Verify tag belongs to workspace
        $wsId = auth()->user()->active_workspace_id;
        $tagExists = Tag::where('workspace_id', $wsId)->where('id', $tagId)->exists();
        if (!$tagExists) return;

        $conversation->tagModels()->syncWithoutDetaching([$tagId]);
        $this->showTagDropdown = false;
        session()->flash('success', 'Tag added.');
    }

    public function removeTag(int $tagId): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $conversation = $this->getConversation();
        if (!$conversation) return;

        $conversation->tagModels()->detach($tagId);
        session()->flash('success', 'Tag removed.');
    }

    public function setPriority(string $priority): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (!$this->conversationId) return;

        $allowed = ['low', 'normal', 'high', 'urgent'];
        if (!in_array($priority, $allowed)) return;

        Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $this->conversationId)
            ->update(['priority' => $priority]);

        $this->showPriorityDropdown = false;
        session()->flash('success', 'Priority updated to ' . ucfirst($priority) . '.');
    }

    public function toggleStar(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $conversation = $this->getConversation();
        if (!$conversation) return;

        $conversation->update(['is_starred' => !$conversation->is_starred]);
        $this->dispatch('conversations-updated');
    }

    public function snooze(string $duration): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (!$this->conversationId) return;

        $snoozedUntil = match ($duration) {
            '1h' => now()->addHour(),
            '4h' => now()->addHours(4),
            'tomorrow' => now()->addDay()->startOfDay()->addHours(9),
            'next_week' => now()->addWeek()->startOfWeek()->addHours(9),
            default => now()->addHour(),
        };

        Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $this->conversationId)
            ->update([
                'status' => 'snoozed',
                'snoozed_until' => $snoozedUntil,
            ]);

        $this->showSnoozeOptions = false;
        $this->dispatch('conversations-updated');
        session()->flash('success', 'Conversation snoozed.');
    }

    public function snoozeConversation(string $duration): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $until = match($duration) {
            '1h' => now()->addHour(),
            '3h' => now()->addHours(3),
            'tomorrow' => now()->addDay()->setTime(9, 0),
            'next_week' => now()->next('Monday')->setTime(9, 0),
            default => now()->addHour(),
        };

        Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $this->conversationId)
            ->update(['snoozed_until' => $until, 'status' => 'snoozed']);

        session()->flash('success', 'Conversation snoozed until ' . $until->format('M j, g:i A'));
        $this->dispatch('conversation-updated');
    }

    public function unsnooze(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $this->conversationId)
            ->update(['snoozed_until' => null, 'status' => 'open']);

        session()->flash('success', 'Conversation unsnoozed.');
        $this->dispatch('conversation-updated');
    }

    public function closeConversation(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (!$this->conversationId) return;

        Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $this->conversationId)
            ->update([
                'status' => 'closed',
                'resolved_at' => now(),
            ]);

        $this->showMoreMenu = false;
        $this->dispatch('conversations-updated');
        session()->flash('success', 'Conversation closed.');
    }

    public function reopenConversation(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (!$this->conversationId) return;

        Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $this->conversationId)
            ->update([
                'status' => 'open',
                'resolved_at' => null,
            ]);

        $this->showMoreMenu = false;
        $this->dispatch('conversations-updated');
        session()->flash('success', 'Conversation reopened.');
    }

    public function moveToSpam(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (!$this->conversationId) return;

        Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $this->conversationId)
            ->update(['status' => 'spam']);

        $this->showMoreMenu = false;
        $this->conversationId = null;
        $this->dispatch('conversations-updated');
        session()->flash('success', 'Conversation moved to spam.');
    }

    public function moveToTrash(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (!$this->conversationId) return;

        Conversation::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $this->conversationId)
            ->delete(); // soft delete

        $this->showMoreMenu = false;
        $this->conversationId = null;
        $this->dispatch('conversations-updated');
        session()->flash('success', 'Conversation moved to trash.');
    }

    protected function resetDropdowns(): void
    {
        $this->showAssignDropdown = false;
        $this->showTagDropdown = false;
        $this->showPriorityDropdown = false;
        $this->showMoreMenu = false;
        $this->showSnoozeOptions = false;
    }

    /**
     * Load Stripe customer data for the contact's email when Stripe integration is connected.
     * Cached for 5 minutes to avoid hammering the Stripe API on every poll cycle.
     */
    protected function getStripeCustomerData(): ?array
    {
        if (! $this->conversationId) {
            return null;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        // Quick check: is Stripe integration active for this workspace?
        $stripeActive = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'stripe')
            ->where('status', 'active')
            ->exists();

        if (! $stripeActive) {
            return null;
        }

        $conversation = $this->getConversation();
        $email = $conversation?->contact?->email;
        if (! $email) {
            return null;
        }

        // Cache per-email to avoid repeated API calls during polling
        $cacheKey = "stripe_profile:{$workspaceId}:{$email}";

        return cache()->remember($cacheKey, 300, function () use ($workspaceId, $email) {
            try {
                $stripeService = app(StripeIntegrationService::class);
                return $stripeService->getCustomerProfile($workspaceId, $email);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('ConversationDetail: Failed to load Stripe data', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    #[On('realtime-refresh')]
    public function pollMessages(): void
    {
        // Render resolves the authorized conversation and current messages.
    }

    public function render()
    {
        $conversation = null;
        $messages = collect();
        $tags = collect();
        $teamMembers = collect();
        $activeViewers = collect();
        $stripeCustomer = null;

        if ($this->conversationId) {
            $conversation = $this->getConversation();

            if ($conversation) {
                // Limit to latest N messages to prevent OOM on long threads
                // Oldest messages beyond this limit can be loaded via "load more"
                // Grab the latest N by id/created_at (efficient LIMIT in SQL),
                // then explicitly sort ascending in PHP so the view always
                // renders oldest-first (chronological). `id` is the tiebreaker
                // for messages with identical created_at timestamps.
                $messages = $conversation->messages()
                    ->with(['sender', 'attachments'])
                    ->where(function ($q) {
                        $q->where('type', '!=', 'ai_draft')
                            ->orWhere(function ($sub) {
                                $sub->where('type', 'ai_draft')
                                    ->where('ai_status', 'draft');
                            });
                    })
                    ->orderBy('created_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->limit(self::MAX_MESSAGES)
                    ->get()
                    ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                    ->values();
            }

            $workspaceId = auth()->user()->active_workspace_id;
            $tags = Tag::where('workspace_id', $workspaceId)->select(['id', 'name', 'color'])->orderBy('name')->get();
            $teamMembers = User::whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId))->select(['id', 'name', 'email'])->get();
            $activeViewers = $this->getActiveViewers();
            $stripeCustomer = $this->getStripeCustomerData();
        }

        return view('livewire.inbox.conversation-detail', [
            'conversation' => $conversation,
            'messages' => $messages,
            'tags' => $tags,
            'teamMembers' => $teamMembers,
            'activeViewers' => $activeViewers,
            'stripeCustomer' => $stripeCustomer,
        ]);
    }
}
