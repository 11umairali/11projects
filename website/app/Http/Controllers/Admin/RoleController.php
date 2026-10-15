<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $query = Role::withCount(['permissions', 'users']);

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($guard = $request->input('guard_name')) {
            $query->where('guard_name', $guard);
        }

        $roles = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        $permissions = Permission::orderBy('name')->get()->groupBy(function ($p) {
            return explode('.', $p->name)[0];
        });

        return view('admin.roles.create', compact('permissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'guard_name' => 'nullable|string|in:web,api',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        if (!empty($validated['permissions'])) {
            $permissions = Permission::whereIn('id', $validated['permissions'])->get();
            $role->syncPermissions($permissions);
        }

        SecurityAuditLogger::log('role_created', 'success', auth()->user(), request(), [
            'role' => $role->name,
            'permissions_count' => count($validated['permissions'] ?? []),
        ]);

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' created successfully.");
    }

    public function show(Role $role): View
    {
        $role->load('permissions', 'users');

        return view('admin.roles.show', compact('role'));
    }

    public function edit(Role $role): View
    {
        $permissions = Permission::orderBy('name')->get()->groupBy(function ($p) {
            return explode('.', $p->name)[0];
        });

        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('admin.roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        // Prevent renaming Super Admin
        if ($role->name === 'Super Admin' && $request->input('name') !== 'Super Admin') {
            return back()->with('error', 'Cannot rename the Super Admin role.');
        }

        $validated = $request->validate([
            'name' => "required|string|max:50|unique:roles,name,{$role->id}",
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update(['name' => $validated['name']]);

        $permissions = Permission::whereIn('id', $validated['permissions'] ?? [])->get();
        $role->syncPermissions($permissions);

        SecurityAuditLogger::log('role_updated', 'success', auth()->user(), request(), [
            'role' => $role->name,
            'permissions_count' => $permissions->count(),
        ]);

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' updated successfully.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin') {
            return back()->with('error', 'Cannot delete the Super Admin role.');
        }

        $name = $role->name;
        $role->syncPermissions([]);
        $role->delete();

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$name}' deleted.");
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $roles = Role::whereIn('id', $request->input('ids'))
            ->where('name', '!=', 'Super Admin')
            ->get();

        foreach ($roles as $role) {
            $role->syncPermissions([]);
            $role->delete();
        }

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', $roles->count() . ' role(s) deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $roles = Role::with('permissions')->orderBy('name')->get();

        return response()->streamDownload(function () use ($roles) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Guard', 'Permissions', 'Users Count']);

            foreach ($roles as $role) {
                fputcsv($handle, [
                    $role->name,
                    $role->guard_name,
                    $role->permissions->pluck('name')->implode('|'),
                    $role->users()->count(),
                ]);
            }

            fclose($handle);
        }, 'roles_' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;

            $name = trim($row[0]);
            $guard = trim($row[1] ?? 'web');
            $permissionNames = array_filter(array_map('trim', explode('|', $row[2] ?? '')));

            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => $guard]
            );

            if (!empty($permissionNames)) {
                // Create any missing permissions
                foreach ($permissionNames as $pName) {
                    Permission::firstOrCreate(['name' => $pName, 'guard_name' => $guard]);
                }
                $role->syncPermissions($permissionNames);
            }

            $imported++;
        }

        fclose($handle);

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "{$imported} role(s) imported.");
    }

    /**
     * Legacy helper — still used by AdminMiddleware during migration.
     */
    public const ROLE_PERMISSIONS = [
        'super_admin' => ['dashboard', 'users', 'plans', 'payments', 'coupons', 'tickets', 'audit-log', 'system', 'settings', 'payment-gateways', 'ai-usage', 'email-deliverability', 'ai-providers', 'cms', 'notifications', 'roles', 'permissions', 'security-audit-logs', 'blocked-ips', 'blocked-locations', 'security-settings', 'system-settings', 'frontend-settings'],
        'admin' => ['dashboard', 'users', 'plans', 'payments', 'coupons', 'tickets', 'audit-log', 'payment-gateways', 'ai-usage', 'email-deliverability', 'ai-providers', 'cms', 'notifications'],
        'support' => ['dashboard', 'tickets', 'users', 'notifications'],
    ];

    public const MODULE_LABELS = [
        'dashboard' => 'Dashboard', 'users' => 'User Management', 'plans' => 'Plan Management',
        'payments' => 'Payments', 'coupons' => 'Coupons', 'tickets' => 'Support Tickets',
        'audit-log' => 'Audit Log', 'system' => 'System Health', 'settings' => 'Settings',
        'payment-gateways' => 'Payment Gateways', 'ai-usage' => 'AI Usage',
        'email-deliverability' => 'Email Deliverability', 'ai-providers' => 'AI Providers',
        'cms' => 'CMS / Pages', 'notifications' => 'Notifications', 'roles' => 'Roles',
        'permissions' => 'Permissions', 'security-audit-logs' => 'Security Audit Logs',
        'blocked-ips' => 'Blocked IPs', 'blocked-locations' => 'Blocked Locations',
        'security-settings' => 'Security Settings', 'system-settings' => 'System Settings',
        'frontend-settings' => 'Frontend Settings',
    ];

    public const SUPPORT_VIEW_ONLY = ['users', 'dashboard'];

    public static function resolveModule(string $path): ?string
    {
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'admin/')) {
            $path = substr($path, 6);
        }
        $segment = explode('/', $path)[0] ?? '';
        $map = [
            'payment-gateways' => 'payment-gateways', 'ai-usage' => 'ai-usage',
            'ai-providers' => 'ai-providers', 'email-deliverability' => 'email-deliverability',
            'audit-log' => 'audit-log', 'security-audit-logs' => 'security-audit-logs',
            'blocked-ips' => 'blocked-ips', 'blocked-locations' => 'blocked-locations',
            'security-settings' => 'security-settings', 'system-settings' => 'system-settings',
            'frontend-settings' => 'frontend-settings',
        ];
        return $map[$segment] ?? ($segment ?: null);
    }

    public static function roleHasAccess(string $role, string $module): bool
    {
        return in_array($module, self::ROLE_PERMISSIONS[$role] ?? [], true);
    }

    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => 'Super Admin', 'admin' => 'Admin',
            'moderator' => 'Moderator', 'support' => 'Support',
            default => ucfirst($role),
        };
    }
}
