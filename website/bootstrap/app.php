<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cookies set by client-side JS (via document.cookie) are plain-text.
        // Laravel's EncryptCookies middleware tries to decrypt every cookie on
        // request and returns null when decryption fails — so without this
        // exception, `request()->cookie('user_tz')` silently returned null.
        $middleware->encryptCookies(except: ['user_tz']);

        // Public form submissions are posted by third-party sites (embed
        // snippets, Framer, Webflow) that don't carry a CSRF token. Rate
        // limiting + honeypot provide abuse protection instead.
        $middleware->validateCsrfTokens(except: [
            'form/*',
            'workflows/hook/*',
            'admin-maintenance-bypass',
        ]);

        // Admin can authenticate from the 503 page even during maintenance.
        $middleware->preventRequestsDuringMaintenance(except: [
            'admin-maintenance-bypass',
        ]);

        // Live-chat widget CORS — MUST run before Laravel's HandleCors so it
        // can intercept api/widget/* OPTIONS preflights and reflect the
        // visitor's Origin back. Without this the widget only loads on the
        // CRM's own domain (Allow-Origin would be hard-set to APP_URL).
        $middleware->prepend(\App\Http\Middleware\WidgetCors::class);

        // DDoS / abuse protection runs FIRST — rejects banned IPs before any
        // framework processing (session, auth, CSRF) to minimise resource use.
        $middleware->prepend(\App\Http\Middleware\DdosProtection::class);

        // Persistent IP blocklist — checked after DDoS (which handles transient
        // bans) but before any other middleware.  Uses a 5-minute cache so the
        // DB is only hit once per IP per window.
        $middleware->prependToGroup('web', \App\Http\Middleware\WebSessionLifetime::class);
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\EnsureIpNotBlocked::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\EmailPasswordResetAccess::class,
            \App\Http\Middleware\SyncDeviceTimezone::class,   // account timezone + IP follow this browser
        ]);
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\EnsureIpNotBlocked::class,
            \App\Http\Middleware\SyncDeviceTimezone::class,   // account timezone + IP follow this phone (X-Timezone header)
        ]);

        // Installer guard — MUST run before session starts so it can
        // switch to file driver when DB isn't configured yet.
        $middleware->prepend(\App\Http\Middleware\EnsureInstalled::class);

        // Global security headers on all responses
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);



        // Honeypot bot detection on form submissions (web routes)
        $middleware->prependToGroup('web', \App\Http\Middleware\WebSessionLifetime::class);
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\HoneypotProtection::class,
            \App\Http\Middleware\MobileOAuthReturn::class,   // sends finished in-app OAuth/payment flows back to dahimail://
        ]);

        // API-specific hardening (Content-Type, UA blocklist, null-byte strip, request ID)
        // Rate-limit headers expose limit/remaining/reset to API consumers
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\ApiSecurityMiddleware::class,
            \App\Http\Middleware\AddRateLimitHeaders::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'workspace' => \App\Http\Middleware\WorkspaceMiddleware::class,
            'plan.feature' => \App\Http\Middleware\CheckPlanFeature::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'honeypot' => \App\Http\Middleware\HoneypotProtection::class,
            'api.security' => \App\Http\Middleware\ApiSecurityMiddleware::class,
            'log.admin' => \App\Http\Middleware\LogAdminActivity::class,
            'admin.permission' => \App\Http\Middleware\EnsureAdminPermission::class,
            // Sanctum token-ability checks used throughout routes/api.php
            // (ability:conversations, ability:contacts, etc.) — this alias
            // was missing, which is why every mobile API route except
            // auth/* failed with "Target class [ability] does not exist."
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(fn () => ['error_reference' => \App\Support\ErrorReference::current()]);
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson() && $e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], $e->status);
            }
            if ($request->expectsJson() && !config('app.debug')) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                return response()->json([
                    'message' => $status === 500 ? 'An unexpected error occurred.' : $e->getMessage(),
                ], $status);
            }
        });
    })->create();
