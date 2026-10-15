<?php

namespace App\Http\Middleware;

use App\Support\AccountFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnboardingAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!AccountFeatures::onboarding()) {
            if ($request->expectsJson()) return response()->json(['message' => __('Onboarding is disabled.')], 403);
            return redirect($request->user()?->is_admin ? '/admin/dashboard' : '/dashboard');
        }
        return $next($request);
    }
}
