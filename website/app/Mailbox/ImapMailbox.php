<?php

namespace App\Mailbox;

use Carbon\Carbon;
use Throwable;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

/**
 * Mailbox access over IMAP (Dovecot on the same server).
 */
class ImapMailbox implements Mailbox
{
    /** Folder names to look for when the server doesn't mark special folders. */
    private const CANDIDATES = [
        'sent' => ['\\Sent', ['Sent', 'Sent Messages', 'Sent Items', 'Sent Mail']],
        'drafts' => ['\\Drafts', ['Drafts', 'Draft']],
        'spam' => ['\\Junk', ['Junk', 'Spam', 'Junk E-mail', 'Bulk Mail']],
        'trash' => ['\\Trash', ['Trash', 'Deleted Messages', 'Deleted Items', 'Bin']],
    ];

    private ?Client $client = null;

    /** @var array<string, string>|null */
    private ?array $paths = null;

    /**
     * @param  array{host: string, port: int, encryption: string, validate_cert: bool, timeout: int}  $server
     */
    public function __construct(
        private readonly array $server,
        private readonly string $username,
        private readonly string $password,
    ) {}

    public function __destruct()
    {
        try {
            $this->client?->disconnect();
        } catch (Throwable) {
            // Closing a dead connection is not worth reporting.
        }
    }

    public function counts(): array
    {
        $counts = [];

        foreach ([Folder::Inbox, Folder::Drafts, Folder::Spam] as $folder) {
            try {
                $status = $this->protocol()->folderStatus($this->path($folder), ['MESSAGES', 'UNSEEN'])->validatedData();
                $counts[$folder->value] = ['unread' => (int) ($status['unseen'] ?? 0), 'total' => (int) ($status['messages'] ?? 0)];
            } catch (MailboxException $e) {
                throw $e;
            } catch (Throwable) {
                $counts[$folder->value] = ['unread' => 0, 'total' => 0];
            }
        }

        return $counts;
    }

    public function list(Folder $folder, int $page, int $perPage, ?string $search = null): MessagePage
    {
        $protocol = $this->select($folder);

        $criteria = ['UNDELETED'];
        if ($folder === Folder::Starred) {
            $criteria[] = 'FLAGGED';
        }

        $search = $this->cleanSearch($search);
        if ($search !== '') {
            if (preg_match('/[^\x20-\x7e]/', $search)) {
                array_unshift($criteria, 'CHARSET', 'UTF-8');
            }
            $criteria[] = 'TEXT';
            $criteria[] = $protocol->escapeString($search);
        }

        $uids = array_map('intval', $this->run(fn () => $protocol->search($criteria, IMAP::ST_UID)->validatedData()));
        rsort($uids);

        $total = count($uids);
        $page = max(1, min($page, max(1, (int) ceil($total / $perPage))));
        $slice = array_slice($uids, ($page - 1) * $perPage, $perPage);

        $messages = [];
        if ($slice !== []) {
            $headers = $this->run(fn () => $protocol->headers($slice, 'RFC822', IMAP::ST_UID)->validatedData());
            $flags = $this->run(fn () => $protocol->flags($slice, IMAP::ST_UID)->validatedData());

            $previews = $this->previews($protocol, $slice, $headers);

            foreach ($slice as $uid) {
                if (! isset($headers[$uid])) {
                    continue;
                }
                $messages[] = $this->summaryFromHeader($uid, (string) $headers[$uid], (array) ($flags[$uid] ?? []))
                    ->withPreview($previews[$uid] ?? '');
            }
        }

        return new MessagePage($messages, $total, $page, $perPage);
    }

    public function message(Folder $folder, int $uid, bool $markSeen = true): MessageDetail
    {
        $message = $this->fetchMessage($folder, $uid);

        if ($markSeen && ! $message->getFlags()->has('seen')) {
            $this->run(fn () => $this->protocol()->store(['\\Seen'], $uid, null, '+', true, IMAP::ST_UID));
        }

        $attachments = [];
        foreach ($message->getAttachments()->values() as $index => $attachment) {
            $attachments[] = $this->attachmentInfo($index, $attachment, withContent: false);
        }

        $flags = array_map('strtolower', array_values($message->getFlags()->toArray()));
        $summary = new MessageSummary(
            uid: $uid,
            subject: $this->decode($this->str($message->getSubject())),
            fromName: $this->addresses($message->getFrom())[0]['name'] ?? '',
            fromEmail: $this->addresses($message->getFrom())[0]['email'] ?? '',
            to: $this->addresses($message->getTo()),
            date: $this->date($message->getDate()),
            seen: true,
            flagged: in_array('flagged', $flags, true),
            answered: in_array('answered', $flags, true),
            hasAttachments: $attachments !== [],
            size: 0,
        );

        return new MessageDetail(
            summary: $summary,
            to: $this->addresses($message->getTo()),
            cc: $this->addresses($message->getCc()),
            replyTo: $this->addresses($message->getReplyTo()),
            messageId: $this->str($message->getMessageId()),
            references: $this->str($message->getReferences()),
            html: $message->hasHTMLBody() ? $message->getHTMLBody() : null,
            text: $message->hasTextBody() ? $message->getTextBody() : '',
            attachments: $attachments,
            inReplyTo: $this->str($message->getHeader()?->get('in_reply_to')),
            draftId: $this->str($message->getHeader()?->get('x_dahify_draft')),
            draftFields: array_filter([
                'to' => $this->str($message->getHeader()?->get('x_dahify_to')),
                'cc' => $this->str($message->getHeader()?->get('x_dahify_cc')),
                'bcc' => $this->str($message->getHeader()?->get('x_dahify_bcc')),
                'context' => $this->str($message->getHeader()?->get('x_dahify_context')),
            ], fn ($v) => $v !== ''),
        );
    }

    public function attachment(Folder $folder, int $uid, int $index): AttachmentInfo
    {
        $attachment = $this->fetchMessage($folder, $uid)->getAttachments()->values()->get($index);

        if (! $attachment instanceof Attachment) {
            throw new MessageNotFound("Attachment {$index} of message {$uid} was not found.");
        }

        return $this->attachmentInfo($index, $attachment, withContent: true);
    }

    public function setSeen(Folder $folder, int $uid, bool $seen): void
    {
        $protocol = $this->select($folder);
        $this->run(fn () => $protocol->store(['\\Seen'], $uid, null, $seen ? '+' : '-', true, IMAP::ST_UID));
    }

    public function setFlagged(Folder $folder, int $uid, bool $flagged): void
    {
        $protocol = $this->select($folder);
        $this->run(fn () => $protocol->store(['\\Flagged'], $uid, null, $flagged ? '+' : '-', true, IMAP::ST_UID));
    }

    public function setAnswered(Folder $folder, int $uid): void
    {
        $protocol = $this->select($folder);
        $this->run(fn () => $protocol->store(['\\Answered'], $uid, null, '+', true, IMAP::ST_UID));
    }

    public function move(Folder $from, int $uid, Folder $to): void
    {
        if ($from->storage() === $to->storage()) {
            return;
        }

        $protocol = $this->select($from);
        $target = $this->path($to);
        $this->run(fn () => $protocol->moveMessage($target, $uid, null, IMAP::ST_UID)->validatedData());
    }

    public function delete(Folder $folder, int $uid): void
    {
        if (! $folder->deletesPermanently()) {
            $this->move($folder, $uid, Folder::Trash);

            return;
        }

        $protocol = $this->select($folder);
        $this->run(fn () => $protocol->store(['\\Deleted'], $uid, null, '+', true, IMAP::ST_UID));
        $this->run(fn () => $protocol->expunge());
    }

    public function append(Folder $folder, string $rawMessage, array $flags = []): void
    {
        $path = $this->path($folder);
        $this->run(fn () => $this->protocol()->appendMessage($path, $rawMessage, $flags === [] ? null : $flags)->validatedData());
    }

    public function usageBytes(): ?int
    {
        $list = $this->run(fn () => $this->protocol()->folders('', '*')->validatedData());
        $total = 0;

        foreach ($list as $name => $info) {
            if (in_array('\\noselect', array_map('strtolower', (array) $info['flags']), true)) {
                continue;
            }
            try {
                // STATUS ... (SIZE) is supported by Dovecot 2.3+.
                $status = $this->protocol()->folderStatus((string) $name, ['SIZE'])->validatedData();
            } catch (Throwable) {
                return null;
            }
            if (! isset($status['size'])) {
                return null;
            }
            $total += (int) $status['size'];
        }

        return $total;
    }

    public function emptyFolder(Folder $folder): int
    {
        if (! $folder->deletesPermanently()) {
            return 0;
        }

        $protocol = $this->select($folder);
        $uids = array_map('intval', (array) $this->run(fn () => $protocol->search(['ALL'], IMAP::ST_UID)->validatedData()));
        if ($uids === []) {
            return 0;
        }

        $this->run(fn () => $protocol->store(['\\Deleted'], min($uids), max($uids), '+', true, IMAP::ST_UID));
        $this->run(fn () => $protocol->expunge());

        return count($uids);
    }

    public function raw(Folder $folder, int $uid): string
    {
        $protocol = $this->select($folder);
        $headers = $this->run(fn () => $protocol->headers([$uid], 'RFC822', IMAP::ST_UID)->validatedData());
        $body = $this->run(fn () => $protocol->content([$uid], 'RFC822', IMAP::ST_UID)->validatedData());

        if (! isset($headers[$uid])) {
            throw new MessageNotFound("Message {$uid} was not found.");
        }

        return rtrim((string) $headers[$uid], "\r\n")."\r\n\r\n".($body[$uid] ?? '');
    }

    public function findByHeader(Folder $folder, string $name, string $value): array
    {
        // Only simple tokens are searched, so nothing can break out of the quotes.
        if (! preg_match('/^[A-Za-z0-9-]{1,40}$/', $name) || ! preg_match('/^[A-Za-z0-9]{1,64}$/', $value)) {
            return [];
        }

        $protocol = $this->select($folder);
        $uids = $this->run(fn () => $protocol->search(['UNDELETED', 'HEADER', '"'.$name.'"', '"'.$value.'"'], IMAP::ST_UID)->validatedData());

        return array_values(array_map('intval', (array) $uids));
    }

    public function purge(Folder $folder, array $uids): void
    {
        if ($uids === []) {
            return;
        }

        $protocol = $this->select($folder);
        foreach ($uids as $uid) {
            $this->run(fn () => $protocol->store(['\\Deleted'], (int) $uid, null, '+', true, IMAP::ST_UID));
        }
        $this->run(fn () => $protocol->expunge());
    }

    // ------------------------------------------------------------------

    private function client(): Client
    {
        if ($this->client === null) {
            $manager = new ClientManager([
                'options' => [
                    'fetch' => IMAP::FT_PEEK,
                    'sequence' => IMAP::ST_UID,
                    'fetch_order' => 'desc',
                    'soft_fail' => false,
                    'rfc822' => true,
                    'debug' => false,
                ],
            ]);

            $this->client = $manager->make([
                'host' => $this->server['host'],
                'port' => $this->server['port'],
                'encryption' => $this->server['encryption'] === 'none' ? false : $this->server['encryption'],
                'validate_cert' => $this->server['validate_cert'],
                'username' => $this->username,
                'password' => $this->password,
                'protocol' => 'imap',
                'authentication' => null,
                'timeout' => $this->server['timeout'] ?? 15,
            ]);

            try {
                $this->client->connect();
            } catch (AuthFailedException) {
                throw MailboxException::authFailed();
            } catch (Throwable $e) {
                if (str_contains(strtolower($e->getMessage()), 'authentication failed')) {
                    throw MailboxException::authFailed();
                }

                throw new MailboxException('Could not connect to the mail server: '.$e->getMessage(), 0, $e);
            }
        }

        return $this->client;
    }

    private function protocol(): ImapProtocol
    {
        /** @var ImapProtocol $protocol */
        $protocol = $this->client()->getConnection();

        return $protocol;
    }

    private function select(Folder $folder): ImapProtocol
    {
        $protocol = $this->protocol();
        $path = $this->path($folder);
        $this->run(fn () => $protocol->selectFolder($path)->validatedData());

        return $protocol;
    }

    /**
     * Finds (or creates) the real IMAP folder for each app folder.
     */
    private function path(Folder $folder): string
    {
        $folder = $folder->storage();

        if ($folder === Folder::Inbox) {
            return 'INBOX';
        }

        if ($this->paths === null) {
            $this->paths = $this->resolvePaths();
        }

        return $this->paths[$folder->value];
    }

    /**
     * @return array<string, string>
     */
    private function resolvePaths(): array
    {
        $list = $this->run(fn () => $this->protocol()->folders('', '*')->validatedData());

        // Dovecot set up with "prefix = INBOX." keeps every folder under
        // INBOX. (INBOX.Sent, INBOX.Trash...), so new folders need it too.
        $prefix = $this->personalNamespacePrefix();

        $paths = [];
        foreach (self::CANDIDATES as $key => [$specialUse, $names]) {
            $paths[$key] = $this->findFolder($list, $specialUse, $names, $prefix);

            if ($paths[$key] === null) {
                $paths[$key] = $prefix.$names[0];
                try {
                    $this->protocol()->createFolder($paths[$key]);
                    $this->protocol()->subscribeFolder($paths[$key]);
                } catch (Throwable) {
                    // Another session may have created it at the same moment.
                }
            }
        }

        return $paths;
    }

    private function personalNamespacePrefix(): string
    {
        try {
            $response = $this->protocol()->requestAndResponse('NAMESPACE', [], false);
            foreach ($response->data() as $line) {
                // * NAMESPACE (("INBOX." ".")) NIL NIL
                if (is_array($line) && ($line[0] ?? null) === 'NAMESPACE' && is_array($line[1] ?? null)) {
                    $personal = $line[1][0] ?? null;

                    return is_array($personal) ? trim((string) ($personal[0] ?? ''), '"') : '';
                }
            }
        } catch (Throwable) {
            // Servers without NAMESPACE support use no prefix.
        }

        return '';
    }

    /**
     * @param  array<string, array{delimiter: string, flags: array<int, string>}>  $list
     * @param  list<string>  $names
     */
    private function findFolder(array $list, string $specialUse, array $names, string $prefix): ?string
    {
        foreach ($list as $name => $info) {
            if (in_array(strtolower($specialUse), array_map('strtolower', (array) $info['flags']), true)) {
                return (string) $name;
            }
        }

        foreach ($names as $candidate) {
            foreach ([$prefix.$candidate, $candidate] as $wanted) {
                foreach (array_keys($list) as $name) {
                    if (strcasecmp((string) $name, $wanted) === 0) {
                        return (string) $name;
                    }
                }
            }
        }

        return null;
    }

    private function fetchMessage(Folder $folder, int $uid): Message
    {
        $this->select($folder);

        try {
            $message = $this->client()->getFolderByPath($this->path($folder))
                ->query()
                ->setFetchBody(true)
                ->leaveUnread()
                ->getMessageByUid($uid);
        } catch (Throwable $e) {
            throw new MessageNotFound("Message {$uid} was not found.", 0, $e);
        }

        if ($message->getFlags()->has('deleted')) {
            throw new MessageNotFound("Message {$uid} was deleted.");
        }

        return $message;
    }

    /**
     * @param  array<int, string>  $flags
     */
    /**
     * The first line or two of each message for the list, read from the
     * start of the body only (a few KB per message, one request).
     *
     * @param  list<int>  $uids
     * @param  array<int|string, mixed>  $headers
     * @return array<int, string>
     */
    private function previews(ImapProtocol $protocol, array $uids, array $headers): array
    {
        try {
            $data = $this->run(fn () => $protocol->fetch(['UID', 'BODY.PEEK[TEXT]<0.'.Preview::FETCH_BYTES.'>'], $uids, null, IMAP::ST_UID)->validatedData());
        } catch (Throwable) {
            return []; // previews are a nice-to-have
        }

        $out = [];
        foreach ((array) $data as $uid => $items) {
            foreach ((array) $items as $key => $value) {
                if (is_string($key) && str_starts_with(strtoupper($key), 'BODY[TEXT]') && is_string($value)) {
                    $out[(int) $uid] = Preview::fromPartial((string) ($headers[$uid] ?? ''), $value);
                }
            }
        }

        return $out;
    }

    private function summaryFromHeader(int $uid, string $raw, array $flags): MessageSummary
    {
        $header = new Header($raw, $this->client()->getConfig());
        $flags = array_map(fn ($f) => strtolower(ltrim((string) $f, '\\')), $flags);
        $from = $this->addresses($header->get('from'));
        $contentType = strtolower($this->str($header->get('content_type')));

        return new MessageSummary(
            uid: $uid,
            subject: $this->decode($this->str($header->get('subject'))),
            fromName: $from[0]['name'] ?? '',
            fromEmail: $from[0]['email'] ?? '',
            to: $this->addresses($header->get('to')),
            date: $this->date($header->get('date')),
            seen: in_array('seen', $flags, true),
            flagged: in_array('flagged', $flags, true),
            answered: in_array('answered', $flags, true),
            hasAttachments: str_contains($contentType, 'multipart/mixed'),
            size: 0,
        );
    }

    private function attachmentInfo(int $index, Attachment $attachment, bool $withContent): AttachmentInfo
    {
        $contentId = $attachment->id ? trim((string) $attachment->id, '<> ') : null;
        $disposition = strtolower((string) ($attachment->disposition ?? ''));
        $name = $this->str($attachment->name) ?: ('attachment-'.($index + 1).($attachment->getExtension() ? '.'.$attachment->getExtension() : ''));
        $content = (string) $attachment->content;

        return new AttachmentInfo(
            index: $index,
            name: $name,
            mime: strtolower($attachment->getMimeType() ?: ($attachment->content_type ?: 'application/octet-stream')),
            size: (int) ($attachment->size ?: strlen($content)),
            contentId: $contentId ?: null,
            inline: $disposition === 'inline' || ($disposition === '' && $contentId),
            content: $withContent ? $content : null,
        );
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    private function addresses(mixed $attribute): array
    {
        $values = match (true) {
            $attribute === null => [],
            is_object($attribute) && method_exists($attribute, 'all') => $attribute->all(),
            is_array($attribute) => $attribute,
            default => [$attribute],
        };

        $out = [];
        foreach ($values as $address) {
            if ($address instanceof Address) {
                $email = strtolower(trim((string) $address->mail));
                $name = trim($this->decode((string) $address->personal), " \"'");
                if ($email !== '' || $name !== '') {
                    $out[] = ['name' => $name === $email ? '' : $name, 'email' => $email];
                }
            }
        }

        return $out;
    }

    private function date(mixed $attribute): ?Carbon
    {
        try {
            $value = is_object($attribute) && method_exists($attribute, 'first') ? $attribute->first() : $attribute;

            return $value instanceof Carbon ? $value : ($value ? Carbon::parse((string) $value) : null);
        } catch (Throwable) {
            return null;
        }
    }

    private function str(mixed $value): string
    {
        if (is_object($value) && method_exists($value, 'first')) {
            $value = $value->first();
        }

        return is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : '';
    }

    /**
     * Decodes RFC 2047 encoded words (=?UTF-8?B?...?=) left in a header.
     */
    private function decode(string $value): string
    {
        if (! str_contains($value, '=?')) {
            return $value;
        }

        $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded === false ? $value : $decoded;
    }

    /**
     * Search text is sent inside an IMAP quoted string, so line breaks and
     * control characters are removed.
     */
    private function cleanSearch(?string $search): string
    {
        return trim(mb_substr(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', (string) $search), 0, 100));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function run(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (MailboxException|MessageNotFound $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MailboxException('Mail server error: '.$e->getMessage(), 0, $e);
        }
    }
}
