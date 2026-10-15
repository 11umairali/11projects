<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    use AuthorizesApiActions;
    /**
     * Overview analytics for the workspace.
     */
    public function overview(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;
        $days = min((int) $request->input('days', 30), 90);
        $since = now()->subDays($days);

        // Conversation stats
        $conversationStats = Conversation::where('workspace_id', $workspaceId)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'open' THEN 1 END) as open,
                COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread,
                COUNT(CASE WHEN created_at >= ? THEN 1 END) as new_in_period
            ", [$since])
            ->first();

        // Average response time (only for conversations with first_response_at)
        $avgResponseTime = Conversation::where('workspace_id', $workspaceId)
            ->whereNotNull('first_response_at')
            ->where('created_at', '>=', $since)
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_minutes')
            ->value('avg_minutes');

        // Message volume by day
        $messagesByDay = Message::where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        // Contact stats
        $contactStats = Contact::where('workspace_id', $workspaceId)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                COUNT(CASE WHEN created_at >= ? THEN 1 END) as new_in_period
            ", [$since])
            ->first();

        // Channel breakdown
        $channelBreakdown = Conversation::where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $since)
            ->selectRaw('channel, COUNT(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel');

        // Campaign stats
        $campaignStats = Campaign::where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $since)
            ->selectRaw("
                COUNT(*) as total,
                SUM(sent_count) as total_sent,
                SUM(opened_count) as total_opened,
                SUM(clicked_count) as total_clicked,
                SUM(bounced_count) as total_bounced
            ")
            ->first();

        return response()->json([
            'data' => [
                'period_days' => $days,
                'conversations' => [
                    'total' => $conversationStats->total,
                    'open' => $conversationStats->open,
                    'closed' => $conversationStats->closed,
                    'pending' => $conversationStats->pending,
                    'unread' => $conversationStats->unread,
                    'new_in_period' => $conversationStats->new_in_period,
                    'avg_response_time_minutes' => $avgResponseTime ? round($avgResponseTime, 1) : null,
                ],
                'contacts' => [
                    'total' => $contactStats->total,
                    'active' => $contactStats->active,
                    'new_in_period' => $contactStats->new_in_period,
                ],
                'messages_by_day' => $messagesByDay,
                'channel_breakdown' => $channelBreakdown,
                'campaigns' => [
                    'total' => $campaignStats->total,
                    'total_sent' => (int) $campaignStats->total_sent,
                    'total_opened' => (int) $campaignStats->total_opened,
                    'total_clicked' => (int) $campaignStats->total_clicked,
                    'total_bounced' => (int) $campaignStats->total_bounced,
                ],
            ],
        ]);
    }

    /**
     * AI-specific analytics.
     */
    public function ai(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'admin')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;
        $days = min((int) $request->input('days', 30), 90);
        $since = now()->subDays($days);

        // AI usage from ai_usage_logs
        $aiUsage = DB::table('ai_usage_logs')
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $since)
            ->selectRaw("
                COUNT(*) as total_requests,
                SUM(tokens_in) as total_tokens_in,
                SUM(tokens_out) as total_tokens_out,
                SUM(cost) as total_cost,
                AVG(response_time_ms) as avg_response_time_ms
            ")
            ->first();

        // AI usage by provider
        $byProvider = DB::table('ai_usage_logs')
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $since)
            ->selectRaw('provider, COUNT(*) as requests, SUM(cost) as cost')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');

        // AI usage by day
        $byDay = DB::table('ai_usage_logs')
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as requests, SUM(cost) as cost')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // AI-handled conversations
        $aiConversations = Conversation::where('workspace_id', $workspaceId)
            ->where('is_ai_handled', true)
            ->where('created_at', '>=', $since)
            ->count();

        // Average AI confidence from messages
        $avgConfidence = Message::where('workspace_id', $workspaceId)
            ->where('sender_type', 'ai')
            ->where('created_at', '>=', $since)
            ->whereNotNull('ai_confidence')
            ->avg('ai_confidence');

        return response()->json([
            'data' => [
                'period_days' => $days,
                'total_requests' => (int) ($aiUsage->total_requests ?? 0),
                'total_tokens_in' => (int) ($aiUsage->total_tokens_in ?? 0),
                'total_tokens_out' => (int) ($aiUsage->total_tokens_out ?? 0),
                'total_cost' => round((float) ($aiUsage->total_cost ?? 0), 4),
                'avg_response_time_ms' => $aiUsage->avg_response_time_ms
                    ? round($aiUsage->avg_response_time_ms)
                    : null,
                'ai_handled_conversations' => $aiConversations,
                'avg_confidence' => $avgConfidence ? round($avgConfidence, 1) : null,
                'by_provider' => $byProvider,
                'by_day' => $byDay,
            ],
        ]);
    }

    /**
     * Team performance analytics.
     */
    public function team(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'admin')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;
        $days = min((int) $request->input('days', 30), 90);
        $since = now()->subDays($days);

        // Per-agent stats: conversations assigned, closed, avg response time
        $agentStats = Conversation::where('conversations.workspace_id', $workspaceId)
            ->whereNotNull('assigned_to')
            ->where('conversations.created_at', '>=', $since)
            ->join('users', 'users.id', '=', 'conversations.assigned_to')
            ->selectRaw("
                users.id as user_id,
                users.name as user_name,
                COUNT(*) as assigned_count,
                COUNT(CASE WHEN conversations.status = 'closed' THEN 1 END) as closed_count,
                AVG(CASE
                    WHEN conversations.first_response_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, conversations.created_at, conversations.first_response_at)
                END) as avg_response_minutes
            ")
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('assigned_count')
            ->get();

        // Per-agent message counts
        $agentMessages = Message::where('messages.workspace_id', $workspaceId)
            ->where('sender_type', 'user')
            ->where('messages.created_at', '>=', $since)
            ->whereNotNull('sender_id')
            ->join('users', 'users.id', '=', 'messages.sender_id')
            ->selectRaw('users.id as user_id, COUNT(*) as messages_sent')
            ->groupBy('users.id')
            ->pluck('messages_sent', 'user_id');

        // Build team performance array
        $teamPerformance = $agentStats->map(function ($agent) use ($agentMessages) {
            return [
                'user_id' => $agent->user_id,
                'name' => $agent->user_name,
                'assigned_conversations' => $agent->assigned_count,
                'closed_conversations' => $agent->closed_count,
                'resolution_rate' => $agent->assigned_count > 0
                    ? round(($agent->closed_count / $agent->assigned_count) * 100, 1)
                    : 0,
                'avg_response_time_minutes' => $agent->avg_response_minutes
                    ? round($agent->avg_response_minutes, 1)
                    : null,
                'messages_sent' => $agentMessages[$agent->user_id] ?? 0,
            ];
        });

        // Overall team totals
        $totals = [
            'total_conversations' => $teamPerformance->sum('assigned_conversations'),
            'total_closed' => $teamPerformance->sum('closed_conversations'),
            'total_messages_sent' => $teamPerformance->sum('messages_sent'),
            'team_members' => $teamPerformance->count(),
        ];

        return response()->json([
            'data' => [
                'period_days' => $days,
                'totals' => $totals,
                'members' => $teamPerformance->values(),
            ],
        ]);
    }
}
