<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Automatically checks Spatie permissions based on route names.
 *
 * Route name format: admin.{module}.{action}
 * Maps to permission: {module}.{action-mapped}
 *
 * Action mapping:
 *   index, show       → view
 *   create, store     → create
 *   edit, update      → update
 *   destroy, bulk-destroy → delete
 *   export            → export
 *   import            → import
 *   refund            → refund
 *   toggle, configure → update
 */
class EnsureAdminPermission
{
    /**
     * Map route action names to permission action names.
     */
    private const ACTION_MAP = [
        'index'        => 'view',
        'show'         => 'view',
        'create'       => 'create',
        'store'        => 'create',
        'edit'         => 'update',
        'update'       => 'update',
        'destroy'      => 'delete',
        'bulk-destroy' => 'delete',
        'export'       => 'export',
        'import'       => 'import',
        'refund'       => 'refund',
        'toggle'       => 'update',
        'configure'    => 'update',
    ];

    /**
     * Routes/modules that don't require specific permission checks.
     * These are accessible to any authenticated admin.
     */
    private const EXEMPT_ROUTES = [
        'admin.dashboard',
        'admin.login',
        'admin.admin-logout',
        'admin.test-dashboard',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user || !$user->is_admin) {
            abort(403, 'Unauthorized.');
        }

        // Super Admin bypasses all permission checks
        if ($user->admin_role === 'super_admin' || $user->hasRole('Super Admin')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return $next($request);
        }

        // Exempt routes don't need permission checks
        if (in_array($routeName, self::EXEMPT_ROUTES, true)) {
            return $next($request);
        }

        // Parse route name: admin.{module}.{action}
        $permission = $this->resolvePermission($routeName);

        if ($permission && !$user->can($permission)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }

            return redirect()->route('admin.dashboard')
                ->with('error', 'You do not have permission to access that section.');
        }

        return $next($request);
    }

    /**
     * Resolve a route name to a Spatie permission string.
     *
     * admin.users.index     → users.view
     * admin.roles.create    → roles.create
     * admin.settings.update → settings.update
     * admin.blocked-ips.store → blocked-ips.create
     */
    private function resolvePermission(string $routeName): ?string
    {
        // Remove 'admin.' prefix
        if (!str_starts_with($routeName, 'admin.')) {
            return null;
        }

        $parts = explode('.', substr($routeName, 6));

        if (count($parts) < 2) {
            // Single-segment route like admin.settings → settings.view
            $module = $parts[0] ?? '';
            return $module ? "{$module}.view" : null;
        }

        // Handle compound module names: blocked-ips, security-settings, etc.
        // Last segment is the action, everything before is the module
        $action = array_pop($parts);
        $module = implode('-', $parts);

        // Handle special cases where module has dots in route name
        // e.g., admin.payment-gateways.toggle → payment-gateways.update
        $mappedAction = self::ACTION_MAP[$action] ?? $action;

        return "{$module}.{$mappedAction}";
    }
}
