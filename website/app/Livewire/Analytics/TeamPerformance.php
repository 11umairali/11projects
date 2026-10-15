<?php

namespace App\Livewire\Analytics;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class TeamPerformance extends Component
{
    use AuthorizesWorkspaceActions;

    #[Url]
    public string $dateRange = '7d';

    public string $sortField = 'conversations';
    public string $sortDirection = 'desc';

    public ?string $customFrom = null;
    public ?string $customTo = null;

    public function mount(): void
    {
        // Team performance with individual agent metrics requires admin+ access
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }
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

        // Get all workspace members (agents)
        $workspace = Workspace::find($workspaceId);
        $members = $workspace ? $workspace->members()->get() : collect();

        if ($members->isEmpty()) {
            $agentData = collect();
        } else {
            $memberIds = $members->pluck('id');

            // Batch query: conversations handled per agent
            $conversationsHandledMap = Conversation::where('workspace_id', $workspaceId)
                ->whereIn('assigned_to', $memberIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('assigned_to, COUNT(*) as cnt')
                ->groupBy('assigned_to')
                ->pluck('cnt', 'assigned_to');

            // Batch query: conversations resolved per agent
            $conversationsResolvedMap = Conversation::where('workspace_id', $workspaceId)
                ->whereIn('assigned_to', $memberIds)
                ->whereNotNull('resolved_at')
                ->whereBetween('resolved_at', [$startDate, $endDate])
                ->selectRaw('assigned_to, COUNT(*) as cnt')
                ->groupBy('assigned_to')
                ->pluck('cnt', 'assigned_to');

            // Batch query: avg response time per agent
            $avgResponseMap = Conversation::where('workspace_id', $workspaceId)
                ->whereIn('assigned_to', $memberIds)
                ->whereNotNull('first_response_at')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('assigned_to, AVG(TIMESTAMPDIFF(SECOND, created_at, first_response_at)) as avg_seconds')
                ->groupBy('assigned_to')
                ->pluck('avg_seconds', 'assigned_to');

            // Batch query: messages sent per agent
            $messagesSentMap = Message::where('workspace_id', $workspaceId)
                ->where('sender_type', 'agent')
                ->whereIn('sender_id', $memberIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('sender_id, COUNT(*) as cnt')
                ->groupBy('sender_id')
                ->pluck('cnt', 'sender_id');

            // Build agent data from pre-fetched maps (no N+1)
            $agentData = $members->map(function ($member) use (
                $conversationsHandledMap, $conversationsResolvedMap, $avgResponseMap, $messagesSentMap
            ) {
                $userId = $member->id;
                $avgResponseSeconds = $avgResponseMap->get($userId);

                return [
                    'id' => $userId,
                    'name' => $member->name,
                    'initials' => $member->initials,
                    'role' => $member->pivot->role ?? 'member',
                    'conversations' => $conversationsHandledMap->get($userId, 0),
                    'resolved' => $conversationsResolvedMap->get($userId, 0),
                    'avg_response_seconds' => $avgResponseSeconds,
                    'avg_response_formatted' => $this->formatDuration($avgResponseSeconds),
                    'messages_sent' => $messagesSentMap->get($userId, 0),
                ];
            });
        }

        // Sort agent data
        $agentData = $agentData->sortBy(function ($agent) {
            return match ($this->sortField) {
                'name' => $agent['name'],
                'conversations' => $agent['conversations'],
                'resolved' => $agent['resolved'],
                'response_time' => $agent['avg_response_seconds'] ?? PHP_INT_MAX,
                'messages' => $agent['messages_sent'],
                default => $agent['conversations'],
            };
        }, SORT_REGULAR, $this->sortDirection === 'desc');

        return view('livewire.analytics.team-performance', [
            'agents' => $agentData->values(),
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
