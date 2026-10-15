<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honeypot-based bot detection for form submissions.
 *
 * Two complementary checks:
 *
 *  1. Hidden text field `_hp_name` — rendered as a CSS-hidden <input> that
 *     a real user never fills in.  If it has a value the request was made
 *     by a bot that blindly fills every field.
 *
 *  2. Timestamp field `_hp_time` — set by JavaScript to `Date.now()/1000`
 *     (epoch seconds) when the page loads.  If the form is submitted in
 *     under `honeypot_min_time_seconds` (default 2 s), it is faster than
 *     any human can type and is therefore automated.
 *
 * Both fields are OPTIONAL per-request.  The middleware only acts when the
 * fields are present and fail validation.  This lets it be added globally
 * without breaking API routes or forms that do not include the honeypot
 * markup.
 *
 * On detection the request is silently dropped with 422 — no redirect,
 * no error flash.  Bots do not read error messages.
 *
 * Usage in Blade / Livewire:
 *
 *     {{-- Hidden honeypot (CSS hide with sr-only or display:none) --}}
 *     <div style="position:absolute;left:-9999px" aria-hidden="true">
 *         <input type="text" name="_hp_name" value="" tabindex="-1" autocomplete="off">
 *     </div>
 *     <input type="hidden" name="_hp_time" value="">
 *     <script>
 *         document.querySelector('input[name="_hp_time"]').value = Math.floor(Date.now()/1000);
 *     </script>
 */
class HoneypotProtection
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('security.honeypot_enabled', true)) {
            return $next($request);
        }

        // Only inspect state-changing methods that carry form data.
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        // -----------------------------------------------------------------
        // Check 1: hidden field must be empty
        // -----------------------------------------------------------------
        if ($request->filled('_hp_name')) {
            Log::info('Honeypot: hidden field filled by bot', [
                'ip'   => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->rejectRequest();
        }

        // -----------------------------------------------------------------
        // Check 2: time-based — reject submissions faster than human speed
        // -----------------------------------------------------------------
        if ($request->has('_hp_time')) {
            $submitted = (int) $request->input('_hp_time');
            $minTime   = (int) config('security.honeypot_min_time_seconds', 2);

            // Only evaluate when the timestamp looks valid (non-zero epoch)
            if ($submitted > 0 && (time() - $submitted) < $minTime) {
                Log::info('Honeypot: form submitted too quickly', [
                    'ip'       => $request->ip(),
                    'path'     => $request->path(),
                    'elapsed'  => time() - $submitted,
                    'min_time' => $minTime,
                ]);

                return $this->rejectRequest();
            }

            // Guard against future timestamps (clock skew abuse).
            // A _hp_time more than 5 seconds in the future is suspicious.
            if ($submitted > time() + 5) {
                Log::info('Honeypot: future timestamp submitted', [
                    'ip'        => $request->ip(),
                    'path'      => $request->path(),
                    'submitted' => $submitted,
                    'now'       => time(),
                ]);

                return $this->rejectRequest();
            }
        }

        // -----------------------------------------------------------------
        // Strip honeypot fields so downstream code never sees them
        // -----------------------------------------------------------------
        $request->request->remove('_hp_name');
        $request->request->remove('_hp_time');

        return $next($request);
    }

    /**
     * Reject with 422 Unprocessable Entity.
     *
     * An empty body is intentional — leaking "honeypot detected" would help
     * an attacker understand why they were rejected.
     */
    protected function rejectRequest(): Response
    {
        return response('', 422)
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
