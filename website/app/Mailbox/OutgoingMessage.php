<?php

namespace App\Mailbox;

use Illuminate\Http\UploadedFile;

final class OutgoingMessage
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     * @param  list<UploadedFile>  $attachments
     * @param  list<AttachmentInfo>  $forwarded  attachments carried over from a forwarded message
     */
    public function __construct(
        public readonly string $fromName,
        public readonly string $fromEmail,
        public readonly array $to,
        public readonly array $cc,
        public readonly array $bcc,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $attachments = [],
        public readonly ?string $inReplyTo = null,
        public readonly ?string $references = null,
        public readonly array $forwarded = [],
    ) {}

    public function recipientCount(): int
    {
        return count(array_unique(array_map('strtolower', [...$this->to, ...$this->cc, ...$this->bcc])));
    }
}
