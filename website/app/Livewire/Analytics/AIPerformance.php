<?php

namespace App\Livewire\Analytics;

use App\Models\Conversation;
use App\Models\Message;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class AIPerformance extends Component
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

        // === AI Summary Cards ===

        // AI replies sent
        $aiRepliesSent = Message::where('workspace_id', $workspaceId)
            ->where('sender_type', 'ai')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Total AI messages for accuracy calculation
        $totalAiMessages = Message::where('workspace_id', $workspaceId)
            ->where('sender_type', 'ai')
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Accuracy: AI messages that were sent (ai_status = 'approved' or 'sent') without edits
        $approvedAi = (clone $totalAiMessages)
            ->whereIn('ai_status', ['approved', 'sent'])
            ->count();

        $totalAiCount = (clone $totalAiMessages)->count();
        $accuracy = $totalAiCount > 0 ? round(($approvedAi / $totalAiCount) * 100, 1) : 0;

        // Average confidence
        $avgConfidence = Message::where('workspace_id', $workspaceId)
            ->where('sender_type', 'ai')
            ->whereNotNull('ai_confidence')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->avg('ai_confidence');
        $avgConfidence = $avgConfidence ? round($avgConfidence, 1) : 0;

        // Escalation rate: AI conversations that got assigned to an agent
        $aiConversations = Conversation::where('workspace_id', $workspaceId)
            ->where('is_ai_handled', true)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $escalatedConversations = Conversation::where('workspace_id', $workspaceId)
            ->where('is_ai_handled', true)
            ->whereNotNull('assigned_to')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $escalationRate = $aiConversations > 0
            ? round(($escalatedConversations / $aiConversations) * 100, 1)
            : 0;

        // Total AI cost
        $totalCost = Message::where('workspace_id', $workspaceId)
            ->where('sender_type', 'ai')
            ->whereNotNull('ai_cost')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('ai_cost');

        // === Topic Performance ===
        // Group AI replies by conversation tags to show accuracy per topic
        $topicPerformance = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('messages.workspace_id', $workspaceId)
            ->where('messages.sender_type', 'ai')
            ->whereNotNull('conversations.tags')
            ->whereBetween('messages.created_at', [$startDate, $endDate])
            ->select(
                'conversations.tags',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN messages.ai_status IN ("approved", "sent") THEN 1 ELSE 0 END) as approved'),
                DB::raw('AVG(messages.ai_confidence) as avg_confidence')
            )
            ->groupBy('conversations.tags')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $tags = json_decode($row->tags, true) ?? [];
                $topTag = $tags[0] ?? 'Uncategorized';
                return [
                    'topic' => $topTag,
                    'total' => $row->total,
                    'accuracy' => $row->total > 0 ? round(($row->approved / $row->total) * 100, 1) : 0,
                    'avg_confidence' => round($row->avg_confidence ?? 0, 1),
                ];
            });

        // === KB Gaps: Low confidence AI messages ===
        $kbGaps = Message::where('workspace_id', $workspaceId)
            ->where('sender_type', 'ai')
            ->where('ai_confidence', '<', 50)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('conversation')
            ->orderBy('ai_confidence')
            ->limit(10)
            ->get()
            ->map(function ($message) {
                return [
                    'subject' => $message->conversation?->subject ?? $message->subject ?? 'No subject',
                    'confidence' => $message->ai_confidence,
                    'date' => $message->created_at->format('M j, g:i A'),
                ];
            });

        // === Cost Breakdown ===
        $costBreakdown = Message::where('workspace_id', $workspaceId)
            ->where('sender_type', 'ai')
            ->whereNotNull('ai_cost')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'ai_provider',
                'ai_model',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(ai_cost) as total_cost'),
                DB::raw('SUM(ai_tokens_in) as total_tokens_in'),
                DB::raw('SUM(ai_tokens_out) as total_tokens_out')
            )
            ->groupBy('ai_provider', 'ai_model')
            ->orderByDesc('total_cost')
            ->get();

        return view('livewire.analytics.ai-performance', [
            'aiRepliesSent' => $aiRepliesSent,
            'accuracy' => $accuracy,
            'avgConfidence' => $avgConfidence,
            'escalationRate' => $escalationRate,
            'totalCost' => $totalCost,
            'topicPerformance' => $topicPerformance,
            'kbGaps' => $kbGaps,
            'costBreakdown' => $costBreakdown,
        ]);
    }
}
