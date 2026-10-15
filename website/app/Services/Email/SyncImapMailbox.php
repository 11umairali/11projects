<?php

namespace App\Services\Email;

use App\Models\EmailAccount;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

/** The same pure-PHP transport used by webmail; no ext-imap dependency. */
class SyncImapMailbox
{
    private ?array $folders = null;
    private int $validity = 0;

    public function __construct(private readonly Client $client) {}

    public static function connect(EmailAccount $account, int $timeout = 8): self
    {
        $manager = new ClientManager(['options' => ['fetch' => IMAP::FT_PEEK, 'sequence' => IMAP::ST_UID, 'soft_fail' => false]]);
        $client = $manager->make([
            'host' => $account->imap_host, 'port' => $account->imap_port ?: 993,
            'encryption' => $account->imap_encryption === 'none' ? false : ($account->imap_encryption ?: 'ssl'),
            'validate_cert' => config('dahify.imap.validate_cert', true),
            'username' => $account->imap_username, 'password' => self::password($account),
            'protocol' => 'imap', 'authentication' => null, 'timeout' => $timeout,
        ]);
        $client->connect();
        return new self($client);
    }

    public static function password(EmailAccount $account): string
    {
        $password = (string) $account->imap_password;
        // Only Gmail's displayed 16-letter app-password format contains separators.
        // Spaces in a regular mailbox password are significant.
        if (strtolower((string) $account->imap_host) === 'imap.gmail.com' && preg_match('/^[a-z]{4}( [a-z]{4}){3}$/i', $password)) {
            return str_replace(' ', '', $password);
        }
        return $password;
    }

    public function resolveFolder(string $name): ?string
    {
        if (strcasecmp($name, 'INBOX') === 0) return 'INBOX';
        $this->folders ??= $this->client->getConnection()->folders('', '*')->validatedData();
        foreach ($this->folders as $path => $info) {
            if (strcasecmp($path, $name) === 0) return $path;
        }
        $aliases = ['sent' => ['sent', 'sent mail', 'sent messages', 'sent items'], 'trash' => ['trash', 'deleted messages', 'deleted items', 'bin'], 'junk' => ['junk', 'spam']];
        foreach ($aliases as $flag => $names) {
            if (!in_array(strtolower($name), $names, true)) continue;
            foreach ($this->folders as $path => $info) {
                $flags = array_map('strtolower', (array) ($info['flags'] ?? []));
                $parts = explode(($info['delimiter'] ?? null) ?: '/', $path);
                if (in_array('\\'.$flag, $flags, true) || in_array(strtolower(end($parts)), $names, true)) return $path;
            }
        }
        return null;
    }

    public function select(string $path): int
    {
        $status = $this->client->openFolder($path, true);
        return $this->validity = (int) ($status['uidvalidity'] ?? 0);
    }

    public function uidValidity(): int
    {
        return $this->validity;
    }

    public function search(string $criteria): array
    {
        return array_values(array_map('intval', $this->client->getConnection()->search(explode(' ', $criteria), IMAP::ST_UID)->validatedData()));
    }

    public function overviews(array $uids): array
    {
        $raw = $this->client->getConnection()->headers($uids, 'RFC822', IMAP::ST_UID)->validatedData();
        $overviews = [];
        foreach ($raw as $uid => $text) {
            $header = new Header((string) $text, $this->client->getConfig());
            $addresses = fn ($list) => implode(', ', array_map(fn ($a) => trim(($a->personal ?? '').' <'.($a->mail ?? '').'>'), $list->toArray()));
            $overviews[] = (object) [
                'uid' => (int) $uid, 'from' => $addresses($header->get('from')), 'to' => $addresses($header->get('to')),
                'message_id' => $header->get('message_id')->toString(), 'subject' => $header->get('subject')->toString(),
                'date' => $header->get('date')->toDate()->toIso8601String(),
                'in_reply_to' => $header->get('in_reply_to')->toString(), 'references' => $header->get('references')->toString(),
            ];
        }
        return $overviews;
    }

    public function message(int $uid): Message
    {
        return new Message($uid, null, $this->client, IMAP::FT_PEEK, true, false, IMAP::ST_UID);
    }

    public function close(): void
    {
        $this->client->disconnect();
    }
}
