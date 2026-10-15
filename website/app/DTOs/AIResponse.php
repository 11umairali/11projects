<?php

namespace App\DTOs;

class AIResponse
{
    public function __construct(
        public readonly string $content,
        public readonly int $confidence,
        public readonly string $model,
        public readonly string $provider,
        public readonly int $tokens_in,
        public readonly int $tokens_out,
        public readonly float $cost,
        public readonly int $response_time_ms,
        public readonly array $sources_used = [],
        public readonly ?string $sentiment = null,
    ) {}

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'confidence' => $this->confidence,
            'model' => $this->model,
            'provider' => $this->provider,
            'tokens_in' => $this->tokens_in,
            'tokens_out' => $this->tokens_out,
            'cost' => $this->cost,
            'response_time_ms' => $this->response_time_ms,
            'sources_used' => $this->sources_used,
            'sentiment' => $this->sentiment,
        ];
    }

    /**
     * Check if AI confidence meets the given threshold.
     */
    public function meetsThreshold(int $threshold): bool
    {
        return $this->confidence >= $threshold;
    }
}
