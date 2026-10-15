<?php

namespace App\Mailbox;

final class AttachmentInfo
{
    public function __construct(
        public readonly int $index,
        public readonly string $name,
        public readonly string $mime,
        public readonly int $size,
        public readonly ?string $contentId,
        public readonly bool $inline,
        public readonly ?string $content = null,
    ) {}

    public function isInlineImage(): bool
    {
        return $this->inline && $this->contentId !== null && str_starts_with($this->mime, 'image/');
    }

    public function humanSize(): string
    {
        return match (true) {
            $this->size >= 1048576 => round($this->size / 1048576, 1).' MB',
            $this->size >= 1024 => round($this->size / 1024).' KB',
            default => $this->size.' B',
        };
    }
}
