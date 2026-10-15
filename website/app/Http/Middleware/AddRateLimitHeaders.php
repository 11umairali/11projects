<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Appends standard rate-limit headers to API responses.
 *
 * Headers follow the IETF draft convention used by GitHub, Stripe, etc.:
 *   X-RateLimit-Limit     — max requests allowed in the window
 *   X-RateLimit-Remaining — requests left before throttling
 *   X-RateLimit-Reset     — Unix timestamp when the window resets
 *
 * The middleware reads state from Laravel's RateLimiter using the same
 * key the `throttle` middleware uses (IP-based for guests, user-ID-based
 * for authenticated requests).  It does NOT enforce the limit — that is
 * still handled by `throttle:60,1` on the route group.
 */
class AddRateLimitHeaders
{
    /** Default per-minute limit matching the API route group throttle. */
    private const DEFAULT_LIMIT = 60;

    /** Window duration in seconds (1 minute). */
    private const WINDOW_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $key = $this->resolveKey($request);
        $limit = self::DEFAULT_LIMIT;
        $attempts = RateLimiter::attempts($key);
        $remaining = max(0, $limit - $attempts);

        // RateLimiter::availableIn returns 0 when the bucket is not yet hit;
        // in that case the window resets after WINDOW_SECONDS from now.
        $resetSeconds = RateLimiter::attempts($key) > 0
            ? RateLimiter::availableIn($key)
            : self::WINDOW_SECONDS;

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        $response->headers->set('X-RateLimit-Reset', (string) (time() + $resetSeconds));

        return $response;
    }

    /**
     * Build the same cache key the framework throttle middleware uses.
     *
     * Authenticated requests are keyed by user ID; guests by IP.
     * The prefix matches Laravel's internal `ThrottleRequests` implementation.
     */
    private function resolveKey(Request $request): string
    {
        if ($user = $request->user()) {
            return 'throttle:' . sha1($user->getAuthIdentifier());
        }

        return 'throttle:' . sha1($request->ip());
    }
}
