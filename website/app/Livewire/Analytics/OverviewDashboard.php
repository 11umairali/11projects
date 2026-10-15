<?php

namespace App\Livewire\Analytics;

use App\Models\Conversation;
use App\Models\Message;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class OverviewDashboard extends Component
{
    use AuthorizesWorkspaceActions;

    #[Url]
    public string $dateRange = '7d';

    public ?string $customFrom = null;
    public ?string $customTo = null;

    public function mount(): void
    {
        if (! $this->authorizeWorkspaceAction('view')) {
            return;
        }
    }

    public function updatedDateRange(): void
    {
        if ($this->dateRange !== 'custom') {
            $this->customFrom = null;
            $this->customTo = null;
        }
    }

    public function applyCustomRange(): void
    {
        $this->dateRange = 'custom';
    }

    protected function getDateBounds(): array
    {
        if ($this->dateRange === 'custom' && $this->customFrom && $this->customTo) {
            return [
                Carbon::parse($this->customFrom)->startOfDay(),
                Carbon::parse($this->customTo)->endOfDay(),
            ];
        }

        $end = now();
        $start = match ($this->dateRange) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(7)->startOfDay(),
            '30d' => now()->subDays(30)->startOfDay(),
            '90d' => now()->subDays(90)->startOfDay(),
            default => now()->subDays(7)->startOfDay(),
        };

        return [$start, $end];
    }

    protected function workspaceId(): ?int
    {
        return auth()->user()->active_workspace_id;
    }

    public function render()
    {
        $workspaceId = $this->workspaceId();
        [$startDate, $endDate] = $this->getDateBounds();

        // === Summary Cards (combined into single query to reduce DB round-trips) ===
        $summaryStats = Conversation::where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('COUNT(*) as total_conversations')
            ->selectRaw('SUM(CASE WHEN resolved_at IS NOT NULL AND resolved_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as resolved_conversations', [$startDate, $endDate])
            ->selectRaw('AVG(CASE WHEN first_response_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, first_response_at) END) as avg_first_response')
            ->selectRaw('AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, resolved_at) END) as avg_resolution')
            ->first();

        $totalConversations = $summaryStats->total_conversations ?? 0;
        $newConversations = $totalConversations;
        $resolvedConversations = $summaryStats->resolved_conversations ?? 0;
        $avgFirstResponseFormatted = $this->formatDuration($summaryStats->avg_first_response);
        $avgResolutionFormatted = $this->formatDuration($summaryStats->avg_resolution);

        // Customer satisfaction (placeholder - will use chat widget ratings when available)
        $csatScore = '--';

        // === Channel Breakdown ===
        $channelBreakdown = Conversation::where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('channel', DB::raw('COUNT(*) as count'))
            ->groupBy('channel')
            ->orderByDesc('count')
            ->get();

        $totalChannelCount = $channelBreakdown->sum('count');
        $channelData = $channelBreakdown->map(function ($item) use ($totalChannelCount) {
            $channelColors = [
                'email' => 'bg-primary-500',
                'whatsapp' => 'bg-green-500',
                'chat' => 'bg-accent-500',
                'telegram' => 'bg-blue-400',
                'sms' => 'bg-yellow-500',
                'slack' => 'bg-purple-500',
            ];

            return [
                'name' => ucfirst($item->channel),
                'count' => $item->count,
                'pct' => $totalChannelCount > 0 ? round(($item->count / $totalChannelCount) * 100, 1) : 0,
                'color' => $channelColors[$item->channel] ?? 'bg-gray-400',
            ];
        });

        // === Response by type (sender_type breakdown) ===
        $senderBreakdown = Message::where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('sender_type', ['contact', 'agent', 'ai'])
            ->select('sender_type', DB::raw('COUNT(*) as count'))
            ->groupBy('sender_type')
            ->orderByDesc('count')
            ->get();

        $totalMessages = $senderBreakdown->sum('count');
        $senderData = $senderBreakdown->map(function ($item) use ($totalMessages) {
            $senderColors = [
                'contact' => 'bg-blue-500',
                'agent' => 'bg-green-500',
                'ai' => 'bg-secondary-500',
            ];
            $senderLabels = [
                'contact' => 'Customer',
                'agent' => 'Agent',
                'ai' => 'AI',
            ];

            return [
                'name' => $senderLabels[$item->sender_type] ?? ucfirst($item->sender_type),
                'count' => $item->count,
                'pct' => $totalMessages > 0 ? round(($item->count / $totalMessages) * 100, 1) : 0,
                'color' => $senderColors[$item->sender_type] ?? 'bg-gray-400',
            ];
        });

        // === Recent Activity ===
        // The previous query read from `audit_logs` which isn't actually
        // populated anywhere in this app — that's why the panel always
        // showed empty. The real activity stream lives in the Spatie
        // `activity_log` table (same source the /activity page uses), so
        // we read from there. Workspace scoping happens via the
        // `properties->workspace_id` JSON field that the listeners stamp on
        // every event, with a fallback to the morphed subject's workspace.
        try {
            $recentActivity = DB::table('activity_log')
                ->where(function ($q) use ($workspaceId) {
                    $q->whereJsonContains('properties->workspace_id', $workspaceId)
                        ->orWhereJsonContains('properties->workspace_id', (string) $workspaceId);
                })
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    $props = json_decode($log->properties ?? '{}', true) ?: [];
                    $event = $log->event ?: $log->log_name;
                    return [
                        'event' => ucfirst(str_replace(['.', '_', '-'], ' ', $event ?? 'activity')),
                        'actor' => $props['causer_name']
                            ?? ($log->causer_id ? 'User #' . $log->causer_id : 'System'),
                        'type' => $log->subject_type ? class_basename($log->subject_type) : null,
                        'time' => Carbon::parse($log->created_at)->diffForHumans(),
                    ];
                });
        } catch (\Exception) {
            // If the activity_log table isn't present yet (fresh install
            // before Spatie migration ran), fall back to an empty list
            // instead of crashing the whole analytics page.
            $recentActivity = collect();
        }

        return view('livewire.analytics.overview-dashboard', [
            'totalConversations' => $totalConversations,
            'newConversations' => $newConversations,
            'resolvedConversations' => $resolvedConversations,
            'avgFirstResponse' => $avgFirstResponseFormatted,
            'avgResolution' => $avgResolutionFormatted,
            'csatScore' => $csatScore,
            'channelData' => $channelData,
            'senderData' => $senderData,
            'recentActivity' => $recentActivity,
        ]);
    }

    protected function formatDuration(?float $seconds): string
    {
        if (! $seconds || $seconds <= 0) {
            return '--';
        }

        if ($seconds < 60) {
            return round($seconds) . 's';
        }

        if ($seconds < 3600) {
            return round($seconds / 60, 1) . 'm';
        }

        return round($seconds / 3600, 1) . 'h';
    }
}
