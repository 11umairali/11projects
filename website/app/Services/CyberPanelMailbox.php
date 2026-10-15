<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates and updates mailboxes in CyberPanel's e_users table, which
 * Postfix (delivery) and Dovecot (IMAP/SMTP login) read directly.
 */
class CyberPanelMailbox
{
    public function __construct(
        private readonly string $domain,
        private readonly string $connectionName,
        private readonly string $vmailRoot,
        private readonly int $bcryptCost,
    ) {}

    public function domain(): string
    {
        return $this->domain;
    }

    public function address(string $username): string
    {
        return strtolower($username).'@'.$this->domain;
    }

    public function exists(string $username): bool
    {
        return $this->db()->table('e_users')
            ->where('email', $this->address($username))
            ->exists();
    }

    public function domainIsConfigured(): bool
    {
        return $this->db()->table('e_domains')
            ->where('domain', $this->domain)
            ->exists();
    }

    public function create(string $username, string $password): void
    {
        if (! $this->domainIsConfigured()) {
            throw new RuntimeException("The mail domain {$this->domain} does not exist in CyberPanel.");
        }

        $username = strtolower($username);

        $this->db()->table('e_users')->insert([
            'email' => $this->address($username),
            'password' => $this->hash($password),
            'mail' => "maildir:{$this->vmailRoot}/{$this->domain}/{$username}/Maildir",
            'DiskUsage' => '0',
            'emailOwner_id' => $this->domain,
        ]);
    }

    public function updatePassword(string $username, string $password): void
    {
        $updated = $this->db()->table('e_users')
            ->where('email', $this->address($username))
            ->update(['password' => $this->hash($password)]);

        if ($updated === 0 && ! $this->exists($username)) {
            throw new RuntimeException("Mailbox {$this->address($username)} was not found.");
        }
    }

    /** The stored password hash, or null if there is no mailbox. */
    public function passwordHash(string $username): ?string
    {
        return $this->db()->table('e_users')->where('email', $this->address($username))->value('password');
    }

    public function setPasswordHash(string $username, string $hash): void
    {
        $this->db()->table('e_users')->where('email', $this->address($username))->update(['password' => $hash]);
    }

    /**
     * Stops IMAP/SMTP logins by storing a hash no password can match.
     * Returns the previous hash so the mailbox can be unlocked later.
     */
    public function lock(string $username): ?string
    {
        $previous = $this->passwordHash($username);
        if ($previous !== null && ! str_starts_with($previous, self::LOCKED)) {
            $this->setPasswordHash($username, self::LOCKED.bin2hex(random_bytes(8)));
        }

        return $previous;
    }

    public const LOCKED = '{CRYPT}!locked-';

    public function delete(string $username): void
    {
        $this->db()->table('e_users')
            ->where('email', $this->address($username))
            ->delete();
    }

    /**
     * Dovecot verifies these with the system crypt(), exactly like the
     * hashes CyberPanel writes itself: "{CRYPT}$2b$12$...". PHP produces
     * the identical algorithm under the "$2y$" prefix, so only the prefix
     * is swapped.
     */
    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->bcryptCost]);

        return '{CRYPT}$2b$'.substr($hash, 4);
    }

    private function db(): ConnectionInterface
    {
        return DB::connection($this->connectionName);
    }
}
