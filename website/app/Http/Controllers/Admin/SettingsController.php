<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Plan;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{   
    /**
     * Display the system settings page with all tab data pre-loaded.
     */
    public function index(): View
    {
        // Load plans for the "default plan" dropdown
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'monthly_price', 'yearly_price']);

        return view('admin.settings', compact('plans'));
    }

    /**
     * Persist admin settings to the system_settings key-value store.
     *
     * Sensitive values (API keys, SMTP password) are encrypted at rest
     * and are never overwritten with blanks to prevent accidental clearing.
     */
    public function update(Request $request): RedirectResponse
    {
        if ($request->has('default_plan')) {
            $request->validate(['default_plan' => 'required|string']);
            \App\Support\BillingPolicy::setDefault($request->input('default_plan'));
        }
        $allowed = [
            // General tab
            'site_name', 'site_tagline', 'site_description',
            'default_language', 'timezone', 'default_currency',
            'support_email', 'support_phone',
            // Branding tab
            'primary_color', 'secondary_color', 'accent_color',
            'logo_light', 'logo_dark', 'favicon',
            // Email / SMTP tab
            'mail_driver', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
            'smtp_encryption', 'smtp_from_address', 'smtp_from_name',
            'mail_from_name', 'mail_from_email',
            // Authentication tab
            'registration_enabled', 'social_login_enabled',
            'require_email_verification', 'trial_days',
            // Security tab
            'max_login_attempts', 'lockout_duration', 'require_2fa_admins',
            'session_lifetime',
            'hcaptcha_site_key', 'hcaptcha_secret_key',
            // AI Configuration — the tab was removed (single source of truth
            // is now /admin/ai-providers). The active keys read by AIManager
            // are `ai_default_provider` / `ai_default_model` / `ai_monthly_budget`
            // and the per-provider api keys, all listed below in the
            // "Legacy / backward compat" block. The 4 legacy keys
            // (default_ai_provider, default_ai_model, ai_temperature,
            // ai_max_tokens) used to be written by the removed AI Config
            // tab but were never actually read anywhere — dropped from
            // the whitelist.
            // Integrations tab
            'google_client_id', 'google_client_secret',
            'microsoft_client_id', 'microsoft_client_secret',
            'github_client_id', 'github_client_secret',
            'stripe_key', 'stripe_secret', 'stripe_webhook_secret',
            'pusher_app_id', 'pusher_key', 'pusher_secret', 'pusher_cluster',
            'whatsapp_phone_number_id', 'whatsapp_access_token', 'whatsapp_verify_token',
            'twilio_sid', 'twilio_auth_token', 'twilio_phone_number',
            'telegram_bot_token',
            'slack_client_id', 'slack_client_secret', 'slack_signing_secret',
            'salesforce_client_id', 'salesforce_client_secret',
            'hubspot_client_id', 'hubspot_client_secret',
            // Integration enabled toggles
            'integration_google_enabled', 'integration_microsoft_enabled',
            'integration_github_enabled', 'integration_stripe_enabled',
            'integration_pusher_enabled', 'integration_whatsapp_enabled',
            'integration_twilio_enabled', 'integration_telegram_enabled',
            // The Slack / Salesforce / HubSpot toggles were missing from here,
            // which silently dropped their value on save — users saw the
            // toggle flip back off on every refresh even after hitting Save.
            'integration_slack_enabled', 'integration_salesforce_enabled',
            'integration_hubspot_enabled',
            // Legal / GDPR tab
            'cookie_consent_enabled', 'cookie_consent_message',
            'gdpr_data_export_enabled', 'gdpr_account_deletion_enabled',
            'terms_of_service_url', 'privacy_policy_url',
            'gdpr_cookie_enabled', 'gdpr_cookie_message',
            'gdpr_cookie_policy_url', 'gdpr_data_retention_days',
            // Maintenance tab
            'maintenance_mode', 'maintenance_message', 'maintenance_allowed_ips',
            // Legacy / backward compat
            'app_url', 'date_format', 'meta_title', 'meta_description',
            'default_user_role', 'recaptcha_enabled', 'email_verification',
            'ai_default_provider', 'ai_default_model', 'ai_monthly_budget',
            'openai_api_key', 'anthropic_api_key', 'gemini_api_key', 'mistral_api_key',
            'pinecone_api_key', 'pinecone_host', 'pinecone_index',
            // Notification toggles
            'notify_new_user', 'notify_new_payment', 'notify_failed_payment',
            'notify_subscription_canceled', 'notify_trial_ending',
            'notify_support_ticket', 'notify_system_alert',
            'notify_ai_usage_high', 'notify_email_bounce', 'notify_quota_warning',
            'notify_payment_success', 'notify_payment_failed', 'notify_new_ticket',
            'notify_plan_change', 'notify_user_suspension', 'notify_ai_budget',
            'notify_high_bounce', 'notify_daily_summary',
            'slack_webhook_url',
            'slack_notify_new_user', 'slack_notify_payment', 'slack_notify_system_alert',
            'slack_notify_ticket', 'slack_notify_ai_usage',
            'slack_notify_payment_failed', 'slack_notify_new_ticket', 'slack_notify_ai_budget',
            'admin_notification_emails',
            // PWA tab
            'pwa_enabled', 'pwa_app_name', 'pwa_short_name', 'pwa_start_url',
            'pwa_theme_color', 'pwa_background_color',
            'pwa_icon', 'pwa_splash',
            // Custom Code tab — admin-injected CSS/JS that renders site-wide
            // into the layout's <head> and just before </body>. Output is
            // raw (not escaped) since the admin is the author by definition.
            // `head_code` and `footer_code` already existed in the frontend
            // layout; we extend with `custom_css` (a dedicated CSS textarea).
            'custom_css', 'head_code', 'footer_code',
        ];

        // Toggle fields normalized to 'true'/'false'
        $booleanKeys = [
            'registration_enabled', 'social_login_enabled', 'require_email_verification',
            'email_verification', 'recaptcha_enabled', 'require_2fa_admins',
            'maintenance_mode', 'cookie_consent_enabled',
            'gdpr_cookie_enabled', 'gdpr_data_export_enabled', 'gdpr_account_deletion_enabled',
            'integration_google_enabled', 'integration_microsoft_enabled',
            'integration_github_enabled', 'integration_stripe_enabled',
            'integration_pusher_enabled', 'integration_whatsapp_enabled',
            'integration_twilio_enabled', 'integration_telegram_enabled',
            'integration_slack_enabled', 'integration_salesforce_enabled',
            'integration_hubspot_enabled',
            'notify_new_user', 'notify_new_payment', 'notify_failed_payment',
            'notify_subscription_canceled', 'notify_trial_ending',
            'notify_support_ticket', 'notify_system_alert',
            'notify_ai_usage_high', 'notify_email_bounce', 'notify_quota_warning',
            'notify_payment_success', 'notify_payment_failed', 'notify_new_ticket',
            'notify_plan_change', 'notify_user_suspension', 'notify_ai_budget',
            'notify_high_bounce', 'notify_daily_summary',
            'slack_notify_new_user', 'slack_notify_payment', 'slack_notify_system_alert',
            'slack_notify_ticket', 'slack_notify_ai_usage',
            'slack_notify_payment_failed', 'slack_notify_new_ticket', 'slack_notify_ai_budget',
            'pwa_enabled',
        ];

        // Derive group from the request (tab context)
        $group = $request->input('_settings_group', 'general');

        // Handle file uploads (logos, favicon) — store to public disk, save path
        $fileFields = ['logo_light', 'logo_dark', 'favicon', 'pwa_icon', 'pwa_splash'];
        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                $folder = str_starts_with($field, 'pwa_') ? 'settings/pwa' : 'settings/branding';
                $path = $request->file($field)->store($folder, 'public');
                DB::table('system_settings')->updateOrInsert(
                    ['key' => $field],
                    ['value' => $path, 'group' => str_starts_with($field, 'pwa_') ? 'pwa' : 'branding', 'updated_at' => now()]
                );
            }
        }

        foreach ($request->only($allowed) as $key => $value) {
            // Skip file fields — already handled above
            if (in_array($key, $fileFields)) {
                continue;
            }
            // Skip empty values for encrypted keys to preserve existing secrets
            if (in_array($key, SystemSetting::ENCRYPTED_KEYS) && ($value === null || $value === '')) {
                continue;
            }

            if (in_array($key, $booleanKeys)) {
                $storeValue = $request->boolean($key) ? 'true' : 'false';
            } elseif (in_array($key, SystemSetting::ENCRYPTED_KEYS)) {
                $storeValue = encrypt($value);
            } else {
                $storeValue = $value ?? '';
            }

            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $storeValue, 'group' => $group, 'updated_at' => now()]
            );
        }

        SystemSetting::clearCache();

        return back()->with('success', 'Settings saved successfully.');
    }

    /**
     * Send a test email to verify SMTP configuration.
     */
    public function testSmtp(Request $request): RedirectResponse
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            // Read what was just saved (not a copy cached for up to 60s) and apply it exactly
            // the way real system mail does, so the test proves what real emails will do.
            SystemSetting::clearCache();
            $driver = \App\Services\Mail\AdminMailConfig::apply() ?? config('mail.default');

            if ($driver === 'log') {
                return redirect()->back()->with('error',
                    'Mail driver is "Log": messages are only written to storage/logs/laravel.log and are NOT delivered. '
                    . 'Choose SMTP, enter your server details, save, then test again.');
            }

            // Drop any mailer already built with old settings, then send via the SAME mailer
            // that was just configured (Mail::raw() would use the default one from .env).
            Mail::purge($driver);

            Mail::mailer($driver)->raw(
                'This is a test email from ' . config('app.name') . ' to verify your SMTP configuration is working correctly.' .
                "\n\nSent at: " . now()->format('Y-m-d H:i:s T'),
                function ($message) use ($request) {
                    $message->to($request->input('test_email'))
                        ->subject(config('app.name') . ' SMTP Test - ' . now()->format('Y-m-d H:i'));
                }
            );

            return redirect()->back()->with('success', 'Test email accepted by the mail server for ' . $request->input('test_email') . '. If it does not arrive, check spam and the mail server log.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'SMTP test failed: ' . $e->getMessage());
        }
    }

    /**
     * Toggle application maintenance mode on/off.
     *
     * Why this isn't simple:
     *
     * Laravel's `php artisan down --secret=XXX` writes a flag file that
     * blocks ALL requests with a 503, including the admin's own browser.
     * To bypass, the visitor has to hit `/XXX` once — that route sets a
     * signed `laravel_maintenance` cookie which whitelists their session
     * for 12 hours. Without that cookie, even the admin who flipped the
     * switch can't reach the site (they'd have to delete
     * storage/framework/down by FTP — bad).
     *
     * So when ENABLING:
     *   1. We generate the secret + call `down`
     *   2. Persist the secret to system_settings so the admin can fetch
     *      it later from this same admin route (admin routes are NOT
     *      exempted by the framework — but our admin group's auth check
     *      runs BEFORE the maintenance middleware in our middleware
     *      stack, so /admin/* still works for logged-in admins via the
     *      bypass cookie)
     *   3. Redirect the admin directly to the bypass URL — that single
     *      hop sets the cookie and bounces them back to /admin/dashboard
     *      so they're never locked out.
     */
    public function toggleMaintenance(Request $request): RedirectResponse
    {
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
            \App\Models\SystemSetting::set('maintenance_secret', '');
            \App\Models\SystemSetting::set('maintenance_started_at', '');
            return redirect()->back()->with('success', 'Maintenance mode disabled. Site is now live.');
        }

        $secret = Str::random(32);

        try {
            Artisan::call('down', [
                '--secret' => $secret,
                // Refresh framework cache so the down file is re-read
                '--render' => 'errors::503',
            ]);
        } catch (\Throwable $e) {
            // Older Laravel versions don't support --render; fall back.
            Artisan::call('down', ['--secret' => $secret]);
        }

        \App\Models\SystemSetting::set('maintenance_secret', $secret);
        \App\Models\SystemSetting::set('maintenance_started_at', now()->toIso8601String());

        // Redirect to the bypass URL — Laravel's maintenance handler
        // detects the matching secret in the path, sets the bypass cookie,
        // and 302-redirects the user to the site root WITHOUT showing the
        // 503. This means the admin who triggered maintenance never sees
        // the "We'll be right back" page and stays signed in.
        $bypassUrl = rtrim(config('app.url'), '/') . '/' . $secret;

        return redirect($bypassUrl)->with([
            'success' => 'Maintenance mode enabled. You have been granted bypass access — bookmark the URL in your address bar to regain access from any device.',
            'maintenance_bypass_url' => $bypassUrl,
            'maintenance_secret' => $secret,
        ]);
    }

    /**
     * AI usage statistics dashboard.
     */
    public function aiUsage()
    {
        $stats = DB::table('ai_usage_logs')
            ->selectRaw("COUNT(*) as total_requests, COALESCE(SUM(tokens_in + tokens_out), 0) as total_tokens, COALESCE(SUM(cost), 0) as total_cost")
            ->first();

        $byProvider = DB::table('ai_usage_logs')
            ->selectRaw("provider, model, COUNT(*) as requests, COALESCE(SUM(cost), 0) as cost, COALESCE(SUM(tokens_in + tokens_out), 0) as tokens")
            ->groupBy('provider', 'model')
            ->orderByDesc('requests')
            ->limit(10)
            ->get();

        return view('admin.ai-usage', compact('stats', 'byProvider'));
    }

    /**
     * Email deliverability statistics dashboard.
     */
    public function emailDeliverability()
    {
        $stats = DB::table('messages')
            ->where('direction', 'outbound')
            ->selectRaw("COUNT(*) as total, SUM(delivery_status = 'sent' OR delivery_status = 'delivered') as delivered, SUM(delivery_status = 'bounced') as bounced, SUM(delivery_status = 'failed') as failed")
            ->first();

        $recentBounces = Message::where('delivery_status', 'bounced')
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.email-deliverability', compact('stats', 'recentBounces'));
    }
}
