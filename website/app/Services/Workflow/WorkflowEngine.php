<?php

namespace App\Services\Workflow;

use App\Jobs\WakeUpWorkflowJob;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Tag;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowExecution;
use App\Models\WorkflowNode;
use App\Models\WorkflowStepLog;
use App\Services\Email\EmailSendService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WorkflowEngine
{
    /**
     * Track visited node IDs during a single execution to detect A->B->A loops.
     * Reset at the start of each execute() or resume() call.
     */
    private array $visitedNodes = [];

    /**
     * FIX-059: Processing stack to detect cycles during recursive traversal.
     * Nodes are pushed before processing and popped after. If a node is already
     * on the stack when we try to process it, we have a cycle (A->B->A).
     */
    private array $processingStack = [];

    /**
     * FIX-063: Execution start timestamp (microtime) for timeout enforcement.
     * Set at the beginning of execute()/resume(). Before each node is processed,
     * elapsed time is checked against the 55-second ceiling.
     */
    private float $executionStartTime = 0.0;

    /**
     * Pre-loaded workflow edges for the current execution to avoid N+1 queries.
     * Populated once at the start of execute()/resume(), then used by getNextNodes().
     */
    protected \Illuminate\Support\Collection $edges;

    /**
     * Maximum traversal depth per execution. Prevents runaway recursive graphs.
     * Reads from config('mailtrixy.workflow.max_depth') or falls back to 200.
     */
    protected int $maxDepth = 200;

    public function __construct(
        private readonly EmailSendService $emailSendService,
    ) {
        $this->edges = collect();
        $this->maxDepth = (int) (config('mailtrixy.workflow.max_depth') ?? 200);
    }

    /**
     * Execute a workflow from its trigger node.
     *
     * @param Workflow     $workflow    The workflow to execute.
     * @param Contact|null $contact     The contact that triggered the workflow (if any).
     * @param array        $triggerData Contextual data from the trigger event.
     *
     * @return WorkflowExecution The created execution record.
     */
    public function execute(Workflow $workflow, ?Contact $contact, array $triggerData = []): WorkflowExecution
    {
        if (!$workflow->isActive()) {
            throw new \RuntimeException(
                "Workflow {$workflow->id} is not active (status: {$workflow->status})."
            );
        }

        // Reset visited nodes and processing stack for this execution run
        $this->visitedNodes = [];
        $this->processingStack = [];

        // FIX-063: Record execution start time for timeout enforcement
        $this->executionStartTime = microtime(true);

        // Preload the entire edge graph once to avoid N+1 queries in getNextNodes()
        $this->edges = $workflow->workflowEdges()->get();

        // Create the execution record
        $execution = WorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'contact_id' => $contact?->id,
            'status' => 'running',
            'trigger_data' => $triggerData,
            'started_at' => now(),
        ]);

        // Increment workflow execution count
        $workflow->increment('executions_count');

        Log::info('Workflow execution started', [
            'execution_id' => $execution->id,
            'workflow_id' => $workflow->id,
            'contact_id' => $contact?->id,
        ]);

        // Find the trigger node (entry point)
        $triggerNode = $workflow->workflowNodes()
            ->where('type', 'trigger')
            ->first();

        if (!$triggerNode) {
            $this->completeExecution($execution, 'failed', 'No trigger node found in workflow.');
            return $execution;
        }

        // Start processing from the trigger node
        try {
            $this->processFromNode($triggerNode, $execution, $triggerData);
        } catch (\Throwable $e) {
            Log::error('Workflow execution failed', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);

            $this->completeExecution($execution, 'failed', $e->getMessage());
        }

        return $execution;
    }

    /**
     * Resume a paused workflow execution from a specific node.
     */
    public function resume(WorkflowExecution $execution, WorkflowNode $currentNode, array $data = []): void
    {
        $executionId = $execution->id;

        // Lock the execution row to prevent concurrent resume attempts
        $execution = WorkflowExecution::where('id', $executionId)
            ->whereIn('status', ['waiting', 'running'])
            ->lockForUpdate()
            ->first();

        if (!$execution) {
            Log::warning('Cannot resume execution — not found or not in resumable state', [
                'execution_id' => $executionId,
            ]);
            return;
        }

        $execution->update(['status' => 'running']);

        // Reset visited nodes and processing stack for this resume run
        $this->visitedNodes = [];
        $this->processingStack = [];

        // FIX-063: Record execution start time for timeout enforcement
        $this->executionStartTime = microtime(true);

        // Preload edge graph for this workflow
        $this->edges = WorkflowEdge::where('workflow_id', $execution->workflow_id)->get();

        try {
            // Get the next nodes after the current wait/delay node
            $nextNodes = $this->getNextNodes($currentNode, $execution->workflow_id);

            foreach ($nextNodes as $nextNode) {
                $this->processFromNode($nextNode, $execution, $data);
            }
        } catch (\Throwable $e) {
            Log::error('Workflow resume failed', [
                'execution_id' => $execution->id,
                'node_id' => $currentNode->id,
                'error' => $e->getMessage(),
            ]);

            $this->completeExecution($execution, 'failed', $e->getMessage());
        }
    }

    /**
     * Process a node and continue along its edges.
     */
    private function processFromNode(
        WorkflowNode $node,
        WorkflowExecution $execution,
        array $data,
        int $depth = 0
    ): void {
        // FIX-060: Prevent infinite loops (depth limit) with warning log
        if ($depth > $this->maxDepth) {
            Log::warning('Workflow execution exceeded maximum depth', [
                'execution_id' => $execution->id,
                'workflow_id' => $execution->workflow_id,
                'max_depth' => $this->maxDepth,
                'node_id' => $node->id,
            ]);
            $this->completeExecution($execution, 'failed', "Maximum workflow depth ({$this->maxDepth}) exceeded at node {$node->id}. The workflow graph may be too deep or contain a long chain.");
            return;
        }

        // FIX-063: Check execution timeout (55s ceiling to stay under 60s job limit)
        $elapsedSeconds = microtime(true) - $this->executionStartTime;
        if ($elapsedSeconds > 55.0) {
            Log::warning('Workflow execution approaching timeout, saving state for resume', [
                'execution_id' => $execution->id,
                'workflow_id' => $execution->workflow_id,
                'elapsed_seconds' => round($elapsedSeconds, 2),
                'paused_at_node' => $node->id,
            ]);
            $execution->update(['status' => 'waiting']);

            // Schedule immediate resume to continue from this node
            WakeUpWorkflowJob::dispatch($execution->id, $node->id)
                ->delay(now()->addSeconds(5))
                ->onQueue('workflows');

            return;
        }

        // FIX-059: Stack-based cycle detection — catches A->B->A within the current path.
        // The processing stack tracks the CURRENT traversal path, not all visited nodes.
        // A node can appear in different branches (visited), but not in the same path (stack).
        if (in_array($node->id, $this->processingStack)) {
            Log::warning('Workflow cycle detected — node is in current processing path', [
                'node_id' => $node->id,
                'workflow_id' => $execution->workflow_id,
                'execution_id' => $execution->id,
                'processing_stack' => $this->processingStack,
            ]);
            $this->completeExecution($execution, 'failed', "Cycle detected: node {$node->id} is already in the current processing path.");
            return;
        }

        // Flat visited check — prevents re-processing the same node across branches
        if (in_array($node->id, $this->visitedNodes)) {
            Log::warning('Workflow loop detected — node already visited in this execution run', [
                'node_id' => $node->id,
                'workflow_id' => $execution->workflow_id,
                'execution_id' => $execution->id,
                'visited' => $this->visitedNodes,
            ]);
            return;
        }

        // Persistent loop detection: check if this node was already successfully processed
        // in this execution (covers resumed executions where in-memory state is lost)
        $alreadyProcessed = WorkflowStepLog::where('execution_id', $execution->id)
            ->where('node_id', $node->id)
            ->where('status', 'success')
            ->exists();

        if ($alreadyProcessed) {
            Log::warning('Workflow loop detected — node already processed in prior execution run', [
                'node_id' => $node->id,
                'execution_id' => $execution->id,
            ]);
            $this->completeExecution($execution, 'failed', 'Cyclic path detected: node ' . $node->id . ' already processed.');
            return;
        }

        // FIX-059: Push onto processing stack before processing, pop after
        $this->processingStack[] = $node->id;
        $this->visitedNodes[] = $node->id;

        // Check if execution was cancelled externally
        $execution->refresh();
        if ($execution->status === 'canceled') {
            array_pop($this->processingStack);
            return;
        }

        try {
            // Process the current node
            $result = $this->processNode($node, $execution, $data);

            // If the node paused the execution (wait/delay), stop processing
            if ($result['paused'] ?? false) {
                array_pop($this->processingStack);
                return;
            }

            // Determine next nodes based on result
            $nextEdgeLabel = $result['next_edge'] ?? null;

            $nextNodes = $this->getNextNodes($node, $execution->workflow_id, $nextEdgeLabel);

            if ($nextNodes->isEmpty()) {
                // No more nodes -- check if this is the only active branch
                $this->checkAndCompleteExecution($execution);
                array_pop($this->processingStack);
                return;
            }

            // Process each next node (handles branching)
            foreach ($nextNodes as $nextNode) {
                $this->processFromNode($nextNode, $execution, $result['data'] ?? $data, $depth + 1);
            }
        } finally {
            // FIX-059: Always pop from stack, even if an exception propagates
            array_pop($this->processingStack);
        }
    }

    /**
     * Process a single workflow node based on its type and subtype.
     *
     * @return array{data: array, paused: bool, next_edge: ?string}
     */
    public function processNode(WorkflowNode $node, WorkflowExecution $execution, array $data): array
    {
        $startTime = microtime(true);
        $config = $node->config ?? [];

        try {
            $result = match ($node->type) {
                'trigger' => $this->processTriggerNode($node, $data),
                'condition' => $this->processConditionNode($node, $execution, $data, $config),
                'action' => $this->processActionNode($node, $execution, $data, $config),
                default => ['data' => $data, 'paused' => false, 'next_edge' => null],
            };

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Log the step
            $status = ($result['paused'] ?? false) ? 'waiting' : 'success';
            $this->logStep($execution, $node, $status, $data, $result, $durationMs, $result['resume_at'] ?? null);

            return $result;

        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->logStep($execution, $node, 'failed', $data, [], $durationMs, error: $e->getMessage());

            Log::error('Workflow node processing failed', [
                'execution_id' => $execution->id,
                'node_id' => $node->id,
                'subtype' => $node->subtype,
                'error' => $e->getMessage(),
            ]);

            // Check if retry is configured
            $retries = $config['retries'] ?? 0;
            $existingAttempts = $execution->stepLogs()
                ->where('node_id', $node->id)
                ->where('status', 'failed')
                ->count();

            if ($existingAttempts <= $retries) {
                // Schedule a retry
                WakeUpWorkflowJob::dispatch($execution->id, $node->id)
                    ->delay(now()->addMinutes(($existingAttempts + 1) * 5))
                    ->onQueue('workflows');

                return ['data' => $data, 'paused' => true, 'next_edge' => null];
            }

            // All retries exhausted -- continue on error path if available
            return ['data' => $data, 'paused' => false, 'next_edge' => 'error'];
        }
    }

    /**
     * Process a trigger node (pass-through -- the trigger already matched).
     */
    private function processTriggerNode(WorkflowNode $node, array $data): array
    {
        return ['data' => $data, 'paused' => false, 'next_edge' => null];
    }

    /**
     * Process a condition node.
     */
    private function processConditionNode(
        WorkflowNode $node,
        WorkflowExecution $execution,
        array $data,
        array $config
    ): array {
        return match ($node->subtype) {
            'if_else' => $this->processIfElse($execution, $data, $config),
            'wait_until' => $this->processWaitUntil($execution, $node, $data, $config),
            'ab_split' => $this->processAbSplit($data, $config),
            'in_group' => $this->conditionInGroup($execution, $data, $config),
            default => ['data' => $data, 'paused' => false, 'next_edge' => null],
        };
    }

    /**
     * Evaluate if/else condition rules and return 'yes' or 'no' edge label.
     */
    private function processIfElse(WorkflowExecution $execution, array $data, array $config): array
    {
        $conditions = $config['conditions'] ?? [];
        $matchType = $config['match'] ?? 'all'; // 'all' (AND) or 'any' (OR)

        if (empty($conditions)) {
            return ['data' => $data, 'paused' => false, 'next_edge' => 'yes'];
        }

        $contact = $execution->contact;
        $results = [];

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? '';

            $actualValue = $this->resolveFieldValue($contact, $data, $field);
            $matched = $this->evaluateCondition($actualValue, $operator, $value);
            $results[] = $matched;
        }

        $passed = match ($matchType) {
            'any' => in_array(true, $results, true),
            default => !in_array(false, $results, true), // 'all'
        };

        return [
            'data' => $data,
            'paused' => false,
            'next_edge' => $passed ? 'yes' : 'no',
        ];
    }

    /**
     * Evaluate a single condition.
     *
     * FIX-061: Type-aware comparison instead of naive string cast.
     * - Numeric values: compared as floats
     * - Booleans: handles "true"/"false"/"1"/"0" properly
     * - Strings: strict string comparison (0 !== "")
     */
    private function evaluateCondition(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals', 'is' => $this->typeAwareEquals($actual, $expected),
            'not_equals', 'is_not' => !$this->typeAwareEquals($actual, $expected),
            'contains' => is_string($actual) && str_contains(strtolower($actual), strtolower((string) $expected)),
            'not_contains' => is_string($actual) && !str_contains(strtolower($actual), strtolower((string) $expected)),
            'starts_with' => is_string($actual) && str_starts_with(strtolower($actual), strtolower((string) $expected)),
            'ends_with' => is_string($actual) && str_ends_with(strtolower($actual), strtolower((string) $expected)),
            'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'greater_or_equal' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'less_or_equal' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            'is_empty' => $actual === null || $actual === '' || $actual === [],
            'is_not_empty' => $actual !== null && $actual !== '' && $actual !== [],
            'regex' => is_string($actual) && $this->safeRegexMatch($expected, $actual),
            'in' => in_array($actual, is_array($expected) ? $expected : explode(',', (string) $expected)),
            default => false,
        };
    }

    /**
     * FIX-061: Type-aware equality comparison.
     *
     * Handles the problem where (string) 0 === (string) "" evaluates to true.
     * - Both numeric: compare as floats
     * - Both boolean or boolean-like strings: compare as booleans
     * - Both null: equal
     * - One null, one not: not equal
     * - Otherwise: strict string comparison
     */
    private function typeAwareEquals(mixed $actual, mixed $expected): bool
    {
        // Null handling
        if ($actual === null && $expected === null) {
            return true;
        }
        if ($actual === null || $expected === null) {
            return false;
        }

        // Both are numeric (including numeric strings like "42")
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        // Boolean handling: detect boolean-like values
        $boolMap = ['true' => true, 'false' => false, '1' => true, '0' => false];

        $actualIsBool = is_bool($actual) || (is_string($actual) && isset($boolMap[strtolower($actual)]));
        $expectedIsBool = is_bool($expected) || (is_string($expected) && isset($boolMap[strtolower($expected)]));

        if ($actualIsBool && $expectedIsBool) {
            $actualBool = is_bool($actual) ? $actual : $boolMap[strtolower((string) $actual)];
            $expectedBool = is_bool($expected) ? $expected : $boolMap[strtolower((string) $expected)];
            return $actualBool === $expectedBool;
        }

        // Default: strict string comparison
        return (string) $actual === (string) $expected;
    }

    /**
     * Safely evaluate a regex pattern against a value.
     * Rejects patterns that are too long or syntactically invalid to prevent
     * ReDoS and preg_match failures from user-supplied workflow config.
     *
     * FIX-064: Added pcre.backtrack_limit protection with save/restore,
     * and a temporary set_time_limit ceiling for the regex operation.
     */
    private function safeRegexMatch(string $pattern, string $actual): bool
    {
        // Length limit
        if (strlen($pattern) > 500) {
            Log::warning('Workflow: regex pattern too long, rejecting', [
                'pattern_length' => strlen($pattern),
            ]);
            return false;
        }

        // Block dangerous regex patterns that cause catastrophic backtracking
        // Reject nested quantifiers like (a+)+, (a*)*,  (a{1,})+, etc.
        if (preg_match('/\([^)]*[+*][^)]*\)[+*]/', $pattern) ||
            preg_match('/\([^)]*\{[^}]*\}[^)]*\)[+*]/', $pattern)) {
            Log::warning('Workflow: regex with nested quantifiers rejected (ReDoS risk)', [
                'pattern' => $pattern,
            ]);
            return false;
        }

        // Block backreferences which can amplify backtracking
        if (preg_match('/\\\\[1-9]/', $pattern)) {
            Log::warning('Workflow: regex with backreferences rejected', [
                'pattern' => $pattern,
            ]);
            return false;
        }

        // Validate syntax
        if (@preg_match("/{$pattern}/i", '') === false) {
            Log::warning('Workflow: invalid regex pattern in condition', [
                'pattern' => $pattern,
            ]);
            return false;
        }

        // FIX-064: Save and temporarily reduce pcre.backtrack_limit
        $prevBacktrackLimit = ini_get('pcre.backtrack_limit');
        $prevRecursionLimit = ini_get('pcre.recursion_limit');
        ini_set('pcre.backtrack_limit', '10000');   // 10K instead of default 1M
        ini_set('pcre.recursion_limit', '5000');     // Cap recursion depth too

        // FIX-064: Temporary time limit for the regex operation (5s ceiling).
        // Only effective if safe_mode is off and the process isn't already limited
        // to a shorter window. We restore the original limit after the match.
        $prevTimeLimit = (int) ini_get('max_execution_time');
        if ($prevTimeLimit === 0 || $prevTimeLimit > 5) {
            @set_time_limit(5);
        }

        try {
            $result = @preg_match("/{$pattern}/i", $actual);
        } finally {
            // FIX-064: Always restore original limits
            ini_set('pcre.backtrack_limit', $prevBacktrackLimit);
            ini_set('pcre.recursion_limit', $prevRecursionLimit);
            @set_time_limit($prevTimeLimit);
        }

        if ($result === false) {
            Log::warning('Workflow: regex match failed (possible backtrack/recursion limit)', [
                'pattern' => $pattern,
            ]);
            return false;
        }

        return (bool) $result;
    }

    /**
     * Resolve a field value from contact or trigger data.
     */
    private function resolveFieldValue(?Contact $contact, array $data, string $field): mixed
    {
        // Check trigger data first
        if (isset($data[$field])) {
            return $data[$field];
        }

        if (!$contact) {
            return null;
        }

        // Standard contact fields
        if (in_array($field, ['first_name', 'last_name', 'email', 'phone', 'company', 'job_title', 'city', 'country', 'timezone', 'lead_score', 'status'])) {
            return $contact->{$field};
        }

        // Custom fields
        if (str_starts_with($field, 'custom_fields.')) {
            $key = str_replace('custom_fields.', '', $field);
            return $contact->custom_fields[$key] ?? null;
        }

        // Tag check
        if ($field === 'has_tag') {
            return $contact->tags->pluck('name')->toArray();
        }

        return $contact->{$field} ?? null;
    }

    /**
     * Wait until a condition is met. If not met, schedule a wake-up check.
     */
    private function processWaitUntil(
        WorkflowExecution $execution,
        WorkflowNode $node,
        array $data,
        array $config
    ): array {
        $conditions = $config['conditions'] ?? [];
        $timeout = $config['timeout_hours'] ?? 72; // Default 3 day timeout
        $checkIntervalMinutes = $config['check_interval_minutes'] ?? 30;

        // Evaluate if the condition is already met
        $contact = $execution->contact;
        $allMet = true;

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? '';

            $actualValue = $this->resolveFieldValue($contact, $data, $field);
            if (!$this->evaluateCondition($actualValue, $operator, $value)) {
                $allMet = false;
                break;
            }
        }

        if ($allMet) {
            return ['data' => $data, 'paused' => false, 'next_edge' => 'yes'];
        }

        // Check if timeout has been reached
        $startedAt = $execution->started_at;
        if ($startedAt && Carbon::now()->diffInHours($startedAt) >= $timeout) {
            return ['data' => $data, 'paused' => false, 'next_edge' => 'timeout'];
        }

        // FIX-062: Acquire lock before scheduling re-check to prevent duplicate jobs
        $lockKey = "workflow-wait:{$execution->id}:{$node->id}";
        $lock = Cache::lock($lockKey, $checkIntervalMinutes * 60);

        if (!$lock->get()) {
            Log::info('Workflow wait_until re-check already scheduled, skipping duplicate', [
                'execution_id' => $execution->id,
                'node_id' => $node->id,
            ]);
            return ['data' => $data, 'paused' => true, 'next_edge' => null];
        }

        // Schedule a re-check
        $resumeAt = now()->addMinutes($checkIntervalMinutes);

        WakeUpWorkflowJob::dispatch($execution->id, $node->id)
            ->delay($resumeAt)
            ->onQueue('workflows');

        $execution->update(['status' => 'waiting']);

        return ['data' => $data, 'paused' => true, 'next_edge' => null, 'resume_at' => $resumeAt];
    }

    /**
     * A/B split: randomly route to one of the configured paths.
     */
    private function processAbSplit(array $data, array $config): array
    {
        $splits = $config['splits'] ?? [
            ['label' => 'A', 'percentage' => 50],
            ['label' => 'B', 'percentage' => 50],
        ];

        $random = mt_rand(1, 100);
        $cumulative = 0;
        $selectedLabel = $splits[0]['label'] ?? 'A';

        foreach ($splits as $split) {
            $cumulative += $split['percentage'];
            if ($random <= $cumulative) {
                $selectedLabel = $split['label'];
                break;
            }
        }

        return ['data' => $data, 'paused' => false, 'next_edge' => strtolower($selectedLabel)];
    }

    /**
     * Process an action node.
     */
    private function processActionNode(
        WorkflowNode $node,
        WorkflowExecution $execution,
        array $data,
        array $config
    ): array {
        return match ($node->subtype) {
            'send_email' => $this->actionSendEmail($execution, $data, $config),
            'send_whatsapp' => $this->actionSendWhatsApp($execution, $data, $config),
            'send_sms' => $this->actionSendSms($execution, $data, $config),
            'add_tag' => $this->actionAddTag($execution, $config),
            'remove_tag' => $this->actionRemoveTag($execution, $config),
            'update_contact' => $this->actionUpdateContact($execution, $config),
            // UI uses "move_deal" (stage change only); engine's richer
            // actionUpdateDeal handles the same config — keep both names routed.
            'update_deal', 'move_deal' => $this->actionUpdateDeal($execution, $data, $config),
            // UI uses "assign_agent"; older internal name was assign_conversation.
            'assign_conversation', 'assign_agent' => $this->actionAssignConversation($data, $config),
            'set_priority' => $this->actionSetPriority($data, $config),
            // UI uses "webhook_call"; engine's internal HTTP caller is the same.
            'http_request', 'webhook_call' => $this->actionHttpRequest($data, $config),
            // UI uses "send_notification"; engine routes by config.channel:
            //   - in_app  → Laravel database notification (bell dropdown)
            //   - slack   → Slack webhook / bot
            //   - email   → plain email send to workspace owner
            // Legacy "slack_notification" subtype is kept for backward compat
            // and forces the slack branch.
            'send_notification' => $this->actionSendNotification($execution, $data, $config),
            'slack_notification' => $this->actionSlackNotification($data, $config),
            'create_task' => $this->actionCreateTask($execution, $data, $config),
            'create_deal' => $this->actionCreateDeal($execution, $data, $config),
            'ai_reply' => $this->actionAiReply($execution, $data, $config),
            'create_calendar_event' => $this->actionCreateCalendarEvent($execution, $data, $config),
            'wait_delay' => $this->actionWaitDelay($execution, $node, $data, $config),
            'add_to_group' => $this->actionAddToGroup($execution, $config),
            'remove_from_group' => $this->actionRemoveFromGroup($execution, $config),
            default => ['data' => $data, 'paused' => false, 'next_edge' => null],
        };
    }

    /**
     * Action: create a new deal linked to the contact, in the workspace's
     * first pipeline (or config pipeline_id). Used by the "Create Deal" block.
     */
    private function actionCreateDeal(WorkflowExecution $execution, array $data, array $config): array
    {
        $contact = $execution->contact;
        if (!$contact) {
            throw new \RuntimeException('No contact on execution for create_deal action.');
        }

        $workspaceId = $execution->workflow->workspace_id;
        $pipelineId = $config['pipeline_id'] ?? null;
        $pipeline = $pipelineId
            ? \App\Models\Pipeline::where('workspace_id', $workspaceId)->find($pipelineId)
            : \App\Models\Pipeline::where('workspace_id', $workspaceId)->orderBy('id')->first();

        if (!$pipeline) {
            throw new \RuntimeException('No pipeline available for create_deal action.');
        }

        $firstStage = $pipeline->stages()->orderBy('sort_order')->first();
        if (!$firstStage) {
            throw new \RuntimeException("Pipeline {$pipeline->id} has no stages.");
        }

        $deal = \App\Models\Deal::create([
            'workspace_id' => $workspaceId,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'deal_stage_id' => $firstStage->id,
            'title' => $this->replaceContactVariables($config['deal_name'] ?? 'New Deal', $contact),
            'value' => (float) ($config['value'] ?? 0),
            'status' => 'open',
        ]);

        return ['data' => array_merge($data, ['deal_id' => $deal->id]), 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: create a Google Calendar event in the workspace's connected
     * calendar account. Config keys:
     *   summary, description, location  (strings, contact vars resolved)
     *   start_offset_minutes, duration_minutes  (relative to execution time)
     *   attendees  (array of emails; defaults to the trigger contact if present)
     *   timezone   (IANA, defaults to workspace tz or UTC)
     * Returns early (no-op) if Google Calendar isn't connected.
     */
    private function actionCreateCalendarEvent(WorkflowExecution $execution, array $data, array $config): array
    {
        $workspaceId = $execution->workflow->workspace_id;
        $gcal = app(\App\Services\Integrations\GoogleCalendarIntegrationService::class);

        if (!$gcal->isActive($workspaceId)) {
            return ['data' => $data, 'paused' => false, 'next_edge' => null];
        }

        $contact = $execution->contact;
        $startOffsetMin = (int) ($config['start_offset_minutes'] ?? 60); // 1h from now by default
        $duration = (int) ($config['duration_minutes'] ?? 30);

        $start = now()->addMinutes($startOffsetMin);
        $end = $start->copy()->addMinutes($duration);

        $attendees = $config['attendees'] ?? [];
        if ($contact && $contact->email && empty($attendees)) {
            $attendees = [$contact->email];
        }

        $timezone = $config['timezone']
            ?? $execution->workflow->workspace?->timezone
            ?? config('app.timezone', 'UTC');

        $eventData = [
            'summary' => $contact ? $this->replaceContactVariables($config['summary'] ?? 'Meeting', $contact) : ($config['summary'] ?? 'Meeting'),
            'description' => $contact ? $this->replaceContactVariables($config['description'] ?? '', $contact) : ($config['description'] ?? ''),
            'location' => $config['location'] ?? null,
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'timezone' => $timezone,
            'attendees' => $attendees,
        ];

        try {
            $eventId = $gcal->createEvent($workspaceId, $eventData);
            if ($eventId) {
                return ['data' => array_merge($data, ['calendar_event_id' => $eventId]), 'paused' => false, 'next_edge' => null];
            }
        } catch (\Throwable $e) {
            Log::warning("Workflow create_calendar_event failed for workspace={$workspaceId}: {$e->getMessage()}");
        }

        return ['data' => $data, 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: kick off an AI-generated reply for the most recent inbound
     * message in the execution's conversation. No-op if there's no conv yet.
     */
    private function actionAiReply(WorkflowExecution $execution, array $data, array $config): array
    {
        $conversationId = $data['conversation_id'] ?? null;
        if (!$conversationId) {
            return ['data' => $data, 'paused' => false, 'next_edge' => null];
        }

        $conversation = \App\Models\Conversation::with('emailAccount')->find($conversationId);
        if (!$conversation || !$conversation->emailAccount) {
            return ['data' => $data, 'paused' => false, 'next_edge' => null];
        }

        $lastMessage = $conversation->messages()
            ->where('direction', 'inbound')
            ->orderByDesc('created_at')
            ->first();

        if (!$lastMessage) {
            return ['data' => $data, 'paused' => false, 'next_edge' => null];
        }

        if (class_exists(\App\Jobs\GenerateAIReplyJob::class)) {
            try {
                \App\Jobs\GenerateAIReplyJob::dispatch($lastMessage, $conversation->emailAccount);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Workflow ai_reply dispatch failed: ' . $e->getMessage());
            }
        }

        return ['data' => array_merge($data, ['ai_reply_dispatched' => true]), 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Send email via the workspace's email account.
     */
    private function actionSendEmail(WorkflowExecution $execution, array $data, array $config): array
    {
        $contact = $execution->contact;

        if (!$contact || !$contact->email) {
            throw new \RuntimeException('No contact or email address for send_email action.');
        }

        $emailAccountId = $config['email_account_id'] ?? null;
        $emailAccount = $emailAccountId
            ? \App\Models\EmailAccount::find($emailAccountId)
            : $execution->workflow->workspace->emailAccounts()->where('is_default', true)->first();

        if (!$emailAccount) {
            throw new \RuntimeException('No email account available for workflow email send.');
        }

        $subject = $this->replaceContactVariables($config['subject'] ?? 'No Subject', $contact);

        // Body resolution priority:
        //   1. Saved email template (if email_template_id set) — compiled at apply-time
        //      and stored in body_html, plus any user edits to body_html or body.
        //   2. Explicit body_html override.
        //   3. Plain `body` field — wrapped as HTML so the email is still well-formed.
        $bodyHtmlRaw = $config['body_html'] ?? '';
        if (trim((string) $bodyHtmlRaw) === '') {
            $plainBody = $config['body'] ?? '';
            $bodyHtmlRaw = $plainBody !== ''
                ? '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;">'
                    . nl2br(e($plainBody)) . '</div>'
                : '';
        }

        $bodyHtml = $this->replaceContactVariables($bodyHtmlRaw, $contact);

        $this->emailSendService->send(
            account: $emailAccount,
            to: $contact->email,
            subject: $subject,
            htmlBody: $bodyHtml,
        );

        return ['data' => array_merge($data, ['email_sent' => true]), 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Send WhatsApp message (delegates to WhatsApp service if available).
     */
    private function actionSendWhatsApp(WorkflowExecution $execution, array $data, array $config): array
    {
        $contact = $execution->contact;

        if (!$contact || !$contact->phone) {
            throw new \RuntimeException('No contact or phone number for send_whatsapp action.');
        }

        // Integrate with WhatsApp Cloud API
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');

        if (!$phoneNumberId || !$accessToken) {
            throw new \RuntimeException('WhatsApp credentials not configured.');
        }

        $templateName = $config['template_name'] ?? null;
        $message = $config['message'] ?? '';

        if ($templateName) {
            // Send template message
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $contact->phone,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $config['language'] ?? 'en'],
                    'components' => $config['template_components'] ?? [],
                ],
            ];
        } else {
            // Send text message
            $message = $this->replaceContactVariables($message, $contact);
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $contact->phone,
                'type' => 'text',
                'text' => ['body' => $message],
            ];
        }

        $response = Http::withToken($accessToken)
            ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", $payload);

        if (!$response->successful()) {
            throw new \RuntimeException("WhatsApp send failed: " . $response->body());
        }

        return ['data' => array_merge($data, ['whatsapp_sent' => true]), 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Send SMS via Twilio.
     */
    private function actionSendSms(WorkflowExecution $execution, array $data, array $config): array
    {
        $contact = $execution->contact;

        if (!$contact || !$contact->phone) {
            throw new \RuntimeException('No contact or phone number for send_sms action.');
        }

        $twilioSid = config('services.twilio.sid');
        $twilioToken = config('services.twilio.auth_token');
        $twilioFrom = config('services.twilio.phone_number');

        if (!$twilioSid || !$twilioToken || !$twilioFrom) {
            throw new \RuntimeException('Twilio credentials not configured.');
        }

        $message = $this->replaceContactVariables($config['message'] ?? '', $contact);

        $response = Http::withBasicAuth($twilioSid, $twilioToken)
            ->asForm()
            ->post(
                "https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json",
                [
                    'From' => $twilioFrom,
                    'To' => $contact->phone,
                    'Body' => $message,
                ]
            );

        if (!$response->successful()) {
            throw new \RuntimeException("SMS send failed: " . $response->body());
        }

        return ['data' => array_merge($data, ['sms_sent' => true]), 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Add tag to contact.
     */
    private function actionAddTag(WorkflowExecution $execution, array $config): array
    {
        $contact = $execution->contact;
        $tagId = $config['tag_id'] ?? null;
        $tagName = $config['tag_name'] ?? null;
        $workspaceId = $execution->workflow->workspace_id;

        // Resolve tag (by id or by name — workspace-scoped firstOrCreate)
        $tag = null;
        if ($tagId) {
            $tag = Tag::find($tagId);
        } elseif ($tagName) {
            $tag = Tag::firstOrCreate([
                'workspace_id' => $workspaceId,
                'name' => $tagName,
            ]);
        }

        if (!$tag) {
            return ['data' => [], 'paused' => false, 'next_edge' => null];
        }

        // Attach to contact
        if ($contact) {
            $alreadyOnContact = $contact->tags()->where('tags.id', $tag->id)->exists();
            $contact->tags()->syncWithoutDetaching([$tag->id]);

            if (!$alreadyOnContact) {
                try { event(new \App\Events\TagAdded($contact, $tag)); } catch (\Throwable $e) {}
            }
        }

        // Also tag the triggering conversation (if trigger was an inbound email)
        // so the tag surfaces in the inbox sidebar immediately — previously this
        // only tagged the contact, which made "add_tag" feel broken to users
        // looking at the conversation view.
        $conversationId = $execution->trigger_data['conversation_id'] ?? null;
        if ($conversationId) {
            $conversation = \App\Models\Conversation::where('workspace_id', $workspaceId)
                ->find($conversationId);
            if ($conversation) {
                $conversation->tagModels()->syncWithoutDetaching([$tag->id]);
            }
        }

        return ['data' => ['tag_id' => $tag->id, 'tag_name' => $tag->name], 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Remove tag from contact.
     */
    private function actionRemoveTag(WorkflowExecution $execution, array $config): array
    {
        $contact = $execution->contact;
        $tagId = $config['tag_id'] ?? null;
        $tagName = $config['tag_name'] ?? null;
        $workspaceId = $execution->workflow->workspace_id;

        // Resolve tag
        $tag = null;
        if ($tagId) {
            $tag = Tag::find($tagId);
        } elseif ($tagName) {
            $tag = Tag::where('workspace_id', $workspaceId)
                ->where('name', $tagName)
                ->first();
        }

        if (!$tag) {
            return ['data' => [], 'paused' => false, 'next_edge' => null];
        }

        if ($contact) {
            $contact->tags()->detach($tag->id);
            try { event(new \App\Events\TagRemoved($contact, $tag)); } catch (\Throwable $e) {}
        }

        // Mirror: detach from the triggering conversation so the inbox sidebar
        // reflects the removal immediately.
        $conversationId = $execution->trigger_data['conversation_id'] ?? null;
        if ($conversationId) {
            $conversation = \App\Models\Conversation::where('workspace_id', $workspaceId)
                ->find($conversationId);
            if ($conversation) {
                $conversation->tagModels()->detach([$tag->id]);
            }
        }

        return ['data' => [], 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Add contact to a contact list (group).
     */
    private function actionAddToGroup(WorkflowExecution $execution, array $config): array
    {
        $contact = $execution->contact;
        $groupId = $config['group_id'] ?? null;

        if ($contact && $groupId) {
            $group = \App\Models\ContactList::where('workspace_id', $execution->workflow->workspace_id)
                ->find($groupId);

            if ($group) {
                $group->contacts()->syncWithoutDetaching([
                    $contact->id => ['added_at' => now()],
                ]);
                $group->refreshContactsCount();
            }
        }

        return ['data' => [], 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Remove contact from a contact list (group).
     */
    private function actionRemoveFromGroup(WorkflowExecution $execution, array $config): array
    {
        $contact = $execution->contact;
        $groupId = $config['group_id'] ?? null;

        if ($contact && $groupId) {
            $group = \App\Models\ContactList::where('workspace_id', $execution->workflow->workspace_id)
                ->find($groupId);

            if ($group) {
                $group->contacts()->detach($contact->id);
                $group->refreshContactsCount();
            }
        }

        return ['data' => [], 'paused' => false, 'next_edge' => null];
    }

    /**
     * Condition: Check if contact is in a contact list (group).
     * Returns 'yes' edge if member, 'no' edge otherwise.
     */
    private function conditionInGroup(WorkflowExecution $execution, array $data, array $config): array
    {
        $contact = $execution->contact;
        $groupId = $config['group_id'] ?? null;

        $inGroup = false;

        if ($contact && $groupId) {
            $inGroup = $contact->lists()->where('contact_lists.id', $groupId)->exists();
        }

        return [
            'data' => $data,
            'paused' => false,
            'next_edge' => $inGroup ? 'yes' : 'no',
        ];
    }

    /**
     * Action: Update contact fields.
     */
    private function actionUpdateContact(WorkflowExecution $execution, array $config): array
    {
        $contact = $execution->contact;

        if (!$contact) {
            return ['data' => [], 'paused' => false, 'next_edge' => null];
        }

        $updates = $config['fields'] ?? [];
        $allowedFields = [
            'first_name', 'last_name', 'company', 'job_title',
            'phone', 'city', 'country', 'timezone', 'lead_score', 'status',
        ];

        $standardUpdates = [];
        $customUpdates = [];

        foreach ($updates as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $standardUpdates[$field] = $value;
            } elseif (str_starts_with($field, 'custom_fields.')) {
                $key = str_replace('custom_fields.', '', $field);
                $customUpdates[$key] = $value;
            }
        }

        if (!empty($standardUpdates)) {
            $contact->update($standardUpdates);
        }

        if (!empty($customUpdates)) {
            $existing = $contact->custom_fields ?? [];
            $contact->update(['custom_fields' => array_merge($existing, $customUpdates)]);
        }

        return ['data' => [], 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Update deal stage or fields.
     */
    private function actionUpdateDeal(WorkflowExecution $execution, array $data, array $config): array
    {
        $dealId = $data['deal_id'] ?? $config['deal_id'] ?? null;

        if (!$dealId) {
            // Try to find deal from contact
            $contact = $execution->contact;
            $deal = $contact?->deals()->open()->first();
        } else {
            $deal = Deal::find($dealId);
        }

        if (!$deal) {
            return ['data' => $data, 'paused' => false, 'next_edge' => null];
        }

        $updates = [];

        if (isset($config['deal_stage_id'])) {
            $oldStageId = $deal->deal_stage_id;
            $updates['deal_stage_id'] = $config['deal_stage_id'];
        }
        if (isset($config['assigned_to'])) {
            $updates['assigned_to'] = $config['assigned_to'];
        }
        if (isset($config['status'])) {
            $updates['status'] = $config['status'];

            if ($config['status'] === 'won') {
                $updates['won_at'] = now();
            } elseif ($config['status'] === 'lost') {
                $updates['lost_at'] = now();
                $updates['lost_reason'] = $config['lost_reason'] ?? null;
            }
        }

        if (!empty($updates)) {
            $deal->update($updates);
        }

        return ['data' => array_merge($data, ['deal_updated' => true]), 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Assign conversation to an agent.
     */
    private function actionAssignConversation(array $data, array $config): array
    {
        $conversationId = $data['conversation_id'] ?? null;

        if (!$conversationId) {
            return ['data' => $data, 'paused' => false, 'next_edge' => null];
        }

        $conversation = Conversation::find($conversationId);
        if ($conversation) {
            $assignTo = $config['assign_to_user_id'] ?? $config['assign_to'] ?? null;

            if ($assignTo === 'round_robin') {
                // Get workspace members available for assignment
                $workspace = $conversation->workspace;
                $availableAgent = $workspace->members()
                    ->wherePivot('available_for_assignment', true)
                    ->wherePivot('status', 'active')
                    ->inRandomOrder()
                    ->first();

                $assignTo = $availableAgent?->id;
            }

            if ($assignTo) {
                $conversation->update(['assigned_to' => $assignTo]);
            }
        }

        return ['data' => $data, 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Set conversation priority.
     */
    private function actionSetPriority(array $data, array $config): array
    {
        $conversationId = $data['conversation_id'] ?? null;

        if ($conversationId) {
            Conversation::where('id', $conversationId)
                ->update(['priority' => $config['priority'] ?? 'normal']);
        }

        return ['data' => $data, 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Make an HTTP request to an external URL.
     */
    private function actionHttpRequest(array $data, array $config): array
    {
        $url = $config['url'] ?? null;
        $method = strtolower($config['method'] ?? 'post');
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? [];
        $timeout = $config['timeout'] ?? 30;

        if (!$url) {
            throw new \RuntimeException('HTTP request action requires a URL.');
        }

        // SSRF Protection: Block internal/private IP ranges
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        if (!$host || !in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            throw new \RuntimeException('HTTP request action requires a valid http/https URL.');
        }

        $ips = gethostbynamel($host);
        if ($ips === false) {
            throw new \RuntimeException("Cannot resolve hostname: {$host}");
        }

        foreach ($ips as $ip) {
            if (
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ) {
                throw new \RuntimeException('HTTP request to private/internal IP addresses is not allowed.');
            }
        }

        // Block cloud metadata endpoints
        $blockedHosts = ['169.254.169.254', 'metadata.google.internal', '100.100.100.200'];
        if (in_array($host, $blockedHosts) || in_array($ips[0] ?? '', $blockedHosts)) {
            throw new \RuntimeException('HTTP request to cloud metadata endpoints is not allowed.');
        }

        // Replace variables in body
        if (is_array($body)) {
            $body = array_map(function ($value) use ($data) {
                if (is_string($value)) {
                    foreach ($data as $key => $val) {
                        if (is_string($val) || is_numeric($val)) {
                            $value = str_replace("{{{$key}}}", (string) $val, $value);
                        }
                    }
                }
                return $value;
            }, $body);
        }

        $request = Http::timeout($timeout)->withHeaders($headers);

        $response = match ($method) {
            'get' => $request->get($url, $body),
            'post' => $request->post($url, $body),
            'put' => $request->put($url, $body),
            'patch' => $request->patch($url, $body),
            'delete' => $request->delete($url, $body),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        $responseData = [
            'http_status' => $response->status(),
            'http_body' => $response->json() ?? $response->body(),
            'http_success' => $response->successful(),
        ];

        if (!$response->successful() && ($config['fail_on_error'] ?? false)) {
            throw new \RuntimeException("HTTP request failed with status {$response->status()}");
        }

        return ['data' => array_merge($data, $responseData), 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Send a notification through the configured channel.
     *
     * Channels:
     *   - in_app  → Laravel database notification (bell dropdown). Recipients
     *               default to all workspace members; can be narrowed via
     *               config.notify_user_ids (array of user ids).
     *   - slack   → existing Slack webhook / bot delegate.
     *   - email   → plain email to workspace owner (or config.email).
     *
     * Variables in `message` are resolved against the contact when present.
     */
    private function actionSendNotification(WorkflowExecution $execution, array $data, array $config): array
    {
        $channel = $config['channel'] ?? 'in_app';
        $message = (string) ($config['message'] ?? 'Workflow notification');
        $title = (string) ($config['title'] ?? 'Workflow Notification');

        $contact = $execution->contact;
        if ($contact) {
            $message = $this->replaceContactVariables($message, $contact);
            $title = $this->replaceContactVariables($title, $contact);
        }

        return match ($channel) {
            'slack' => $this->actionSlackNotification($data, array_merge($config, ['message' => $message])),
            'email' => $this->dispatchNotificationEmail($execution, $data, $config, $title, $message),
            default => $this->dispatchInAppNotification($execution, $data, $config, $title, $message),
        };
    }

    /**
     * Helper: write an in-app notification (database channel) to the targeted users.
     */
    private function dispatchInAppNotification(
        WorkflowExecution $execution,
        array $data,
        array $config,
        string $title,
        string $message
    ): array {
        $explicitIds = $config['notify_user_ids'] ?? null;
        $actionUrl = $config['action_url'] ?? null;

        // If a conversation_id is in the trigger context, deep-link to it
        if (!$actionUrl && !empty($data['conversation_id'])) {
            $actionUrl = url('/inbox?conversation=' . (int) $data['conversation_id']);
        }

        // Resolve recipients: explicit ids first, otherwise all active workspace members
        if (is_array($explicitIds) && !empty($explicitIds)) {
            $users = \App\Models\User::whereIn('id', array_filter(array_map('intval', $explicitIds)))->get();
        } else {
            $workspace = $execution->workflow->workspace;
            if (!$workspace) {
                return ['data' => $data, 'paused' => false, 'next_edge' => null];
            }
            // Notifications go to all active workspace members.
            $users = $workspace->members()
                ->wherePivot('status', 'active')
                ->get();
        }

        $sentCount = 0;
        foreach ($users as $user) {
            try {
                $user->notify(new \App\Notifications\InAppNotification(
                    title: $title,
                    body: $message,
                    actionUrl: $actionUrl,
                    icon: $config['icon'] ?? 'bell',
                    type: 'workflow',
                ));
                $sentCount++;
            } catch (\Throwable $e) {
                Log::warning('Workflow in_app notification failed for user', [
                    'user_id' => $user->id,
                    'execution_id' => $execution->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'data' => array_merge($data, ['notifications_sent' => $sentCount]),
            'paused' => false,
            'next_edge' => null,
        ];
    }

    /**
     * Helper: dispatch a workflow notification as a plain email to the configured
     * recipient (config.email) or fall back to the workspace owner.
     */
    private function dispatchNotificationEmail(
        WorkflowExecution $execution,
        array $data,
        array $config,
        string $title,
        string $message
    ): array {
        $workspace = $execution->workflow->workspace;
        $to = $config['email'] ?? $workspace?->owner()?->email;

        if (!$to) {
            return ['data' => $data, 'paused' => false, 'next_edge' => null];
        }

        $emailAccount = $workspace?->emailAccounts()->where('is_default', true)->first()
            ?? $workspace?->emailAccounts()->first();

        if (!$emailAccount) {
            return ['data' => $data, 'paused' => false, 'next_edge' => null];
        }

        try {
            $this->emailSendService->send(
                account: $emailAccount,
                to: $to,
                subject: $title,
                htmlBody: '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;">'
                    . nl2br(e($message)) . '</div>',
            );
        } catch (\Throwable $e) {
            Log::warning('Workflow email notification failed', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);
        }

        return ['data' => array_merge($data, ['notification_email_sent' => true]), 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Send a Slack notification.
     */
    private function actionSlackNotification(array $data, array $config): array
    {
        $webhookUrl = $config['webhook_url'] ?? config('services.slack.webhook_url');
        $channel = $config['channel'] ?? config('services.slack.notifications.channel');
        $message = $config['message'] ?? 'Workflow notification';

        if (!$webhookUrl) {
            // Try using bot token
            $botToken = config('services.slack.notifications.bot_user_oauth_token');
            if (!$botToken || !$channel) {
                throw new \RuntimeException('Slack webhook URL or bot token not configured.');
            }

            $response = Http::withToken($botToken)
                ->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $channel,
                    'text' => $message,
                    'blocks' => $config['blocks'] ?? null,
                ]);
        } else {
            $response = Http::post($webhookUrl, [
                'text' => $message,
                'channel' => $channel,
            ]);
        }

        if (!$response->successful()) {
            throw new \RuntimeException("Slack notification failed: " . $response->body());
        }

        return ['data' => array_merge($data, ['slack_sent' => true]), 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Create an internal task/note.
     */
    private function actionCreateTask(WorkflowExecution $execution, array $data, array $config): array
    {
        // Store as an activity log entry
        activity()
            ->performedOn($execution->contact ?? $execution->workflow)
            ->causedBy(null)
            ->withProperties([
                'type' => 'workflow_task',
                'title' => $config['title'] ?? 'Workflow Task',
                'description' => $config['description'] ?? '',
                'priority' => $config['priority'] ?? 'normal',
                'due_at' => isset($config['due_hours'])
                    ? now()->addHours($config['due_hours'])->toDateTimeString()
                    : null,
                'workflow_id' => $execution->workflow_id,
                'execution_id' => $execution->id,
            ])
            ->log('workflow_task_created');

        return ['data' => $data, 'paused' => false, 'next_edge' => null];
    }

    /**
     * Action: Wait/delay for a configured duration.
     *
     * FIX-062: Uses an operation lock to prevent duplicate wake-up jobs when
     * concurrent requests try to schedule the same delay node.
     */
    private function actionWaitDelay(
        WorkflowExecution $execution,
        WorkflowNode $node,
        array $data,
        array $config
    ): array {
        $delayValue = $config['delay_value'] ?? 1;
        $delayUnit = $config['delay_unit'] ?? 'hours';

        $resumeAt = match ($delayUnit) {
            'minutes' => now()->addMinutes($delayValue),
            'hours' => now()->addHours($delayValue),
            'days' => now()->addDays($delayValue),
            default => now()->addHours($delayValue),
        };

        // FIX-062: Acquire lock before scheduling to prevent duplicate wake-up jobs
        $lockKey = "workflow-delay:{$execution->id}:{$node->id}";
        $lock = Cache::lock($lockKey, 300); // 5-minute TTL

        if (!$lock->get()) {
            Log::info('Workflow delay already scheduled, skipping duplicate', [
                'execution_id' => $execution->id,
                'node_id' => $node->id,
            ]);
            return ['data' => $data, 'paused' => true, 'next_edge' => null, 'resume_at' => $resumeAt];
        }

        // Schedule wake-up job
        WakeUpWorkflowJob::dispatch($execution->id, $node->id)
            ->delay($resumeAt)
            ->onQueue('workflows');

        $execution->update(['status' => 'waiting']);

        Log::info('Workflow paused for delay', [
            'execution_id' => $execution->id,
            'node_id' => $node->id,
            'resume_at' => $resumeAt->toDateTimeString(),
        ]);

        return ['data' => $data, 'paused' => true, 'next_edge' => null, 'resume_at' => $resumeAt];
    }

    /**
     * Get the next nodes connected by edges from the given node.
     * Uses the pre-loaded $this->edges collection to avoid N+1 queries.
     */
    private function getNextNodes(WorkflowNode $node, int $workflowId, ?string $edgeLabel = null): \Illuminate\Support\Collection
    {
        // Filter from pre-loaded edge collection
        $matchingEdges = $this->edges
            ->where('from_node_id', $node->id);

        if ($edgeLabel !== null) {
            $labeled = $matchingEdges->where('label', $edgeLabel);

            if ($labeled->isEmpty()) {
                // Fallback: try edges without a specific label (default path)
                $labeled = $matchingEdges->whereNull('label');
            }

            $matchingEdges = $labeled;
        }

        $edgeToNodeIds = $matchingEdges->pluck('to_node_id')->unique()->values();

        if ($edgeToNodeIds->isEmpty()) {
            return collect();
        }

        return WorkflowNode::whereIn('id', $edgeToNodeIds)->get();
    }

    /**
     * Log a workflow step execution.
     */
    private function logStep(
        WorkflowExecution $execution,
        WorkflowNode $node,
        string $status,
        array $inputData,
        array $outputData,
        int $durationMs,
        ?\DateTimeInterface $resumeAt = null,
        ?string $error = null
    ): void {
        WorkflowStepLog::create([
            'execution_id' => $execution->id,
            'node_id' => $node->id,
            'status' => $status,
            'input_data' => $inputData,
            'output_data' => $outputData['data'] ?? $outputData,
            'error_message' => $error,
            'duration_ms' => $durationMs,
            'executed_at' => now(),
            'resume_at' => $resumeAt,
        ]);
    }

    /**
     * Complete a workflow execution.
     */
    private function completeExecution(WorkflowExecution $execution, string $status, ?string $error = null): void
    {
        $execution->update([
            'status' => $status,
            'completed_at' => now(),
        ]);

        if ($error) {
            Log::warning("Workflow execution {$execution->id} completed with status: {$status}", [
                'error' => $error,
            ]);
        }
    }

    /**
     * Check if all branches are complete and finalize execution.
     */
    private function checkAndCompleteExecution(WorkflowExecution $execution): void
    {
        // If there are no waiting steps, the execution is complete
        $hasWaiting = $execution->stepLogs()
            ->where('status', 'waiting')
            ->exists();

        if (!$hasWaiting && $execution->status === 'running') {
            $this->completeExecution($execution, 'completed');
        }
    }

    /**
     * Replace contact variables in text.
     */
    private function replaceContactVariables(string $text, Contact $contact): string
    {
        return str_replace(
            ['{first_name}', '{last_name}', '{full_name}', '{email}', '{company}', '{phone}'],
            [
                $contact->first_name ?? '',
                $contact->last_name ?? '',
                $contact->full_name ?? '',
                $contact->email ?? '',
                $contact->company ?? '',
                $contact->phone ?? '',
            ],
            $text
        );
    }
}
