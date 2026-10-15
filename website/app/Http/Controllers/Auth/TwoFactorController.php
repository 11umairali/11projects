<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Show the 2FA setup page with QR code and manual secret.
     */
    public function setup(Request $request)
    {
        $user = $request->user();

        // If already fully enabled, redirect to security settings
        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('settings.security')
                ->with('status', 'Two-factor authentication is already enabled.');
        }

        // Generate a new secret
        $secret = $this->google2fa->generateSecretKey(32);

        // Store encrypted secret in session until user confirms with a valid code
        $request->session()->put('2fa_setup_secret', Crypt::encryptString($secret));

        // Generate QR code provisioning URL
        $appName = config('app.name', 'MailTrixy');
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            $appName,
            $user->email,
            $secret
        );

        // Build an inline SVG-compatible URL for a QR code via Google Charts API
        $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrCodeUrl);

        return view('auth.two-factor-setup', [
            'secret'     => $secret,
            'qrImageUrl' => $qrImageUrl,
            'user'       => $user,
        ]);
    }

    /**
     * Verify the TOTP code and enable 2FA on the user account.
     */
    public function enable(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        // SEC-001: Rate limit 2FA setup verification to prevent brute-force
        $throttleKey = '2fa-enable:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 60)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors(['code' => "Too many attempts. Please try again in {$seconds} seconds."]);
        }

        // Retrieve the secret from session
        $encryptedSecret = $request->session()->get('2fa_setup_secret');
        if (!$encryptedSecret) {
            return redirect()->route('two-factor.setup')
                ->withErrors(['code' => 'Setup session expired. Please start again.']);
        }

        $secret = Crypt::decryptString($encryptedSecret);

        // Verify the code against the secret
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            RateLimiter::hit($throttleKey, 60);
            return back()->withErrors(['code' => 'The verification code is invalid. Please try again.']);
        }

        // Verification succeeded — clear the rate limiter
        RateLimiter::clear($throttleKey);

        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();

        // Hash recovery codes individually for secure storage
        // Plain codes are shown once to the user, then only hashed versions are stored
        $hashedCodes = array_map(fn ($code) => hash('sha256', strtolower(trim($code))), $recoveryCodes);

        // Persist 2FA to the user record
        $user->forceFill([
            'two_factor_enabled'      => true,
            'two_factor_secret'       => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($hashedCodes)),
            'two_factor_method'       => 'totp',
            'two_factor_confirmed_at' => now(),
        ])->save();

        // Clear setup session
        $request->session()->forget('2fa_setup_secret');

        // Flash plain-text codes for one-time display — they cannot be shown again
        $request->session()->flash('recovery_codes', $recoveryCodes);

        return redirect()->route('settings.security')
            ->with('status', 'Two-factor authentication has been enabled. Save your recovery codes now — they will not be shown again.');
    }

    /**
     * Disable 2FA after verifying the user's password.
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'The password you entered is incorrect.']);
        }

        $user->forceFill([
            'two_factor_enabled'        => false,
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_method'         => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        return redirect()->route('settings.security')
            ->with('status', 'Two-factor authentication has been disabled.');
    }

    /**
     * Show the 2FA challenge form during login.
     */
    public function challenge(Request $request)
    {
        // Ensure we have a pending 2FA login in session
        if (!$request->session()->has('2fa:user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    /**
     * Verify the 2FA code during login and complete authentication.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = $request->session()->get('2fa:user_id');
        $remember = $request->session()->get('2fa:remember', false);

        if (!$userId) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your session has expired. Please log in again.']);
        }

        // SEC-001: Rate limit 2FA login verification to prevent brute-force
        $throttleKey = '2fa-verify:' . $userId;
        if (RateLimiter::tooManyAttempts($throttleKey, 60)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors(['code' => "Too many attempts. Please try again in {$seconds} seconds."]);
        }

        $user = User::find($userId);

        if (!$user || !$user->hasTwoFactorEnabled()) {
            $request->session()->forget(['2fa:user_id', '2fa:remember']);
            return redirect()->route('login')
                ->withErrors(['email' => 'Authentication failed. Please try again.']);
        }

        $code = $request->code;

        // Try TOTP verification first
        $secret = Crypt::decryptString($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $code);

        // If TOTP fails, try recovery codes
        if (!$valid) {
            $valid = $this->useRecoveryCode($user, $code);
        }

        if (!$valid) {
            // SEC-001: Count failed attempt against rate limiter
            RateLimiter::hit($throttleKey, 60);

            // Track failed attempts in DB
            $this->recordFailedAttempt($user, $request);

            return back()->withErrors(['code' => 'The authentication code is invalid.']);
        }

        // SEC-001: Verification succeeded — clear the rate limiter
        RateLimiter::clear($throttleKey);

        // Clear 2FA session data
        $request->session()->forget(['2fa:user_id', '2fa:remember']);

        // Log the user in
        Auth::login($user, $remember);

        $request->session()->regenerate();

        // Determine redirect
        if ($user->is_admin) {
            return redirect()->intended('/admin/dashboard');
        } elseif (!$user->hasWorkspace()) {
            return redirect()->intended('/onboarding/step-1');
        }

        return redirect()->intended('/dashboard');
    }

    /**
     * Try to use a recovery code to authenticate.
     */
    protected function useRecoveryCode(User $user, string $code): bool
    {
        if (!$user->two_factor_recovery_codes) {
            return false;
        }

        try {
            $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);
        } catch (\Exception $e) {
            return false;
        }

        if (!is_array($codes)) {
            return false;
        }

        // Hash the input code for constant-time comparison against stored hashes
        $hashedInput = hash('sha256', strtolower(trim($code)));

        $index = null;
        foreach ($codes as $i => $storedCode) {
            if (hash_equals($storedCode, $hashedInput)) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return false;
        }

        // Remove the used recovery code
        unset($codes[$index]);
        $codes = array_values($codes);

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes)),
        ])->save();

        return true;
    }

    /**
     * Generate a set of recovery codes.
     */
    protected function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        }
        return $codes;
    }

    /**
     * Record a failed 2FA attempt for brute-force protection.
     */
    protected function recordFailedAttempt(User $user, Request $request): void
    {
        try {
            \DB::table('two_factor_attempts')->updateOrInsert(
                [
                    'user_id'    => $user->id,
                    'ip_address' => $request->ip(),
                ],
                [
                    'attempts'        => \DB::raw('attempts + 1'),
                    'last_attempt_at' => now(),
                    'updated_at'      => now(),
                ]
            );
        } catch (\Exception $e) {
            // Silently fail -- do not block login flow for tracking errors
            report($e);
        }
    }
}
