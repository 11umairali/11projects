<?php

namespace App\Providers;

use App\Mailbox\MailboxCredentials;
use App\Models\User;
use App\Support\MailDomains;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            \Laravel\Fortify\Contracts\LoginResponse::class,
            \App\Http\Responses\LoginResponse::class
        );

        // Custom RegisterResponse — runs InviteController::processPendingInvite()
        // for brand-new users who registered via a /invite/{token} link, so they
        // join the inviting workspace instead of being routed into the
        // new-workspace onboarding flow.
        $this->app->singleton(
            \Laravel\Fortify\Contracts\RegisterResponse::class,
            \App\Http\Responses\RegisterResponse::class
        );
    }

    public function boot(): void
    {
        // Rate limiting — login (60 per minute — generous to avoid 429 during testing/demo)
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(60)->by($throttleKey);
        });

        // Rate limiting — two-factor challenge
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(20)->by($request->session()->get('login.id'));
        });

        // Rate limiting — password reset
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perHour(15)->by($request->input('email').'|'.$request->ip());
        });

        // Rate limiting — registration
        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Custom auth logic.
        //
        // The login field (still submitted under the historical name
        // "email" — see resources/views/auth/login.blade.php) now accepts
        // either a bare username ("sara") or the full address
        // ("sara@dahimail.com" or any configured old domain). Both resolve
        // to the same account. The pre-existing admin account (a real
        // Gmail address, no username) still logs in with that address
        // exactly as before — MailDomains::username() returns null for
        // addresses on other domains and we fall back to the raw input.
        Fortify::authenticateUsing(function (Request $request) {
            $login = strtolower(trim((string) $request->email));

            $username = MailDomains::username($login);
            $lookupEmail = $username !== null ? $username.'@'.MailDomains::current() : $login;

            $user = User::whereRaw('LOWER(email) = ?', [$lookupEmail])->first();

            if (! $user) {
                return null;
            }

            if ($user->status !== 'active') {
                return null;
            }

            if (Hash::check($request->password, $user->password)) {
                // The webmail engine (ImapMailbox/MailSender) and
                // EmailAccountProvisioner both need the plaintext password
                // for as long as this session lives — Dovecot/Postfix
                // authenticate with it directly, there's no other token.
                app(MailboxCredentials::class)->remember($request->password);

                return $user;
            }

            return null;
        });

        // Views
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register', [
            'formToken' => app(\App\Services\SignupGuard::class)->token(),
        ]));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));

        // Custom registration
        Fortify::createUsersUsing(\App\Actions\Fortify\CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(\App\Actions\Fortify\UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(\App\Actions\Fortify\UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(\App\Actions\Fortify\ResetUserPassword::class);
    }
}
