<?php

namespace App\Mailbox;

/**
 * A full message as read from the mailbox. The HTML body is untrusted and
 * must go through HtmlBody before it is shown.
 */
final class MessageDetail
{
    /**
     * @param  list<array{name: string, email: string}>  $to
     * @param  list<array{name: string, email: string}>  $cc
     * @param  list<array{name: string, email: string}>  $replyTo
     * @param  list<AttachmentInfo>  $attachments
     * @param  array{to?: string, cc?: string, bcc?: string, context?: string}  $draftFields  what was typed, saved with a draft
     */
    public function __construct(
        public readonly MessageSummary $summary,
        public readonly array $to,
        public readonly array $cc,
        public readonly array $replyTo,
        public readonly string $messageId,
        public readonly string $references,
        public readonly ?string $html,
        public readonly string $text,
        public readonly array $attachments,
        public readonly string $inReplyTo = '',
        public readonly string $draftId = '',
        public readonly array $draftFields = [],
    ) {}

    /**
     * @return list<AttachmentInfo>
     */
    public function downloadableAttachments(): array
    {
        return array_values(array_filter($this->attachments, fn (AttachmentInfo $a) => ! $a->isInlineImage()));
    }
}
