<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\User;
use App\Services\CyberPanelMailbox;
use App\Services\RecoveryPhrase;
use App\Services\UsernameAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Public (no token) endpoints the app needs for a fully in-app account lifecycle:
 *
 *   GET  ping                      – connectivity / deployment check (shown on the login screen)
 *   GET  auth/username-available   – live availability check while typing on the sign-up form
 *   POST auth/recover/phrase       – forgot password → username + 12-word recovery phrase + new password
 *   POST auth/password/code        – forgot password for accounts with an external email → 6-digit code by email
 *   POST auth/password/reset-code  – finish the code flow
 *
 * Why the phrase flow: every @dahimail.com account's login email IS its DahiMail mailbox, so a reset link
 * sent by email is unreachable for someone who forgot the password. The recovery phrase created at sign-up
 * (users.recovery_phrase_hash) is the only sane way back in.
 */
class AuthExtrasController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        $out = ['ok' => true, 'app' => config('app.name'), 'api' => 'v1', 'mobile' => 2, 'time' => now()->toIso8601String()];
        if ($request->boolean('deep')) {
            // Booleans only – the real error text is written to storage/logs/laravel.log, never sent to clients.
            $check = function (callable $fn, string $what) {
                try { return (bool) $fn(); } catch (\Throwable $e) { \Log::warning("Health check failed ({$what}): " . get_class($e) . ': ' . $e->getMessage()); return false; }
            };
            $out['database']    = $check(fn () => \DB::select('select 1') !== null, 'main database');
            $out['mail_db']     = $check(fn () => app(\App\Services\CyberPanelMailbox::class)->domainIsConfigured() || true, 'CyberPanel database');
            $out['mail_domain'] = $out['mail_db'] && $check(fn () => app(\App\Services\CyberPanelMailbox::class)->domainIsConfigured(), 'mail domain in e_domains');
            $out['signup_ready'] = $out['database'] && $out['mail_db'] && $out['mail_domain'];
        }
        return response()->json($out);
    }

    /** Public: tells the sign-up screen whether to show the phone field and whether numbers get verified. */
    public function phoneConfig(): JsonResponse
    {
        return response()->json(['data' => [
            'account_features' => \App\Support\AccountFeatures::all(),
            'credential_policy' => \App\Support\CredentialPolicy::publicRules(),
            'registration' => \App\Support\PhoneSettings::registrationMode(),   // off | optional | required
            'verification' => \App\Support\PhoneSettings::channels() !== [],
            'channels' => \App\Support\PhoneSettings::channels(),
        ]]);
    }

    public function usernameAvailable(Request $request): JsonResponse
    {
        $u = trim((string) $request->query('username', ''));
        if ($u === '') return response()->json(['available' => false, 'message' => 'Enter a username.']);
        try {
            $problem = app(UsernameAvailability::class)->problem($u);
        } catch (\Throwable $e) {
            \Log::warning('Username check failed: ' . $e->getMessage());
            return response()->json(['available' => false, 'message' => 'Could not check right now.'], 503);
        }
        return response()->json(['available' => $problem === null, 'message' => $problem ?? 'Available']);
    }

    private function findUser(string $id): ?User
    {
        $id = strtolower(trim($id));
        if ($id === '') return null;
        $local = preg_replace('/@dahimail\.com$/', '', $id);
        return User::where(fn ($q) => $q->whereRaw('LOWER(email) = ?', [$id])->orWhereRaw('LOWER(username) = ?', [$local]))
            ->where('status', 'active')->first();
    }

    private function applyPassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password, 'remember_token' => \Illuminate\Support\Str::random(60), 'force_password_reset' => 0])->save();
        if ($user->username) {
            try {
                app(CyberPanelMailbox::class)->updatePassword($user->username, $password);
            } catch (\Throwable $e) {
                \Log::warning('Mailbox password sync failed: ' . $e->getMessage());
            }
            EmailAccount::where('user_id', $user->id)->where('email', $user->email)->update(['imap_password' => $password, 'smtp_password' => $password]);
        }
        $user->tokens()->delete(); // sign out every device
    }

    public function recoverWithPhrase(Request $request): JsonResponse
    {
        if (!\App\Support\AccountFeatures::phraseRecovery()) return \App\Support\AccountFeatures::phraseRecoveryDisabled();
        $d = $request->validate([
            'username' => 'required|string|max:120',
            'phrase'   => 'required|string|max:400',
            'password' => \App\Support\CredentialPolicy::passwordRules(),
        ]);
        $key = 'recover:' . sha1(strtolower($d['username']) . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.'], 429);
        }
        RateLimiter::hit($key, 900);

        $user = $this->findUser($d['username']);
        // Same error for "no such user", "no phrase on file" and "wrong phrase" – prevents account probing.
        if (!$user || !app(RecoveryPhrase::class)->check($d['phrase'], $user->recovery_phrase_hash)) {
            return response()->json(['message' => 'Username and recovery phrase do not match.'], 422);
        }
        if (str_contains(strtolower($d['password']), strtolower((string) $user->username))) {
            return response()->json(['errors' => ['password' => ['Your password must not contain your username.']]], 422);
        }
        RateLimiter::clear($key);
        $this->applyPassword($user, $d['password']);
        return response()->json(['message' => 'Password changed. Sign in with your new password.']);
    }

    public function sendCode(Request $request): JsonResponse
    {
        if (!\App\Support\AccountFeatures::emailRecovery()) return \App\Support\AccountFeatures::emailRecoveryDisabled();
        $d = $request->validate(['email' => 'required|email']);
        $email = strtolower($d['email']);
        $key = 'pwd-code:' . sha1($email . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['message' => 'Too many requests. Try again in ' . RateLimiter::availableIn($key) . ' seconds.'], 429);
        }
        RateLimiter::hit($key, 600);

        $user = User::whereRaw('LOWER(email) = ?', [$email])->where('status', 'active')->first();
        // dahimail.com logins cannot receive their own reset mail – the app steers those users to the phrase flow.
        if ($user && !str_ends_with($email, '@dahimail.com')) {
            $code = (string) random_int(100000, 999999);
            Cache::put('pwd_code:' . $email, ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(15));
            try {
                Mail::raw("Your " . config('app.name') . " password reset code is {$code}\n\nIt expires in 15 minutes. If you did not ask for it, ignore this message.", fn ($m) => $m->to($email)->subject(config('app.name') . ' password reset code'));
            } catch (\Throwable $e) {
                \Log::warning('Reset code mail failed: ' . $e->getMessage());
            }
        }
        return response()->json(['message' => 'If that address belongs to an account, a 6-digit code has been sent.', 'uses_phrase' => str_ends_with($email, '@dahimail.com')]);
    }

    public function resetWithCode(Request $request): JsonResponse
    {
        if (!\App\Support\AccountFeatures::emailRecovery()) return \App\Support\AccountFeatures::emailRecoveryDisabled();
        $d = $request->validate([
            'email' => 'required|email', 'code' => 'required|digits:6',
            'password' => \App\Support\CredentialPolicy::passwordRules(),
        ]);
        $email = strtolower($d['email']);
        $rec = Cache::get('pwd_code:' . $email);
        $user = User::whereRaw('LOWER(email) = ?', [$email])->where('status', 'active')->first();
        if (!$rec || !$user) return response()->json(['message' => 'The code is invalid or has expired.'], 422);
        if ($rec['tries'] >= 5) { Cache::forget('pwd_code:' . $email); return response()->json(['message' => 'Too many wrong codes. Request a new one.'], 429); }
        if (!Hash::check($d['code'], $rec['hash'])) {
            $rec['tries']++; Cache::put('pwd_code:' . $email, $rec, now()->addMinutes(15));
            return response()->json(['message' => 'The code is invalid or has expired.'], 422);
        }
        Cache::forget('pwd_code:' . $email);
        $this->applyPassword($user, $d['password']);
        return response()->json(['message' => 'Password changed. Sign in with your new password.']);
    }
}
