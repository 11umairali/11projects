<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the admin login form.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /**
     * Handle admin login with IP whitelist enforcement and rate limiting.
     */
    public function login(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->only('email'));
        }

        // Rate limit by IP: 10 attempts per minute
        $ipThrottleKey = 'admin-login-ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipThrottleKey, 60)) {
            $seconds = RateLimiter::availableIn($ipThrottleKey);

            return redirect()->back()
                ->withErrors(['email' => "Too many login attempts. Please try again in {$seconds} seconds."])
                ->withInput($request->only('email'));
        }

        // Rate limit by email: 5 attempts per 15 minutes (prevents credential stuffing)
        $email = strtolower($request->input('email'));
        $emailThrottleKey = 'admin-login:' . $email;
        if (RateLimiter::tooManyAttempts($emailThrottleKey, 30)) {
            $seconds = RateLimiter::availableIn($emailThrottleKey);

            Log::warning('Admin login rate-limited by email', [
                'email' => $email,
                'ip' => $request->ip(),
                'lockout_seconds' => $seconds,
            ]);

            return redirect()->back()
                ->withErrors(['email' => "Too many login attempts for this account. Please try again in {$seconds} seconds."])
                ->withInput($request->only('email'));
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            // Hit both rate limiters: IP decays after 60s, email after 15 minutes (900s)
            RateLimiter::hit($ipThrottleKey, 60);
            RateLimiter::hit($emailThrottleKey, 900);

            Log::warning('Admin login failed', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->back()
                ->withErrors(['email' => 'Invalid credentials.'])
                ->withInput($request->only('email'));
        }

        // IP whitelist enforcement: check AFTER authentication succeeds so the
        // attacker doesn't learn whether the credentials were correct
        $admin = Auth::guard('admin')->user();
        if ($admin->ip_whitelist && is_array($admin->ip_whitelist) && count($admin->ip_whitelist) > 0) {
            if (!in_array($request->ip(), $admin->ip_whitelist, true)) {
                Auth::guard('admin')->logout();

                Log::warning('Admin login blocked by IP whitelist', [
                    'admin_id' => $admin->id,
                    'email' => $admin->email,
                    'ip' => $request->ip(),
                    'allowed_ips' => $admin->ip_whitelist,
                ]);

                DB::table('audit_logs')->insert([
                    'event' => 'admin_login_blocked_ip',
                    'actor_type' => 'admin',
                    'actor_id' => $admin->id,
                    'actor_name' => $admin->name,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return redirect()->back()
                    ->withErrors(['email' => 'Login not allowed from this IP address.'])
                    ->withInput($request->only('email'));
            }
        }

        // Clear rate limiters on success
        RateLimiter::clear($ipThrottleKey);
        RateLimiter::clear($emailThrottleKey);

        $request->session()->regenerate();

        // Log the admin login
        DB::table('audit_logs')->insert([
            'event' => 'admin_login',
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Handle admin logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        $adminId = Auth::guard('admin')->id();

        if ($adminId) {
            DB::table('audit_logs')->insert([
                'event' => 'admin_logout',
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
