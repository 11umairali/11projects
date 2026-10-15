<?php

namespace App\Livewire\Workflows;

use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStepLog;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class WorkflowExecutionLog extends Component
{
    use WithPagination;
    use AuthorizesWorkspaceActions;

    public int $workflowId;
    public string $workflowName = '';

    #[Url]
    public string $statusFilter = 'all';

    public ?int $expandedExecutionId = null;

    // Step log pagination
    public int $stepLogsPage = 1;
    public int $stepLogsPerPage = 25;

    public function mount(int $workflowId): void
    {
        if (!$this->authorizeWorkspaceAction('view')) {
            abort(403);
        }

        $this->workflowId = $workflowId;

        $workflow = Workflow::where('workspace_id', $this->workspaceId())
            ->findOrFail($workflowId);

        $this->workflowName = $workflow->name;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function toggleExpand(int $executionId): void
    {
        if ($this->expandedExecutionId === $executionId) {
            $this->expandedExecutionId = null;
        } else {
            $this->expandedExecutionId = $executionId;
            $this->stepLogsPage = 1; // Reset step log pagination when expanding new execution
        }
    }

    public function loadMoreStepLogs(): void
    {
        $this->stepLogsPage++;
    }

    protected function workspaceId(): ?int
    {
        return auth()->user()->active_workspace_id;
    }

    public function render()
    {
        // Verify workflow belongs to workspace
        Workflow::where('workspace_id', $this->workspaceId())
            ->findOrFail($this->workflowId);

        $query = WorkflowExecution::where('workflow_id', $this->workflowId)
            ->with('contact')
            ->latest('started_at');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $executions = $query->paginate(15);

        // Load step logs for expanded execution with pagination
        $stepLogs = collect();
        if ($this->expandedExecutionId) {
            $stepLogs = WorkflowStepLog::where('execution_id', $this->expandedExecutionId)
                ->with('node')
                ->orderBy('executed_at')
                ->paginate($this->stepLogsPerPage, ['*'], 'stepLogsPage', $this->stepLogsPage);
        }

        // Summary counts -- single query using conditional aggregation (cached for render)
        $statusCounts = WorkflowExecution::where('workflow_id', $this->workflowId)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running")
            ->first();

        $totalExecutions = (int) ($statusCounts->total ?? 0);
        $completedCount = (int) ($statusCounts->completed ?? 0);
        $failedCount = (int) ($statusCounts->failed ?? 0);
        $runningCount = (int) ($statusCounts->running ?? 0);

        return view('livewire.workflows.workflow-execution-log', [
            'executions' => $executions,
            'stepLogs' => $stepLogs,
            'totalExecutions' => $totalExecutions,
            'completedCount' => $completedCount,
            'failedCount' => $failedCount,
            'runningCount' => $runningCount,
        ]);
    }
}
