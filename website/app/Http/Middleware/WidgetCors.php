<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permissive CORS for the live-chat widget API.
 *
 * Registered as a GLOBAL middleware (after HandleCors) so it can
 * overwrite the restrictive CORS headers for widget routes.
 *
 * The widget is embedded on external customer websites, so these
 * endpoints must accept requests from any origin. Authentication
 * is via Bearer token (public_id), not cookies, so there is no
 * CSRF risk from open CORS.
 */
class WidgetCors
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to widget API routes
        if (! str_starts_with($request->path(), 'api/widget')) {
            return $next($request);
        }

        $origin = $request->header('Origin', '*');

        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept')
                ->header('Access-Control-Allow-Credentials', 'false')
                ->header('Access-Control-Max-Age', '7200');
        }

        $response = $next($request);

        // Overwrite any headers set by HandleCors
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
        $response->headers->remove('Access-Control-Allow-Credentials');

        return $response;
    }
}
