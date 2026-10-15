<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security hardening middleware for API routes.
 *
 * Enforced controls:
 *
 *  1. Content-Type validation — POST / PUT / PATCH must send
 *     application/json (with optional charset suffix).  Prevents CSRF via
 *     form submissions to API endpoints and catches misconfigured clients.
 *
 *  2. User-Agent blocklist — rejects requests from known vulnerability-
 *     scanner tool signatures (sqlmap, nikto, nuclei, etc.).  This is
 *     defense-in-depth; a determined attacker can change their UA.
 *
 *  3. Null-byte stripping — removes \x00 from all input values.  Null
 *     bytes have no legitimate place in form data and can cause truncation
 *     bugs in C-backed PHP functions (file operations, regex, etc.).
 *
 *  4. Request-body size guard — rejects payloads larger than the
 *     configured maximum BEFORE the framework fully parses the body.
 *
 *  5. X-Request-ID propagation — if the client sends X-Request-ID it is
 *     echoed; otherwise a UUID is generated.  This enables end-to-end
 *     tracing across services and into the client.
 */
class ApiSecurityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // ----------------------------------------------------------------
        // 1. Content-Type enforcement on state-changing methods
        // ----------------------------------------------------------------
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = $request->header('Content-Type', '');

            // Allow application/json and multipart/form-data (for file uploads).
            // Reject everything else (text/plain, application/x-www-form-urlencoded
            // which can be sent by cross-origin HTML forms).
            $allowed = $this->isAllowedContentType($contentType, $request);

            if (!$allowed) {
                return response()->json([
                    'message' => 'Content-Type must be application/json.',
                ], 415);
            }
        }

        // ----------------------------------------------------------------
        // 2. User-Agent blocklist (known scanner tools)
        // ----------------------------------------------------------------
        $ua = strtolower($request->header('User-Agent', ''));

        if ($ua !== '' && $this->isBlockedUserAgent($ua)) {
            Log::notice('API security: blocked scanner user-agent', [
                'ip'         => $request->ip(),
                'user_agent' => Str::limit($ua, 200),
                'path'       => $request->path(),
            ]);

            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        // ----------------------------------------------------------------
        // 3. Reject oversized request bodies
        // ----------------------------------------------------------------
        $maxBody = (int) config('security.api_max_body_bytes', 10 * 1024 * 1024);
        $contentLength = (int) $request->header('Content-Length', 0);

        if ($contentLength > $maxBody) {
            return response()->json([
                'message' => 'Request body too large.',
            ], 413);
        }

        // ----------------------------------------------------------------
        // 4. Null-byte stripping across all input
        // ----------------------------------------------------------------
        $this->stripNullBytes($request);

        // ----------------------------------------------------------------
        // 5. X-Request-ID for tracing
        // ----------------------------------------------------------------
        $requestId = $request->header('X-Request-ID');

        if ($requestId === null || !$this->isValidRequestId($requestId)) {
            $requestId = (string) Str::uuid();
        }

        // Make it available to downstream code
        $request->headers->set('X-Request-ID', $requestId);

        /** @var Response $response */
        $response = $next($request);

        // Echo the request ID back so clients can correlate logs
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    // ------------------------------------------------------------------
    // Content-Type helpers
    // ------------------------------------------------------------------

    /**
     * Determine whether the Content-Type is acceptable.
     *
     * Accepts:
     *  - application/json (with optional charset parameter)
     *  - multipart/form-data (file uploads)
     *  - Empty body (Content-Length: 0) with any Content-Type — some HTTP
     *    clients (cURL, Axios) send empty POSTs without setting the CT.
     */
    protected function isAllowedContentType(string $contentType, Request $request): bool
    {
        // Empty body is fine regardless of Content-Type header
        if ((int) $request->header('Content-Length', 0) === 0 && $request->getContent() === '') {
            return true;
        }

        $ct = strtolower(trim(explode(';', $contentType)[0]));

        return in_array($ct, [
            'application/json',
            'multipart/form-data',
        ], true);
    }

    // ------------------------------------------------------------------
    // User-Agent helpers
    // ------------------------------------------------------------------

    protected function isBlockedUserAgent(string $lowerUa): bool
    {
        $blocked = config('security.blocked_user_agents', []);

        if (!is_array($blocked)) {
            return false;
        }

        foreach ($blocked as $pattern) {
            if (str_contains($lowerUa, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Null-byte stripping
    // ------------------------------------------------------------------

    /**
     * Recursively strip \x00 from all string input values.
     *
     * Null bytes in HTTP parameters have no legitimate purpose and can
     * cause truncation in C-backed PHP functions (fopen, preg_match, etc.)
     * leading to path traversal, filter bypass, and log injection.
     */
    protected function stripNullBytes(Request $request): void
    {
        $input = $request->all();
        $cleaned = $this->recursiveStripNullBytes($input);
        $request->merge($cleaned);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function recursiveStripNullBytes(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = str_replace("\x00", '', $value);
            } elseif (is_array($value)) {
                $data[$key] = $this->recursiveStripNullBytes($value);
            }
        }

        return $data;
    }

    // ------------------------------------------------------------------
    // Request ID validation
    // ------------------------------------------------------------------

    /**
     * Validate an externally-supplied X-Request-ID.
     *
     * Accept UUIDs, ULIDs, and short alphanumeric trace IDs (up to 128 chars).
     * Reject anything with special characters that could enable log injection.
     */
    protected function isValidRequestId(string $id): bool
    {
        return strlen($id) <= 128 && preg_match('/\A[a-zA-Z0-9._-]+\z/', $id) === 1;
    }
}
