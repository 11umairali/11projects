<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * GET /app-handoff/{token} (web, no auth)
 * Consumes the one-time token issued by POST /api/v1/auth/web-link, signs the
 * user into the web session and redirects to the whitelisted path. Used by the
 * mobile app to open payment checkout and OAuth connect flows in the browser.
 */
class AppHandoffController extends Controller
{
    public function consume(Request $request, string $token)
    {
        $payload = Cache::pull('app_handoff:' . $token);   // single use
        if (!$payload || empty($payload['user_id']) || empty($payload['path'])) {
            return redirect('/login')->with('error', 'This link has expired. Please try again from the app.');
        }
        if (!preg_match('#^/(checkout|settings|integrations|email-oauth)(/|$|\?)#', $payload['path'])) {
            abort(403);
        }

        Auth::guard('web')->loginUsingId($payload['user_id']);
        $request->session()->regenerate();
        // Started from the app: MobileOAuthReturn will bounce the finished flow back to dahimail://oauth
        if (!empty($payload['app'])) $request->session()->put('mobile_app', true);

        return redirect($payload['path']);
    }
}
