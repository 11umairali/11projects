<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // Use env variable or default for demo — change in production
        $password = env('DEMO_PASSWORD', '12345678');

        // Create demo user
        $user = User::firstOrCreate(
            ['email' => env('DEMO_EMAIL', 'demo@mailtrixy.com')],
            [
                'name' => 'Demo User',
                'password' => \Illuminate\Support\Facades\Hash::make($password),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        $this->command->info('Demo user: demo@mailtrixy.com');
        $this->command->warn("Password: {$password}");
        $this->command->warn('SAVE THIS PASSWORD — it will not be shown again!');

        // Create workspace
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'demo-workspace'],
            [
                'name' => "Demo's Workspace",
                'industry' => 'SaaS / Technology',
                'team_size' => '2-5',
                'onboarding_completed' => true,
                'onboarding_step' => 5,
            ]
        );

        // Attach user as owner (if not already)
        if (!$workspace->members()->where('user_id', $user->id)->exists()) {
            $workspace->members()->attach($user->id, [
                'role' => 'owner',
                'status' => 'offline',
            ]);
        }

        // Set active workspace
        $user->update(['active_workspace_id' => $workspace->id]);

        // Ensure admin user is also a member of the demo workspace
        $admin = User::where('email', 'admin@mailtrixy.com')->first();
        if ($admin) {
            if (!$workspace->members()->where('user_id', $admin->id)->exists()) {
                $workspace->members()->attach($admin->id, [
                    'role' => 'admin',
                    'status' => 'offline',
                ]);
            }
            if (!$admin->active_workspace_id) {
                $admin->update(['active_workspace_id' => $workspace->id]);
            }
        }
    }
}
