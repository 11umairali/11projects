<?php

namespace App\Actions\Fortify;

use App\Mailbox\MailboxCredentials;
use App\Models\User;
use App\Services\AccountCreator;
use App\Services\DefaultWorkspaceProvisioner;
use App\Services\EmailAccountProvisioner;
use App\Services\SignupGuard;
use App\Services\Turnstile;
use App\Services\UsernameAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Registration is now identical to the webmail app's: name, username,
 * password. The account's address is always constructed as
 * "username@{dahify.domain}" (see config/dahify.php) — there is no
 * "email" field on the signup form any more, and no way to register
 * with an outside address such as Gmail or Yahoo.
 *
 * A real mailbox is provisioned on the same mail server the webmail app
 * uses (CyberPanel/Postfix/Dovecot — see CyberPanelMailbox), and the
 * user's mailbox password is stashed in their session (MailboxCredentials)
 * so it's available a moment later when EmailAccountProvisioner wires up
 * their built-in inbox inside the SaaS workspace they're about to create.
 */
class CreateNewUser implements CreatesNewUsers
{
    public function __construct(
        private readonly UsernameAvailability $availability,
        private readonly AccountCreator $accounts,
        private readonly Turnstile $turnstile,
        private readonly MailboxCredentials $credentials,
        private readonly SignupGuard $guard,
        private readonly DefaultWorkspaceProvisioner $workspaces,
        private readonly EmailAccountProvisioner $emailAccounts,
    ) {}

    public function create(array $input): User
    {
        $request = request();

        $this->guard->checkBot($request);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:60'],
            'username' => ['required', 'string'],
            'password' => \App\Support\CredentialPolicy::passwordRules(),
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Please accept the terms to continue.',
        ])->validate();
        $username = strtolower(trim($input['username']));
        $phoneE164 = app(\App\Services\Friends\RegistrationPhone::class)->validated($input);

        if ($problem = $this->availability->problem((string) ($input['username'] ?? ''))) {
            throw ValidationException::withMessages(['username' => $problem]);
        }

        $password = (string) $input['password'];

        if (str_contains(strtolower($password), $username)) {
            throw ValidationException::withMessages(['password' => 'Your password must not contain your username.']);
        }

        if (! $this->turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages(['captcha' => 'Please complete the security check.']);
        }

        $this->guard->checkSiteWideRate();

        // FIX-120 (preserved from the previous registration action):
        // referral codes only apply if they actually belong to an active
        // account.
        $referredBy = $input['referral_code'] ?? null;
        if ($referredBy) {
            $referrerExists = User::where('referral_code', strtoupper(trim($referredBy)))
                ->where('status', 'active')
                ->exists();
            if (! $referrerExists) {
                $referredBy = null;
            }
        }

        [$user, $recoveryWords] = $this->accounts->create($input['name'], $username, $password, $request->ip(), $referredBy, $phoneE164);
        try { app(\App\Services\Friends\RegistrationPhone::class)->attach($user, $phoneE164); } catch (\Throwable $e) {}

        $this->guard->afterSignup($user);

        // Needed immediately after this call returns: Fortify logs the user
        // in and RegisterResponse sends them into onboarding, where
        // EmailAccountProvisioner needs this same password to wire up
        // their built-in mailbox as an EmailAccount.
        $this->credentials->remember($password);
        $request->session()->put('recovery_phrase', $recoveryWords);

        // The dashboard setting overrides the legacy environment flag.
        // With the wizard disabled, provision the essentials automatically.
        if (!\App\Support\AccountFeatures::onboarding()) {
            try {
                $workspace = $this->workspaces->ensure($user);
                if ($workspace) {
                    $this->emailAccounts->ensure($user, $workspace);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Auto workspace/mailbox provisioning failed during registration', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $user->notify(new \App\Notifications\WelcomeNotification());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Welcome email skipped: '.$e->getMessage());
        }

        return $user;
    }
}