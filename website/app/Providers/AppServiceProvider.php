<?php

namespace App\Providers;

use App\Helpers\SvgSanitizer;
use App\Http\Responses\LogoutResponse;
use App\Listeners\IntegrationNotificationListener;
use App\Listeners\WorkflowTriggerListener;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Tag;
use App\Models\Workspace;
use App\Observers\MessageObserver;
use App\Observers\PlanObserver;
use App\Observers\TagObserver;
use App\Observers\WorkspaceObserver;
use App\Mailbox\ImapMailbox;
use App\Mailbox\Mailbox;
use App\Mailbox\MailboxCredentials;
use App\Mailbox\MailboxException;
use App\Mailbox\MailSender;
use App\Services\CyberPanelMailbox;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Services\Friends\ContactNames::class);

        // Bind StripeService as a singleton (reuses the same Stripe client)
        $this->app->singleton(\App\Services\Billing\StripeService::class);

        // Bind FileSecurityService as a singleton
        $this->app->singleton(\App\Services\FileSecurityService::class);

        // Register custom logout response to clean up impersonation sessions
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);

        // ── @dahimail.com mail engine (ported from the webmail app) ──
        $this->app->singleton(CyberPanelMailbox::class, fn () => new CyberPanelMailbox(
            domain: config('dahify.domain'),
            connectionName: config('dahify.cyberpanel.connection'),
            vmailRoot: config('dahify.cyberpanel.vmail_root'),
            bcryptCost: config('dahify.cyberpanel.bcrypt_cost'),
        ));

        $this->app->scoped(MailboxCredentials::class, fn ($app) => new MailboxCredentials($app['session.store']));

        // The signed-in user's own mailbox, opened with the password kept
        // in their session (see FortifyServiceProvider::authenticateUsing
        // and Actions/Fortify/CreateNewUser). Only meaningful for users
        // with a username — the legacy admin account (real Gmail address)
        // has no mailbox here.
        $this->app->scoped(Mailbox::class, function ($app) {
            $user = $app['auth']->user();
            $password = $app->make(MailboxCredentials::class)->password();

            if (! $user || ! $user->username || $password === null) {
                throw MailboxException::authFailed();
            }

            return new ImapMailbox(config('dahify.imap'), $user->email, $password);
        });

        $this->app->singleton(MailSender::class, fn () => new MailSender(config('dahify.smtp')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Laravel\Sanctum\Sanctum::authenticateAccessTokensUsing(function ($token, bool $valid) {
            if (!in_array('mobile-session', $token->abilities ?? [], true)) return $valid;
            return $token->tokenable instanceof \App\Models\User
                && (!$token->expires_at || !$token->expires_at->isPast());
        });
        \App\Models\FriendRequest::observe(\App\Observers\FriendRequestObserver::class);
        \App\Models\Message::observe(\App\Observers\MessageNotificationObserver::class);
        // An older event cache can omit this listener. Register a fallback only
        // after all providers have booted, so discovery cannot add a duplicate.
        $this->app->booted(function () {
            $event = \Illuminate\Notifications\Events\NotificationSent::class;
            $listener = \App\Listeners\SendPushForNotification::class;
            foreach (Event::getRawListeners()[$event] ?? [] as $registered) {
                if ($registered === $listener || $registered === $listener.'@handle'
                    || $registered === [$listener, 'handle']) return;
            }
            Event::listen($event, $listener);
        });
        // Super Admin bypasses all Spatie permission checks (like SnapNest)
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('Super Admin')) {
                return true;
            }
        });

        // ── Per-user rate limiters (fall back to IP for guests) ──────
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(600)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(600)->by($request->user()?->id ?: $request->ip());
        });

        // Configure public disk URL for subdirectory deployment support
        $this->configurePublicDiskUrl();
        $this->ensureStorageLink();

        // White-label: override Laravel's app.name + mail.from.name with the
        // admin-configured site_name from the database. This means EVERY
        // transactional email, every config('app.name') reference, and every
        // generic notification automatically picks up the platform brand
        // without touching individual templates.
        $this->applyBrandOverride();

        // Admin > Settings > Email must drive REAL system emails (welcome, password
        // reset, invites...), not just the test button.
        $this->applyAdminMailSettings();

        // Override config/services with admin settings from database
        // so admin panel keys actually work (not just .env)
        $this->applyAdminIntegrationKeys();

        // Register the workflow trigger event subscriber
        Event::subscribe(WorkflowTriggerListener::class);

        // Register integration notification subscriber (Slack, Zapier dispatches on domain events)
        Event::subscribe(IntegrationNotificationListener::class);

        // Synchronous keyword auto-reply — fires immediately when a message
        // arrives so the customer gets their reply within the same request,
        // not on the minute-tick queue worker.
        Event::listen(
            \App\Events\MessageReceived::class,
            [\App\Listeners\AutoReplyListener::class, 'handle']
        );

        // Sanitize Message body_html on every create/update — defense in depth
        Message::observe(MessageObserver::class);

        // Cache invalidation observers — bust stale cache entries on model writes
        Workspace::observe(WorkspaceObserver::class);
        Tag::observe(TagObserver::class);
        Plan::observe(PlanObserver::class);

        // Add a convenience macro: $request->workspace()
        Request::macro('workspace', function () {
            /** @var Request $this */
            return $this->attributes->get('workspace');
        });

        // Register @safeSvg Blade directive for XSS-safe SVG icon rendering
        Blade::directive('safeSvg', function (string $expression) {
            return "<?php echo \App\Helpers\SvgSanitizer::sanitize($expression); ?>";
        });

        // Register @sanitize Blade directive for whitelist-based HTML sanitization
        Blade::directive('sanitize', function (string $expression) {
            return "<?php echo \App\Helpers\HtmlSanitizer::sanitize($expression); ?>";
        });

        // Register @currency Blade directive for dynamic currency formatting
        // Usage: @currency($amount) or @currency($amount, 'EUR')
        Blade::directive('currency', function (string $expression) {
            return "<?php echo \App\Helpers\CurrencyHelper::display($expression); ?>";
        });
    }

    /**
     * Apply the SMTP / mail-driver values saved in the admin panel.
     * Skips silently before install / when the database isn't reachable.
     */
    private function applyAdminMailSettings(): void
    {
        if (!file_exists(storage_path('installed')) && env('INSTALLED', '0') !== '1') {
            return;
        }

        try {
            \App\Services\Mail\AdminMailConfig::apply();
        } catch (\Throwable $e) {
            // DB not available yet — fall back to .env silently
        }
    }

    /**
     * Pull the admin-configured site_name (and SMTP from-name overrides
     * if set) out of system_settings and apply them to:
     *   - config('app.name')         → used by every transactional email
     *                                    template, notification subject, etc.
     *   - config('mail.from.name')   → the visible sender name on every
     *                                    outbound email from the platform
     *
     * Skips silently when the DB isn't reachable yet (during initial install).
     * Cached at the SystemSetting layer so this stays cheap on every request.
     */
    private function applyBrandOverride(): void
    {
        // Skip if not installed — DB tables don't exist yet
        if (!file_exists(storage_path('installed')) && env('INSTALLED', '0') !== '1') {
            return;
        }

        try {
            $siteName = \App\Models\SystemSetting::get('site_name', '');
            if (!empty($siteName)) {
                config(['app.name' => $siteName]);
                // Only override mail.from.name if the admin hasn't explicitly
                // set a different SMTP from-name in settings.
                $smtpFromName = \App\Models\SystemSetting::get('smtp_from_name', '');
                $mailFromName = \App\Models\SystemSetting::get('mail_from_name', '');
                if (empty($smtpFromName) && empty($mailFromName)) {
                    config(['mail.from.name' => $siteName]);
                }
            }
        } catch (\Throwable $e) {
            // DB not available — fall back to env values silently
        }
    }

    /**
     * Auto-detect subdirectory deployment and configure all URL
     * generators (url(), asset(), route(), Storage::url()) to
     * include the correct base path.
     */
    private function configurePublicDiskUrl(): void
    {
        // Use APP_URL as the single source of truth for base URL
        $baseUrl = rtrim(config('app.url', ''), '/');

        // During web requests, prefer the actual host+base path over a
        // misconfigured APP_URL. This auto-corrects subdirectory installs
        // where the admin set APP_URL to the bare domain (e.g.
        // https://example.com) instead of including the subdir
        // (https://example.com/mailtrixy/public). Without this override,
        // url() / asset() / route() generated links pointing at the root
        // domain instead of the subdirectory and broke navigation.
        //
        // Only override when:
        //   - we have a request context (skip CLI / queue jobs)
        //   - the request has a non-empty base path (we're in a subdir)
        //   - APP_URL doesn't already include that base path
        if (app()->bound('request')) {
            try {
                $request = request();
                $requestBase = rtrim($request->getSchemeAndHttpHost() . $request->getBasePath(), '/');
                $basePath = $request->getBasePath(); // e.g. "/mailtrixy/public"

                if ($basePath !== ''
                    && (empty($baseUrl) || !str_contains($baseUrl, $basePath))
                ) {
                    $baseUrl = $requestBase;
                }
            } catch (\Throwable $e) {
                // Request not actually available — keep APP_URL value
            }
        }

        // Fallback to request detection if APP_URL not set
        if (empty($baseUrl) && app()->bound('request')) {
            $request = request();
            $baseUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBasePath(), '/');
        }

        if (empty($baseUrl)) {
            return;
        }

        // Force the URL generator root so url(), asset(), route() all
        // respect the subdirectory (e.g. /MailTrixy/public)
        URL::forceRootUrl($baseUrl);

        // Shared hosting fix: if storage symlink doesn't exist, use direct path
        $storagePath = public_path('storage');
        if (!is_link($storagePath)) {
            if (!file_exists($storagePath)) {
                @mkdir($storagePath, 0755, true);
            }
            config(['filesystems.disks.public.root' => $storagePath]);
        }

        // Fix Storage::url() for subdirectory path
        config(['filesystems.disks.public.url' => $baseUrl . '/storage']);
    }

    /**
     * Auto-create storage:link if missing (shared hosting compatibility).
     */
    private function ensureStorageLink(): void
    {
        $storagePath = public_path('storage');

        if (!is_link($storagePath) && !file_exists($storagePath)) {
            try {
                \Illuminate\Support\Facades\Artisan::call('storage:link');
            } catch (\Throwable $e) {
                // Fail silently — likely file permission issue on some hostings
            }
        }
    }

    /**
     * Read admin-configured OAuth/API keys from system_settings
     * and apply them to config('services.*') at runtime.
     * This bridges the admin UI → config/services.php gap.
     */
    private function applyAdminIntegrationKeys(): void
    {
        // Skip if not installed (no DB yet)
        if (!file_exists(storage_path('installed')) && env('INSTALLED', '0') !== '1') {
            return;
        }

        try {
            $map = [
                'google_client_id'       => 'services.google.client_id',
                'google_client_secret'   => 'services.google.client_secret',
                'microsoft_client_id'    => 'services.microsoft.client_id',
                'microsoft_client_secret'=> 'services.microsoft.client_secret',
                'github_client_id'       => 'services.github.client_id',
                'github_client_secret'   => 'services.github.client_secret',
                'slack_client_id'        => 'services.slack.client_id',
                'slack_client_secret'    => 'services.slack.client_secret',
                'slack_signing_secret'   => 'services.slack.signing_secret',
                'salesforce_client_id'   => 'services.salesforce.client_id',
                'salesforce_client_secret'=> 'services.salesforce.client_secret',
                'stripe_key'             => 'services.stripe.key',
                'stripe_secret'          => 'services.stripe.secret',
                'stripe_webhook_secret'  => 'services.stripe.webhook_secret',
                'pusher_app_id'          => 'services.pusher.app_id',
                'pusher_key'             => 'services.pusher.key',
                'pusher_secret'          => 'services.pusher.secret',
                'pusher_cluster'         => 'services.pusher.cluster',
                // AI provider keys (platform defaults — workspace keys override these)
                'openai_api_key'         => 'services.openai.api_key',
                'anthropic_api_key'      => 'services.anthropic.api_key',
                'gemini_api_key'         => 'services.gemini.api_key',
                'mistral_api_key'        => 'services.mistral.api_key',
            ];

            foreach ($map as $settingKey => $configKey) {
                $value = \App\Models\SystemSetting::get($settingKey);
                if (!empty($value)) {
                    config([$configKey => $value]);
                }
            }

            \App\Support\RealtimeSettings::apply();

            // Auto-derive OAuth redirect URIs from APP_URL unless the admin
            // explicitly set one in .env. Saves every admin from having to
            // hand-configure these for each provider — the path is always
            // /integrations/{provider}/callback and must match what the
            // vendor app (Slack/Salesforce/Google) has registered.
            $base = rtrim(config('app.url', ''), '/');
            if ($base !== '') {
                $defaults = [
                    'services.slack.redirect' => "{$base}/integrations/slack/callback",
                    'services.salesforce.redirect' => "{$base}/integrations/salesforce/callback",
                    'services.google_calendar.redirect' => "{$base}/integrations/google-calendar/callback",
                ];
                foreach ($defaults as $key => $url) {
                    if (empty(config($key))) {
                        config([$key => $url]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // DB not available yet — silently skip
        }
    }
}
