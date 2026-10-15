<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Models\Message;
use App\Models\UsageRecord;
use App\Models\Workspace;
use App\Helpers\HtmlSanitizer;
use App\Services\AI\AIManager;
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

        // ── SPAM PROTECTION: Quick filter for obvious no-reply addresses ──
        $fromEmail = strtolower($message->from_email ?? '');
        $obviousSkip = ['noreply', 'no-reply', 'mailer-daemon', 'postmaster@', 'bounce', 'nobody@', 'daemon@'];
        foreach ($obviousSkip as $p) {
            if (str_contains($fromEmail, $p)) return;
        }

        // ── AI-POWERED CHECK: Let AI decide if this email deserves a reply ──
        try {
            $fromName = $message->from_name ?? $fromEmail;
            $subject = $message->subject ?? '(no subject)';
            $bodyPreview = Str::limit(strip_tags($message->body_text ?? $message->body_html ?? ''), 500);

            $checkPrompt = "Analyze this email and respond with ONLY one word: REPLY or SKIP.\n\n"
                . "SKIP if: newsletter, marketing, promo, automated notification, bounce, delivery failure, "
                . "no-reply, system email, out-of-office, auto-reply, subscription confirmation, "
                . "order confirmation, receipt, shipping update, or any email that does NOT expect a personal reply.\n\n"
                . "REPLY only if from a real person expecting a personal response.\n\n"
                . "From: {$fromName} <{$fromEmail}>\nSubject: {$subject}\nBody: {$bodyPreview}";

            $workspace = Workspace::with('aiConfig')->find($message->workspace_id);
            if ($workspace) {
                $response = $aiManager->generateCompose($workspace, $checkPrompt, 'professional', []);
                $decision = strtoupper(trim($response->content ?? ''));
                // Default to SKIP unless AI explicitly says REPLY — safe by default
                if (!str_contains($decision, 'REPLY')) {
                    Log::info("GenerateAIReplyJob: AI classified as SKIP ({$decision})", [
                        'message_id' => $message->id, 'from' => $fromEmail, 'subject' => $subject,
                    ]);
                    return;
                }
                Log::info("GenerateAIReplyJob: AI classified as REPLY — proceeding", [
                    'message_id' => $message->id, 'from' => $fromEmail,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("GenerateAIReplyJob: AI classification failed, proceeding", ['error' => $e->getMessage()]);
        }

        $workspace = Workspace::with('aiConfig')->find($message->workspace_id);
        if (!$workspace) {
            Log::warning('GenerateAIReplyJob: workspace not found', [
                'workspace_id' => $message->workspace_id,
            ]);
            return;
        }

        // FIX-033: Verify email account belongs to the same workspace
        if ($this->emailAccount && $this->emailAccount->workspace_id !== $workspace->id) {
            Log::error('GenerateAIReplyJob: email account workspace mismatch', [
                'message_workspace' => $workspace->id,
                'account_workspace' => $this->emailAccount->workspace_id,
            ]);
            return;
        }

        $aiConfig = $workspace->aiConfig;

        // Check if AI replies are allowed for this conversation
        if ($aiConfig) {
            // Respect max AI replies per conversation limit
            $aiReplyCount = $conversation->ai_replies_count ?? 0;
            $maxReplies = $aiConfig->max_ai_replies_per_conversation ?? 3;
            if ($aiReplyCount >= $maxReplies) {
                Log::info('GenerateAIReplyJob: max AI replies reached', [
                    'conversation_id' => $conversation->id,
                    'current' => $aiReplyCount,
                    'max' => $maxReplies,
                ]);
                return;
            }

            // Respect first_message_only setting
            if ($aiConfig->first_message_only && $conversation->messages_count > 1) {
                Log::info('GenerateAIReplyJob: first_message_only is set, skipping', [
                    'conversation_id' => $conversation->id,
                ]);
                return;
            }

            // Respect skip_own_threads
            if ($aiConfig->skip_own_threads && $message->direction === 'outbound') {
                return;
            }
        }

        // Check AI spending cap before generating
        if ($aiConfig && $aiConfig->monthly_cost_limit > 0) {
            $currentMonth = now()->format('Y-m');
            $monthlySpend = DB::table('ai_usage_logs')
                ->where('workspace_id', $workspace->id)
                ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$currentMonth])
                ->sum('cost');

            if ($monthlySpend >= $aiConfig->monthly_cost_limit) {
                Log::warning("AI spending cap reached for workspace {$workspace->id}: \${$monthlySpend} / \${$aiConfig->monthly_cost_limit}");
                return;
            }
        }

        // Build context from conversation history
        $context = $this->buildContext($message, $conversation);

        try {
            $aiResponse = $aiManager->generateReply($workspace, $message->body_text ?? strip_tags($message->body_html ?? ''), $context);

            // Determine the AI status based on confidence and send mode
            $sendMode = $aiConfig?->send_mode ?? 'approval';
            $confidenceThreshold = $aiConfig?->confidence_threshold ?? 75;
            $meetsThreshold = $aiResponse->meetsThreshold($confidenceThreshold);

            $aiStatus = match (true) {
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

            // If autonomous mode and meets threshold, dispatch send job
            if ($aiStatus === 'approved') {
                $delay = $this->resolveReplyDelay($aiConfig?->reply_delay ?? 'none');

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
