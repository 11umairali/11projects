<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminImpersonation
{
    public static function minutes(): int
    {
        return max(1, min(480, (int) config('auth.impersonation_minutes', 10)));
    }

    /** The admin identity comes only from the server-side session. */
    public function restore(Request $request): bool
    {
        $id = $request->session()->get('admin_impersonating');
        $admin = $id ? User::find($id) : null;
        $request->session()->forget(['admin_impersonating', 'admin_impersonating_name', 'admin_impersonation_expires_at']);
        if (!$admin || !$admin->is_admin || $admin->status !== 'active') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return false;
        }
        $target = Auth::guard('web')->id();
        Auth::guard('web')->login($admin);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        Log::info('Admin impersonation ended', ['admin_id' => $admin->id, 'user_id' => $target]);
        return true;
    }
}
