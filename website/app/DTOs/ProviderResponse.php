<?php

namespace App\DTOs;

class ProviderResponse
{
    public function __construct(
        public readonly string $content,
        public readonly int $tokens_in,
        public readonly int $tokens_out,
        public readonly string $model,
        public readonly string $finish_reason = 'stop',
        public readonly bool $truncated = false, // FIX-079: flag when response was truncated due to max_tokens
    ) {}

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'tokens_in' => $this->tokens_in,
            'tokens_out' => $this->tokens_out,
            'model' => $this->model,
            'finish_reason' => $this->finish_reason,
            'truncated' => $this->truncated,
        ];
    }
}
