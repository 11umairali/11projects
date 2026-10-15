<?php

namespace App\Http\Middleware;

use App\Services\TimezoneSync;
use Closure;
use Illuminate\Http\Request;

/** Runs after authentication: copies the device timezone (header X-Timezone from the app, cookie user_tz from the browser) and the real IP to the user. */
class SyncDeviceTimezone
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = $request->user() ?: $request->user('sanctum');
            if ($user) {
                $tz = $request->header('X-Timezone') ?: $request->cookie('user_tz');
                app(TimezoneSync::class)->apply($user, $tz ? urldecode((string) $tz) : null, $request->ip());
            }
        } catch (\Throwable $e) {
            // never block a request because of this
        }
        return $next($request);
    }
}
