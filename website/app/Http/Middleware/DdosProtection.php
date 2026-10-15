<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Application-layer DDoS / abuse protection.
 *
 * This is NOT a replacement for network-level DDoS mitigation (Cloudflare,
 * AWS Shield, etc.).  It provides a pragmatic, file-cache-compatible layer
 * that catches:
 *
 *  1. Volumetric abuse   - too many requests from a single IP per minute.
 *  2. Vulnerability scans - an IP rapidly probing many distinct paths.
 *
 * Design constraints:
 *  - File cache only (no Redis / no atomics).  The counter is not perfectly
 *    accurate under heavy concurrency; this is acceptable because the goal
 *    is abuse detection, not billing metering.
 *  - All cache keys are prefixed with "ddos:" so a `php artisan cache:clear`
 *    doesn't silently unban attackers who should remain blocked.
 *
 * Threat model notes:
 *  - X-Forwarded-For is NOT trusted unless SECURITY_TRUST_PROXY=true AND
 *    Laravel's TrustProxies middleware is properly configured.
 *  - Banned IPs receive a minimal 429 with no body — no information leakage.
 */
class DdosProtection
{
    public function handle(Request $request, Closure $next): Response
    {
        // DDoS protection disabled — use Cloudflare or server-level protection instead
        return $next($request);

        // ----------------------------------------------------------------
        // 1. Trusted IPs bypass all checks
        // ----------------------------------------------------------------
        if ($this->isTrustedIp($ip)) {
            return $next($request);
        }

        // ----------------------------------------------------------------
        // 2. Check existing ban
        // ----------------------------------------------------------------
        $banKey = "ddos:ban:{$ip}";
        $banTtl = Cache::get($banKey);

        if ($banTtl !== null) {
            return $this->tooManyRequestsResponse((int) $banTtl);
        }

        // ----------------------------------------------------------------
        // 3. Per-minute rate counter
        // ----------------------------------------------------------------
        $isApi = $request->is('api/*');
        $limit = $isApi
            ? config('security.api_max_requests_per_minute', 1000)
            : config('security.max_requests_per_minute', 5000);

        $countKey = "ddos:rpm:{$ip}";

        // Use cache()->increment() which is atomic even with the file driver
        // (Laravel acquires a file lock internally).  This eliminates the
        // read-then-write race where concurrent requests could both read
        // the same count and each write count+1 instead of count+2.
        $count = (int) Cache::increment($countKey);

        // On the first hit, increment creates the key with value 1 but
        // does not set a TTL.  We set a 60-second expiry so the counter
        // resets each minute.
        if ($count === 1) {
            Cache::put($countKey, 1, 60);
        }

        if ($count >= $limit) {
            $banSeconds = (int) config('security.rate_limit_ban_seconds', 60);
            Cache::put($banKey, $banSeconds, $banSeconds);

            // Clean up the counter key -- the ban key is the authority now
            Cache::forget($countKey);

            Log::warning('DDoS protection: IP rate-limited', [
                'ip'      => $ip,
                'count'   => $count,
                'limit'   => $limit,
                'is_api'  => $isApi,
                'ban_sec' => $banSeconds,
            ]);

            return $this->tooManyRequestsResponse($banSeconds);
        }

        // ----------------------------------------------------------------
        // 4. Vulnerability-scanner detection (many unique paths, short window)
        // ----------------------------------------------------------------
        $scanWindowSec = (int) config('security.scanner_window_seconds', 5);
        $scanThreshold = (int) config('security.scanner_threshold', 200);
        $scanKey       = "ddos:scan:{$ip}";

        $endpoints = Cache::get($scanKey, []);
        $path      = $request->path();

        if (!in_array($path, $endpoints, true)) {
            $endpoints[] = $path;
            Cache::put($scanKey, $endpoints, $scanWindowSec);
        }

        if (count($endpoints) >= $scanThreshold) {
            $scanBanSec = (int) config('security.scanner_ban_seconds', 300);
            Cache::put($banKey, $scanBanSec, $scanBanSec);

            // Purge the scan key so it doesn't keep growing
            Cache::forget($scanKey);

            Log::warning('DDoS protection: scanner pattern detected', [
                'ip'             => $ip,
                'unique_paths'   => count($endpoints),
                'window_seconds' => $scanWindowSec,
                'ban_sec'        => $scanBanSec,
            ]);

            return $this->forbiddenResponse();
        }

        return $next($request);
    }

    // ------------------------------------------------------------------
    // IP resolution
    // ------------------------------------------------------------------

    /**
     * Resolve the client IP in a proxy-safe way.
     *
     * If SECURITY_TRUST_PROXY is false (default) we read REMOTE_ADDR
     * directly, ignoring any X-Forwarded-For header.  This prevents
     * an attacker from spoofing their IP with a fake header.
     *
     * If SECURITY_TRUST_PROXY is true we fall back to $request->ip()
     * which uses Laravel's TrustProxies middleware logic.
     */
    protected function resolveClientIp(Request $request): string
    {
        if (config('security.trust_proxy', false)) {
            // Laravel's ->ip() respects the TrustedProxies middleware.
            return $request->ip() ?? '0.0.0.0';
        }

        return $request->server('REMOTE_ADDR', '0.0.0.0');
    }

    /**
     * Check whether the resolved IP is in the trusted list.
     */
    protected function isTrustedIp(string $ip): bool
    {
        $trusted = config('security.trusted_ips', []);

        return is_array($trusted) && in_array($ip, $trusted, true);
    }

    // ------------------------------------------------------------------
    // Responses
    // ------------------------------------------------------------------

    protected function tooManyRequestsResponse(int $retryAfter): Response
    {
        return response('', 429)
            ->header('Retry-After', (string) $retryAfter)
            ->header('X-Content-Type-Options', 'nosniff');
    }

    protected function forbiddenResponse(): Response
    {
        return response('', 403)
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
