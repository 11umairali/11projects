<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // --- Super Admin: ALL permissions ---
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // --- Admin: everything EXCEPT sensitive role/permission/system management ---
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $adminExcluded = [
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
            'security-settings.update',
            'system-settings.update',
            'system.view',
        ];

        $adminPermissions = Permission::whereNotIn('name', $adminExcluded)->pluck('name')->toArray();
        $admin->syncPermissions($adminPermissions);

        // --- Moderator: limited operational access ---
        $moderator = Role::firstOrCreate(['name' => 'Moderator', 'guard_name' => 'web']);

        $moderatorPermissions = [
            'users.view',
            'plans.view',
            'payments.view',
            'tickets.view',
            'tickets.create',
            'tickets.update',
            'audit-logs.view',
            'notifications.view',
            'ai-usage.view',
        ];

        $moderator->syncPermissions($moderatorPermissions);

        // --- Support: minimal ticket-focused access ---
        $support = Role::firstOrCreate(['name' => 'Support', 'guard_name' => 'web']);

        $supportPermissions = [
            'tickets.view',
            'tickets.create',
            'tickets.update',
            'notifications.view',
            'users.view',
        ];

        $support->syncPermissions($supportPermissions);

        // --- User: no admin permissions ---
        $user = Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);
        $user->syncPermissions([]);

        // Clear cached permissions so changes take effect immediately
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Role-permission assignments seeder complete.');
    }
}
