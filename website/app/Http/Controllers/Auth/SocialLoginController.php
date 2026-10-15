<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    public function redirect(string $provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        // Whitelist allowed providers to prevent XSS via flash message
        $allowedProviders = ['google', 'microsoft', 'github'];
        $safeProviderName = in_array($provider, $allowedProviders, true)
            ? ucfirst($provider)
            : 'the selected provider';

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect(url('/login'))->with('error', 'Unable to login with ' . $safeProviderName . '. Please try again.');
        }

        // Validate that the OAuth provider returned a valid access token
        if (!$socialUser->token) {
            return redirect(url('/login'))->with('error', 'Authentication failed — no access token received.');
        }

        // Find existing social account
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            $existingUser = $socialAccount->user;

            // FIX-012: Check account status before login
            if ($existingUser->status !== 'active') {
                return redirect(url('/login'))->with('error', 'Your account has been suspended or deactivated.');
            }

            // Update tokens
            $socialAccount->update([
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);

            // FIX-008: Regenerate session BEFORE login to prevent session fixation
            session()->regenerate();
            Auth::login($existingUser);
            return redirect(url('/dashboard'));
        }

        // FIX-011: Normalize email to lowercase for case-insensitive lookup
        $email = strtolower($socialUser->getEmail());

        if (!$email) {
            return redirect(url('/login'))->with('error', 'No email address received from ' . $safeProviderName . '.');
        }

        // Find user by email. Registration is @dahimail.com-only now (see
        // Actions/Fortify/CreateNewUser) — social login can no longer be
        // used to CREATE an account with an outside address, only to sign
        // in to (or link) an existing one. If you want "sign in with
        // Google" as a login shortcut for an existing dahimail.com user,
        // that should be built as an explicit account-linking flow from
        // Settings while the user is already authenticated, not implicitly
        // here from a bare email match.
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user) {
            return redirect(url('/register'))
                ->with('error', "We couldn't find an account for that {$safeProviderName} address. Create your account with a username first, then you can link {$safeProviderName} from Settings.");
        }

        // FIX-010/012: Verify existing user is active before linking social account
        if ($user->status !== 'active') {
            return redirect(url('/login'))->with('error', 'Your account has been suspended or deactivated.');
        }

        // Create social account link
        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
        ]);

        // FIX-008: Regenerate session BEFORE login to prevent session fixation
        session()->regenerate();
        Auth::login($user);

        // FIX-009: Send verification email if not yet verified
        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        if ($user->is_admin) {
            return redirect(url('/admin/dashboard'));
        }

        if (!$user->hasWorkspace()) {
            return redirect(url('/onboarding/step-1'));
        }

        return redirect(url('/dashboard'));
    }
}
