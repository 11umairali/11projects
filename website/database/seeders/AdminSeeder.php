<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Use env variable or generate a secure random password for production
        $password = env('ADMIN_PASSWORD', Str::random(16));

        // Create admin in the 'admins' table (used by the 'admin' guard)
        Admin::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@mailtrixy.com')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'role' => 'super_admin',
            ]
        );

        $this->command->info('Admin created: admin@mailtrixy.com');
        $this->command->warn("Password: {$password}");
        $this->command->warn('SAVE THIS PASSWORD — it will not be shown again!');

        // Also create a matching user in the 'users' table (for sidebar admin link)
        $user = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@mailtrixy.com')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // Set admin fields explicitly — they are NOT mass-assignable
        $user->is_admin = true;
        $user->admin_role = 'super_admin';
        $user->save();

        // Default system settings
        $settings = [
            // General
            ['key' => 'site_name', 'value' => config('app.name', 'MailTrixy'), 'group' => 'general'],
            ['key' => 'site_tagline', 'value' => 'Your AI Communication Brain', 'group' => 'general'],
            ['key' => 'site_description', 'value' => 'AI-powered communication automation that thinks, replies, and grows your business', 'group' => 'general'],
            ['key' => 'default_language', 'value' => 'en', 'group' => 'general'],
            ['key' => 'timezone', 'value' => 'UTC', 'group' => 'general'],
            ['key' => 'default_currency', 'value' => 'USD', 'group' => 'general'],
            ['key' => 'support_email', 'value' => 'support@example.com', 'group' => 'general'],
            // Branding
            ['key' => 'primary_color', 'value' => '#4F46E5', 'group' => 'branding'],
            ['key' => 'secondary_color', 'value' => '#7C3AED', 'group' => 'branding'],
            ['key' => 'accent_color', 'value' => '#06B6D4', 'group' => 'branding'],
            // AI
            ['key' => 'default_ai_provider', 'value' => 'openai', 'group' => 'ai'],
            ['key' => 'default_ai_model', 'value' => 'gpt-4o-mini', 'group' => 'ai'],
            ['key' => 'ai_temperature', 'value' => '0.7', 'group' => 'ai'],
            ['key' => 'ai_max_tokens', 'value' => '4096', 'group' => 'ai'],
            // Auth
            ['key' => 'registration_enabled', 'value' => 'true', 'group' => 'auth'],
            ['key' => 'social_login_enabled', 'value' => 'true', 'group' => 'auth'],
            ['key' => 'require_email_verification', 'value' => 'true', 'group' => 'auth'],
            ['key' => 'default_plan', 'value' => 'enterprise', 'group' => 'auth'],
            ['key' => 'trial_days', 'value' => '14', 'group' => 'auth'],
            // Security
            ['key' => 'max_login_attempts', 'value' => '5', 'group' => 'security'],
            ['key' => 'lockout_duration', 'value' => '15', 'group' => 'security'],
            ['key' => 'session_lifetime', 'value' => '120', 'group' => 'security'],
            ['key' => 'password_min_length', 'value' => '8', 'group' => 'security'],
            ['key' => 'require_2fa_admins', 'value' => 'false', 'group' => 'security'],
            // Legal
            ['key' => 'cookie_consent_enabled', 'value' => 'true', 'group' => 'legal'],
            ['key' => 'gdpr_data_export_enabled', 'value' => 'true', 'group' => 'legal'],
            ['key' => 'gdpr_account_deletion_enabled', 'value' => 'true', 'group' => 'legal'],
            // Maintenance
            ['key' => 'maintenance_mode', 'value' => 'false', 'group' => 'maintenance'],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
