<?php

use App\Http\Controllers\Admin\BlockedIpController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Auth\RecoveryPhraseController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\IntegrationOAuthController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// ─── Web Installer ────────────────────────────────────────────────
Route::prefix('install')->middleware(\App\Http\Middleware\UseFileSession::class)->withoutMiddleware([
    \App\Http\Middleware\EnsureInstalled::class,
    \App\Http\Middleware\EnsureIpNotBlocked::class,
    \App\Http\Middleware\HoneypotProtection::class,
    \App\Http\Middleware\DdosProtection::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
])->group(function () {
    Route::get('/',              [\App\Http\Controllers\InstallController::class, 'welcome'])->name('install.welcome');
    Route::get('/requirements',  [\App\Http\Controllers\InstallController::class, 'requirements'])->name('install.requirements');
    Route::post('/requirements', [\App\Http\Controllers\InstallController::class, 'checkRequirementsPost'])->name('install.requirements.check');
    Route::get('/database',      [\App\Http\Controllers\InstallController::class, 'database'])->name('install.database');
    Route::post('/database/test',[\App\Http\Controllers\InstallController::class, 'testDatabase'])->name('install.database.test');
    Route::post('/database',     [\App\Http\Controllers\InstallController::class, 'saveDatabase'])->name('install.database.save');
    Route::get('/application',   [\App\Http\Controllers\InstallController::class, 'application'])->name('install.application');
    Route::post('/application',  [\App\Http\Controllers\InstallController::class, 'saveApplication'])->name('install.application.save');
    Route::get('/admin',         [\App\Http\Controllers\InstallController::class, 'admin'])->name('install.admin');
    Route::post('/admin',        [\App\Http\Controllers\InstallController::class, 'saveAdmin'])->name('install.admin.save');
    Route::get('/run',           [\App\Http\Controllers\InstallController::class, 'run'])->name('install.run');
    Route::post('/execute',      [\App\Http\Controllers\InstallController::class, 'execute'])->name('install.execute');
    Route::get('/complete',      [\App\Http\Controllers\InstallController::class, 'complete'])->name('install.complete');
});

// Sitemap
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index']);

// Admin bypass during maintenance — exempt from CSRF + maintenance middleware
// (see bootstrap/app.php). Validates admin email/password and redirects to
// /{maintenance_secret} so Laravel sets the bypass cookie automatically.
Route::post('/admin-maintenance-bypass', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required|string',
    ]);

    $user = \App\Models\User::where('email', $request->input('email'))->first();

    if (!$user
        || !$user->is_admin
        || ($user->status ?? 'active') !== 'active'
        || !\Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)
    ) {
        return response()->json([
            'ok' => false,
            'message' => 'Invalid admin credentials.',
        ], 422);
    }

    $secret = \App\Models\SystemSetting::get('maintenance_secret', '');
    if (!$secret) {
        return response()->json([
            'ok' => false,
            'message' => 'Bypass secret is not configured. Run `php artisan up` from the server.',
        ], 500);
    }

    \Illuminate\Support\Facades\Auth::login($user, true);

    return response()->json([
        'ok' => true,
        'redirect' => '/' . $secret,
    ]);
})->name('admin.maintenance.bypass');

// Language switcher
Route::post('/locale/switch', function (\Illuminate\Http\Request $request) {
    $code = $request->input('locale');
    $language = \App\Models\Language::where('code', $code)->where('is_active', true)->first();
    if ($language) {
        session(['locale' => $language->code]);
        if ($request->user()) {
            $request->user()->update(['language' => $language->code]);
        }
    }
    return back();
})->name('locale.switch');

// Homepage
Route::get('/', function () {
    if (!\App\Models\SystemSetting::enabled('frontend_homepage_enabled', false)) {
        return redirect(auth()->check() ? '/dashboard' : '/login');
    }
    $section = fn(string $type) => \App\Models\Page::where('type', $type)->first()?->content ?? [];
    $homepageTheme = \App\Models\SystemSetting::get('frontend_homepage_theme', 'classic');
    $homepageView = $homepageTheme === 'modern' ? 'frontend.landing.modern' : 'frontend.landing.index';

    return view($homepageView, [
        'hero'         => $section('hero'),
        'features'     => $section('features'),
        'testimonials' => $section('testimonials'),
        'faq'          => $section('faq'),
        'cta'          => $section('cta'),
    ]);
});

// Legal pages (public, no auth)
Route::get('/terms', [\App\Http\Controllers\LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacy', [\App\Http\Controllers\LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/refund-policy', [\App\Http\Controllers\LegalController::class, 'refund'])->name('legal.refund');
Route::get('/contact', [\App\Http\Controllers\LegalController::class, 'contact'])->name('contact');
Route::get('/about', [\App\Http\Controllers\LegalController::class, 'about'])->name('about');
Route::get('/why-us', [\App\Http\Controllers\LegalController::class, 'whyUs'])->name('why-us');

// API Documentation (public, optionally shows user context when authenticated)
Route::get('/docs/api', [\App\Http\Controllers\ApiDocsController::class, 'index'])->name('api.docs');

// PWA offline fallback
Route::get('/offline', fn () => view('offline'));

// PWA dynamic manifest — reads settings from admin panel
Route::get('/manifest.json', function () {
    $s = fn(string $key, string $default = '') => \App\Models\SystemSetting::get($key, $default);

    $appName   = $s('pwa_app_name', $s('site_name', 'MailTrixy'));
    $shortName = $s('pwa_short_name', $appName);
    $startUrl  = $s('pwa_start_url', '/dashboard');
    $themeColor = $s('pwa_theme_color', '#6366f1');
    $bgColor    = $s('pwa_background_color', '#0f1117');

    // Build icons array from uploaded PWA icon or fallback
    $pwaIcon = $s('pwa_icon');
    $iconUrl = $pwaIcon ? asset('storage/' . $pwaIcon) : asset('images/icon-512.png');

    $icons = [];
    foreach ([72, 96, 128, 144, 152, 192, 384, 512] as $size) {
        $icons[] = [
            'src'     => $iconUrl,
            'sizes'   => "{$size}x{$size}",
            'type'    => 'image/png',
            'purpose' => 'any maskable',
        ];
    }

    $manifest = [
        'name'             => $appName,
        'short_name'       => $shortName,
        'description'      => $s('site_tagline', 'AI-Powered Communication Automation'),
        'start_url'        => $startUrl,
        'display'          => 'standalone',
        'orientation'      => 'any',
        'theme_color'      => $themeColor,
        'background_color' => $bgColor,
        'icons'            => $icons,
    ];

    return response()->json($manifest)
        ->header('Content-Type', 'application/manifest+json')
        ->header('Cache-Control', 'public, max-age=3600');
})->name('pwa.manifest');

// Invite acceptance (public — handles logged-in and logged-out users)
Route::get('/invite/{token}', [\App\Http\Controllers\InviteController::class, 'accept'])->name('invite.accept');

// Mobile app handoff (public — single-use token logs the user in, then redirects)
Route::get('/app-handoff/{token}', [\App\Http\Controllers\AppHandoffController::class, 'consume'])
    ->middleware('throttle:20,1')->name('app.handoff');

// Auth routes handled by Fortify (login, register, password reset, email verification, 2fa)
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password/phrase', [\App\Http\Controllers\Auth\PhrasePasswordController::class, 'create'])->name('password.phrase');
    Route::post('/forgot-password/phrase', [\App\Http\Controllers\Auth\PhrasePasswordController::class, 'store'])
        ->middleware('throttle:10,1')->name('password.phrase.update');
});

// Recovery phrase — shown once, right after registration (ported from
// the webmail app). Session-gated in the controller itself (it redirects
// away if there's no phrase pending), so `auth` middleware alone is fine.
Route::middleware(['auth'])->group(function () {
    Route::get('/welcome/recovery-phrase', [RecoveryPhraseController::class, 'show'])->name('recovery-phrase.show');
    Route::post('/welcome/recovery-phrase', [RecoveryPhraseController::class, 'confirm'])->name('recovery-phrase.confirm');
});

// Two-Factor Authentication — challenge routes (no auth: user is mid-login)
Route::middleware('web')->group(function () {
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor/verify', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
});

// Two-Factor Authentication — setup routes (auth required)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/activate', [TwoFactorController::class, 'enable'])->name('two-factor.activate');
    Route::post('/two-factor/deactivate', [TwoFactorController::class, 'disable'])->name('two-factor.deactivate');

    // In-app notifications (bell dropdown)
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/notifications/{id}/open', [\App\Http\Controllers\NotificationController::class, 'open'])->name('notifications.open');
});

// Email Account OAuth (Gmail/Outlook connection — requires auth)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/email-oauth/google/redirect', [\App\Http\Controllers\EmailOAuthController::class, 'redirectGoogle'])->name('email-oauth.google');
    Route::get('/email-oauth/google/callback', [\App\Http\Controllers\EmailOAuthController::class, 'callbackGoogle']);
    Route::get('/email-oauth/microsoft/redirect', [\App\Http\Controllers\EmailOAuthController::class, 'redirectMicrosoft'])->name('email-oauth.microsoft');
    Route::get('/email-oauth/microsoft/callback', [\App\Http\Controllers\EmailOAuthController::class, 'callbackMicrosoft']);
});

// Integration OAuth callbacks (Slack, Salesforce, Google Calendar — requires auth)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/integrations/slack/redirect', [IntegrationOAuthController::class, 'slackRedirect'])->name('integrations.slack.redirect');
    Route::get('/integrations/slack/callback', [IntegrationOAuthController::class, 'slackCallback'])->name('integrations.slack.callback');
    Route::get('/integrations/salesforce/redirect', [IntegrationOAuthController::class, 'salesforceRedirect'])->name('integrations.salesforce.redirect');
    Route::get('/integrations/salesforce/callback', [IntegrationOAuthController::class, 'salesforceCallback'])->name('integrations.salesforce.callback');
    Route::get('/integrations/google-calendar/redirect', [IntegrationOAuthController::class, 'googleCalendarRedirect'])->name('integrations.google-calendar.redirect');
    Route::get('/integrations/google-calendar/callback', [IntegrationOAuthController::class, 'googleCalendarCallback'])->name('integrations.google-calendar.callback');
});

// OAuth Social Login
Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\Auth\SocialLoginController::class, 'redirect'])
    ->where('provider', 'google|microsoft|github');
Route::get('/auth/{provider}/callback', [\App\Http\Controllers\Auth\SocialLoginController::class, 'callback'])
    ->where('provider', 'google|microsoft|github');

// Authenticated routes
Route::middleware(['auth', 'verified', 'workspace'])->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $workspace = $user?->activeWorkspace;
        $subscription = $workspace?->subscription;

        // Super Admins bypass billing/plan gates — they're operating the platform,
        // not consuming a subscription. Any other user without an active plan
        // gets the upgrade nudge.
        $isSuperAdmin = $user && (
            ($user->is_admin && $user->admin_role === 'super_admin')
            || $user->hasRole('Super Admin')
        );

        if (!\App\Support\BillingPolicy::isFree() && !$isSuperAdmin && (! $subscription || ! $subscription->isActive())) {
            session()->flash('warning', __('You don\'t have an active plan. Please subscribe to unlock all features.'));
        }

        return view('dashboard.index');
    })->name('dashboard');

    // Checkout (multi-gateway)
    Route::get('/checkout/{plan}', [\App\Http\Controllers\CheckoutController::class, 'show'])->name('checkout.show')->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);
    Route::post('/checkout/{plan}/process', [\App\Http\Controllers\CheckoutController::class, 'process'])->name('checkout.process')->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);
    Route::post('/checkout/validate-coupon', [\App\Http\Controllers\CheckoutController::class, 'validateCoupon'])->name('checkout.validate-coupon')->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);
    Route::get('/checkout-success', [\App\Http\Controllers\CheckoutController::class, 'success'])->name('checkout.success')->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);
    Route::get('/checkout-cancel', [\App\Http\Controllers\CheckoutController::class, 'cancel'])->name('checkout.cancel')->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);

    // Onboarding (5-step wizard)
    Route::prefix('onboarding')->name('onboarding.')->middleware(\App\Http\Middleware\OnboardingAccess::class)->group(function () {
        Route::get('/step-1', [OnboardingController::class, 'step1'])->name('step-1');
        Route::post('/step-1', [OnboardingController::class, 'storeStep1'])->name('store-step-1');
        Route::get('/step-2', [OnboardingController::class, 'step2'])->name('step-2');
        Route::post('/step-2', [OnboardingController::class, 'storeStep2'])->name('store-step-2');
        Route::get('/step-3', [OnboardingController::class, 'step3'])->name('step-3');
        Route::post('/step-3', [OnboardingController::class, 'storeStep3'])->name('store-step-3');
        Route::get('/step-4', [OnboardingController::class, 'step4'])->name('step-4');
        Route::post('/step-4', [OnboardingController::class, 'storeStep4'])->name('store-step-4');
        Route::get('/step-5', [OnboardingController::class, 'step5'])->name('step-5');
        Route::post('/step-5', [OnboardingController::class, 'storeStep5'])->name('store-step-5');
        Route::get('/complete', [OnboardingController::class, 'complete'])->name('complete');
        Route::post('/test-connection', [OnboardingController::class, 'testConnection'])->name('test-connection');
    });

    // Inbox (Alpine.js — no Livewire)
    Route::get('/inbox', function () {
        return view('inbox.alpine-inbox');
    })->name('inbox');

    // Inbox API endpoints (JSON)
    Route::get('/inbox/api/conversations', [\App\Http\Controllers\InboxApiController::class, 'conversations']);
    Route::get('/inbox/api/conversations/{id}/messages', [\App\Http\Controllers\InboxApiController::class, 'messages']);
    Route::post('/inbox/api/conversations/{id}/action', [\App\Http\Controllers\InboxApiController::class, 'action']);
    Route::post('/inbox/api/conversations/{id}/summarize', [\App\Http\Controllers\InboxApiController::class, 'summarize']);
    Route::post('/inbox/api/conversations/{id}/ai-chat', [\App\Http\Controllers\InboxApiController::class, 'aiChat']);
    Route::post('/inbox/api/conversations/{id}/send', [\App\Http\Controllers\Api\Mobile\SendController::class, 'reply']);
    Route::get('/inbox/api/messages/{id}/body', [\App\Http\Controllers\Api\Mobile\InboxExtrasController::class, 'messageBody']);
    Route::post('/inbox/api/bulk', [\App\Http\Controllers\InboxApiController::class, 'bulk']);
    Route::get('/inbox/api/poll', [\App\Http\Controllers\InboxApiController::class, 'poll']);
    Route::get('/inbox/api/sidebar', [\App\Http\Controllers\InboxApiController::class, 'sidebar']);
    Route::get('/inbox/api/tags', [\App\Http\Controllers\InboxApiController::class, 'tags']);
    Route::get('/inbox/api/team', [\App\Http\Controllers\InboxApiController::class, 'team']);
    Route::get('/inbox/api/accounts', [\App\Http\Controllers\InboxApiController::class, 'accounts']);
    Route::post('/inbox/api/sync', [\App\Http\Controllers\InboxApiController::class, 'syncNow']);
    Route::post('/timezone/sync', function (\Illuminate\Http\Request $r) {
        app(\App\Services\TimezoneSync::class)->apply($r->user(), (string) $r->input('timezone'), $r->ip());
        return response()->json(['ok' => true]);
    })->name('timezone.sync');
    Route::post('/inbox/api/set-timezone', [\App\Http\Controllers\InboxApiController::class, 'setTimezone']);
    Route::post('/inbox/api/conversations/{id}/fetch-bodies', [\App\Http\Controllers\InboxApiController::class, 'fetchBodies']);

    Route::get('/inbox/compose', function () {
        return view('inbox.compose');
    })->name('inbox.compose');

    Route::get('/inbox/canned-responses', function () {
        return view('inbox.canned-responses');
    })->name('inbox.canned-responses');

    // Email HTML viewer (serves raw email HTML for iframe rendering)
    // Proxies external images through our server to avoid CORP/CORS blocks.
    Route::get('/messages/{message}/html', function (\App\Models\Message $message) {
        $workspaceId = auth()->user()->active_workspace_id;
        abort_unless($message->workspace_id === $workspaceId, 404);

        $raw = request()->has('raw');
        $html = $message->body_html ?? '';

        // No body — show subject as fallback
        if (empty($html) && empty($message->body_text)) {
            $text = e($message->subject ?? 'Email content unavailable');
            if ($raw) return response('<p>' . nl2br($text) . '</p>', 200)->header('Content-Type', 'text/html; charset=utf-8');
            $fallback = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:16px;font-family:system-ui,sans-serif;font-size:14px;color:#999">' . nl2br($text) . '</body></html>';
            return response($fallback, 200)->header('Content-Type', 'text/html; charset=utf-8');
        }

        // Plain text only
        if (empty($html)) {
            $text = e($message->body_text ?? $message->subject ?? '');
            if ($raw) return response('<p>' . nl2br($text) . '</p>', 200)->header('Content-Type', 'text/html; charset=utf-8');
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:16px;font-family:system-ui,sans-serif;font-size:14px;color:#333">' . nl2br($text) . '</body></html>';
            return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
        }

        // Wrap incomplete HTML in a proper document structure
        if ($html && !preg_match('/<html[\s>]/i', $html)) {
            // Strip preheader/hidden text
            $html = preg_replace('/<span[^>]*style="[^"]*display\s*:\s*none[^"]*"[^>]*>.*?<\/span>/is', '', $html);
            $html = preg_replace('/<div[^>]*style="[^"]*display\s*:\s*none[^"]*"[^>]*>.*?<\/div>/is', '', $html);
            $html = preg_replace('/<span[^>]*class="[^"]*preheader[^"]*"[^>]*>.*?<\/span>/is', '', $html);
            // Replace cid: images with transparent placeholder
            $html = preg_replace('/src=["\']cid:[^"\']*["\']/is', 'src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"', $html);

            // Extract <style> blocks and <body> content if present
            $styles = '';
            if (preg_match_all('/<style[^>]*>.*?<\/style>/is', $html, $styleMatches)) {
                $styles = implode("\n", $styleMatches[0]);
            }

            // If there's a <body> tag, extract its attributes and content
            if (preg_match('/<body([^>]*)>(.*)/is', $html, $bodyMatch)) {
                $bodyAttrs = $bodyMatch[1];
                $bodyContent = $bodyMatch[2];
                // Remove closing </body> if present
                $bodyContent = preg_replace('/<\/body>\s*$/is', '', $bodyContent);
                // Strip any text/meta before <body>
                $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' . $styles . '<style>img{max-width:100%}</style></head><body' . $bodyAttrs . '>' . $bodyContent . '</body></html>';
            } else {
                // No <body> tag — strip bare text before first HTML element and wrap
                $html = preg_replace('/^[^<]+/', '', ltrim($html));
                $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' . $styles . '<style>img{max-width:100%}</style></head><body style="margin:0;padding:8px">' . $html . '</body></html>';
            }
        }

        // Rewrite external image URLs to go through our proxy
        $html = preg_replace_callback(
            '/(src\s*=\s*["\'])(https?:\/\/[^"\']+)(["\'])/i',
            function ($matches) use ($message) {
                $url = $matches[2];
                $proxied = route('image.proxy', ['url' => base64_encode($url), 'msg' => $message->id]);
                return $matches[1] . $proxied . $matches[3];
            },
            $html
        );

        // Raw mode: extract just body content + styles for direct injection (no iframe)
        if ($raw) {
            // Strip <script> tags for security
            $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
            // Extract styles
            $styles = '';
            if (preg_match_all('/<style[^>]*>.*?<\/style>/is', $html, $sm)) {
                $styles = implode("\n", $sm[0]);
            }
            // Extract body content
            if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $bm)) {
                $body = $bm[1];
            } else {
                $body = $html;
            }
            return response($styles . $body, 200)->header('Content-Type', 'text/html; charset=utf-8');
        }

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Security-Policy', "script-src 'none'; img-src * data: cid: blob:; style-src 'unsafe-inline' *; font-src * data:;");
    })->name('message.html')->withoutMiddleware([\App\Http\Middleware\SecurityHeaders::class]);

    // Image proxy for email content — fetches external images server-side
    Route::get('/image-proxy/{msg}', function (\Illuminate\Http\Request $request, int $msg) {
        $workspaceId = auth()->user()->active_workspace_id;
        $message = \App\Models\Message::where('workspace_id', $workspaceId)->findOrFail($msg);

        $encodedUrl = $request->query('url');
        if (!$encodedUrl) abort(400);

        $url = base64_decode($encodedUrl);
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) abort(400);

        // Only allow http/https
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) abort(400);

        // SSRF protection: block private/internal IPs
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) abort(400);

        $hostLower = strtolower($host);

        // Block dangerous domain suffixes
        if (str_ends_with($hostLower, '.local')
            || str_ends_with($hostLower, '.internal')
            || str_ends_with($hostLower, '.localhost')) {
            abort(403, 'Blocked: internal hostname.');
        }

        // Block cloud metadata endpoint
        if ($hostLower === '169.254.169.254' || $hostLower === 'metadata.google.internal') {
            abort(403, 'Blocked: metadata endpoint.');
        }

        // Resolve hostname and check all IPs
        $resolvedIps = gethostbynamel($hostLower);
        if (!$resolvedIps) {
            // If it's a raw IP, check it directly
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                $resolvedIps = [$host];
            } else {
                abort(403, 'Blocked: hostname could not be resolved.');
            }
        }

        foreach ($resolvedIps as $ip) {
            // filter_var returns false if IP is private or reserved
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                abort(403, 'Blocked: private or reserved IP address.');
            }
            // Block IPv6 loopback
            if ($ip === '::1') {
                abort(403, 'Blocked: loopback address.');
            }
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->get($url);

            if (!$response->successful()) abort(404);

            $contentType = $response->header('Content-Type') ?? 'image/png';

            // Only proxy image types
            if (!str_starts_with($contentType, 'image/')) abort(403);

            return response($response->body(), 200)
                ->header('Content-Type', $contentType)
                ->header('Cache-Control', 'public, max-age=86400');
        } catch (\Throwable $e) {
            abort(404);
        }
    })->name('image.proxy')->withoutMiddleware([\App\Http\Middleware\SecurityHeaders::class]);

    // Contacts
    Route::get('/contacts', function () {
        return view('contacts.index');
    })->name('contacts');

    Route::get('/contacts/groups', function () {
        return view('contacts.groups');
    })->name('contacts.groups');

    Route::get('/contacts/trash', function () {
        return view('contacts.trash');
    })->name('contacts.trash');

    Route::get('/contacts/merge', function () {
        return view('contacts.merge');
    })->name('contacts.merge');

    Route::get('/contacts/{contact}', function (\App\Models\Contact $contact) {
        // Ensure the contact belongs to the user's active workspace
        abort_unless($contact->workspace_id === auth()->user()->active_workspace_id, 404);
        $contact->load('tags');
        return view('contacts.show', compact('contact'));
    })->name('contacts.detail');

    // Deals
    Route::get('/deals', function () {
        return view('deals.index');
    })->name('deals')->middleware('plan.feature:deal_pipeline');

    // Campaigns
    Route::middleware('plan.feature:campaigns')->group(function () {
        Route::get('/campaigns', function () {
            return view('campaigns.index');
        })->name('campaigns');

        Route::get('/campaigns/templates', function () {
            return view('campaigns.templates');
        })->name('campaigns.templates');

        Route::get('/campaigns/create', function () {
            return view('campaigns.create');
        })->name('campaigns.create');

        Route::get('/campaigns/{campaign}/edit', function ($campaign) {
            return view('campaigns.edit', ['campaignId' => (int) $campaign]);
        })->name('campaigns.edit');

        Route::get('/campaigns/{campaign}/report', function ($campaign) {
            return view('campaigns.report', ['campaignId' => (int) $campaign]);
        })->name('campaigns.report');
    });

    // Workflows
    Route::middleware('plan.feature:workflows')->group(function () {
        Route::get('/workflows', function () {
            return view('workflows.index');
        })->name('workflows');

        Route::get('/workflows/create', function () {
            return view('workflows.create');
        })->name('workflows.create');

        Route::get('/workflows/{workflow}/edit', function ($workflow) {
            return view('workflows.edit', ['workflowId' => (int) $workflow]);
        })->name('workflows.edit');

        Route::get('/workflows/{workflow}/logs', function ($workflow) {
            return view('workflows.logs', ['workflowId' => (int) $workflow]);
        })->name('workflows.logs');
    });

    // Knowledge Base
    Route::get('/knowledge-base', function () {
        return view('knowledge-base.index');
    })->name('knowledge-base')->middleware('plan.feature:knowledge_base');
    Route::get('/friends', function () { return view('friends.index'); })->name('friends');
    Route::get('/friends/chat/@{username}', [\App\Http\Controllers\Friends\ChatPageController::class, 'username'])->name('friends.chat.prefixed');
    Route::get('/friends/chat/user/{uuid}', [\App\Http\Controllers\Friends\ChatPageController::class, 'uuid'])->whereUuid('uuid')->name('friends.chat.uuid');
    Route::get('/friends/chat/u/{username}', [\App\Http\Controllers\Friends\ChatPageController::class, 'username'])->name('friends.chat.numeric');
    Route::get('/friends/chat/{userId}', [\App\Http\Controllers\Friends\ChatPageController::class, 'legacy'])->whereNumber('userId')->name('friends.chat.legacy');
    Route::get('/friends/chat/{username}', [\App\Http\Controllers\Friends\ChatPageController::class, 'username'])->name('friends.chat');
    Route::get('/people/u/{username}', function ($username) { $u = \App\Support\Username::find($username); return view('friends.person', ['p' => app(\App\Services\Friends\PeopleService::class)->profile(auth()->user(), $u->id)]); })->name('people.username');
    Route::post('/friends/api/chats/organize', [\App\Http\Controllers\Friends\FriendChatController::class, 'organize']);
    Route::delete('/friends/api/messages/{userId}', [\App\Http\Controllers\Friends\FriendChatController::class, 'clear'])->whereNumber('userId');
    Route::get('/people', function () { return view('friends.people'); })->name('people');
    Route::get('/people/calls', function () { return view('friends.calls'); })->name('calls.history');
    Route::get('/people/{userId}', function ($userId) { return view('friends.person', ['p' => app(\App\Services\Friends\PeopleService::class)->profile(auth()->user(), (int) $userId)]); })->whereNumber('userId')->name('people.show');
    Route::get('/meetings/recording/{id}', [\App\Http\Controllers\Meetings\MeetingRecordingController::class, 'file'])->whereNumber('id')->name('meetings.recording');
    Route::get('/meetings', [\App\Http\Controllers\Meetings\MeetingController::class, 'index'])->name('meetings.index');
    Route::post('/meetings', [\App\Http\Controllers\Meetings\MeetingController::class, 'store'])->name('meetings.store');
    Route::post('/meetings/{code}/cancel', [\App\Http\Controllers\Meetings\MeetingController::class, 'cancelForm'])->name('meetings.cancel');
    Route::post('/meetings/{code}/update', [\App\Http\Controllers\Meetings\MeetingController::class, 'update'])->name('meetings.update');
    Route::post('/people/{userId}/unfriend', function ($userId) { [$ok, $msg] = app(\App\Services\Friends\FriendService::class)->remove(auth()->user(), (int) $userId); return redirect('/people')->with('status', $msg); })->whereNumber('userId')->name('people.unfriend');
    Route::prefix('friends/api')->group(function () {
        Route::get('messages/{userId}', [\App\Http\Controllers\Friends\FriendChatController::class, 'messages'])->whereNumber('userId');
        Route::post('messages/{userId}/read', [\App\Http\Controllers\Friends\FriendChatController::class, 'read'])->whereNumber('userId');
        Route::post('messages/{userId}', [\App\Http\Controllers\Friends\FriendChatController::class, 'send'])->whereNumber('userId');
        Route::get('files/{messageId}', [\App\Http\Controllers\Friends\FriendChatController::class, 'file'])->whereNumber('messageId');
        Route::post('calls/start/{userId}', [\App\Http\Controllers\Friends\FriendCallController::class, 'start'])->whereNumber('userId');
        Route::patch('messages/{userId}/{messageId}', [\App\Http\Controllers\Friends\FriendChatController::class, 'edit'])->whereNumber('userId')->whereNumber('messageId');
        Route::delete('messages/{userId}/{messageId}', [\App\Http\Controllers\Friends\FriendChatController::class, 'delete'])->whereNumber('userId')->whereNumber('messageId');
        Route::post('calls/video/{userId}', [\App\Http\Controllers\Friends\FriendCallController::class, 'startVideo'])->whereNumber('userId');
        Route::post('messages/{userId}/{messageId}/react', [\App\Http\Controllers\Friends\FriendChatController::class, 'react'])->whereNumber('userId')->whereNumber('messageId');
        Route::post('messages/{userId}/{messageId}/forward', [\App\Http\Controllers\Friends\FriendChatController::class, 'forward'])->whereNumber('userId')->whereNumber('messageId');
        Route::get('forward-targets', [\App\Http\Controllers\Friends\FriendChatController::class, 'targets']);
        Route::get('chats', [\App\Http\Controllers\Friends\FriendChatController::class, 'chats']);
        Route::get('search', [\App\Http\Controllers\Friends\FriendSearchController::class, 'search'])->middleware('throttle:60,1');
        Route::post('request', [\App\Http\Controllers\Friends\FriendSearchController::class, 'request'])->middleware('throttle:30,1');
        Route::get('presence', [\App\Http\Controllers\Friends\PresenceController::class, 'show']);
        Route::post('presence', [\App\Http\Controllers\Friends\PresenceController::class, 'update']);
        Route::get('calls/poll', [\App\Http\Controllers\Friends\FriendCallController::class, 'poll']);
        Route::get('calls/history', [\App\Http\Controllers\Friends\FriendCallController::class, 'history']);
        Route::delete('calls/history', [\App\Http\Controllers\Friends\FriendCallController::class, 'clearHistory']);
        Route::get('calls/{id}/signals', [\App\Http\Controllers\Friends\FriendCallController::class, 'signals'])->whereNumber('id');
        Route::post('calls/{id}/signal', [\App\Http\Controllers\Friends\FriendCallController::class, 'signal'])->whereNumber('id');
        Route::post('calls/{id}/answer', [\App\Http\Controllers\Friends\FriendCallController::class, 'answer'])->whereNumber('id');
        Route::post('calls/{id}/decline', [\App\Http\Controllers\Friends\FriendCallController::class, 'decline'])->whereNumber('id');
        Route::post('calls/{id}/end', [\App\Http\Controllers\Friends\FriendCallController::class, 'end'])->whereNumber('id');
        Route::post('device', [\App\Http\Controllers\Friends\WebPushController::class, 'register']);
        Route::delete('device', [\App\Http\Controllers\Friends\WebPushController::class, 'unregister']);
    });

    // Temp Mail
    Route::get('/temp-mail', function () {
        return view('temp-mail.index');
    })->name('temp-mail');

    // Activity
    Route::get('/activity', function () {
        return view('activity.index');
    })->name('activity');

    // Analytics
    Route::get('/analytics', function () {
        return view('analytics.index');
    })->name('analytics')->middleware('plan.feature:analytics');

    // Help Center
    Route::get('/help', function () {
        return view('help.index');
    })->name('help');

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', fn () => redirect(url('/settings/profile')));
        Route::get('/profile', function () { return view('settings.profile'); })->name('profile');
        Route::get('/security', function () { return view('settings.security'); })->name('security');
        Route::get('/email', function () { return view('settings.email'); })->name('email');
        Route::get('/phone', function () { return view('settings.phone'); })->name('phone');
        Route::get('/privacy', function () { return view('settings.privacy'); })->name('privacy');
        Route::get('/mail-server', function () { return view('settings.mail-server'); })->name('mail-server');
        Route::get('/channels', function () { return view('settings.channels'); })->name('channels');
        Route::get('/ai', function () { return view('settings.ai'); })->name('ai');
        Route::get('/contacts', function () { return view('settings.contacts'); })->name('contacts');
        Route::get('/team', function () { return view('settings.team'); })->name('team');
        Route::get('/billing', function () { return view('settings.billing'); })->name('billing')->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);
        Route::get('/workspace', function () { return view('settings.workspace'); })->name('workspace');
        Route::get('/notifications', function () { return view('settings.notifications'); })->name('notifications');
        Route::get('/auto-reply', function () { return view('settings.auto-reply'); })->name('auto-reply');
        Route::get('/integrations', function () { return view('settings.integrations'); })->name('integrations');
        Route::get('/webhooks', function () { return view('settings.webhooks'); })->name('webhooks');
        Route::get('/data-privacy', function () { return view('settings.data-privacy'); })->name('data-privacy');
        Route::get('/account', function () { return view('settings.account'); })->name('account');
    });

    // GDPR data export & deletion (rate limited — export is expensive and generates large payloads)
    Route::get('/settings/export-data', [\App\Http\Controllers\DataExportController::class, 'export'])
        ->middleware('throttle:sensitive')
        ->name('settings.export-data');
    Route::post('/settings/delete-account', [\App\Http\Controllers\DataExportController::class, 'requestDeletion'])
        ->middleware('throttle:sensitive')
        ->name('settings.delete-account');
    Route::post('/settings/deactivate-account', [\App\Http\Controllers\DataExportController::class, 'deactivate'])
        ->middleware('throttle:sensitive')
        ->name('settings.deactivate-account');

    // Search (real Livewire backend)
    Route::get('/search', function () {
        return view('search.index');
    })->name('search');

    // Quick search JSON endpoint for header dropdown
    Route::get('/search/quick', function (\Illuminate\Http\Request $request) {
        $q = trim($request->input('q', ''));
        $wsId = auth()->user()->active_workspace_id;

        if (strlen($q) < 2 || !$wsId) {
            return response()->json(['contacts' => [], 'conversations' => []]);
        }

        $contacts = \App\Models\Contact::where('workspace_id', $wsId)
            ->where(fn ($query) => $query->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('company', 'like', "%{$q}%"))
            ->limit(5)
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => trim("{$c->first_name} {$c->last_name}"),
                'email' => $c->email ?? '',
                'initials' => strtoupper(substr($c->first_name ?? '?', 0, 1) . substr($c->last_name ?? '', 0, 1)),
                'url' => url("/contacts/{$c->id}"),
            ]);

        $conversations = \App\Models\Conversation::where('workspace_id', $wsId)
            ->where('subject', 'like', "%{$q}%")
            ->with('contact:id,first_name,last_name')
            ->limit(5)
            ->get(['id', 'subject', 'contact_id'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'subject' => $c->subject ?: 'No subject',
                'contact' => $c->contact ? trim("{$c->contact->first_name} {$c->contact->last_name}") : 'Unknown',
                'url' => url("/inbox?cid={$c->id}"),
            ]);

        return response()->json([
            'contacts' => $contacts,
            'conversations' => $conversations,
        ]);
    })->name('search.quick');

});

Route::get('/test-inboc', function () {
    return view('test-inbox');
})->name('test-inboc');

Route::get('/test-compose', function () {
    return view('test-compose');
})->name('test-compose');

// Admin login (separate guard — no auth required)
Route::prefix('admin')->name('admin.')->middleware('web')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('admin-logout');
});

// The impersonated user is not an admin, so this return route must sit outside admin middleware.
Route::post('/admin/impersonation/stop', function (\Illuminate\Http\Request $request) {
    abort_unless($request->session()->has('admin_impersonating'), 403);
    $restored = app(\App\Services\AdminImpersonation::class)->restore($request);
    return redirect()->route($restored ? 'admin.dashboard' : 'login');
})->middleware('auth')->name('admin.impersonation.stop');

// Admin panel (same user auth, admin middleware checks is_admin flag + Spatie permissions)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'admin', 'admin.permission', 'log.admin'])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));

    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/test-dashboard', function () {
        return view('admin.test-dashboard');
    })->name('test-dashboard');

    // Export, import, and bulk routes (registered BEFORE resource routes to avoid {id} conflicts)
    Route::get('/users/export', [\App\Http\Controllers\Admin\UserController::class, 'export'])->name('users.export');
    Route::post('/users/import', [\App\Http\Controllers\Admin\UserController::class, 'import'])->name('users.import');
    Route::post('/users/bulk-destroy', [\App\Http\Controllers\Admin\UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
    Route::post('/plans/default', [\App\Http\Controllers\Admin\PlanController::class, 'configureDefault'])->name('plans.configure');
    Route::get('/plans/export', [\App\Http\Controllers\Admin\PlanController::class, 'export'])->name('plans.export');
    Route::post('/plans/import', [\App\Http\Controllers\Admin\PlanController::class, 'import'])->name('plans.import');
    Route::post('/plans/bulk-destroy', [\App\Http\Controllers\Admin\PlanController::class, 'bulkDestroy'])->name('plans.bulk-destroy');
    Route::get('/payments/export', [\App\Http\Controllers\Admin\PaymentController::class, 'export'])->name('payments.export');
    Route::post('/payments/import', [\App\Http\Controllers\Admin\PaymentController::class, 'import'])->name('payments.import');
    Route::get('/tickets/export', [\App\Http\Controllers\Admin\TicketController::class, 'export'])->name('tickets.export');
    Route::post('/tickets/import', [\App\Http\Controllers\Admin\TicketController::class, 'import'])->name('tickets.import');
    Route::post('/tickets/bulk-destroy', [\App\Http\Controllers\Admin\TicketController::class, 'bulkDestroy'])->name('tickets.bulk-destroy');
    Route::get('/coupons/export', [\App\Http\Controllers\Admin\CouponController::class, 'export'])->name('coupons.export');
    Route::post('/coupons/import', [\App\Http\Controllers\Admin\CouponController::class, 'import'])->name('coupons.import');
    Route::post('/coupons/bulk-destroy', [\App\Http\Controllers\Admin\CouponController::class, 'bulkDestroy'])->name('coupons.bulk-destroy');
    Route::get('/audit-log/export', [\App\Http\Controllers\Admin\AuditLogController::class, 'export'])->name('audit-log.export');
    Route::post('/blocked-ips/bulk-destroy', [BlockedIpController::class, 'bulkDestroy'])->name('blocked-ips.bulk-destroy');

    Route::resource('/users', \App\Http\Controllers\Admin\UserController::class)->only(['index', 'show', 'create', 'store', 'destroy']);
    Route::post('/users/{user}/impersonate', [\App\Http\Controllers\Admin\UserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/users/{user}/suspend', [\App\Http\Controllers\Admin\UserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/unsuspend', [\App\Http\Controllers\Admin\UserController::class, 'unsuspend'])->name('users.unsuspend');

    Route::resource('/plans', \App\Http\Controllers\Admin\PlanController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

    Route::get('/payments', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{payment}/refund', [\App\Http\Controllers\Admin\PaymentController::class, 'refund'])->name('payments.refund');
    Route::post('/payments/{payment}/approve', [\App\Http\Controllers\Admin\PaymentController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{payment}/reject', [\App\Http\Controllers\Admin\PaymentController::class, 'reject'])->name('payments.reject');
    Route::delete('/payments/{payment}', [\App\Http\Controllers\Admin\PaymentController::class, 'destroy'])->name('payments.destroy');

    Route::get('/tickets', [\App\Http\Controllers\Admin\TicketController::class, 'index'])->name('tickets.index');
    Route::post('/tickets', [\App\Http\Controllers\Admin\TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\Admin\TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [\App\Http\Controllers\Admin\TicketController::class, 'reply'])->name('tickets.reply');
    Route::patch('/tickets/{ticket}/status', [\App\Http\Controllers\Admin\TicketController::class, 'updateStatus'])->name('tickets.status');
    Route::delete('/tickets/{ticket}', [\App\Http\Controllers\Admin\TicketController::class, 'destroy'])->name('tickets.destroy');

    Route::get('/system', [\App\Http\Controllers\Admin\SystemController::class, 'index'])->name('system');
    Route::get('/system-hub', fn () => view('admin.system-hub'))->name('system-hub');
    Route::resource('testimonials', \App\Http\Controllers\Admin\TestimonialController::class)->except(['show']);
    Route::get('/frontend-settings', [\App\Http\Controllers\Admin\FrontendSettingsController::class, 'index'])->name('frontend-settings.index');
    Route::put('/frontend-settings/account-features', [\App\Http\Controllers\Admin\FrontendSettingsController::class, 'updateAccountFeatures'])->name('frontend-settings.account-features');
    Route::put('/frontend-settings/homepage-access', [\App\Http\Controllers\Admin\FrontendSettingsController::class, 'updateHomepageAccess'])->name('frontend-settings.homepage-access');
    Route::put('/frontend-settings/homepage-theme', [\App\Http\Controllers\Admin\FrontendSettingsController::class, 'updateHomepageTheme'])->name('frontend-settings.homepage-theme');
    Route::get('/frontend-settings/{type}/edit', [\App\Http\Controllers\Admin\FrontendSettingsController::class, 'edit'])->name('frontend-settings.edit');
    Route::put('/frontend-settings/{type}', [\App\Http\Controllers\Admin\FrontendSettingsController::class, 'update'])->name('frontend-settings.update');
    Route::post('/frontend-settings/landing', function (\Illuminate\Http\Request $request) {
        \App\Models\SystemSetting::set('landing_template', $request->input('template', 'default'), 'frontend');
        \App\Models\SystemSetting::set('landing_hero_title', $request->input('hero_title', ''), 'frontend');
        \App\Models\SystemSetting::set('landing_hero_subtitle', $request->input('hero_subtitle', ''), 'frontend');
        \App\Models\SystemSetting::set('landing_hero_cta', $request->input('hero_cta', 'Get Started Free'), 'frontend');
        \App\Models\SystemSetting::set('landing_hero_cta_url', $request->input('hero_cta_url', url('/register')), 'frontend');
        return back()->with('success', 'Landing page settings saved.');
    })->name('frontend-settings.landing');
    Route::post('/frontend-settings/custom-code', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'custom_landing_html' => 'nullable|string|max:50000',
            'head_code' => 'nullable|string|max:10000',
            'footer_code' => 'nullable|string|max:10000',
        ]);
        \App\Models\SystemSetting::set('custom_landing_html', $request->input('custom_landing_html', ''), 'frontend');
        \App\Models\SystemSetting::set('head_code', $request->input('head_code', ''), 'frontend');
        \App\Models\SystemSetting::set('footer_code', $request->input('footer_code', ''), 'frontend');
        return back()->with('success', 'Custom code saved.');
    })->name('frontend-settings.custom-code');

    Route::get('/audit-log', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-log');

    Route::resource('/coupons', \App\Http\Controllers\Admin\CouponController::class);

    Route::get('/payment-gateways', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
    Route::post('/payment-gateways/{gateway}/toggle', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'toggle'])->name('payment-gateways.toggle');
    Route::post('/payment-gateways/{gateway}/configure', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'configure'])->name('payment-gateways.configure');

    Route::get('/ai-providers', function () {
        return view('admin.ai-providers');
    })->name('ai-providers');

    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings');

    Route::post('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/test-smtp', [\App\Http\Controllers\Admin\SettingsController::class, 'testSmtp'])->name('settings.test-smtp');
    Route::post('/settings/maintenance', [\App\Http\Controllers\Admin\SettingsController::class, 'toggleMaintenance'])->name('settings.maintenance');

    Route::post('/cms/bulk-destroy', [\App\Http\Controllers\Admin\CmsPageController::class, 'bulkDestroy'])->name('cms.bulk-destroy');
    Route::get('/cms/export', [\App\Http\Controllers\Admin\CmsPageController::class, 'export'])->name('cms.export');
    Route::resource('/cms', \App\Http\Controllers\Admin\CmsPageController::class);

    Route::get('/ai-usage', [\App\Http\Controllers\Admin\SettingsController::class, 'aiUsage'])->name('ai-usage');

    Route::get('/email-deliverability', [\App\Http\Controllers\Admin\SettingsController::class, 'emailDeliverability'])->name('email-deliverability');
    Route::get('/phone-verification', [\App\Http\Controllers\Admin\PhoneVerificationController::class, 'index'])->name('phone-verification');
    Route::post('/phone-verification', [\App\Http\Controllers\Admin\PhoneVerificationController::class, 'update'])->name('phone-verification.save');
    Route::get('/friends-settings', [\App\Http\Controllers\Admin\FriendsSettingsController::class, 'index'])->name('friends-settings');
    Route::post('/friends-settings', [\App\Http\Controllers\Admin\FriendsSettingsController::class, 'update'])->name('friends-settings.save');

    Route::get('/notifications', function () { return view('admin.notifications'); })->name('notifications');
    Route::middleware('admin:super_admin')->prefix('broadcasts')->name('broadcasts.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\BroadcastController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\BroadcastController::class, 'store'])->name('store');
        Route::put('/{broadcast}', [\App\Http\Controllers\Admin\BroadcastController::class, 'store'])->name('update');
        Route::get('/{broadcast}', [\App\Http\Controllers\Admin\BroadcastController::class, 'show'])->name('show');
        Route::post('/{broadcast}/send', [\App\Http\Controllers\Admin\BroadcastController::class, 'send'])->middleware('throttle:10,1')->name('send');
        Route::post('/{broadcast}/duplicate', [\App\Http\Controllers\Admin\BroadcastController::class, 'duplicate'])->name('duplicate');
    });

    // ── Roles (full CRUD with Spatie Permission) ─────────────────
    Route::get('/roles/export', [\App\Http\Controllers\Admin\RoleController::class, 'export'])->name('roles.export');
    Route::post('/roles/import', [\App\Http\Controllers\Admin\RoleController::class, 'import'])->name('roles.import');
    Route::post('/roles/bulk-destroy', [\App\Http\Controllers\Admin\RoleController::class, 'bulkDestroy'])->name('roles.bulk-destroy');
    Route::resource('/roles', \App\Http\Controllers\Admin\RoleController::class);

    // ── Permissions (full CRUD) ───────────────────────────────
    Route::get('/permissions/export', [\App\Http\Controllers\Admin\PermissionController::class, 'export'])->name('permissions.export');
    Route::post('/permissions/import', [\App\Http\Controllers\Admin\PermissionController::class, 'import'])->name('permissions.import');
    Route::post('/permissions/bulk-destroy', [\App\Http\Controllers\Admin\PermissionController::class, 'bulkDestroy'])->name('permissions.bulk-destroy');
    Route::resource('/permissions', \App\Http\Controllers\Admin\PermissionController::class);

    // ── Security Audit Logs ───────────────────────────────────
    Route::get('/security-audit-logs/export', [\App\Http\Controllers\Admin\SecurityAuditLogController::class, 'export'])->name('security-audit-logs.export');
    Route::post('/security-audit-logs/bulk-destroy', [\App\Http\Controllers\Admin\SecurityAuditLogController::class, 'bulkDestroy'])->name('security-audit-logs.bulk-destroy');
    Route::resource('/security-audit-logs', \App\Http\Controllers\Admin\SecurityAuditLogController::class)->only(['index', 'show', 'destroy']);

    // ── Security Settings ─────────────────────────────────────
    Route::get('/security-settings', [\App\Http\Controllers\Admin\SecuritySettingsController::class, 'index'])->name('security-settings.index');
    Route::post('/security-settings', [\App\Http\Controllers\Admin\SecuritySettingsController::class, 'update'])->name('security-settings.update');

    // ── Blocked IPs ───────────────────────────────────────────
    Route::get('/blocked-ips', [BlockedIpController::class, 'index'])->name('blocked-ips.index');
    Route::get('/sending-limits', [\App\Http\Controllers\Admin\SendingLimitsController::class, 'index'])->name('sending-limits.index');
    Route::post('/sending-limits', [\App\Http\Controllers\Admin\SendingLimitsController::class, 'update'])->name('sending-limits.update');
    Route::post('/blocked-ips', [BlockedIpController::class, 'store'])->name('blocked-ips.store');
    Route::delete('/blocked-ips/{id}', [BlockedIpController::class, 'destroy'])->name('blocked-ips.destroy');

    // ── Blocked Locations ─────────────────────────────────────
    Route::get('/blocked-locations/export', [\App\Http\Controllers\Admin\BlockedLocationController::class, 'export'])->name('blocked-locations.export');
    Route::post('/blocked-locations/import', [\App\Http\Controllers\Admin\BlockedLocationController::class, 'import'])->name('blocked-locations.import');
    Route::post('/blocked-locations/bulk-destroy', [\App\Http\Controllers\Admin\BlockedLocationController::class, 'bulkDestroy'])->name('blocked-locations.bulk-destroy');
    Route::resource('/blocked-locations', \App\Http\Controllers\Admin\BlockedLocationController::class)->except(['show']);

    // ── Languages ────────────────────────────────────────────
    Route::get('/languages', [LanguageController::class, 'index'])->name('languages.index');
    Route::post('/languages', [LanguageController::class, 'store'])->name('languages.store');
    Route::put('/languages/{language}', [LanguageController::class, 'update'])->name('languages.update');
    Route::delete('/languages/{language}', [LanguageController::class, 'destroy'])->name('languages.destroy');
    Route::post('/languages/{language}/toggle', [LanguageController::class, 'toggleActive'])->name('languages.toggle');
    Route::post('/languages/{language}/default', [LanguageController::class, 'setDefault'])->name('languages.default');

    // ── Currencies ───────────────────────────────────────────
    Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies.index');
    Route::post('/currencies', [CurrencyController::class, 'store'])->name('currencies.store');
    Route::put('/currencies/{currency}', [CurrencyController::class, 'update'])->name('currencies.update');
    Route::delete('/currencies/{currency}', [CurrencyController::class, 'destroy'])->name('currencies.destroy');
    Route::post('/currencies/{currency}/toggle', [CurrencyController::class, 'toggleActive'])->name('currencies.toggle');
    Route::post('/currencies/{currency}/default', [CurrencyController::class, 'setDefault'])->name('currencies.default');

    // ── Temp Mail Admin ──────────────────────────────────
    Route::get('/temp-mail', [\App\Http\Controllers\Admin\TempMailDomainController::class, 'index'])->name('temp-mail.index');
    Route::post('/temp-mail', [\App\Http\Controllers\Admin\TempMailDomainController::class, 'store'])->name('temp-mail.store');
    Route::put('/temp-mail/{domain}', [\App\Http\Controllers\Admin\TempMailDomainController::class, 'update'])->name('temp-mail.update');
    Route::delete('/temp-mail/{domain}', [\App\Http\Controllers\Admin\TempMailDomainController::class, 'destroy'])->name('temp-mail.destroy');
    Route::post('/temp-mail/{domain}/toggle', [\App\Http\Controllers\Admin\TempMailDomainController::class, 'toggleActive'])->name('temp-mail.toggle');
    Route::post('/temp-mail/{domain}/test', [\App\Http\Controllers\Admin\TempMailDomainController::class, 'testConnection'])->name('temp-mail.test');
    Route::post('/temp-mail/{domain}/sync', [\App\Http\Controllers\Admin\TempMailDomainController::class, 'syncNow'])->name('temp-mail.sync');
    Route::post('/temp-mail/settings', [\App\Http\Controllers\Admin\TempMailDomainController::class, 'updateSettings'])->name('temp-mail.settings.update');

    // ── System Update (Super Admin only — guard inside controller via HasMiddleware) ──
    // Uploads a new release ZIP, runs Laravel migrations on top of the
    // existing DB (no data loss), preserves uploaded media + .env. Each
    // step is a JSON endpoint driven by the wizard at /admin/update.
    Route::get('/update', [\App\Http\Controllers\Admin\UpdateController::class, 'index'])->name('update.index');
    Route::post('/update/backup', [\App\Http\Controllers\Admin\UpdateController::class, 'backup'])->name('update.backup');
    Route::post('/update/upload', [\App\Http\Controllers\Admin\UpdateController::class, 'upload'])->name('update.upload');
    Route::post('/update/apply', [\App\Http\Controllers\Admin\UpdateController::class, 'apply'])->name('update.apply');
    Route::post('/update/migrate', [\App\Http\Controllers\Admin\UpdateController::class, 'migrate'])->name('update.migrate');
    Route::post('/update/finalize', [\App\Http\Controllers\Admin\UpdateController::class, 'finalize'])->name('update.finalize');
    Route::post('/update/rollback', [\App\Http\Controllers\Admin\UpdateController::class, 'rollback'])->name('update.rollback');
});

// Payment gateway callbacks and webhooks (no auth — verified by gateway signatures)
Route::any('/payment/callback/{gateway}', [\App\Http\Controllers\PaymentCallbackController::class, 'handle'])
    ->name('payment.callback')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/payment/webhook/{gateway}', [\App\Http\Controllers\PaymentCallbackController::class, 'webhook'])
    ->name('payment.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Stripe webhook (no auth — verified by Stripe signature; excluded from CSRF)
Route::post('/stripe/webhook', [\App\Http\Controllers\Webhooks\StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Webhook endpoints (no auth — verified by provider signatures; CSRF exempted)
Route::prefix('api/webhooks')->name('webhooks.')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('throttle:600,1')
    ->group(function () {
    Route::match(['get', 'post'], '/whatsapp', [\App\Http\Controllers\Webhooks\WhatsAppWebhookController::class, 'handle'])->name('whatsapp');
    Route::post('/twilio/incoming', [\App\Http\Controllers\Webhooks\TwilioWebhookController::class, 'incoming'])->name('twilio.incoming');
    Route::post('/twilio/status', [\App\Http\Controllers\Webhooks\TwilioWebhookController::class, 'status'])->name('twilio.status');
    Route::post('/telegram', [\App\Http\Controllers\Webhooks\TelegramWebhookController::class, 'handle'])->name('telegram');
    Route::post('/slack/events', [\App\Http\Controllers\Webhooks\SlackWebhookController::class, 'events'])->name('slack.events');
    Route::post('/slack/commands', [\App\Http\Controllers\Webhooks\SlackWebhookController::class, 'commands'])->name('slack.commands');
    Route::post('/slack/interactions', [\App\Http\Controllers\Webhooks\SlackWebhookController::class, 'interactions'])->name('slack.interactions');
    Route::post('/zapier/{webhookToken}', [\App\Http\Controllers\Webhooks\ZapierWebhookController::class, 'handle'])->name('zapier');
});

// Email tracking (no auth — UUID-based lookups, rate limited to prevent inflation)
Route::middleware('throttle:600,1')->group(function () {
    Route::get('/api/track/open/{messageId}', [\App\Http\Controllers\TrackingController::class, 'open']);
    Route::get('/api/track/click/{messageId}', [\App\Http\Controllers\TrackingController::class, 'click']);
    Route::get('/api/track/campaign/{recipientId}/open', [\App\Http\Controllers\TrackingController::class, 'campaignOpen']);
    Route::get('/api/track/campaign/{recipientId}/click', [\App\Http\Controllers\TrackingController::class, 'campaignClick']);
});
Route::get('/api/unsubscribe/{contactId}', [\App\Http\Controllers\TrackingController::class, 'unsubscribe'])
    ->name('unsubscribe')
    ->where('contactId', '[0-9a-f\-]+'); // Accept both integer IDs and UUIDs

// Public form-submission endpoint — drives workflow `form_submitted` trigger.
// No auth: the workflow id itself is the "token" (public-by-design since the
// URL is embedded on marketing pages). Abuse protection comes from the
// per-IP rate limit in FormSubmissionController + honeypot middleware.
Route::get('/form/{workflow}', [\App\Http\Controllers\FormSubmissionController::class, 'show'])
    ->where('workflow', '[0-9]+')->name('form.show');
Route::post('/form/{workflow}', [\App\Http\Controllers\FormSubmissionController::class, 'submit'])
    ->where('workflow', '[0-9]+')->name('form.submit');

// Public inbound webhook — drives workflow `webhook_received` trigger.
// Path contains a random per-workflow token so only the workflow owner who
// copies the URL can fire it. Accepts any JSON body; fields become
// triggerData for the workflow.
Route::post('/workflows/hook/{token}', [\App\Http\Controllers\WorkflowWebhookController::class, 'receive'])
    ->where('token', '[A-Za-z0-9]+')->name('workflow.webhook');

// Health check endpoint (no auth — for monitoring)
Route::get('/api/health', function () {
    try {
        \DB::connection()->getPdo();
        $dbOk = true;
    } catch (\Exception $e) {
        $dbOk = false;
    }

    $status = $dbOk ? 'healthy' : 'degraded';
    $code = $dbOk ? 200 : 503;

    return response()->json([
        'status' => $status,
        'database' => $dbOk ? 'connected' : 'disconnected',
        'cache' => cache()->has('__health_check__') || cache()->put('__health_check__', true, 10) ? 'ok' : 'error',
        'timestamp' => now()->toIso8601String(),
    ], $code);
});

// Hidden cache clear route (no auth — use this when locked out by rate limiter)
Route::get('/clear-cache-x7k9m', function () {
    // Delete cached config file directly (in case artisan can't)
    @unlink(base_path('bootstrap/cache/config.php'));
    @unlink(base_path('bootstrap/cache/routes-v7.php'));

    \Artisan::call('cache:clear');
    \Artisan::call('config:clear');
    \Artisan::call('route:clear');
    \Artisan::call('view:clear');

    // Also create storage link if missing
    try { \Artisan::call('storage:link'); } catch (\Exception $e) {}

    return response()->json([
        'status' => 'success',
        'message' => 'All caches cleared, storage linked.',
        'app_url' => config('app.url'),
        'storage_url' => config('filesystems.disks.public.url'),
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Live chat widget API (authenticated by workspace token, rate limited to prevent abuse)
// WidgetCors (global middleware) allows any origin for these routes.
// CSRF excluded — widget authenticates via Bearer token, not cookies.
Route::prefix('api/widget')
    ->middleware(['throttle:600,1'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->group(function () {
    Route::post('/start-chat', [\App\Http\Controllers\Widget\ChatWidgetController::class, 'startChat']);
    Route::post('/send-message', [\App\Http\Controllers\Widget\ChatWidgetController::class, 'sendMessage']);
    Route::get('/history', [\App\Http\Controllers\Widget\ChatWidgetController::class, 'history']);
    Route::post('/rate', [\App\Http\Controllers\Widget\ChatWidgetController::class, 'rate']);
    Route::post('/close', [\App\Http\Controllers\Widget\ChatWidgetController::class, 'close']);
    Route::get('/status', [\App\Http\Controllers\Widget\ChatWidgetController::class, 'status']);
});

// ── Meetings (public room page + JSON API; guests use a guest token) ──
Route::get('/meet/{code}', [\App\Http\Controllers\Meetings\MeetingController::class, 'room'])->name('meetings.room');
Route::get('/meet/{code}/ics', [\App\Http\Controllers\Meetings\MeetingController::class, 'ics'])->name('meetings.ics');
Route::prefix('meetings/api/{code}')->group(function () {
    Route::get('/', [\App\Http\Controllers\Meetings\MeetingController::class, 'show'])->middleware('throttle:60,1');
    Route::post('join', [\App\Http\Controllers\Meetings\MeetingController::class, 'join'])->middleware('throttle:30,1');
    Route::get('poll', [\App\Http\Controllers\Meetings\MeetingController::class, 'poll'])->middleware('throttle:240,1');
    Route::post('signal', [\App\Http\Controllers\Meetings\MeetingController::class, 'signal'])->middleware('throttle:600,1');
    Route::post('state', [\App\Http\Controllers\Meetings\MeetingController::class, 'state'])->middleware('throttle:120,1');
    Route::post('chat', [\App\Http\Controllers\Meetings\MeetingController::class, 'chat'])->middleware('throttle:60,1');
    Route::post('host', [\App\Http\Controllers\Meetings\MeetingController::class, 'host'])->middleware('throttle:120,1');
    Route::post('leave', [\App\Http\Controllers\Meetings\MeetingController::class, 'leave'])->middleware('throttle:60,1');
    Route::get('invitable', [\App\Http\Controllers\Meetings\MeetingController::class, 'invitable'])->middleware('throttle:60,1');
    Route::post('invite', [\App\Http\Controllers\Meetings\MeetingController::class, 'invite'])->middleware('throttle:40,1');
    Route::post('update', [\App\Http\Controllers\Meetings\MeetingController::class, 'update'])->middleware('throttle:30,1');
    // recording (policy engine) – guests use the same guest token as for every other room call
    Route::post('recording/start', [\App\Http\Controllers\Meetings\MeetingController::class, 'recStart'])->middleware('throttle:30,1');
    Route::post('recording/respond', [\App\Http\Controllers\Meetings\MeetingController::class, 'recRespond'])->middleware('throttle:30,1');
    Route::post('recording/stop', [\App\Http\Controllers\Meetings\MeetingController::class, 'recStop'])->middleware('throttle:30,1');
    Route::post('recording/claim', [\App\Http\Controllers\Meetings\MeetingController::class, 'recClaim'])->middleware('throttle:30,1');
    Route::post('recording/upload', [\App\Http\Controllers\Meetings\MeetingController::class, 'recUpload'])->middleware('throttle:20,1');
});
