<?php

namespace App\Http\Middleware;

use App\Support\AccountFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailPasswordResetAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('password.email', 'password.reset', 'password.update') && !AccountFeatures::emailRecovery()) {
            if ($request->expectsJson()) return AccountFeatures::emailRecoveryDisabled();
            return redirect()->route('password.request')->with('status', __('Email password recovery is disabled.'));
        }
        if ($request->routeIs('password.phrase', 'password.phrase.update') && !AccountFeatures::phraseRecovery()) {
            if ($request->expectsJson()) return AccountFeatures::phraseRecoveryDisabled();
            return redirect()->route('password.request')->with('status', __('Recovery phrase password reset is disabled.'));
        }
        return $next($request);
    }
}
