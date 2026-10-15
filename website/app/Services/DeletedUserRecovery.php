<?php

namespace App\Services;

use App\Models\User;
use App\Support\CredentialPolicy;
use App\Support\Username;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Server-administrator recovery of a surviving mailbox after a user row was deleted. */
class DeletedUserRecovery
{
    public function __construct(
        private readonly CyberPanelMailbox $mailbox,
        private readonly RecoveryPhrase $recovery,
    ) {}

    /** @return array{username: string, email: string} */
    public function inspect(string $username): array
    {
        $username = strtolower(trim($username));
        if ($problem = CredentialPolicy::usernameProblem($username)) $this->fail($problem);
        $email = $this->mailbox->address($username);
        if (User::whereRaw(Username::SQL.' = ?', [$username])->orWhereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->fail('An application account already owns this username or email. Use its existing account recovery.');
        }
        $this->loginHash($username);
        return ['username' => $username, 'email' => $email];
    }

    /** @return array{0: User, 1: list<string>} */
    public function recover(string $username, string $name): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) $this->fail('Enter a display name of 1–255 characters.');
        return DB::transaction(function () use ($username, $name) {
            $identity = $this->inspect($username);
            $hash = $this->loginHash($identity['username']);
            $words = $this->recovery->generate(config('dahify.recovery.words', 12));
            $user = new User([
                'name' => $name,
                'username' => $identity['username'],
                'email' => $identity['email'],
                // Reuse the existing mailbox password without reading its plaintext.
                'password' => $hash,
                'recovery_phrase_hash' => $this->recovery->hash($words),
                'status' => 'active',
            ]);
            $user->email_verified_at = now();
            $user->is_admin = false;
            $user->save();
            return [$user, $words];
        });
    }

    private function loginHash(string $username): string
    {
        $hash = $this->mailbox->passwordHash($username);
        if ($hash === null) $this->fail('No existing CyberPanel mailbox was found. Restore it from backup first.');
        // Only accept the bcrypt scheme produced by CyberPanelMailbox::hash().
        if (!preg_match('/^\{CRYPT\}\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}$/D', $hash)) {
            $this->fail('The mailbox is locked or uses an unsupported password hash. Reset its password in CyberPanel first.');
        }
        return '$2y$'.substr($hash, 11);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['username' => $message]);
    }
}
