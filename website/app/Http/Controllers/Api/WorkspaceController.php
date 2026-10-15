<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    /**
     * Show the current user's active workspace details.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaceId = $user->active_workspace_id;

        if (!$workspaceId) {
            return response()->json(['message' => 'No active workspace.'], 404);
        }

        $workspace = Workspace::withCount([
            'contacts',
            'conversations',
            'emailAccounts',
            'campaigns',
            'workflows',
        ])
            ->with(['subscription.plan'])
            ->find($workspaceId);

        if (!$workspace) {
            return response()->json(['message' => 'Workspace not found.'], 404);
        }

        $memberCount = $workspace->members()->count();
        $userRole = $user->workspaceRole($workspaceId);

        return response()->json([
            'data' => [
                'id' => $workspace->id,
                'uuid' => $workspace->uuid,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'logo_path' => $workspace->logo_path,
                'industry' => $workspace->industry,
                'team_size' => $workspace->team_size,
                'timezone' => $workspace->timezone,
                'onboarding_completed' => $workspace->onboarding_completed,
                'settings' => $workspace->settings,
                'counts' => [
                    'contacts' => $workspace->contacts_count,
                    'conversations' => $workspace->conversations_count,
                    'email_accounts' => $workspace->email_accounts_count,
                    'campaigns' => $workspace->campaigns_count,
                    'workflows' => $workspace->workflows_count,
                    'members' => $memberCount,
                ],
                'subscription' => $workspace->subscription ? [
                    'plan_name' => $workspace->subscription->plan?->name,
                    'plan_slug' => $workspace->subscription->plan?->slug,
                    'status' => $workspace->subscription->status,
                    'billing_cycle' => $workspace->subscription->billing_cycle,
                    'trial_ends_at' => $workspace->subscription->trial_ends_at?->toIso8601String(),
                    'current_period_end' => $workspace->subscription->current_period_end?->toIso8601String(),
                ] : null,
                'your_role' => $userRole,
                'created_at' => $workspace->created_at?->toIso8601String(),
            ],
        ]);
    }
}
