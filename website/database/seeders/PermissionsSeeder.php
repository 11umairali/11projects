<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'users'                => ['view', 'create', 'update', 'delete', 'export', 'import'],
            'roles'                => ['view', 'create', 'update', 'delete', 'export', 'import'],
            'permissions'          => ['view', 'create', 'update', 'delete', 'export', 'import'],
            'plans'                => ['view', 'create', 'update', 'delete', 'export', 'import'],
            'payments'             => ['view', 'export', 'import', 'refund'],
            'coupons'              => ['view', 'create', 'update', 'delete', 'export', 'import'],
            'tickets'              => ['view', 'create', 'update', 'delete', 'export', 'import'],
            'audit-logs'           => ['view', 'export'],
            'security-audit-logs'  => ['view', 'create', 'delete', 'export'],
            'blocked-ips'          => ['view', 'create', 'delete', 'export', 'import'],
            'blocked-locations'    => ['view', 'create', 'update', 'delete', 'export', 'import'],
            'settings'             => ['view', 'update'],
            'security-settings'    => ['view', 'update'],
            'system-settings'      => ['view', 'update'],
            'payment-gateways'     => ['view', 'update'],
            'ai-providers'         => ['view', 'update'],
            'ai-usage'             => ['view', 'export'],
            'email-deliverability' => ['view'],
            'cms'                  => ['view', 'update'],
            'notifications'        => ['view'],
            'system'               => ['view'],
            'frontend-settings'    => ['view', 'update'],
        ];

        $count = 0;

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name'       => "{$module}.{$action}",
                    'guard_name' => 'web',
                ]);
                $count++;
            }
        }

        // Clear cached permissions so changes take effect immediately
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info("Permissions seeder complete: {$count} permissions ensured.");
    }
}
