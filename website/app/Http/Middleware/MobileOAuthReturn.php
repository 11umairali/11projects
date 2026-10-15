<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * OAuth connect flows (Gmail, Outlook, Slack, Google Calendar, Salesforce) and payment checkouts end with a redirect
 * to a /settings/... or /checkout-... page. When the flow was started from the app (AppHandoffController sets
 * session('mobile_app')), send the secure in-app browser back to the app instead:  dahimail://oauth?status=ok|error&message=…
 * The user never lands on the website.
 */
class MobileOAuthReturn
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (!$response instanceof RedirectResponse || !$request->hasSession() || !$request->session()->get('mobile_app')) {
            return $response;
        }
        $path = '/' . ltrim((string) parse_url($response->getTargetUrl(), PHP_URL_PATH), '/');
        $host = parse_url($response->getTargetUrl(), PHP_URL_HOST);
        if ($host && $host !== $request->getHost()) return $response;            // still bouncing to Google / Slack / a gateway
        if (!preg_match('#^/(settings|checkout-success|checkout-cancel|dashboard)#', $path)) return $response;

        $session = $request->session();
        $ok = $session->has('success') || str_starts_with($path, '/checkout-success');
        $msg = (string) ($session->get('success') ?? $session->get('error') ?? '');
        $session->forget(['mobile_app', 'success', 'error']);

        return redirect()->away('dahimail://oauth?' . http_build_query(['status' => $ok ? 'ok' : 'error', 'message' => $msg, 'path' => $path]));
    }
}
