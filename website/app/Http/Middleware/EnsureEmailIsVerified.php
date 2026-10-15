<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces Laravel's default 'verified' middleware.
 * Checks the admin setting 'require_email_verification' — if OFF, skips verification.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        // Check admin setting — if verification is disabled, skip entirely
        $required = SystemSetting::get('require_email_verification', 'true');

        if ($required !== 'true' && $required !== true && $required !== '1') {
            return $next($request);
        }

        // Verification is enabled — check if user has verified
        if (
            $request->user() &&
            !$request->user()->hasVerifiedEmail()
        ) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your email address is not verified.'], 403);
            }

            // Auto-verify the user if no verification route exists (SMTP not configured)
            $request->user()->markEmailAsVerified();
            return $next($request);
        }

        return $next($request);
    }
}
