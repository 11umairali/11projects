<?php

namespace App\Livewire\Settings;

use App\Jobs\RetryWebhookJob;
use App\Models\WebhookLog;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Component;
use Livewire\WithPagination;

class WebhookLogs extends Component
{
    use AuthorizesWorkspaceActions, WithPagination;

    public string $statusFilter = '';
    public string $search = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    /** ID of the log currently shown in the detail modal. */
    public ?int $viewingLogId = null;

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function viewLog(int $id): void
    {
        $this->viewingLogId = $id;
    }

    public function closeModal(): void
    {
        $this->viewingLogId = null;
    }

    public function retryWebhook(int $id): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        $log = WebhookLog::where('workspace_id', $workspaceId)->findOrFail($id);

        if ($log->status === 'success') {
            session()->flash('error', 'This webhook already succeeded.');
            return;
        }

        // Validate webhook URL is still accessible
        if (empty($log->url)) {
            session()->flash('error', 'Cannot retry — webhook URL is missing.');
            return;
        }

        // Reset attempts to allow retry
        $log->update([
            'status' => 'pending',
            'attempts' => 0,
            'next_retry_at' => now(),
            'error_message' => null,
        ]);

        RetryWebhookJob::dispatch($log);

        session()->flash('success', 'Webhook retry queued. Check back shortly for the result.');
    }

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $query = WebhookLog::where('workspace_id', $workspaceId);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where('url', 'like', '%' . $this->search . '%');
        }

        $allowedSorts = ['created_at', 'url', 'status', 'response_status', 'duration_ms', 'attempts'];
        $sort = in_array($this->sortField, $allowedSorts) ? $this->sortField : 'created_at';

        $logs = $query->orderBy($sort, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate(20);

        $viewingLog = $this->viewingLogId
            ? WebhookLog::where('workspace_id', $workspaceId)->find($this->viewingLogId)
            : null;

        $rawCounts = WebhookLog::where('workspace_id', $workspaceId)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw("SUM(CASE WHEN status = 'retrying' THEN 1 ELSE 0 END) as retrying_count")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->first();

        $counts = [
            'all' => (int) $rawCounts->total,
            'success' => (int) $rawCounts->success_count,
            'failed' => (int) $rawCounts->failed_count,
            'retrying' => (int) $rawCounts->retrying_count,
            'pending' => (int) $rawCounts->pending_count,
        ];

        return view('livewire.settings.webhook-logs', [
            'logs' => $logs,
            'viewingLog' => $viewingLog,
            'counts' => $counts,
        ]);
    }
}
