<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates the CyberPanel mailbox and the SaaS user account together.
 *
 * This is the SaaS-side equivalent of Dahify's own AccountCreator: same
 * mailbox provisioning, but produces a row in the SaaS app's own `users`
 * table (with its uuid/referral_code/status/etc.) instead of Dahify's.
 */
class AccountCreator
{
    public function __construct(
        private readonly CyberPanelMailbox $mailbox,
        private readonly RecoveryPhrase $recovery,
    ) {}

    /**
     * @return array{0: User, 1: list<string>} the account and its recovery phrase
     */
    public function create(string $name, string $username, string $password, ?string $ip, ?string $referredBy = null, ?string $phone = null): array
    {
        $username = strtolower(trim($username));

        try {
            $this->mailbox->create($username, $password);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'That username is already taken.']);
        }

        $words = $this->recovery->generate(config('dahify.recovery.words'));

        try {
            $user = new User([
                'name' => $name,
                'phone' => $phone,
                'username' => $username,
                'email' => $this->mailbox->address($username),
                // The mailbox we just created *is* the verified identity —
                // there's nothing external to confirm by clicking a link.
                'password' => $password,
                'recovery_phrase_hash' => $this->recovery->hash($words),
                'signup_ip' => $ip,
                'referral_code' => strtoupper(Str::random(16)),
                'referred_by' => $referredBy,
                'status' => 'active',
            ]);
            // This trusted provisioning path owns the newly created mailbox.
            // email_verified_at is deliberately not a mass-assignable user field.
            $user->forceFill(['email_verified_at' => now()]);
            $user->save();
        } catch (Throwable $e) {
            // Don't leave an orphaned mailbox behind.
            $this->mailbox->delete($username);

            if ($e instanceof UniqueConstraintViolationException) {
                if ($phone && User::where('phone', $phone)->exists()) throw ValidationException::withMessages(['phone_national' => 'This mobile number is already registered to another account.']);
                throw ValidationException::withMessages(['username' => 'That username is already taken.']);
            }

            throw $e;
        }

        return [$user, $words];
    }
}
