<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Security headers applied to every response.
     *
     * Covers OWASP Secure Headers recommendations:
     * - Anti-clickjacking (X-Frame-Options)
     * - MIME-sniffing prevention (X-Content-Type-Options)
     * - XSS protection for legacy browsers (X-XSS-Protection)
     * - Referrer leakage prevention (Referrer-Policy)
     * - Feature restriction (Permissions-Policy)
     * - Transport security (Strict-Transport-Security)
     * - Content Security Policy (CSP)
     * - Server identity removal (X-Powered-By)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // ── Anti-fingerprinting ──────────────────────────────────────
        // Remove headers that leak server technology to attackers.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('server');

        // ── MIME-type sniffing prevention ─────────────────────────────
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // ── Clickjacking protection ──────────────────────────────────
        // DENY for admin panel (no legitimate reason to iframe admin).
        // SAMEORIGIN for app pages (allows internal iframes/modals).
        $response->headers->set(
            'X-Frame-Options',
            str_starts_with($request->path(), 'admin') ? 'DENY' : 'SAMEORIGIN'
        );

        // ── XSS filter (legacy browsers) ─────────────────────────────
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // ── Referrer policy ──────────────────────────────────────────
        // Send origin only on cross-origin requests; full URL on same-origin.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ── Permissions policy ───────────────────────────────────────
        // Disable dangerous browser features this app does not need.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(), payment=()');

        // ── HSTS ─────────────────────────────────────────────────────
        // Force HTTPS for 1 year, include subdomains, enable preload list.
        // Only set when connection is already secure OR in production
        // (avoids breaking http://localhost during dev).
        if ($request->secure() || config('app.env') === 'production') {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // ── Content Security Policy ──────────────────────────────────
        // Disabled: Livewire 3 + Alpine.js DOM morphing conflicts with CSP
        // restrictions during SPA-style navigations and component updates.
        // Re-enable once Alpine.js CSP-safe build is fully compatible.

        // ── Cache control for authenticated pages ────────────────────
        // Prevent browsers/proxies from caching responses that contain
        // user-specific data (tokens, account info, etc.).
        if (env('INSTALLED', '0') === '1' && $request->user()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
