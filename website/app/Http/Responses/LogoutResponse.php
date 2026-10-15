<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * Custom logout response that cleans up admin impersonation session data.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        // Clean up any impersonation session data
        if ($request->hasSession()) {
            $session = $request->session();
            $session->forget('impersonating_admin_id');
            $session->forget('impersonation_started_at');
            $session->forget('original_workspace_id');
        }

        // Invalidate and regenerate the session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(url('/login'));
    }
}
