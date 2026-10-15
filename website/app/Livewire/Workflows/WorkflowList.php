<?php

namespace App\Livewire\Workflows;

use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class WorkflowList extends Component
{
    use AuthorizesWorkspaceActions;

    public string $search = '';
    public string $statusFilter = 'all';

    public function toggleStatus(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        $workflow = Workflow::where('workspace_id', $this->workspaceId())->findOrFail($id);
        $workflow->update([
            'status' => $workflow->status === 'active' ? 'paused' : 'active',
        ]);

        $newStatus = $workflow->status === 'active' ? 'activated' : 'paused';
        session()->flash('success', "Workflow \"{$workflow->name}\" {$newStatus}.");
    }

    public function deleteWorkflow(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        $workflow = Workflow::where('workspace_id', $this->workspaceId())
            ->where('id', $id)
            ->first();

        if ($workflow) {
            $workflow->delete();
            session()->flash('success', "Workflow \"{$workflow->name}\" deleted.");
        }
    }

    public function duplicateWorkflow(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        $this->withOperationLock('duplicate-workflow-' . $id, function () use ($id) {
            $workflow = Workflow::where('workspace_id', $this->workspaceId())
                ->with(['workflowNodes', 'workflowEdges'])
                ->findOrFail($id);

            DB::transaction(function () use ($workflow) {
                $new = $workflow->replicate();
                $new->uuid = null;
                $new->name = $workflow->name . ' (Copy)';
                $new->status = 'draft';
                $new->executions_count = 0;
                $new->webhook_token = null;
                $new->save();

                // Duplicate nodes and build old->new ID map
                $nodeIdMap = [];
                foreach ($workflow->workflowNodes as $node) {
                    $newNode = $node->replicate();
                    $newNode->workflow_id = $new->id;
                    $newNode->save();
                    $nodeIdMap[$node->id] = $newNode->id;
                }

                // Duplicate edges with remapped node IDs
                foreach ($workflow->workflowEdges as $edge) {
                    $fromId = $nodeIdMap[$edge->from_node_id] ?? null;
                    $toId = $nodeIdMap[$edge->to_node_id] ?? null;

                    // Skip edges where node mapping failed
                    if (!$fromId || !$toId) continue;

                    $new->workflowEdges()->create([
                        'from_node_id' => $fromId,
                        'to_node_id' => $toId,
                        'condition' => $edge->condition ?? null,
                        'label' => $edge->label ?? null,
                        'sort_order' => $edge->sort_order ?? 0,
                    ]);
                }
            });

            session()->flash('success', 'Workflow duplicated with all nodes and connections.');
        });
    }

    protected function workspaceId(): ?int
    {
        return auth()->user()->active_workspace_id;
    }

    public function render()
    {
        $wid = $this->workspaceId();

        $query = Workflow::where('workspace_id', $wid)
            ->with(['workflowNodes' => fn($q) => $q->where('type', 'trigger')->limit(1)])
            ->latest();

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        $workflows = $query->get();

        // Get execution stats in bulk (one query for all workflows)
        $workflowIds = $workflows->pluck('id')->toArray();

        $executionStats = WorkflowExecution::whereIn('workflow_id', $workflowIds)
            ->selectRaw('workflow_id')
            ->selectRaw('COUNT(*) as total_executions')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw('MAX(started_at) as last_run')
            ->groupBy('workflow_id')
            ->get()
            ->keyBy('workflow_id');

        // Total execution stats for the workspace
        $totalExecutions = $executionStats->sum('total_executions');
        $totalCompleted = $executionStats->sum('completed_count');

        // Attach trigger info and calculated metrics
        $workflows->each(function ($wf) use ($executionStats) {
            $trigger = $wf->workflowNodes->first();
            $wf->trigger_subtype = $trigger?->subtype ?? 'none';

            $stats = $executionStats->get($wf->id);
            $totalExec = $stats?->total_executions ?? 0;
            $completedExec = $stats?->completed_count ?? 0;
            $wf->success_rate = $totalExec > 0 ? round(($completedExec / $totalExec) * 100) : 0;
            $wf->last_run_at = $stats?->last_run;
        });

        $totalWorkflows = Workflow::where('workspace_id', $wid)->count();
        $activeWorkflows = Workflow::where('workspace_id', $wid)->where('status', 'active')->count();

        $avgSuccessRate = $totalExecutions > 0
            ? round(($totalCompleted / $totalExecutions) * 100)
            : 0;

        return view('livewire.workflows.workflow-list', [
            'workflows' => $workflows,
            'totalWorkflows' => $totalWorkflows,
            'activeWorkflows' => $activeWorkflows,
            'totalExecutions' => $totalExecutions,
            'avgSuccessRate' => $avgSuccessRate,
        ]);
    }
}
