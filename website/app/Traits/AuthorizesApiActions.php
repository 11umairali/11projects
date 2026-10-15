<?php

namespace App\Traits;

use App\Exceptions\PlanLimitReachedException;
use App\Models\Workspace;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Provides workspace role authorization and plan limit enforcement for API controllers.
 *
 * Role hierarchy: owner (4) > admin (3) > agent (2) > viewer (1)
 * Permission levels:
 *   - view:      viewer+   (GET index, show, export)
 *   - create:    agent+    (POST store, import)
 *   - interact:  agent+    (PUT update, assign, reply)
 *   - manage:    admin+    (DELETE destroy, settings, send campaigns)
 *   - dangerous: owner only
 */
trait AuthorizesApiActions
{
    /**
     * Deny the request unless the user has at least the given workspace role.
     * Returns null if authorized, or a 403 JsonResponse if denied.
     *
     * Usage:
     *   if ($deny = $this->denyUnlessRole($request, 'create')) return $deny;
     */
    protected function denyUnlessRole(Request $request, string $level): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->active_workspace_id) {
            return response()->json([
                'error' => 'No active workspace.',
                'code' => 'NO_WORKSPACE',
            ], 403);
        }

        $role = $this->resolveWorkspaceRole($user->id, $user->active_workspace_id);

        $roleWeight = match ($role) {
            'owner' => 4,
            'admin' => 3,
            'agent' => 2,
            'viewer' => 1,
            default => 0,
        };

        $requiredWeight = match ($level) {
            'view' => 1,
            'interact', 'create' => 2,
            'manage' => 3,
            'dangerous' => 4,
            default => 4,
        };

        if ($roleWeight < $requiredWeight) {
            return response()->json([
                'error' => 'Insufficient permissions.',
                'code' => 'FORBIDDEN',
                'required_role' => $level,
            ], 403);
        }

        return null;
    }

    /**
     * Deny the request unless the workspace's plan allows creating more of this resource.
     * Returns null if allowed, or a 403 JsonResponse if at limit.
     *
     * Usage:
     *   if ($deny = $this->denyUnlessPlanAllows($request, 'contacts')) return $deny;
     */
    protected function denyUnlessPlanAllows(Request $request, string $featureKey): ?JsonResponse
    {
        $user = $request->user();
        $workspace = Workspace::find($user->active_workspace_id);

        if (!$workspace) {
            return response()->json([
                'error' => 'No active workspace.',
                'code' => 'NO_WORKSPACE',
            ], 403);
        }

        $limitService = app(PlanLimitService::class);

        try {
            $limitService->assertCanCreate($workspace, $featureKey);
        } catch (PlanLimitReachedException $e) {
            return response()->json([
                'error' => 'Plan limit reached',
                'message' => $e->getMessage(),
                'code' => 'PLAN_LIMIT_REACHED',
                'feature' => $featureKey,
                'limit' => $limitService->getLimit($workspace, $featureKey),
                'upgrade_url' => config('app.url') . '/settings/billing',
            ], 403);
        }

        return null;
    }

    /**
     * Resolve the user's workspace role with short-lived caching (10s).
     */
    private function resolveWorkspaceRole(int $userId, int $workspaceId): string
    {
        $cacheKey = "ws_role:{$userId}:{$workspaceId}";

        return Cache::remember($cacheKey, 10, function () use ($userId, $workspaceId) {
            return \Illuminate\Support\Facades\DB::table('workspace_members')
                ->where('workspace_id', $workspaceId)
                ->where('user_id', $userId)
                ->value('role') ?? 'viewer';
        });
    }
}
