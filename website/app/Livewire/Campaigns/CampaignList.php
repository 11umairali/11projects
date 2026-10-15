<?php

namespace App\Livewire\Campaigns;

use App\Models\Campaign;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CampaignList extends Component
{
    use WithPagination;
    use AuthorizesWorkspaceActions;

    #[Url]
    public string $activeTab = 'all';

    public string $search = '';

    public array $selectedCampaigns = [];
    public bool $selectAll = false;

    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-4 animate-pulse">
            <div class="flex justify-between"><div class="h-10 w-48 bg-gray-200 rounded-xl"></div><div class="h-10 w-32 bg-gray-200 rounded-xl"></div></div>
            <div class="bg-gray-200 rounded-2xl h-96"></div>
        </div>
        HTML;
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            // Select all campaigns on the current page
            $workspaceId = $this->workspaceId();
            $query = Campaign::where('workspace_id', $workspaceId)->latest();

            if ($this->activeTab !== 'all') {
                $query->where('status', $this->activeTab);
            }

            if ($this->search) {
                $term = '%' . $this->search . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                      ->orWhere('subject', 'like', $term);
                });
            }

            $this->selectedCampaigns = $query->paginate(10)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedCampaigns = [];
        }
    }

    public function bulkDeleteCampaigns(): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        $this->withOperationLock('bulk-delete-campaigns', function () {
            $workspaceId = $this->workspaceId();
            $ids = array_map('intval', $this->selectedCampaigns);

            if (empty($ids)) {
                session()->flash('error', 'No campaigns selected.');
                return;
            }

            $count = Campaign::where('workspace_id', $workspaceId)
                ->whereIn('id', $ids)
                ->whereIn('status', ['draft', 'sent', 'paused', 'canceled'])
                ->count();

            Campaign::where('workspace_id', $workspaceId)
                ->whereIn('id', $ids)
                ->whereIn('status', ['draft', 'sent', 'paused', 'canceled'])
                ->delete();

            $this->selectedCampaigns = [];
            $this->selectAll = false;
            session()->flash('success', "{$count} campaign(s) deleted.");
        });
    }

    public function bulkDuplicateCampaigns(): void
    {
        if (!$this->authorizeWorkspaceAction('create')) return;

        $this->withOperationLock('bulk-duplicate-campaigns', function () {
            $workspaceId = $this->workspaceId();
            $ids = array_map('intval', $this->selectedCampaigns);

            if (empty($ids)) {
                session()->flash('error', 'No campaigns selected.');
                return;
            }

            $campaigns = Campaign::where('workspace_id', $workspaceId)
                ->whereIn('id', $ids)
                ->get();

            foreach ($campaigns as $campaign) {
                $copy = $campaign->replicate();
                $copy->uuid = null;
                $copy->name = $campaign->name . ' (Copy)';
                $copy->status = 'draft';
                $copy->sent_count = 0;
                $copy->delivered_count = 0;
                $copy->opened_count = 0;
                $copy->clicked_count = 0;
                $copy->bounced_count = 0;
                $copy->unsubscribed_count = 0;
                $copy->scheduled_at = null;
                $copy->sent_at = null;
                $copy->completed_at = null;
                $copy->save();
            }

            $this->selectedCampaigns = [];
            $this->selectAll = false;
            session()->flash('success', count($ids) . ' campaign(s) duplicated.');
        });
    }

    public function deleteCampaign(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        Campaign::where('workspace_id', $this->workspaceId())
            ->where('id', $id)
            ->delete();

        session()->flash('success', 'Campaign deleted successfully.');
    }

    public function duplicateCampaign(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('create')) return;

        $campaign = Campaign::where('workspace_id', $this->workspaceId())
            ->findOrFail($id);

        $newCampaign = $campaign->replicate();
        $newCampaign->uuid = null;
        $newCampaign->name = $campaign->name . ' (Copy)';
        $newCampaign->status = 'draft';
        $newCampaign->sent_count = 0;
        $newCampaign->delivered_count = 0;
        $newCampaign->opened_count = 0;
        $newCampaign->clicked_count = 0;
        $newCampaign->bounced_count = 0;
        $newCampaign->unsubscribed_count = 0;
        $newCampaign->scheduled_at = null;
        $newCampaign->sent_at = null;
        $newCampaign->completed_at = null;
        $newCampaign->save();

        session()->flash('success', 'Campaign duplicated successfully.');
    }

    public function pauseCampaign(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        Campaign::where('workspace_id', $this->workspaceId())
            ->where('id', $id)
            ->whereIn('status', ['scheduled', 'sending'])
            ->update(['status' => 'paused']);

        session()->flash('success', 'Campaign paused.');
    }

    protected function workspaceId(): ?int
    {
        return auth()->user()->active_workspace_id;
    }

    public function render()
    {
        $workspaceId = $this->workspaceId();

        $query = Campaign::where('workspace_id', $workspaceId)
            ->latest();

        if ($this->search) {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('subject', 'like', $term);
            });
        }

        if ($this->activeTab !== 'all') {
            $query->where('status', $this->activeTab);
        }

        $campaigns = $query->paginate(10);

        // Summary stats -- single query for counts + aggregates (avoid N+1)
        $totalCampaigns = Campaign::where('workspace_id', $workspaceId)->count();
        $totalSent = Campaign::where('workspace_id', $workspaceId)->sum('sent_count');

        // Use DB aggregation instead of loading all campaigns into memory
        $avgStats = Campaign::where('workspace_id', $workspaceId)
            ->where('delivered_count', '>', 0)
            ->selectRaw('AVG(opened_count * 100.0 / delivered_count) as avg_open_rate')
            ->selectRaw('AVG(clicked_count * 100.0 / delivered_count) as avg_click_rate')
            ->first();

        $avgOpenRate = round($avgStats->avg_open_rate ?? 0, 1);
        $avgClickRate = round($avgStats->avg_click_rate ?? 0, 1);

        // Tab counts -- single query instead of 3 separate queries
        $tabCounts = Campaign::where('workspace_id', $workspaceId)
            ->whereIn('status', ['draft', 'scheduled', 'sent'])
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $draftCount = $tabCounts->get('draft', 0);
        $scheduledCount = $tabCounts->get('scheduled', 0);
        $sentCount = $tabCounts->get('sent', 0);

        return view('livewire.campaigns.campaign-list', [
            'campaigns' => $campaigns,
            'totalCampaigns' => $totalCampaigns,
            'totalSent' => $totalSent,
            'avgOpenRate' => $avgOpenRate,
            'avgClickRate' => $avgClickRate,
            'draftCount' => $draftCount,
            'scheduledCount' => $scheduledCount,
            'sentCount' => $sentCount,
        ]);
    }
}
