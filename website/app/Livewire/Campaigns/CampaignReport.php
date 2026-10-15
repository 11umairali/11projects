<?php

namespace App\Livewire\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignLink;
use App\Models\CampaignRecipient;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Component;
use Livewire\WithPagination;

class CampaignReport extends Component
{
    use WithPagination;
    use AuthorizesWorkspaceActions;

    public int $campaignId;
    public string $recipientFilter = 'all';
    public string $activeTab = 'overview';

    public function mount(int $campaignId): void
    {
        if (!$this->authorizeWorkspaceAction('view')) {
            abort(403);
        }

        $this->campaignId = $campaignId;

        Campaign::where('workspace_id', $this->workspaceId())
            ->findOrFail($campaignId);
    }

    public function updatedRecipientFilter(): void
    {
        $this->resetPage();
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
        $this->recipientFilter = 'all';
    }

    protected function workspaceId(): ?int
    {
        return auth()->user()->active_workspace_id;
    }

    public function render()
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId())
            ->with(['emailAccount', 'createdBy'])
            ->findOrFail($this->campaignId);

        $deliveredCount = $campaign->delivered_count ?: 0;
        $recipientsCount = $campaign->recipients_count ?: 0;

        // Count failed recipients
        $failedCount = CampaignRecipient::where('campaign_id', $this->campaignId)
            ->where('status', 'failed')
            ->count();

        $stats = [
            'recipients' => $recipientsCount,
            'delivered' => $deliveredCount,
            'opened' => $campaign->opened_count,
            'open_rate' => $campaign->open_rate,
            'clicked' => $campaign->clicked_count,
            'click_rate' => $campaign->click_rate,
            'bounced' => $campaign->bounced_count,
            'bounce_rate' => ($deliveredCount + $campaign->bounced_count) > 0
                ? round(($campaign->bounced_count / ($deliveredCount + $campaign->bounced_count)) * 100, 1)
                : 0,
            'unsubscribed' => $campaign->unsubscribed_count,
            'unsub_rate' => $deliveredCount > 0
                ? round(($campaign->unsubscribed_count / $deliveredCount) * 100, 1)
                : 0,
            'failed' => $failedCount,
        ];

        // All recipients (filtered)
        $recipientQuery = CampaignRecipient::where('campaign_id', $this->campaignId)->with('contact');
        if ($this->recipientFilter !== 'all') {
            $recipientQuery->where('status', $this->recipientFilter);
        }
        $recipients = $recipientQuery->orderByDesc('updated_at')->paginate(20);

        // Tab-specific queries
        $openedRecipients = collect();
        $clickedRecipients = collect();
        $bouncedRecipients = collect();
        $links = collect();

        if ($this->activeTab === 'overview' || $this->activeTab === 'clicks') {
            $links = CampaignLink::where('campaign_id', $this->campaignId)
                ->orderByDesc('clicks_count')
                ->limit(50)
                ->get();
        }

        if ($this->activeTab === 'opens') {
            $openedRecipients = CampaignRecipient::where('campaign_id', $this->campaignId)
                ->whereNotNull('opened_at')
                ->with('contact')
                ->orderByDesc('opened_at')
                ->paginate(20);
        }

        if ($this->activeTab === 'clicks') {
            $clickedRecipients = CampaignRecipient::where('campaign_id', $this->campaignId)
                ->whereNotNull('clicked_at')
                ->with('contact')
                ->orderByDesc('clicked_at')
                ->paginate(20);
        }

        if ($this->activeTab === 'bounces') {
            $bouncedRecipients = CampaignRecipient::where('campaign_id', $this->campaignId)
                ->whereIn('status', ['bounced', 'failed'])
                ->with('contact')
                ->orderByDesc('updated_at')
                ->paginate(20);
        }

        return view('livewire.campaigns.campaign-report', [
            'campaign' => $campaign,
            'stats' => $stats,
            'recipients' => $recipients,
            'links' => $links,
            'openedRecipients' => $openedRecipients,
            'clickedRecipients' => $clickedRecipients,
            'bouncedRecipients' => $bouncedRecipients,
        ]);
    }
}
