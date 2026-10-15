<?php

namespace App\Http\Middleware;

use App\Support\BillingPolicy;
use Closure;
use Illuminate\Http\Request;

class EnsureBillingEnabled
{
    public function handle(Request $request, Closure $next)
    {
        BillingPolicy::assertBillingEnabled();
        return $next($request);
    }
}
