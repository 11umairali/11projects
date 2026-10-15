<?php

namespace App\Services\AI\Providers;

use App\DTOs\ProviderResponse;
use Illuminate\Support\Facades\Log;
use OpenAI;
use OpenAI\Client;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\TransporterException;

class OpenAIProvider
{
    /**
     * Model pricing per 1M tokens: [input, output].
     */
    private const MODEL_PRICING = [
        // GPT-4o family — flagship multimodal models
        'gpt-4o'              => ['input' => 2.50,  'output' => 10.00],
        'gpt-4o-2024-11-20'   => ['input' => 2.50,  'output' => 10.00],
        'gpt-4o-2024-08-06'   => ['input' => 2.50,  'output' => 10.00],
        'gpt-4o-mini'         => ['input' => 0.15,  'output' => 0.60],
        'gpt-4o-mini-2024-07-18' => ['input' => 0.15, 'output' => 0.60],

        // GPT-4 family — original high-quality models
        'gpt-4-turbo'         => ['input' => 10.00, 'output' => 30.00],
        'gpt-4'               => ['input' => 30.00, 'output' => 60.00],

        // o-series — advanced reasoning models
        'o1'                  => ['input' => 15.00, 'output' => 60.00],
        'o1-preview'          => ['input' => 15.00, 'output' => 60.00],
        'o1-mini'             => ['input' => 3.00,  'output' => 12.00],
        'o3-mini'             => ['input' => 1.10,  'output' => 4.40],
    ];

    /**
     * Available models with human-readable descriptions for UI display.
     */
    public const AVAILABLE_MODELS = [
        'gpt-4o'        => ['name' => 'GPT-4o',          'description' => 'Best quality — flagship multimodal model',       'tier' => 'recommended'],
        'gpt-4o-mini'   => ['name' => 'GPT-4o Mini',     'description' => 'Fast & cheap — great for most tasks',            'tier' => 'value'],
        'gpt-4-turbo'   => ['name' => 'GPT-4 Turbo',     'description' => 'High quality with vision support',               'tier' => 'premium'],
        'gpt-4'         => ['name' => 'GPT-4',           'description' => 'Original GPT-4 — proven reliability',            'tier' => 'premium'],
        'o1-preview'    => ['name' => 'o1 Preview',       'description' => 'Advanced reasoning — complex analysis',          'tier' => 'reasoning'],
        'o1-mini'       => ['name' => 'o1 Mini',          'description' => 'Fast reasoning — math & code',                   'tier' => 'reasoning'],
        'o3-mini'       => ['name' => 'o3 Mini',          'description' => 'Latest reasoning — fast & efficient',            'tier' => 'reasoning'],
    ];

    private const EMBEDDING_PRICING = [
        'text-embedding-3-small' => 0.02,  // per 1M tokens
        'text-embedding-3-large' => 0.13,
        'text-embedding-ada-002' => 0.10,
    ];

    private Client $client;

    public function __construct(string $apiKey, ?string $organization = null)
    {
        $factory = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withHttpHeader('OpenAI-Beta', 'assistants=v2');

        if ($organization) {
            $factory = $factory->withOrganization($organization);
        }

        $this->client = $factory->make();
    }

    /**
     * Send a chat completion request to OpenAI.
     *
     * @param string $systemPrompt  The system-level instructions.
     * @param string $userMessage   The user's message to respond to.
     * @param array  $config        Model parameters: model, temperature, max_tokens, top_p, etc.
     *
     * @throws \RuntimeException On API errors or timeouts.
     */
    public function chat(string $systemPrompt, string $userMessage, array $config = []): ProviderResponse
    {
        $model = $config['model'] ?? 'gpt-4o';
        $temperature = (float) ($config['temperature'] ?? 0.3);
        $maxTokens = $this->resolveMaxTokens($config['max_reply_length'] ?? 'medium');
        $topP = (float) ($config['top_p'] ?? 0.9);
        $frequencyPenalty = (float) ($config['frequency_penalty'] ?? 0.3);
        $presencePenalty = (float) ($config['presence_penalty'] ?? 0.1);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ];

        // Append conversation history if provided
        if (!empty($config['conversation_history']) && is_array($config['conversation_history'])) {
            // Insert history between system and the latest user message
            $systemMsg = array_shift($messages);
            $lastUserMsg = array_pop($messages);
            $messages = [$systemMsg, ...$config['conversation_history'], $lastUserMsg];
        }

        $params = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'top_p' => $topP,
            'frequency_penalty' => $frequencyPenalty,
            'presence_penalty' => $presencePenalty,
        ];

        try {
            $response = $this->client->chat()->create($params);

            $content = $response->choices[0]->message->content ?? '';
            $finishReason = $response->choices[0]->finishReason ?? 'stop';
            $tokensIn = $response->usage->promptTokens ?? 0;
            $tokensOut = $response->usage->completionTokens ?? 0;

            // FIX-079: Detect and flag truncated responses
            $truncated = $finishReason === 'length';
            if ($truncated) {
                $content = trim($content) . "\n\n[Response truncated]";
                Log::info('OpenAI response truncated due to max_tokens', [
                    'model' => $model,
                    'tokens_out' => $tokensOut,
                ]);
            }

            return new ProviderResponse(
                content: trim($content),
                tokens_in: $tokensIn,
                tokens_out: $tokensOut,
                model: $response->model ?? $model,
                finish_reason: $finishReason,
                truncated: $truncated,
            );
        } catch (ErrorException $e) {
            Log::error('OpenAI API error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'model' => $model,
            ]);

            throw new \RuntimeException(
                "OpenAI API error: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        } catch (TransporterException $e) {
            Log::error('OpenAI transport error (timeout/network)', [
                'message' => $e->getMessage(),
                'model' => $model,
            ]);

            throw new \RuntimeException(
                "OpenAI connection error: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Generate an embedding vector for the given text.
     *
     * @return float[] The embedding vector.
     */
    public function embed(string $text, string $model = 'text-embedding-3-small'): array
    {
        try {
            $response = $this->client->embeddings()->create([
                'model' => $model,
                'input' => $text,
            ]);

            return $response->embeddings[0]->embedding;
        } catch (ErrorException $e) {
            Log::error('OpenAI Embeddings API error', [
                'message' => $e->getMessage(),
                'model' => $model,
                'text_length' => mb_strlen($text),
            ]);

            throw new \RuntimeException(
                "OpenAI Embeddings error: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        } catch (TransporterException $e) {
            Log::error('OpenAI Embeddings transport error', [
                'message' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "OpenAI Embeddings connection error: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Calculate the cost of a request based on model and token counts.
     */
    public function calculateCost(string $model, int $tokensIn, int $tokensOut): float
    {
        $pricing = self::MODEL_PRICING[$model] ?? self::MODEL_PRICING['gpt-4o'];

        $inputCost = ($tokensIn / 1_000_000) * $pricing['input'];
        $outputCost = ($tokensOut / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Calculate the cost of an embedding request.
     */
    public function calculateEmbeddingCost(string $model, int $tokens): float
    {
        $pricePerMillion = self::EMBEDDING_PRICING[$model] ?? self::EMBEDDING_PRICING['text-embedding-3-small'];

        return round(($tokens / 1_000_000) * $pricePerMillion, 6);
    }

    /**
     * Convert the max_reply_length setting to a token count.
     */
    private function resolveMaxTokens(string $length): int
    {
        return match ($length) {
            'short' => 256,
            'medium' => 1024,
            'long' => 2048,
            'very_long' => 4096,
            default => is_numeric($length) ? (int) $length : 1024,
        };
    }
}
