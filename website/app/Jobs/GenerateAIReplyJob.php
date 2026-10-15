<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Models\Message;
use App\Models\UsageRecord;
use App\Models\Workspace;
use App\Helpers\HtmlSanitizer;
use App\Services\AI\AIManager;
use App\Services\AI\AiConfigResolver;
use App\Services\AI\AIReplyFilters;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateAIReplyJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Exponential backoff: 30s, 2min, 5min between retries.
     */
    public array $backoff = [30, 120, 300];

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Unique lock duration in seconds.
     */
    public int $uniqueFor = 300;

    /**
     * The queue this job should be dispatched to.
     */

    public function __construct(
        private readonly Message $message,
        private readonly ?EmailAccount $emailAccount = null,
    ) {}

    /**
     * Unique ID to prevent duplicate AI replies for the same message.
     */
    public function uniqueId(): string
    {
        return 'ai-reply-' . $this->message->id;
        $this->onQueue('ai');
    }

    public function handle(AIManager $aiManager): void
    {
        $message = $this->message;
        $conversation = $message->conversation;

        if (!$conversation) {
            Log::warning('GenerateAIReplyJob: conversation not found', [
                'message_id' => $message->id,
            ]);
            return;
        }

        $workspace = Workspace::with('aiConfig')->find($message->workspace_id);
        if (!$workspace) {
            Log::warning('GenerateAIReplyJob: workspace not found', [
                'workspace_id' => $message->workspace_id,
            ]);
            return;
        }

        // Channel-aware AI config: each channel can have its own enable
        // toggle, prompt, send_mode, escalation, and skip_filters.
        $channel = $conversation->channel ?: 'email';
        $cfg = AiConfigResolver::for($workspace, $channel);

        // 1. Master enable check — channel must be opted in.
        if (!$cfg->enabled()) {
            Log::debug('GenerateAIReplyJob: AI auto-reply disabled for this channel', [
                'message_id' => $message->id,
                'channel' => $channel,
                'workspace_id' => $workspace->id,
            ]);
            return;
        }

        // 2. Reliability filters (no-reply addresses, bounces, auto-replies,
        //    promotional, custom blocklist). Channel-aware: email defaults are
        //    strict, chat-like channels are permissive. Returns a structured
        //    skip decision so admins can see exactly why a message was filtered.
        $filterDecision = AIReplyFilters::decide($message, $channel, $cfg->skipFilters());
        if ($filterDecision['skip']) {
            Log::info('GenerateAIReplyJob: skipped by reliability filter', [
                'message_id' => $message->id,
                'channel' => $channel,
                'reason' => $filterDecision['reason'],
                'from' => $message->from_email,
                'subject' => $message->subject,
            ]);
            return;
        }

        // 3. AI-POWERED secondary classifier — only run for the email channel
        //    where the noise/signal ratio is worst. Other channels already
        //    have a real human on the other end (chat/SMS/WhatsApp users
        //    don't normally send autoresponders mid-conversation).
        if ($channel === 'email') {
            try {
                $fromEmail = strtolower($message->from_email ?? '');
                $fromName = $message->from_name ?? $fromEmail;
                $subject = $message->subject ?? '(no subject)';
                $bodyPreview = Str::limit(strip_tags($message->body_text ?? $message->body_html ?? ''), 500);

                $checkPrompt = "Analyze this email and respond with ONLY one word: REPLY or SKIP.\n\n"
                    . "SKIP if: newsletter, marketing, promo, automated notification, bounce, delivery failure, "
                    . "no-reply, system email, out-of-office, auto-reply, subscription confirmation, "
                    . "order confirmation, receipt, shipping update, or any email that does NOT expect a personal reply.\n\n"
                    . "REPLY only if from a real person expecting a personal response.\n\n"
                    . "From: {$fromName} <{$fromEmail}>\nSubject: {$subject}\nBody: {$bodyPreview}";

                $response = $aiManager->generateCompose($workspace, $checkPrompt, 'professional', []);
                $decision = strtoupper(trim($response->content ?? ''));
                if (!str_contains($decision, 'REPLY')) {
                    Log::info("GenerateAIReplyJob: AI classifier said SKIP ({$decision})", [
                        'message_id' => $message->id, 'from' => $fromEmail, 'subject' => $subject,
                    ]);
                    return;
                }
                Log::info("GenerateAIReplyJob: AI classifier said REPLY", [
                    'message_id' => $message->id, 'from' => $fromEmail,
                ]);
            } catch (\Throwable $e) {
                Log::warning("GenerateAIReplyJob: AI classifier failed, proceeding", ['error' => $e->getMessage()]);
            }
        }

        // FIX-033: Verify email account belongs to the same workspace
        if ($this->emailAccount && $this->emailAccount->workspace_id !== $workspace->id) {
            Log::error('GenerateAIReplyJob: email account workspace mismatch', [
                'message_workspace' => $workspace->id,
                'account_workspace' => $this->emailAccount->workspace_id,
            ]);
            return;
        }

        // Channel-resolved limits (channel-config wins, falls back to global).
        $aiReplyCount = $conversation->ai_replies_count ?? 0;
        $maxReplies = $cfg->maxRepliesPerConversation();
        if ($aiReplyCount >= $maxReplies) {
            Log::info('GenerateAIReplyJob: max AI replies reached', [
                'conversation_id' => $conversation->id,
                'current' => $aiReplyCount,
                'max' => $maxReplies,
            ]);
            return;
        }

        if ($cfg->firstMessageOnly() && $conversation->messages_count > 1) {
            Log::info('GenerateAIReplyJob: first_message_only is set, skipping', [
                'conversation_id' => $conversation->id,
            ]);
            return;
        }

        if ($cfg->skipOwnThreads() && $message->direction === 'outbound') {
            return;
        }

        // Spending cap is workspace-global (billing is not per-channel).
        $monthlyCostLimit = $cfg->monthlyCostLimit();
        if ($monthlyCostLimit > 0) {
            $currentMonth = now()->format('Y-m');
            $monthlySpend = DB::table('ai_usage_logs')
                ->where('workspace_id', $workspace->id)
                ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$currentMonth])
                ->sum('cost');

            if ($monthlySpend >= $monthlyCostLimit) {
                Log::warning("AI spending cap reached for workspace {$workspace->id}: \${$monthlySpend} / \${$monthlyCostLimit}");
                return;
            }
        }


        // Build context from conversation history
        $context = $this->buildContext($message, $conversation);

        try {
            $aiResponse = $aiManager->generateReply($workspace, $message->body_text ?? strip_tags($message->body_html ?? ''), $context);

            // Determine the AI status from the channel-resolved config.
            $sendMode = $cfg->sendMode();
            $confidenceThreshold = $cfg->confidenceThreshold();
            $meetsThreshold = $aiResponse->meetsThreshold($confidenceThreshold);

            // Auto-escalation: if enabled and AI confidence is below the
            // escalation floor, force this reply to "ai_draft" (do NOT send
            // autonomously) AND assign the conversation + tag it for human review.
            $shouldEscalate = $cfg->escalationEnabled()
                && (int) ($aiResponse->confidence ?? 0) < $cfg->escalateBelowConfidence();

            $aiStatus = match (true) {
                $shouldEscalate => 'ai_draft',
                $sendMode === 'autonomous' && $meetsThreshold => 'approved',
                $sendMode === 'suggestions' => 'suggestion',
                default => 'ai_draft',
            };

            // Resolve the email account: prefer the explicitly provided one,
            // then fall back to the conversation's linked account.
            $resolvedAccount = $this->emailAccount ?? $conversation->emailAccount;

            // Create the AI reply message
            $replyMessage = Message::create([
                'uuid' => Str::uuid(),
                'conversation_id' => $conversation->id,
                'workspace_id' => $workspace->id,
                'direction' => 'outbound',
                'sender_type' => 'ai',
                'type' => 'ai_draft',
                'body_html' => HtmlSanitizer::sanitize($aiResponse->content),
                'body_text' => strip_tags($aiResponse->content),
                'subject' => $conversation->subject ? "Re: {$conversation->subject}" : null,
                'from_email' => $resolvedAccount?->email ?? null,
                'to_emails' => $message->from_email ? [$message->from_email] : [],
                'ai_confidence' => $aiResponse->confidence,
                'ai_model' => $aiResponse->model,
                'ai_provider' => $aiResponse->provider,
                'ai_tokens_in' => $aiResponse->tokens_in,
                'ai_tokens_out' => $aiResponse->tokens_out,
                'ai_cost' => $aiResponse->cost,
                'ai_response_time_ms' => $aiResponse->response_time_ms,
                'ai_sources_used' => $aiResponse->sources_used,
                'ai_status' => $aiStatus,
            ]);

            // Update conversation counters
            $conversation->increment('ai_replies_count');
            $conversation->update([
                'is_ai_handled' => true,
                'last_message_at' => now(),
            ]);

            // Track AI reply usage for plan limit enforcement
            UsageRecord::incrementUsage($workspace->id, 'ai_replies');

            // Auto-escalate to a human when confidence is below the threshold:
            //   1. Assign the conversation (specific user OR round-robin across
            //      workspace members marked available_for_assignment).
            //   2. Tag it with the configured escalation tag (default: needs_human).
            //   3. Notify the assignee via in-app notification.
            // The reply remains as ai_draft so the human can review/edit before sending.
            if ($shouldEscalate) {
                $this->escalateToHuman($conversation, $workspace, $cfg, $aiResponse->confidence);
            }

            // If autonomous mode and meets threshold, dispatch send job
            if ($aiStatus === 'approved') {
                $delay = $this->resolveReplyDelay($cfg->replyDelay());

                if ($delay > 0) {
                    SendAIReplyJob::dispatch($replyMessage)->delay(now()->addSeconds($delay));
                } else {
                    SendAIReplyJob::dispatch($replyMessage);
                }

                Log::info('AI reply approved for autonomous send', [
                    'message_id' => $replyMessage->id,
                    'confidence' => $aiResponse->confidence,
                    'delay_seconds' => $delay,
                ]);
            } else {
                Log::info('AI reply created as draft', [
                    'message_id' => $replyMessage->id,
                    'status' => $aiStatus,
                    'confidence' => $aiResponse->confidence,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('GenerateAIReplyJob failed', [
                'message_id' => $message->id,
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Let the queue retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('GenerateAIReplyJob permanently failed', [
            'message_id' => $this->message->id,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Build context array from conversation history for the AI provider.
     */
    private function buildContext(Message $message, $conversation): array
    {
        $context = [
            'sender_name' => $message->from_name ?? '',
            'subject' => $conversation->subject ?? '',
        ];

        // Load recent conversation messages for context (last 10)
        $recentMessages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->where('id', '!=', $message->id)
            ->limit(10)
            ->get()
            ->reverse();

        $history = [];
        foreach ($recentMessages as $msg) {
            $role = $msg->direction === 'inbound' ? 'user' : 'assistant';
            $content = $msg->body_text ?? strip_tags($msg->body_html ?? '');
            if ($content) {
                $history[] = ['role' => $role, 'content' => $content];
            }
        }

        $context['conversation_history'] = $history;

        // Add agent name if assigned — use already loaded relation or load efficiently
        if ($conversation->assigned_to) {
            $conversation->loadMissing('assignedTo');
            if ($conversation->assignedTo) {
                $context['agent_name'] = $conversation->assignedTo->name ?? '';
            }
        }

        return $context;
    }

    /**
     * Auto-escalate a conversation when AI confidence is below the
     * configured threshold:
     *   1. Pick the assignee (config.escalation_assignee_id, else round-robin
     *      across active workspace members marked available_for_assignment).
     *   2. Update conversation.assigned_to and bump priority to "high".
     *   3. Attach the escalation tag (workspace-scoped firstOrCreate).
     *   4. Send an in-app notification to the assignee with a deep link
     *      to the conversation.
     *
     * Idempotent: if already assigned + tagged, skips re-assignment but
     * still sends a notification so the human knows it's a fresh low-confidence event.
     */
    private function escalateToHuman(
        \App\Models\Conversation $conversation,
        \App\Models\Workspace $workspace,
        \App\Services\AI\ResolvedAiConfig $cfg,
        ?int $confidence,
    ): void {
        $assigneeId = $cfg->escalationAssigneeId();

        // Round-robin fallback: pick a random active member who has
        // available_for_assignment enabled.
        if (!$assigneeId) {
            $candidate = $workspace->members()
                ->wherePivot('status', 'active')
                ->wherePivot('available_for_assignment', true)
                ->inRandomOrder()
                ->first();

            $assigneeId = $candidate?->id;
        }

        // Verify the chosen assignee actually belongs to this workspace
        $assignee = $assigneeId
            ? $workspace->members()->where('users.id', $assigneeId)->first()
            : null;

        if (!$assignee) {
            Log::warning('AI escalation: no assignee available — tag-only escalation', [
                'conversation_id' => $conversation->id,
                'workspace_id' => $workspace->id,
            ]);
        }

        // Assign + bump priority (only if not already assigned to skip churn)
        $updates = [];
        if ($assignee && !$conversation->assigned_to) {
            $updates['assigned_to'] = $assignee->id;
        }
        if ($conversation->priority !== 'high' && $conversation->priority !== 'urgent') {
            $updates['priority'] = 'high';
        }
        if (!empty($updates)) {
            $conversation->update($updates);
        }

        // Attach tag (workspace-scoped firstOrCreate so it always exists)
        $tagName = $cfg->escalationTag() ?: 'needs_human';
        try {
            $tag = \App\Models\Tag::firstOrCreate([
                'workspace_id' => $workspace->id,
                'name' => $tagName,
            ], [
                'color' => '#f59e0b',
            ]);

            if (method_exists($conversation, 'tagModels')) {
                $conversation->tagModels()->syncWithoutDetaching([$tag->id]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI escalation: tag attach failed', [
                'conversation_id' => $conversation->id,
                'tag' => $tagName,
                'error' => $e->getMessage(),
            ]);
        }

        // Notify the assignee via in-app notification
        if ($assignee) {
            try {
                $subjectPreview = $conversation->subject
                    ?: ('Conversation #' . $conversation->id);

                $assignee->notify(new \App\Notifications\InAppNotification(
                    title: 'AI escalated to you — needs human review',
                    body: "Low AI confidence ({$confidence}%) on: " . Str::limit($subjectPreview, 80),
                    actionUrl: url('/inbox?conversation=' . $conversation->id),
                    icon: 'alert',
                    type: 'ai_escalation',
                ));
            } catch (\Throwable $e) {
                Log::warning('AI escalation: in-app notification failed', [
                    'user_id' => $assignee->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('AI escalation completed', [
            'conversation_id' => $conversation->id,
            'assignee_id' => $assignee?->id,
            'tag' => $tagName,
            'confidence' => $confidence,
            'threshold' => $cfg->escalateBelowConfidence(),
        ]);
    }

    /**
     * Resolve the reply_delay setting to seconds.
     */
    private function resolveReplyDelay(string $delay): int
    {
        return match ($delay) {
            '30s' => 30,
            '1m' => 60,
            '2m' => 120,
            '5m' => 300,
            'random' => random_int(30, 180),
            default => 0,
        };
    }
}
