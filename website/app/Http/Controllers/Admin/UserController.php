<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminMiddleware;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    /**
     * List all users with search, filter, and pagination.
     */
    public function index(Request $request): View
    {
        $query = User::with(['activeWorkspace']);

        // Search by name or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            if ($status === 'suspended') {
                $query->whereNotNull('suspended_at');
            } else {
                $query->where('status', $status)->whereNull('suspended_at');
            }
        }

        // Filter by plan (through workspace subscription)
        if ($planId = $request->input('plan_id')) {
            $query->whereHas('activeWorkspace', function ($q) use ($planId) {
                $q->whereHas('subscription', function ($sub) use ($planId) {
                    $sub->where('plan_id', $planId)->where('status', 'active');
                });
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['name', 'email', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $users = $query->paginate(25)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show user details.
     */
    public function show(Request $request, int $id): View
    {
        $admin = auth()->user();

        // SEC-002: Verify admin has user management permission
        if ($admin->admin_role !== 'super_admin') {
            $module = RoleController::resolveModule('users');
            if (!RoleController::roleHasAccess($admin->admin_role ?? 'support', $module ?? 'users')) {
                abort(403, 'You do not have permission to view user details.');
            }
        }

        $user = User::with([
            'activeWorkspace.subscription.plan',
            'workspaces',
            'socialAccounts',
        ])->findOrFail($id);

        // Get user's workspaces with stats
        $workspaces = $user->workspaces()
            ->withCount(['contacts', 'conversations', 'campaigns'])
            ->get();

        // Login activity (from audit logs)
        $loginHistory = DB::table('audit_logs')
            ->where('actor_id', $user->id)
            ->where('actor_type', 'user')
            ->where('event', 'like', '%login%')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Payment history
        $payments = [];
        if ($user->active_workspace_id) {
            $payments = DB::table('payments')
                ->where('workspace_id', $user->active_workspace_id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }

        return view('admin.users.show', compact('user', 'workspaces', 'loginHistory', 'payments'));
    }

    /**
     * Show the create user form (SnapNest-style with all fields).
     */
    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => \Spatie\Permission\Models\Role::orderBy('name')->get(),
            'plans' => \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Store a newly created user with full profile, roles, plan assignment.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z][A-Za-z\s.\'-]*$/'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => \App\Support\CredentialPolicy::passwordRules(),
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'is_active' => ['sometimes'],
            'email_verified' => ['sometimes'],
            'is_admin' => ['sometimes'],
            'admin_role' => ['nullable', 'string', 'in:super_admin,admin,moderator,support'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'name.regex' => 'Name must start with a letter and contain only letters, spaces, dots, hyphens, or apostrophes.',
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => $request->boolean('is_active') ? 'active' : 'suspended',
            'email_verified_at' => $request->boolean('email_verified') ? now() : null,
            'is_admin' => $request->boolean('is_admin'),
            'admin_role' => $request->boolean('is_admin') ? ($validated['admin_role'] ?? null) : null,
        ];

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $userData['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create($userData);

        // Assign Spatie roles
        $roles = $validated['roles'] ?? [];
        if (!empty($roles)) {
            $user->syncRoles($roles);
        } else {
            // Assign default "User" role if no roles selected
            $defaultRole = \Spatie\Permission\Models\Role::where('name', 'User')->first();
            if ($defaultRole) {
                $user->assignRole($defaultRole);
            }
        }

        // Audit log
        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => $user->id,
            'event' => 'user_created',
            'actor_type' => 'admin',
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()->name,
            'new_values' => json_encode([
                'email' => $user->email,
                'name' => $user->name,
                'roles' => $roles,
                'is_admin' => $userData['is_admin'],
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($request->has('save_and_new')) {
            return redirect()->route('admin.users.create')
                ->with('success', "User {$user->name} created. You can add another.");
        }

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} created successfully.");
    }

    /**
     * Delete a user permanently.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Prevent deleting super admins unless you are one
        if ($user->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can delete other super admins.');
        }

        $name = $user->name;
        $email = $user->email;

        // Audit log before delete
        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => $user->id,
            'event' => 'user_deleted',
            'actor_type' => 'admin',
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()->name,
            'old_values' => json_encode(['email' => $email, 'name' => $name]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Remove roles before delete
        $user->syncRoles([]);
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "User {$name} ({$email}) deleted permanently.");
    }

    /**
     * Impersonate a user (log in as them).
     *
     * SEC-003: Requires password confirmation before impersonation.
     * SEC-002: Prevents impersonating super_admin or equal/higher-level admins.
     *
     * Timeout defaults to 10 minutes (auth.impersonation_minutes) — impersonation
     * should be a brief diagnostic action, not an extended session.
     */
    public function impersonate(Request $request, int $id): RedirectResponse
    {
        // SEC-003: Require password confirmation before impersonation
        $request->validate([
            'confirm_password' => ['required', 'string'],
        ]);

        $admin = auth()->user();

        if (!Hash::check($request->confirm_password, $admin->password)) {
            // SEC-010: Log the failed impersonation attempt
            DB::table('audit_logs')->insert([
                'auditable_type' => 'App\\Models\\User',
                'auditable_id' => $id,
                'event' => 'impersonation_failed',
                'actor_type' => 'admin',
                'actor_id' => $admin->id,
                'actor_name' => $admin->name,
                'new_values' => json_encode([
                    'reason' => 'password_confirmation_failed',
                    'target_user_id' => $id,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::warning('Admin impersonation failed — wrong password', [
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'target_user_id' => $id,
                'ip' => $request->ip(),
            ]);

            return back()->withErrors(['confirm_password' => 'Password confirmation failed.']);
        }

        $user = User::findOrFail($id);

        // SEC-002: Prevent impersonating users with super_admin role
        if ($user->is_admin && $user->admin_role === 'super_admin') {
            DB::table('audit_logs')->insert([
                'auditable_type' => 'App\\Models\\User',
                'auditable_id' => $user->id,
                'event' => 'impersonation_blocked',
                'actor_type' => 'admin',
                'actor_id' => $admin->id,
                'actor_name' => $admin->name,
                'new_values' => json_encode([
                    'reason' => 'target_is_super_admin',
                    'target_email' => $user->email,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::warning('Admin impersonation blocked — target is super_admin', [
                'admin_id' => $admin->id,
                'target_user_id' => $user->id,
                'target_email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return back()->with('error', 'Super admin accounts cannot be impersonated.');
        }

        // SEC-002: Prevent impersonating admins of equal or higher level
        if ($user->is_admin && !AdminMiddleware::canManageRole($admin->admin_role ?? 'support', $user->admin_role ?? 'support')) {
            DB::table('audit_logs')->insert([
                'auditable_type' => 'App\\Models\\User',
                'auditable_id' => $user->id,
                'event' => 'impersonation_blocked',
                'actor_type' => 'admin',
                'actor_id' => $admin->id,
                'actor_name' => $admin->name,
                'new_values' => json_encode([
                    'reason' => 'insufficient_role_level',
                    'actor_role' => $admin->admin_role,
                    'target_role' => $user->admin_role,
                    'target_email' => $user->email,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return back()->with('error', 'You cannot impersonate an admin of equal or higher privilege level.');
        }

        abort_if($request->session()->has('admin_impersonating'), 403, 'Return to your admin account first.');
        $minutes = \App\Services\AdminImpersonation::minutes();
        // Store admin ID and expiration in session so we can return and auto-expire
        $request->session()->put('admin_impersonating', $admin->id);
        $request->session()->put('admin_impersonating_name', $admin->name);
        $request->session()->put('admin_impersonation_expires_at', now()->addMinutes($minutes)->timestamp);

        // Log the impersonation
        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => $user->id,
            'event' => 'user_impersonated',
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'new_values' => json_encode(['impersonated_user' => $user->email]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::warning('Admin impersonation started', [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip' => $request->ip(),
            'expires_at' => now()->addMinutes($minutes)->toIso8601String(),
        ]);

        // Log in as the user on the web guard
        Auth::guard('web')->login($user);

        return redirect()->route('dashboard')
            ->with('warning', "IMPERSONATION ACTIVE: You are logged in as {$user->name} ({$user->email}). This session will expire in {$minutes} minutes. Return to admin panel to stop impersonating.");
    }

    /**
     * Suspend a user.
     */
    public function suspend(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $admin = auth()->user();

        // SEC-002: Prevent suspending admins of equal or higher level
        if ($user->is_admin) {
            if (!AdminMiddleware::canManageRole($admin->admin_role ?? 'support', $user->admin_role ?? 'support')) {
                DB::table('audit_logs')->insert([
                    'auditable_type' => 'App\\Models\\User',
                    'auditable_id' => $user->id,
                    'event' => 'suspension_blocked',
                    'actor_type' => 'admin',
                    'actor_id' => $admin->id,
                    'actor_name' => $admin->name,
                    'new_values' => json_encode([
                        'reason' => 'insufficient_role_level',
                        'actor_role' => $admin->admin_role,
                        'target_role' => $user->admin_role,
                        'target_email' => $user->email,
                    ]),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return redirect()->back()
                    ->with('error', 'You cannot suspend an admin of equal or higher privilege level.');
            }
        }

        $oldStatus = $user->status;

        $user->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        // SEC-010: Enhanced audit logging for suspension
        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => $user->id,
            'event' => 'user_suspended',
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'old_values' => json_encode(['status' => $oldStatus]),
            'new_values' => json_encode(['status' => 'suspended', 'user_email' => $user->email]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "User {$user->name} has been suspended.");
    }

    /**
     * Unsuspend a user.
     */
    public function unsuspend(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $admin = auth()->user();

        // SEC-002: Prevent unsuspending admins of equal or higher level
        if ($user->is_admin) {
            if (!AdminMiddleware::canManageRole($admin->admin_role ?? 'support', $user->admin_role ?? 'support')) {
                DB::table('audit_logs')->insert([
                    'auditable_type' => 'App\\Models\\User',
                    'auditable_id' => $user->id,
                    'event' => 'unsuspension_blocked',
                    'actor_type' => 'admin',
                    'actor_id' => $admin->id,
                    'actor_name' => $admin->name,
                    'new_values' => json_encode([
                        'reason' => 'insufficient_role_level',
                        'actor_role' => $admin->admin_role,
                        'target_role' => $user->admin_role,
                        'target_email' => $user->email,
                    ]),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return redirect()->back()
                    ->with('error', 'You cannot unsuspend an admin of equal or higher privilege level.');
            }
        }

        $user->update([
            'status' => 'active',
            'suspended_at' => null,
        ]);

        // SEC-010: Enhanced audit logging for unsuspension
        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => $user->id,
            'event' => 'user_unsuspended',
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'old_values' => json_encode(['status' => 'suspended']),
            'new_values' => json_encode(['status' => 'active', 'user_email' => $user->email]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "User {$user->name} has been unsuspended.");
    }

    /**
     * Export users as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $users = User::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
            ->when($request->status, function ($q, $s) {
                if ($s === 'suspended') {
                    $q->whereNotNull('suspended_at');
                } else {
                    $q->where('status', $s)->whereNull('suspended_at');
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'users-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($users) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Email', 'Status', 'Created At']);
            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->suspended_at ? 'Suspended' : ucfirst($user->status),
                    $user->created_at->format('Y-m-d H:i'),
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Import users from a CSV file.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');
        $header = fgetcsv($handle);

        if (!$header) {
            fclose($handle);
            return back()->with('error', 'CSV file is empty or unreadable.');
        }

        // Normalize header keys to lowercase
        $header = array_map(fn($h) => strtolower(trim($h)), $header);
        $imported = 0;
        $skipped = 0;

        while ($row = fgetcsv($handle)) {
            if (count($row) !== count($header)) {
                $skipped++;
                continue;
            }

            $data = array_combine($header, $row);
            $email = $data['email'] ?? null;

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'] ?? 'Imported User',
                    'password' => Hash::make(Str::random(16)),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $imported++;
        }
        fclose($handle);

        $message = "{$imported} users imported successfully.";
        if ($skipped > 0) {
            $message .= " {$skipped} rows skipped (invalid or malformed).";
        }

        return back()->with('success', $message);
    }

    /**
     * Bulk delete users.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        // Prevent deleting the current admin
        $ids = collect($request->ids)->reject(fn($id) => (int) $id === auth()->id())->values()->all();

        $adminRole = auth()->user()->admin_role;
        // Filter out current user and users with equal/higher role
        $users = User::whereIn('id', $ids)
            ->where('id', '!=', auth()->id())
            ->get()
            ->filter(function ($user) use ($adminRole) {
                if (!$user->is_admin) return true; // non-admins can be deleted
                return AdminMiddleware::canManageRole($adminRole, $user->admin_role ?? 'support');
            });

        $count = 0;
        foreach ($users as $user) {
            $user->delete();
            $count++;
        }

        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => 0,
            'event' => 'users_bulk_deleted',
            'actor_type' => 'admin',
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()->name,
            'new_values' => json_encode(['deleted_count' => $count, 'ids' => $ids]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', "{$count} users deleted.");
    }
}
