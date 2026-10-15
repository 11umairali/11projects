<?php

namespace App\Livewire\Dashboard;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\KbDocument;
use App\Models\Message;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ActionableInsights extends Component
{
    use AuthorizesWorkspaceActions;

    /** @var array<string> IDs of insights dismissed this session (optimistic UI). */
    public array $dismissedIds = [];

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 animate-pulse">
            <div class="h-32 bg-gray-200 dark:bg-gray-700 rounded-2xl"></div>
            <div class="h-32 bg-gray-200 dark:bg-gray-700 rounded-2xl"></div>
        </div>
        HTML;
    }

    /**
     * Dismiss an insight so it doesn't reappear for 7 days.
     */
    public function dismiss(string $insightId): void
    {
        $userId = auth()->id();
        $cacheKey = "insight_dismissed:{$userId}:{$insightId}";
        Cache::put($cacheKey, true, now()->addDays(7));

        $this->dismissedIds[] = $insightId;
    }

    /**
     * Build the list of actionable insights for the current workspace.
     *
     * Each insight: id, icon, title, description, actionUrl, actionLabel, priority.
     * Priority: urgent (red), attention (amber), suggestion (blue), positive (green).
     *
     * @return array<int, array<string, string>>
     */
    protected function generateInsights(): array
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $userId = auth()->id();

        if (! $workspaceId) {
            return [];
        }

        $insights = [];

        // ---------------------------------------------------------------
        // a) Conversations awaiting reply (open > 24 h)
        // ---------------------------------------------------------------
        $awaitingReply = Conversation::where('workspace_id', $workspaceId)
            ->where('status', 'open')
            ->where('last_message_at', '<=', now()->subHours(24))
            ->count();

        if ($awaitingReply > 0) {
            $insights[] = [
                'id'          => 'awaiting_reply',
                'icon'        => 'inbox',
                'title'       => "{$awaitingReply} conversation" . ($awaitingReply > 1 ? 's' : '') . " awaiting reply",
                'description' => 'Open conversations have been waiting more than 24 hours for a response.',
                'actionUrl'   => '/inbox',
                'actionLabel' => 'Go to Inbox',
                'priority'    => 'urgent',
                'weight'      => 100,
            ];
        }

        // ---------------------------------------------------------------
        // b) Campaign performance alert (open rate < 15%)
        // ---------------------------------------------------------------
        $poorCampaign = Campaign::where('workspace_id', $workspaceId)
            ->where('status', 'sent')
            ->where('delivered_count', '>', 0)
            ->whereRaw('(opened_count * 100.0 / delivered_count) < 15')
            ->latest('sent_at')
            ->first();

        if ($poorCampaign) {
            $openRate = round(($poorCampaign->opened_count * 100.0 / $poorCampaign->delivered_count), 1);
            $insights[] = [
                'id'          => 'campaign_alert_' . $poorCampaign->id,
                'icon'        => 'alert-triangle',
                'title'       => 'Campaign performance alert',
                'description' => "\"{$poorCampaign->name}\" has a {$openRate}% open rate. Try a different subject line or sending time.",
                'actionUrl'   => '/campaigns/' . $poorCampaign->id . '/report',
                'actionLabel' => 'View Report',
                'priority'    => 'attention',
                'weight'      => 90,
            ];
        }

        // ---------------------------------------------------------------
        // c) AI suggestion drafts awaiting review
        // ---------------------------------------------------------------
        $aiDrafts = Message::where('workspace_id', $workspaceId)
            ->where('sender_type', 'ai')
            ->where('ai_status', 'draft')
            ->count();

        if ($aiDrafts > 0) {
            $insights[] = [
                'id'          => 'ai_drafts',
                'icon'        => 'sparkles',
                'title'       => "{$aiDrafts} AI suggestion" . ($aiDrafts > 1 ? 's' : '') . " available",
                'description' => 'AI-generated reply drafts are waiting for your review and approval.',
                'actionUrl'   => '/inbox',
                'actionLabel' => 'Review Drafts',
                'priority'    => 'suggestion',
                'weight'      => 70,
            ];
        }

        // ---------------------------------------------------------------
        // d) Inactive contacts (not contacted in 30+ days)
        // ---------------------------------------------------------------
        $inactiveContacts = Contact::where('workspace_id', $workspaceId)
            ->where(function ($q) {
                $q->where('last_contacted_at', '<=', now()->subDays(30))
                    ->orWhereNull('last_contacted_at');
            })
            ->count();

        if ($inactiveContacts > 10) {
            $insights[] = [
                'id'          => 'inactive_contacts',
                'icon'        => 'users',
                'title'       => "{$inactiveContacts} inactive contacts",
                'description' => "Contacts haven't been reached in over 30 days. Re-engage them with a campaign.",
                'actionUrl'   => '/campaigns/create',
                'actionLabel' => 'Create Campaign',
                'priority'    => 'suggestion',
                'weight'      => 50,
            ];
        }

        // ---------------------------------------------------------------
        // e) Follow up with engaged contacts
        // ---------------------------------------------------------------
        $engagedCount = DB::table('campaign_recipients')
            ->join('campaigns', 'campaign_recipients.campaign_id', '=', 'campaigns.id')
            ->where('campaigns.workspace_id', $workspaceId)
            ->where('campaign_recipients.opened_at', '>=', now()->subDays(7))
            ->whereNull('campaign_recipients.clicked_at')
            ->distinct('campaign_recipients.contact_id')
            ->count('campaign_recipients.contact_id');

        if ($engagedCount > 0) {
            $insights[] = [
                'id'          => 'follow_up_engaged',
                'icon'        => 'trending-up',
                'title'       => 'Follow up with engaged contacts',
                'description' => "{$engagedCount} contact" . ($engagedCount > 1 ? 's' : '') . " opened your last campaign but didn't reply.",
                'actionUrl'   => '/contacts',
                'actionLabel' => 'View Contacts',
                'priority'    => 'positive',
                'weight'      => 60,
            ];
        }

        // ---------------------------------------------------------------
        // f) Knowledge base needs update (docs older than 90 days)
        // ---------------------------------------------------------------
        $staleKbDocs = KbDocument::where('workspace_id', $workspaceId)
            ->where('updated_at', '<=', now()->subDays(90))
            ->count();

        if ($staleKbDocs > 0) {
            $insights[] = [
                'id'          => 'stale_kb',
                'icon'        => 'book-open',
                'title'       => 'Knowledge base needs update',
                'description' => "{$staleKbDocs} document" . ($staleKbDocs > 1 ? 's have' : ' has') . " not been updated in over 90 days.",
                'actionUrl'   => '/knowledge-base',
                'actionLabel' => 'Update KB',
                'priority'    => 'suggestion',
                'weight'      => 40,
            ];
        }

        // ---------------------------------------------------------------
        // g) Workflow optimization (high failure rate)
        // ---------------------------------------------------------------
        $failedWorkflows = WorkflowExecution::whereHas('workflow', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($failedWorkflows >= 3) {
            $insights[] = [
                'id'          => 'workflow_failures',
                'icon'        => 'zap',
                'title'       => 'Workflow optimization needed',
                'description' => "{$failedWorkflows} workflow executions failed in the last 7 days. Review logs for errors.",
                'actionUrl'   => '/workflows',
                'actionLabel' => 'View Logs',
                'priority'    => 'attention',
                'weight'      => 80,
            ];
        }

        // ---------------------------------------------------------------
        // h) Team performance — slow response times
        // ---------------------------------------------------------------
        $avgResponse = DB::table('conversations')
            ->where('workspace_id', $workspaceId)
            ->where('first_response_at', '>=', now()->subDays(7))
            ->whereNotNull('first_response_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_minutes')
            ->value('avg_minutes');

        if ($avgResponse !== null && $avgResponse > 120) {
            $hours = round($avgResponse / 60, 1);
            $insights[] = [
                'id'          => 'team_response_time',
                'icon'        => 'clock',
                'title'       => 'Team performance insight',
                'description' => "Average first-response time this week is {$hours}h. Industry benchmark is under 1h.",
                'actionUrl'   => '/analytics',
                'actionLabel' => 'View Analytics',
                'priority'    => 'attention',
                'weight'      => 75,
            ];
        }

        // ---------------------------------------------------------------
        // Filter out dismissed insights
        // ---------------------------------------------------------------
        $insights = array_filter($insights, function ($insight) use ($userId) {
            if (in_array($insight['id'], $this->dismissedIds, true)) {
                return false;
            }

            $cacheKey = "insight_dismissed:{$userId}:{$insight['id']}";
            return ! Cache::has($cacheKey);
        });

        // Sort by weight descending, take top 4
        usort($insights, fn ($a, $b) => ($b['weight'] ?? 0) <=> ($a['weight'] ?? 0));

        return array_slice($insights, 0, 4);
    }

    public function render()
    {
        $insights = $this->generateInsights();

        return view('livewire.dashboard.actionable-insights', [
            'insights' => $insights,
        ]);
    }
}
