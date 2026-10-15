<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\RoleController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Role hierarchy levels — higher value = more privileged.
     * Used to enforce that admins cannot manage users at or above their own level.
     */
    private const ROLE_HIERARCHY = [
        'super_admin' => 4,
        'admin'       => 3,
        'moderator'   => 2,
        'support'     => 1,
    ];

    /**
     * Determine whether the actor role outranks the target role.
     *
     * Returns true only when the actor is strictly above the target in the
     * hierarchy. Unknown roles are treated as level 0 (unprivileged).
     */
    public static function canManageRole(string $actorRole, string $targetRole): bool
    {
        $actorLevel  = self::ROLE_HIERARCHY[$actorRole]  ?? 0;
        $targetLevel = self::ROLE_HIERARCHY[$targetRole] ?? 0;

        return $actorLevel > $targetLevel;
    }

    /**
     * Get the numeric hierarchy level for a role (0 for unknown roles).
     */
    public static function getRoleLevel(string $role): int
    {
        return self::ROLE_HIERARCHY[$role] ?? 0;
    }

    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = auth()->user();

        if (!auth()->check() || !$user || !$user->is_admin) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            abort(403, 'Unauthorized.');
        }

        // FIX-014: Check if admin account is active (not suspended/banned)
        if ($user->status !== 'active') {
            auth()->logout();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account suspended.'], 403);
            }
            abort(403, 'Your admin account has been suspended.');
        }

        // Optional explicit role check — e.g. middleware('admin:super_admin')
        if ($role && $user->admin_role !== $role) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Insufficient admin privileges.'], 403);
            }
            abort(403, 'Insufficient admin privileges.');
        }

        // ── Role-based module access control ──────────────────────────
        // super_admin bypasses all checks (treat null admin_role as super_admin for backward compat)
        if ($user->admin_role === 'super_admin') {
            return $next($request);
        }

        $module = RoleController::resolveModule($request->path());

        // Allow the base /admin redirect (no module)
        if ($module === null || $module === '') {
            return $next($request);
        }

        // Check if the user's admin_role has access to this module
        $adminRole = $user->admin_role ?? 'support';

        if (! RoleController::roleHasAccess($adminRole, $module)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You do not have permission to access this section.',
                ], 403);
            }

            return redirect()->route('admin.dashboard')
                ->with('error', 'You do not have permission to access that section.');
        }

        // For support role: block mutating requests on view-only modules
        if ($adminRole === 'support' && in_array($module, RoleController::SUPPORT_VIEW_ONLY, true)) {
            if (! $request->isMethod('GET')) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Your role has read-only access to this section.',
                    ], 403);
                }

                return redirect()->back()
                    ->with('error', 'Your role has read-only access to this section.');
            }
        }

        return $next($request);
    }
}
