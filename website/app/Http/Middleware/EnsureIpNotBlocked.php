<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureIpNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!file_exists(storage_path('installed')) && env('INSTALLED', '0') !== '1') {
            return $next($request);
        }

        $ip = $request->ip();

        // Cache blocked IPs for 5 minutes to avoid DB query per request
        $cacheKey = "blocked_ip:{$ip}";

        $isBlocked = Cache::remember($cacheKey, 300, function () use ($ip) {
            // Guard: table may not exist yet if migration hasn't run
            if (! Schema::hasTable('blocked_ips')) {
                return false;
            }

            return DB::table('blocked_ips')
                ->where('ip_address', $ip)
                ->where(function ($q) {
                    $q->whereNull('blocked_until')
                      ->orWhere('blocked_until', '>', now());
                })
                ->exists();
        });

        if ($isBlocked) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
