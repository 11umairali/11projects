<?php

namespace App\Livewire\Dashboard;

use App\Models\AiConfig;
use App\Models\ChannelIntegration;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\EmailAccount;
use App\Models\KbDocument;
use App\Models\Message;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class DashboardStats extends Component
{
    use AuthorizesWorkspaceActions;

    public function mount(): void
    {
        if (! $this->authorizeWorkspaceAction('view')) {
            return;
        }
    }

    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-6 animate-pulse">
            <div class="h-32 bg-gray-200 rounded-2xl"></div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="h-32 bg-gray-200 rounded-2xl"></div>
                <div class="h-32 bg-gray-200 rounded-2xl"></div>
                <div class="h-32 bg-gray-200 rounded-2xl"></div>
                <div class="h-32 bg-gray-200 rounded-2xl"></div>
            </div>
            <div class="h-64 bg-gray-200 rounded-2xl"></div>
        </div>
        HTML;
    }

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;

        // Cache core stats for 30 seconds to reduce DB load on frequent renders
        $coreStats = Cache::remember("dashboard:core-stats:{$workspaceId}", 30, function () use ($workspaceId) {
            $stats = DB::table('conversations')
                ->where('workspace_id', $workspaceId)
                ->selectRaw('COUNT(*) as conversation_count')
                ->selectRaw('AVG(CASE WHEN first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, first_response_at) END) as avg_time')
                ->first();

            return [
                'conversation_count' => $stats->conversation_count ?? 0,
                'avg_time' => $stats->avg_time,
                'ai_replies_count' => Message::where('workspace_id', $workspaceId)->where('sender_type', 'ai')->count(),
                'contact_count' => Contact::where('workspace_id', $workspaceId)->count(),
            ];
        });

        $conversationCount = $coreStats['conversation_count'];
        $avgResponseTime = $coreStats['avg_time'];
        $aiRepliesCount = $coreStats['ai_replies_count'];
        $contactCount = $coreStats['contact_count'];

        if ($avgResponseTime !== null) {
            if ($avgResponseTime < 60) {
                $avgResponseTimeDisplay = round($avgResponseTime) . 'm';
            } else {
                $avgResponseTimeDisplay = round($avgResponseTime / 60, 1) . 'h';
            }
        } else {
            $avgResponseTimeDisplay = '—';
        }

        // Recent conversations (real data, not hardcoded)
        $recentConversations = $workspaceId
            ? Conversation::where('workspace_id', $workspaceId)
                ->with('contact')
                ->latest('last_message_at')
                ->limit(5)
                ->get()
            : collect();

        // 7-day conversation trend for sparklines
        $dailyTrend = DB::table('conversations')
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();

        // Pad to 7 days if fewer results
        while (count($dailyTrend) < 7) {
            array_unshift($dailyTrend, 0);
        }
        $dailyTrend = array_slice($dailyTrend, -7);

        // Normalize to percentages (0-100) for bar heights
        $maxTrend = max(1, max($dailyTrend));
        $sparklineHeights = array_map(fn ($v) => max(10, round(($v / $maxTrend) * 100)), $dailyTrend);

        // Getting started checklist — cache for 5 minutes to avoid hitting DB every render
        $hasWorkspace = auth()->user()->active_workspace_id !== null;
        $checklist = $workspaceId ? cache()->remember("dashboard:checklist:{$workspaceId}", 300, function () use ($workspaceId) {
            return [
                'hasEmailAccount' => EmailAccount::where('workspace_id', $workspaceId)->exists(),
                'hasKbDocs' => KbDocument::where('workspace_id', $workspaceId)->exists(),
                'hasSentAiReply' => Message::where('workspace_id', $workspaceId)->where('sender_type', 'ai')->exists(),
                'hasAiConfig' => AiConfig::where('workspace_id', $workspaceId)->exists(),
                'hasChannels' => ChannelIntegration::where('workspace_id', $workspaceId)->where('status', 'active')->exists(),
                'hasTeamMembers' => DB::table('workspace_members')->where('workspace_id', $workspaceId)->count() > 1,
            ];
        }) : [];
        $hasEmailAccount = $checklist['hasEmailAccount'] ?? false;
        $hasKbDocs = $checklist['hasKbDocs'] ?? false;
        $hasSentAiReply = $checklist['hasSentAiReply'] ?? false;
        $hasAiConfig = $checklist['hasAiConfig'] ?? false;
        $hasChannels = $checklist['hasChannels'] ?? false;
        $hasTeamMembers = $checklist['hasTeamMembers'] ?? false;

        // AI spending cap warning
        $aiConfig = AiConfig::where('workspace_id', $workspaceId)->first();
        $aiCostLimit = $aiConfig->monthly_cost_limit ?? 0;
        $aiMonthlyCost = 0;
        $aiCostPercent = 0;
        if ($aiCostLimit > 0) {
            $aiMonthlyCost = (float) DB::table('ai_usage_logs')
                ->where('workspace_id', $workspaceId)
                ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [now()->format('Y-m')])
                ->sum('cost');
            $aiCostPercent = round(($aiMonthlyCost / $aiCostLimit) * 100);
        }

        // Plan usage warning — handle unlimited (free) plans correctly
        $subscription = Subscription::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->with('plan.planFeatures')
            ->first();
        $aiRepliesLimit = \App\Support\BillingPolicy::isFree() ? null : ($subscription?->plan?->featureLimit('ai_replies') ?? 50);
        $currentPeriod = now()->format('Y-m');
        $aiRepliesUsed = UsageRecord::where('workspace_id', $workspaceId)
            ->where('feature_key', 'ai_replies')
            ->where('period', $currentPeriod)
            ->sum('quantity');

        // Handle unlimited plans: if limit is 0 or null (unlimited), show "Unlimited"
        if ($aiRepliesLimit <= 0) {
            $usagePercent = 0;
            $aiRepliesLimitDisplay = 'Unlimited';
        } else {
            $usagePercent = round(($aiRepliesUsed / $aiRepliesLimit) * 100);
            $aiRepliesLimitDisplay = (string) $aiRepliesLimit;
        }

        return view('livewire.dashboard.dashboard-stats', [
            'conversationCount' => $conversationCount,
            'aiRepliesCount' => $aiRepliesCount,
            'contactCount' => $contactCount,
            'avgResponseTimeDisplay' => $avgResponseTimeDisplay,
            'sparklineHeights' => $sparklineHeights,
            'recentConversations' => $recentConversations,
            'hasWorkspace' => $hasWorkspace,
            'hasEmailAccount' => $hasEmailAccount,
            'hasKbDocs' => $hasKbDocs,
            'hasSentAiReply' => $hasSentAiReply,
            'hasAiConfig' => $hasAiConfig,
            'hasChannels' => $hasChannels,
            'hasTeamMembers' => $hasTeamMembers,
            'aiRepliesUsed' => $aiRepliesUsed,
            'aiRepliesLimit' => $aiRepliesLimit,
            'aiRepliesLimitDisplay' => $aiRepliesLimitDisplay,
            'usagePercent' => $usagePercent,
            'aiCostLimit' => $aiCostLimit,
            'aiMonthlyCost' => $aiMonthlyCost,
            'aiCostPercent' => $aiCostPercent,
        ]);
    }
}
