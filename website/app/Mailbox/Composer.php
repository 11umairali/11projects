<?php

namespace App\Mailbox;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Writing mail: prefilling replies and forwards, sending, and drafts.
 * Shared by the website and the mobile/desktop apps.
 */
class Composer
{
    public function __construct(
        private readonly Mailbox $mailbox,
        private readonly MailSender $sender,
        private readonly MailboxCredentials $credentials,
        private readonly SendingLimit $limit,
        private readonly Quota $quota,
        private readonly Drafts $drafts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function sendRules(): array
    {
        return [
            'to' => ['required', 'string', 'max:5000'],
            'cc' => ['nullable', 'string', 'max:5000'],
            'bcc' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:250'],
            'body' => ['nullable', 'string', 'max:200000'],
            'quoted' => ['nullable', 'string', 'max:500000'],
            'include_signature' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:'.(config('dahify.mail.max_attachment_mb') * 1024)],
            'mode' => ['nullable', 'in:reply,reply-all,forward'],
            'folder' => ['nullable', 'string'],
            'uid' => ['nullable', 'integer'],
            'include_attachments' => ['nullable', 'boolean'],
            'draft_id' => ['nullable', 'string', 'max:64'],
            'draft_uid' => ['nullable', 'integer'],
            'in_reply_to' => ['nullable', 'string', 'max:500'],
            'references' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sendMessages(): array
    {
        return [
            'to.required' => 'Add at least one recipient.',
            'attachments.*.max' => 'Each attachment can be up to '.config('dahify.mail.max_attachment_mb').' MB.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function draftRules(): array
    {
        return [
            'to' => ['nullable', 'string', 'max:5000'],
            'cc' => ['nullable', 'string', 'max:5000'],
            'bcc' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:250'],
            'body' => ['nullable', 'string', 'max:200000'],
            'quoted' => ['nullable', 'string', 'max:500000'],
            'include_signature' => ['nullable', 'boolean'],
            'draft_id' => ['required', 'string', 'regex:/^[A-Za-z0-9]{16,64}$/'],
            'draft_uid' => ['nullable', 'integer'],
            'mode' => ['nullable', 'in:reply,reply-all,forward'],
            'folder' => ['nullable', 'string', 'max:20'],
            'uid' => ['nullable', 'integer'],
            'in_reply_to' => ['nullable', 'string', 'max:500'],
            'references' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * The text that is sent: what the user wrote, their signature and the
     * quoted message (compose shows these as three separate parts).
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fullBody(User $user, array $validated): string
    {
        $norm = fn ($text) => str_replace(["\r\n", "\r"], "\n", (string) $text);
        $body = $norm($validated['body'] ?? '');

        if (! array_key_exists('quoted', $validated) && ! array_key_exists('include_signature', $validated)) {
            return $body; // older clients send everything in one box
        }

        $parts = [rtrim($body)];
        if (! empty($validated['include_signature']) && filled($user->signature)) {
            $parts[] = "-- \n".$norm($user->signature);
        }
        if (trim($norm($validated['quoted'] ?? '')) !== '') {
            $parts[] = trim($norm($validated['quoted']), "\n");
        }

        return implode("\n\n", array_filter($parts, fn ($p) => $p !== '')).(count($parts) > 1 ? "\n" : '');
    }

    /**
     * Sends a validated message (see sendRules()).
     *
     * @param  array<string, mixed>  $validated
     * @param  list<UploadedFile>  $files
     * @return array{folder: ?Folder, uid: ?int} the message that was replied to or forwarded
     *
     * @throws ValidationException
     */
    public function send(User $user, array $validated, array $files, bool $includeAttachments, ?string $ip): array
    {
        $maxBytes = config('dahify.mail.max_attachment_mb') * 1048576;

        $to = $this->addresses($validated['to'], 'to');
        $cc = $this->addresses($validated['cc'] ?? '', 'cc');
        $bcc = $this->addresses($validated['bcc'] ?? '', 'bcc');

        if ($to === []) {
            throw ValidationException::withMessages(['to' => 'Add at least one recipient.']);
        }

        $totalBytes = array_sum(array_map(fn (UploadedFile $f) => $f->getSize(), $files));

        $original = null;
        $folder = Folder::tryFrom((string) ($validated['folder'] ?? ''));
        $uid = ! empty($validated['uid']) ? (int) $validated['uid'] : null;
        if (! empty($validated['mode']) && $folder && $uid) {
            $original = $this->mailbox->message($folder, $uid, markSeen: false);
        }

        $forwarded = [];
        if ($original && $validated['mode'] === 'forward' && $includeAttachments) {
            foreach ($original->downloadableAttachments() as $info) {
                $forwarded[] = $this->mailbox->attachment($folder, $uid, $info->index);
                $totalBytes += $info->size;
            }
        }

        if ($totalBytes > $maxBytes) {
            throw ValidationException::withMessages(['attachments' => 'Attachments can be up to '.config('dahify.mail.max_attachment_mb').' MB in total.']);
        }

        $message = new OutgoingMessage(
            fromName: $user->name,
            fromEmail: $user->email,
            to: $to,
            cc: $cc,
            bcc: $bcc,
            subject: trim((string) ($validated['subject'] ?? '')) ?: '(no subject)',
            body: self::fullBody($user, $validated),
            attachments: $files,
            inReplyTo: ($original && $validated['mode'] !== 'forward' && $original->messageId !== '')
                ? $original->messageId
                : (($validated['in_reply_to'] ?? '') !== '' ? trim($validated['in_reply_to'], '<> ') : null),
            references: $original?->references ?? ($validated['references'] ?? null),
            forwarded: $forwarded,
        );

        if ($message->recipientCount() > config('dahify.mail.max_recipients')) {
            throw ValidationException::withMessages(['to' => 'You can send to at most '.config('dahify.mail.max_recipients').' people at once.']);
        }

        // A full mailbox can't store the copy in Sent; ask for space first.
        if ($this->quota->summary($user, $this->mailbox)['full'] ?? false) {
            throw ValidationException::withMessages([
                'to' => 'Your mailbox is full. Delete some messages or empty Trash, then send again.',
            ]);
        }

        if ($limitError = $this->limit->check($user, $message->recipientCount(), $ip)) {
            throw ValidationException::withMessages(['to' => $limitError]);
        }

        $raw = $this->sender->send($message, (string) $this->credentials->password());
        $this->limit->record($user, $message->recipientCount(), $ip);

        // The message is already sent; failing to file it is not fatal.
        try {
            $this->mailbox->append(Folder::Sent, $raw, ['\\Seen']);
            $this->drafts->discard($validated['draft_id'] ?? null, isset($validated['draft_uid']) ? (int) $validated['draft_uid'] : null);
            if ($original && in_array($validated['mode'], ['reply', 'reply-all'], true)) {
                $this->mailbox->setAnswered($folder, $uid);
            }
        } catch (Throwable $e) {
            Log::warning('Sent message could not be saved to Sent', ['user' => $user->id, 'error' => $e->getMessage()]);
        }

        return ['folder' => $original ? $folder : null, 'uid' => $original ? $uid : null];
    }

    /**
     * @param  array<string, mixed>  $validated  see draftRules()
     */
    public function saveDraft(User $user, array $validated): void
    {
        $original = null;
        $folder = Folder::tryFrom((string) ($validated['folder'] ?? ''));
        if (! empty($validated['mode']) && $folder && ! empty($validated['uid'])) {
            try {
                $original = $this->mailbox->message($folder, (int) $validated['uid'], markSeen: false);
            } catch (MessageNotFound) {
                // The original is gone; keep whatever threading we already have.
            }
        }

        $this->drafts->save($user, $validated['draft_id'], [
            ...$validated,
            'body' => self::fullBody($user, $validated),
            'context' => ! empty($validated['mode']) && $folder ? $validated['mode'].':'.$folder->value.':'.(int) $validated['uid'] : '',
            'in_reply_to' => $original && $validated['mode'] !== 'forward' ? $original->messageId : ($validated['in_reply_to'] ?? ''),
            'references' => $original?->references ?? ($validated['references'] ?? ''),
        ], isset($validated['draft_uid']) ? (int) $validated['draft_uid'] : null);
    }

    /**
     * Fields for a new message, reply, forward or saved draft.
     *
     * @return array{draft: array<string, mixed>, original: ?MessageDetail}
     */
    public function prefill(User $user, ?string $mode, ?Folder $folder, ?int $uid, ?int $draftUid, ?string $to = null): array
    {
        $draft = [
            'to' => '', 'cc' => '', 'bcc' => '', 'subject' => '', 'body' => '', 'quoted' => '',
            'signature' => (string) $user->signature, 'include_signature' => filled($user->signature),
            'mode' => null, 'folder' => null, 'uid' => null,
            'draft_id' => Str::random(24), 'draft_uid' => null, 'in_reply_to' => '', 'references' => '',
        ];
        $original = null;

        if ($draftUid) {
            $saved = $this->mailbox->message(Folder::Drafts, $draftUid, markSeen: false);
            $draft = [...$draft, ...$this->fromDraft($saved, $user), 'draft_uid' => $saved->summary->uid];
            $original = $this->originalFor($draft);
        } elseif (in_array($mode, ['reply', 'reply-all', 'forward'], true) && $folder && $uid) {
            $original = $this->mailbox->message($folder, $uid, markSeen: false);
            $draft = [...$draft, ...$this->replyFields($original, $mode, $user), 'mode' => $mode, 'folder' => $folder->value, 'uid' => $uid];
        } elseif ($to) {
            $draft['to'] = $to;
        }

        return ['draft' => $draft, 'original' => $original];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromDraft(MessageDetail $saved, User $user): array
    {
        $fields = $saved->draftFields;
        $context = ['mode' => null, 'folder' => null, 'uid' => null];

        // X-Dahify-Context: "reply:inbox:12"
        if (preg_match('/^(reply|reply-all|forward):([a-z]+):(\d+)$/', $fields['context'] ?? '', $m) && Folder::tryFrom($m[2])) {
            $context = ['mode' => $m[1], 'folder' => $m[2], 'uid' => (int) $m[3]];
        }

        return [
            'to' => $fields['to'] ?? implode(', ', array_column($saved->to, 'email')),
            'cc' => $fields['cc'] ?? implode(', ', array_column($saved->cc, 'email')),
            'bcc' => $fields['bcc'] ?? '',
            'subject' => $saved->summary->subject,
            ...QuotedText::split(QuotedText::plain($saved), (string) $user->signature),
            'draft_id' => Drafts::validId($saved->draftId) ? $saved->draftId : Str::random(24),
            'in_reply_to' => $saved->inReplyTo,
            'references' => $saved->references,
            ...$context,
        ];
    }

    /**
     * The message a draft replies to or forwards, if it still exists.
     *
     * @param  array<string, mixed>  $draft
     */
    private function originalFor(array $draft): ?MessageDetail
    {
        $folder = Folder::tryFrom((string) ($draft['folder'] ?? ''));
        if (! $draft['mode'] || ! $folder || ! $draft['uid']) {
            return null;
        }

        try {
            return $this->mailbox->message($folder, (int) $draft['uid'], markSeen: false);
        } catch (MessageNotFound) {
            return null;
        }
    }

    /**
     * @return array{to: string, cc: string, subject: string, quoted: string}
     */
    private function replyFields(MessageDetail $original, string $mode, User $user): array
    {
        $subject = $original->summary->subject;
        $self = strtolower($user->email);

        if ($mode === 'forward') {
            return [
                'to' => '',
                'cc' => '',
                'subject' => preg_match('/^(fwd?|fw):/i', $subject) ? $subject : 'Fwd: '.$subject,
                'quoted' => QuotedText::forward($original),
            ];
        }

        $replyTo = $original->replyTo !== [] ? $original->replyTo : [['name' => $original->summary->fromName, 'email' => $original->summary->fromEmail]];
        $to = array_column($replyTo, 'email');
        $cc = [];

        if ($mode === 'reply-all') {
            $to = [...$to, ...array_column($original->to, 'email')];
            $cc = array_column($original->cc, 'email');
        }

        $clean = fn (array $list) => array_values(array_unique(array_filter(array_map('strtolower', $list), fn ($a) => $a !== '' && $a !== $self)));
        $to = $clean($to);
        $cc = array_values(array_diff($clean($cc), $to));

        // Replying to your own sent message goes to its original recipients.
        if ($to === [] && strtolower($original->summary->fromEmail) === $self) {
            $to = $clean(array_column($original->to, 'email'));
        }

        return [
            'to' => implode(', ', $to),
            'cc' => implode(', ', $cc),
            'subject' => preg_match('/^re:/i', $subject) ? $subject : 'Re: '.$subject,
            'quoted' => QuotedText::reply($original),
        ];
    }

    /**
     * Splits "a@x.com, B <b@y.com>; c@z.com" into validated addresses.
     *
     * @return list<string>
     */
    private function addresses(string $input, string $field): array
    {
        $parts = preg_split('/[,;\n]+/', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/<([^<>]+)>\s*$/', $part, $m)) {
                $part = trim($m[1]);
            }
            $part = strtolower($part);

            if (Validator::make(['a' => $part], ['a' => 'email:rfc,filter'])->fails()) {
                throw ValidationException::withMessages([$field => "\"{$part}\" is not a valid email address."]);
            }
            $out[] = $part;
        }

        return array_values(array_unique($out));
    }
}
