<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Workflow;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying.
     */
    public array $backoff = [60, 300, 900];

    /**
     * Maximum seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * The queue this job should be dispatched to.
     */

    public function __construct(
        public int $workflowId,
        public ?int $contactId,
        public array $triggerData = [],
    ) {}

    public function handle(WorkflowEngine $engine): void
    {
        // FIX-065: Use withTrashed() so we can detect soft-deleted workflows
        // instead of silently getting null and treating it as "not found"
        $workflow = Workflow::withTrashed()->find($this->workflowId);

        if (!$workflow) {
            Log::warning('ExecuteWorkflowJob: Workflow not found', [
                'workflow_id' => $this->workflowId,
            ]);
            return;
        }

        // FIX-065: If the workflow was soft-deleted, cancel any pending execution
        if ($workflow->trashed()) {
            Log::warning('ExecuteWorkflowJob: Workflow was soft-deleted, canceling execution', [
                'workflow_id' => $this->workflowId,
                'deleted_at' => $workflow->deleted_at->toDateTimeString(),
            ]);

            // Mark any existing pending/running executions for this workflow as canceled
            $workflow->workflowExecutions()
                ->whereIn('status', ['running', 'waiting', 'pending'])
                ->update([
                    'status' => 'canceled',
                    'completed_at' => now(),
                ]);

            return;
        }

        if (!$workflow->isActive()) {
            Log::info('ExecuteWorkflowJob: Workflow is not active, skipping', [
                'workflow_id' => $this->workflowId,
                'status' => $workflow->status,
            ]);
            return;
        }

        $contact = $this->contactId ? Contact::find($this->contactId) : null;

        $engine->execute($workflow, $contact, $this->triggerData);
        $this->onQueue('processing');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteWorkflowJob permanently failed', [
            'workflow_id' => $this->workflowId,
            'contact_id' => $this->contactId,
            'error' => $exception->getMessage(),
        ]);
    }
}
