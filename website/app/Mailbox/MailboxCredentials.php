<?php

namespace App\Mailbox;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Crypt;

/**
 * The web app signs in to the user's IMAP/SMTP mailbox with the user's own
 * password. It is kept encrypted (with APP_KEY) in the server-side session
 * and disappears when the session ends, the same approach Roundcube uses.
 */
class MailboxCredentials
{
    private const KEY = 'mailbox.secret';

    /** Set for app (API) requests, where there is no web session. */
    private ?string $runtime = null;

    public function __construct(private readonly Session $session) {}

    public function useForRequest(string $password): void
    {
        $this->runtime = $password;
    }

    public function remember(string $password): void
    {
        $this->session->put(self::KEY, Crypt::encryptString($password));
    }

    public function password(): ?string
    {
        if ($this->runtime !== null) {
            return $this->runtime;
        }

        $secret = $this->session->get(self::KEY);

        if (! is_string($secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($secret);
        } catch (DecryptException) {
            $this->forget();

            return null;
        }
    }

    public function has(): bool
    {
        return $this->password() !== null;
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }
}
