<?php

namespace App\Mailbox;

use Carbon\CarbonInterface;

/**
 * One row in a message list.
 */
final class MessageSummary
{
    /**
     * @param  list<array{name: string, email: string}>  $to
     */
    public function __construct(
        public readonly int $uid,
        public readonly string $subject,
        public readonly string $fromName,
        public readonly string $fromEmail,
        public readonly array $to,
        public readonly ?CarbonInterface $date,
        public readonly bool $seen,
        public readonly bool $flagged,
        public readonly bool $answered,
        public readonly bool $hasAttachments,
        public readonly int $size,
        public readonly string $preview = '',
    ) {}

    public function withPreview(string $preview): self
    {
        return new self($this->uid, $this->subject, $this->fromName, $this->fromEmail, $this->to, $this->date,
            $this->seen, $this->flagged, $this->answered, $this->hasAttachments, $this->size, $preview);
    }

    public function fromLabel(): string
    {
        return $this->fromName !== '' ? $this->fromName : ($this->fromEmail !== '' ? $this->fromEmail : 'Unknown sender');
    }

    public function toLabel(): string
    {
        if ($this->to === []) {
            return '(no recipient)';
        }

        $first = $this->to[0]['name'] !== '' ? $this->to[0]['name'] : $this->to[0]['email'];

        return count($this->to) > 1 ? $first.' +'.(count($this->to) - 1) : $first;
    }
}
