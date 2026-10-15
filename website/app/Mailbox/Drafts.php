<?php

namespace App\Mailbox;

use App\Models\User;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Drafts live in the mailbox's real Drafts folder, so desktop and phone
 * mail apps see them too. Each draft carries a random X-Dahify-Draft id;
 * saving again replaces the previous copy with the same id.
 *
 * What was typed in To/Cc/Bcc is kept as-is in private headers, because a
 * half-written address is not valid in a real To header yet.
 */
class Drafts
{
    public const ID_HEADER = 'X-Dahify-Draft';

    public function __construct(private readonly Mailbox $mailbox) {}

    public static function validId(?string $id): bool
    {
        return is_string($id) && preg_match('/^[A-Za-z0-9]{16,64}$/', $id) === 1;
    }

    /**
     * @param  array{to?: ?string, cc?: ?string, bcc?: ?string, subject?: ?string, body?: ?string, context?: ?string, in_reply_to?: ?string, references?: ?string}  $fields
     */
    public function save(User $user, string $draftId, array $fields, ?int $replacesUid = null): void
    {
        $email = (new Email)
            ->from(new Address($user->email, $user->name))
            ->subject($this->line($fields['subject'] ?? ''))
            ->text(str_replace("\r\n", "\n", (string) ($fields['body'] ?? '')));

        foreach (['to' => 'addTo', 'cc' => 'addCc'] as $field => $method) {
            foreach ($this->validAddresses($fields[$field] ?? '') as $address) {
                $email->{$method}($address);
            }
        }

        $headers = $email->getHeaders();
        $headers->addTextHeader(self::ID_HEADER, $draftId);
        foreach (['to' => 'X-Dahify-To', 'cc' => 'X-Dahify-Cc', 'bcc' => 'X-Dahify-Bcc', 'context' => 'X-Dahify-Context'] as $field => $header) {
            $value = $this->line($fields[$field] ?? '');
            if ($value !== '') {
                $headers->addTextHeader($header, $value);
            }
        }
        if (! empty($fields['in_reply_to'])) {
            $headers->addIdHeader('In-Reply-To', trim($fields['in_reply_to'], '<> '));
        }
        if (! empty($fields['references'])) {
            $ids = array_values(array_filter(array_map(fn ($r) => trim($r, '<> '), preg_split('/\s+/', $fields['references']))));
            if ($ids !== []) {
                $headers->addIdHeader('References', array_slice($ids, -20));
            }
        }

        $previous = $this->mailbox->findByHeader(Folder::Drafts, self::ID_HEADER, $draftId);

        $this->mailbox->append(Folder::Drafts, $email->toString(), ['\\Seen', '\\Draft']);

        if ($replacesUid) {
            $previous[] = $replacesUid;
        }
        $this->mailbox->purge(Folder::Drafts, array_values(array_unique($previous)));
    }

    public function discard(?string $draftId, ?int $uid): void
    {
        $uids = self::validId($draftId) ? $this->mailbox->findByHeader(Folder::Drafts, self::ID_HEADER, $draftId) : [];
        if ($uid) {
            $uids[] = $uid;
        }
        $this->mailbox->purge(Folder::Drafts, array_values(array_unique($uids)));
    }

    /**
     * @return list<string>
     */
    private function validAddresses(?string $input): array
    {
        $out = [];
        foreach (preg_split('/[,;\n]+/', (string) $input, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $part = trim($part);
            if (preg_match('/<([^<>]+)>\s*$/', $part, $m)) {
                $part = trim($m[1]);
            }
            if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $out[] = strtolower($part);
            }
        }

        return array_values(array_unique($out));
    }

    /** One header-safe line. */
    private function line(?string $value): string
    {
        return trim(mb_substr(preg_replace('/[\r\n\t]+/', ' ', (string) $value) ?? '', 0, 900));
    }
}
