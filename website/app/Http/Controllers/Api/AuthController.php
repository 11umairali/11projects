<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AccountCreator;
use App\Services\CyberPanelMailbox;
use App\Services\UsernameAvailability;
use App\Support\MailDomains;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;

/**
 * Mobile / public REST auth.
 *
 * Issues Sanctum personal access tokens for use by the MailTrixy mobile app
 * and any external API consumer. All endpoints return JSON; on success they
 * include a `token` (plain-text Bearer) and `user` payload.
 *
 * Routes (registered in routes/api.php under prefix `v1/auth`):
 *   POST   /v1/auth/register
 *   POST   /v1/auth/login
 *   POST   /v1/auth/logout                (auth:sanctum)
 *   POST   /v1/auth/forgot-password
 *   POST   /v1/auth/reset-password
 *   POST   /v1/auth/social/{provider}
 *   GET    /v1/auth/me                    (auth:sanctum)
 *
 * Throttling — login + forgot-password are rate-limited per IP+email to
 * prevent credential-stuffing and reset-flood. The /api `throttle:api`
 * middleware (60/min) gives a baseline; sensitive endpoints stack a
 * stricter custom limiter on top.
 */
class AuthController extends Controller
{
    private const TOKEN_NAME = 'mobile';

    /**
     * Allowed providers for social-login token exchange.
     * Limited to providers configured in config/services.php + SocialLoginController.
     */
    private const SOCIAL_PROVIDERS = ['google', 'microsoft', 'github'];

    public function register(Request $request): JsonResponse
    {
        // Registration is @dahimail.com-only across this whole platform (see
        // Actions/Fortify/CreateNewUser for the web equivalent) — the mobile
        // app must create accounts the same way, not with an arbitrary email.
        $data = Validator::make($request->all(), [
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'username' => ['required', 'string'],
            'password' => \App\Support\CredentialPolicy::passwordRules(),
            'terms'    => ['accepted'],
            'referral_code' => ['nullable', 'string', 'max:32'],
        ])->validate();

        $username = strtolower(trim($data['username']));

        // Phone number (country code + number): optional / required / hidden – set by the super admin (Admin → Phone Verification).
        try {
            $phoneE164 = app(\App\Services\Friends\RegistrationPhone::class)->validated($request->only(['phone_country', 'phone_national']));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        try {
            $problem = app(UsernameAvailability::class)->problem($data['username']);
        } catch (\Throwable $e) {
            return $this->mailServerUnavailable($e);
        }
        if ($problem) {
            return response()->json(['errors' => ['username' => [$problem]]], 422);
        }

        if (str_contains(strtolower($data['password']), $username)) {
            return response()->json(['errors' => ['password' => ['Your password must not contain your username.']]], 422);
        }

        $referredBy = $data['referral_code'] ?? null;
        if ($referredBy) {
            $referrerExists = User::where('referral_code', strtoupper(trim($referredBy)))
                ->where('status', 'active')
                ->exists();
            if (!$referrerExists) {
                $referredBy = null;
            }
        }

        try {
            app(\App\Services\SignupGuard::class)->checkSiteWideRate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 429);
        }

        try {
            [$user, $recoveryWords] = app(AccountCreator::class)->create(
                $data['name'], $username, $data['password'], $request->ip(), $referredBy, $phoneE164
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return $this->mailServerUnavailable($e);
        }

        try { app(\App\Services\SignupGuard::class)->afterSignup($user); } catch (\Throwable) {}
        try { app(\App\Services\Friends\RegistrationPhone::class)->attach($user, $phoneE164); } catch (\Throwable) {}
        if (config('dahify.auto_provision_workspace', true)) $this->provisionAccount($user, $data['password']);

        try {
            $user->notify(new \App\Notifications\WelcomeNotification());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Welcome email skipped: ' . $e->getMessage());
        }

        $token = $user->createToken(self::TOKEN_NAME, ['*', 'mobile-session'])->plainTextToken;

        return response()->json([
            'message'        => 'Registration successful.',
            'token'          => $token,
            'user'           => $this->transformUser($user),
            // Shown to the user exactly once — same as the web signup. The
            // app must display it immediately and never request it again.
            'recovery_words' => $recoveryWords,
        ], 201);
    }

    /**
     * Same follow-up the website performs after sign-up / sign-in: make sure the account has its default workspace
     * and its own IMAP/SMTP mailbox connection (needs the plaintext password, which only exists at this moment).
     * Idempotent and never fatal – a failure is logged, the person is still signed in.
     */
    private function provisionAccount(User $user, string $plainPassword): void
    {
        if (!$user->username) return;                       // legacy non-dahimail accounts have nothing to provision
        try {
            app(\App\Mailbox\MailboxCredentials::class)->useForRequest($plainPassword);
            $workspace = app(\App\Services\DefaultWorkspaceProvisioner::class)->ensure($user);
            if ($workspace) app(\App\Services\EmailAccountProvisioner::class)->ensure($user, $workspace);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Mobile account provisioning failed for user ' . $user->id . ': ' . $e->getMessage());
        }
    }

    /** The real cause goes to storage/logs/laravel.log; the app gets a readable message instead of a bare 500. */
    private function mailServerUnavailable(\Throwable $e): JsonResponse
    {
        \Illuminate\Support\Facades\Log::error('Mobile registration failed: ' . get_class($e) . ': ' . $e->getMessage());
        return response()->json(['message' => 'Sign-up is temporarily unavailable: the mail server could not be reached. Please try again later or contact support.'], 503);
    }

    public function login(Request $request): JsonResponse
    {
        // Not an 'email' format rule: this field also accepts a bare
        // username ("sara"), normalized below the same way the website does.
        $data = Validator::make($request->all(), [
            'email'    => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ])->validate();

        $login = strtolower(trim($data['email']));
        $username = MailDomains::username($login);
        $email = $username !== null ? $username . '@' . MailDomains::current() : $login;

        $key = 'mobile-login:' . sha1($email . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Too many login attempts. Try again in {$seconds} seconds.",
            ], 429);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user || !$user->password || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account has been suspended or deactivated.',
            ], 403);
        }

        // 2FA gate — if user has 2FA enabled, require a code
        if ($user->hasTwoFactorEnabled()) {
            $code = $request->input('two_factor_code');
            if (!$code) {
                return response()->json([
                    'message' => 'Two-factor code required.',
                    'two_factor_required' => true,
                ], 422);
            }

            // Cast 'encrypted' on User auto-decrypts the secret on access.
            $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);

            if (!$google2fa->verifyKey($user->two_factor_secret, (string) $code)) {
                RateLimiter::hit($key, 60);
                return response()->json([
                    'message' => 'Invalid two-factor code.',
                ], 422);
            }
        }

        RateLimiter::clear($key);

        $deviceName = $data['device_name'] ?? self::TOKEN_NAME;
        $this->provisionAccount($user, (string) $data['password']);

        $token = $user->createToken($deviceName, ['*', 'mobile-session'])->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => $this->transformUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        if (!\App\Support\AccountFeatures::emailRecovery()) return \App\Support\AccountFeatures::emailRecoveryDisabled();
        $data = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
        ])->validate();

        $email = strtolower($data['email']);
        $key = 'mobile-forgot:' . sha1($email . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Too many requests. Try again in {$seconds} seconds.",
            ], 429);
        }
        RateLimiter::hit($key, 300);

        // Always return the same response whether or not the email exists
        // (prevents account enumeration)
        try {
            Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Password reset email failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'If an account with that email exists, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        if (!\App\Support\AccountFeatures::emailRecovery()) return \App\Support\AccountFeatures::emailRecoveryDisabled();
        $data = Validator::make($request->all(), [
            'token'    => ['required', 'string'],
            'email'    => ['required', 'string', 'email'],
            'password' => \App\Support\CredentialPolicy::passwordRules(),
        ])->validate();

        $status = Password::reset(
            [
                'email'    => strtolower($data['email']),
                'password' => $data['password'],
                'password_confirmation' => $data['password'],
                'token'    => $data['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Dahimail.com users: the mail server (Dovecot/Postfix) has its
                // own copy of the password and must be changed too, or IMAP/SMTP
                // logins break — mirrors Actions/Fortify/ResetUserPassword.
                if ($user->username) {
                    app(CyberPanelMailbox::class)->updatePassword($user->username, $password);

                    \App\Models\EmailAccount::where('user_id', $user->id)
                        ->where('email', $user->email)
                        ->update(['imap_password' => $password, 'smtp_password' => $password]);
                }

                // Revoke all existing tokens — force re-login on every device
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successful. Please sign in with your new password.',
        ]);
    }

    /**
     * Social login from a mobile app.
     *
     * The mobile app uses the platform's native SDK (Google Sign-In, MSAL,
     * GitHub OAuth) to obtain an access token, then POSTs that token here.
     * We exchange it for a User and issue a Sanctum token.
     */
    public function socialLogin(Request $request, string $provider): JsonResponse
    {
        if (!in_array($provider, self::SOCIAL_PROVIDERS, true)) {
            return response()->json([
                'message' => 'Unsupported provider.',
            ], 422);
        }

        $data = Validator::make($request->all(), [
            'access_token' => ['required', 'string'],
            'device_name'  => ['nullable', 'string', 'max:80'],
        ])->validate();

        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver($provider);
            $socialUser = $driver->userFromToken($data['access_token']);
        } catch (\Throwable) {
            return response()->json([
                'message' => "Could not verify {$provider} access token.",
            ], 401);
        }

        if (!$socialUser || !$socialUser->getId()) {
            return response()->json([
                'message' => "No identity returned from {$provider}.",
            ], 401);
        }

        $email = strtolower((string) $socialUser->getEmail());

        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            $user = $socialAccount->user;

            if (!$user || $user->status !== 'active') {
                return response()->json([
                    'message' => 'Your account has been suspended or deactivated.',
                ], 403);
            }

            $socialAccount->update([
                'token'             => $data['access_token'],
                'token_expires_at'  => null,
            ]);
        } else {
            // Registration is @dahimail.com-only — social sign-in can only match
            // an EXISTING account by email, never create one from a Google/
            // Microsoft/GitHub identity (mirrors Auth\SocialLoginController on
            // the web). A dahimail.com address will never match here, so this
            // is effectively link-only for the legacy admin-style account.
            if (!$email) {
                return response()->json([
                    'message' => "No email address received from {$provider}.",
                ], 422);
            }

            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if (!$user) {
                return response()->json([
                    'message' => "No account found for that {$provider} address. "
                        . 'Create your dahimail.com account with a username first, then link '
                        . "{$provider} from Settings.",
                ], 404);
            }
            if ($user->status !== 'active') {
                return response()->json([
                    'message' => 'Your account has been suspended or deactivated.',
                ], 403);
            }

            $user->socialAccounts()->create([
                'provider'         => $provider,
                'provider_id'      => $socialUser->getId(),
                'token'            => $data['access_token'],
                'token_expires_at' => null,
            ]);
        }

        if (!$user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable) {
                // Non-fatal — verification email can be resent later
            }
        }

        $deviceName = $data['device_name'] ?? "social:{$provider}";
        $token = $user->createToken($deviceName, ['*', 'mobile-session'])->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => $this->transformUser($user),
            'is_new'  => $user->wasRecentlyCreated,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->transformUser($request->user()),
        ]);
    }

    /**
     * Shape a User for client consumption — never leak password/2FA secrets.
     */
    private function transformUser(User $user): array
    {
        $user->loadMissing('activeWorkspace');

        return [
            'id'                  => $user->id,
            'uuid'                => $user->uuid,
            'name'                => $user->name,
            'email'               => $user->email,
            'username'            => $user->username,
            'phone'               => $user->phone ?? null,
            'avatar_url'          => $user->avatar_path ? asset('storage/' . $user->avatar_path) : null,
            'billing_enabled' => !\App\Support\BillingPolicy::isFree(),
            'is_admin'            => (bool) $user->is_admin,
            'admin_role'          => $user->admin_role ?? null,
            'email_verified'      => $user->hasVerifiedEmail(),
            'two_factor_enabled'  => (bool) ($user->two_factor_secret && $user->two_factor_confirmed_at),
            'has_workspace'       => method_exists($user, 'hasWorkspace') ? $user->hasWorkspace() : (bool) $user->active_workspace_id,
            'active_workspace'    => $user->activeWorkspace ? [
                'id'   => $user->activeWorkspace->id,
                'name' => $user->activeWorkspace->name,
            ] : null,
            'created_at'          => $user->created_at?->toIso8601String(),
        ];
    }
}
