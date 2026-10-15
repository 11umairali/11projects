<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Permission::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($guard = $request->input('guard_name')) {
            $query->where('guard_name', $guard);
        }

        $permissions = $query->orderBy('name')->paginate(50)->withQueryString();

        // Group for display
        $grouped = Permission::orderBy('name')->get()->groupBy(function ($p) {
            return explode('.', $p->name)[0];
        });

        return view('admin.permissions.index', compact('permissions', 'grouped'));
    }

    public function create(): View
    {
        return view('admin.permissions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80|unique:permissions,name',
            'guard_name' => 'nullable|string|in:web,api',
        ]);

        Permission::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.permissions.index')
            ->with('success', "Permission '{$validated['name']}' created.");
    }

    public function show(Permission $permission): View
    {
        $permission->loadCount('roles');
        return view('admin.permissions.show', compact('permission'));
    }

    public function edit(Permission $permission): View
    {
        return view('admin.permissions.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => "required|string|max:80|unique:permissions,name,{$permission->id}",
        ]);

        $permission->update(['name' => $validated['name']]);
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.permissions.index')
            ->with('success', "Permission updated.");
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $name = $permission->name;
        $permission->delete();
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.permissions.index')
            ->with('success', "Permission '{$name}' deleted.");
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $count = Permission::whereIn('id', $request->input('ids'))->delete();
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "{$count} permission(s) deleted.");
    }

    public function export(): StreamedResponse
    {
        $permissions = Permission::orderBy('name')->get();

        return response()->streamDownload(function () use ($permissions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Guard', 'Roles Count']);

            foreach ($permissions as $p) {
                fputcsv($handle, [$p->name, $p->guard_name, $p->roles()->count()]);
            }

            fclose($handle);
        }, 'permissions_' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 1) continue;
            $name = trim($row[0]);
            $guard = trim($row[1] ?? 'web');
            if ($name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
                $imported++;
            }
        }

        fclose($handle);
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "{$imported} permission(s) imported.");
    }
}
