<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-logs admin mutating actions to the `audit_logs` table.
 *
 * Runs AFTER the request completes (terminate-style, but inline so we
 * can read the response status).  Only logs successful mutations
 * (2xx status on POST/PUT/PATCH/DELETE).  GET requests are skipped
 * to avoid flooding the audit trail with read-only page loads.
 *
 * Skipped routes:
 *   - audit-log pages (avoid recursive logging)
 *   - dashboard (high-frequency read-only)
 */
class LogAdminActivity
{
    /** HTTP methods that represent a mutation worth auditing. */
    private const MUTATION_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Route name patterns to exclude from logging. */
    private const SKIP_PATTERNS = [
        'admin.audit',      // audit-log viewing routes
        'admin.dashboard',  // dashboard (read-only, high-frequency)
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->logIfApplicable($request, $response);

        return $response;
    }

    private function logIfApplicable(Request $request, Response $response): void
    {
        // Only log mutations — skip all GET/HEAD/OPTIONS.
        if (!in_array($request->method(), self::MUTATION_METHODS, true)) {
            return;
        }

        // Only log successful responses (2xx).
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return;
        }

        // Must have an authenticated admin user.
        $admin = $this->resolveAdmin($request);
        if (!$admin) {
            return;
        }

        // Skip excluded route patterns.
        $routeName = $request->route()?->getName() ?? '';
        foreach (self::SKIP_PATTERNS as $pattern) {
            if (str_starts_with($routeName, $pattern)) {
                return;
            }
        }

        $this->writeAuditLog($request, $admin, $routeName);
    }

    /**
     * Resolve the admin actor — supports both default guard (User with
     * is_admin flag) and the dedicated `admin` guard.
     */
    private function resolveAdmin(Request $request): ?object
    {
        // Try the admin guard first (separate admins table).
        if (Auth::guard('admin')->check()) {
            return Auth::guard('admin')->user();
        }

        // Fall back to default guard with is_admin flag.
        $user = $request->user();
        if ($user && !empty($user->is_admin)) {
            return $user;
        }

        return null;
    }

    private function writeAuditLog(Request $request, object $admin, string $routeName): void
    {
        try {
            $event = $this->resolveEvent($request->method());
            $auditableType = $this->resolveAuditableType($routeName);
            $auditableId = $this->resolveAuditableId($request);

            DB::table('audit_logs')->insert([
                'auditable_type' => $auditableType,
                'auditable_id'   => $auditableId,
                'event'          => $event,
                'actor_type'     => $this->resolveActorType($admin),
                'actor_id'       => $admin->id,
                'actor_name'     => $admin->name ?? $admin->email ?? 'Unknown',
                'old_values'     => null,
                'new_values'     => $this->sanitizePayload($request),
                'ip_address'     => $request->ip(),
                'user_agent'     => $request->userAgent(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit logging must never break the request — degrade silently.
            Log::warning('LogAdminActivity: failed to write audit log', [
                'error'  => $e->getMessage(),
                'route'  => $routeName,
                'method' => $request->method(),
            ]);
        }
    }

    /**
     * Map HTTP method to a human-readable event name.
     */
    private function resolveEvent(string $method): string
    {
        return match ($method) {
            'POST'   => 'created',
            'PUT'    => 'updated',
            'PATCH'  => 'updated',
            'DELETE' => 'deleted',
            default  => 'unknown',
        };
    }

    /**
     * Extract a readable auditable type from the route name.
     *
     * e.g. `admin.users.update` -> `user`
     *      `admin.coupons.store` -> `coupon`
     *      `admin.settings.update` -> `settings`
     */
    private function resolveAuditableType(string $routeName): ?string
    {
        if (empty($routeName)) {
            return null;
        }

        // Strip the 'admin.' prefix and the trailing action segment.
        $parts = explode('.', $routeName);

        // Remove 'admin' prefix if present.
        if (($parts[0] ?? '') === 'admin') {
            array_shift($parts);
        }

        // Remove the last segment (action verb: store, update, destroy, etc.)
        if (count($parts) > 1) {
            array_pop($parts);
        }

        $resource = implode('.', $parts);

        // Singularize common plurals for cleaner log entries.
        if (str_ends_with($resource, 's') && !str_ends_with($resource, 'ss')) {
            $resource = rtrim($resource, 's');
        }

        return $resource ?: null;
    }

    /**
     * Try to extract the resource ID from route parameters.
     */
    private function resolveAuditableId(Request $request): ?int
    {
        $params = $request->route()?->parameters() ?? [];

        if (empty($params)) {
            return null;
        }

        // Take the last route parameter value — typically the resource ID.
        $lastParam = end($params);

        // If it is an Eloquent model, grab its key.
        if (is_object($lastParam) && method_exists($lastParam, 'getKey')) {
            return (int) $lastParam->getKey();
        }

        // If it is a numeric string/int, use it directly.
        if (is_numeric($lastParam)) {
            return (int) $lastParam;
        }

        return null;
    }

    /**
     * Determine actor_type string for the audit log.
     */
    private function resolveActorType(object $admin): string
    {
        $class = get_class($admin);

        // Dedicated Admin model.
        if (str_contains($class, 'Admin')) {
            return 'admin';
        }

        // Regular User acting as admin.
        return 'user';
    }

    /**
     * Sanitize the request payload for storage — strip sensitive fields.
     */
    private function sanitizePayload(Request $request): ?string
    {
        $data = $request->except([
            'password',
            'password_confirmation',
            'current_password',
            'secret',
            'token',
            'api_key',
            'api_secret',
            '_token',
            '_method',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
        ]);

        if (empty($data)) {
            return null;
        }

        // Truncate large values to prevent bloated audit rows.
        array_walk_recursive($data, function (&$value) {
            if (is_string($value) && strlen($value) > 500) {
                $value = substr($value, 0, 500) . '...[truncated]';
            }
        });

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
