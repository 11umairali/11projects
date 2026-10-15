<?php

namespace App\Http\Middleware;

use App\Support\SecuritySettings;
use Closure;
use Illuminate\Http\Request;

class WebSessionLifetime
{
    public function handle(Request $request, Closure $next)
    {
        config(['session.lifetime' => max(5, (int) SecuritySettings::getValue('session_lifetime_minutes', 120))]);
        return $next($request);
    }
}
