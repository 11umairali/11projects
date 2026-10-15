<?php

namespace App\Jobs;

use App\Models\WorkflowExecution;
use App\Models\WorkflowNode;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WakeUpWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying.
     */
    public array $backoff = [30, 120, 600];

    /**
     * Maximum seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * The queue this job should be dispatched to.
     */

    public function __construct(
        public int $executionId,
        public int $nodeId,
    ) {}

    public function handle(WorkflowEngine $engine): void
    {
        // Use lockForUpdate inside a transaction to prevent concurrent wake-ups
        // from racing on the same execution row.
        $execution = DB::transaction(function () {
            return WorkflowExecution::where('id', $this->executionId)
                ->where('status', 'waiting')
                ->lockForUpdate()
                ->first();
        });

        if (!$execution) {
            Log::info('WakeUpWorkflowJob: Execution not found or no longer waiting, skipping wake up', [
                'execution_id' => $this->executionId,
            ]);
            return;
        }

        $node = WorkflowNode::find($this->nodeId);

        if (!$node) {
            Log::warning('WakeUpWorkflowJob: Node not found', [
                'node_id' => $this->nodeId,
            ]);
            return;
        }

        // Check if the workflow is still active
        $workflow = $execution->workflow;
        if (!$workflow || !$workflow->isActive()) {
            $execution->update([
                'status' => 'canceled',
                'completed_at' => now(),
            ]);
            Log::info('WakeUpWorkflowJob: Workflow no longer active, cancelling execution', [
                'execution_id' => $this->executionId,
                'workflow_id' => $workflow?->id,
            ]);
            return;
        }

        Log::info('WakeUpWorkflowJob: Resuming workflow execution', [
            'execution_id' => $this->executionId,
            'node_id' => $this->nodeId,
        ]);

        // Reload the contact data in case it changed during the wait
        $triggerData = $execution->trigger_data ?? [];

        $engine->resume($execution, $node, $triggerData);
        $this->onQueue('processing');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('WakeUpWorkflowJob permanently failed', [
            'execution_id' => $this->executionId,
            'node_id' => $this->nodeId,
            'error' => $exception->getMessage(),
        ]);

        $execution = WorkflowExecution::find($this->executionId);
        if ($execution && $execution->status === 'waiting') {
            $execution->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);
        }
    }
}
