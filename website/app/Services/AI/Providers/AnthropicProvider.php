<?php

namespace App\Services\AI\Providers;

use App\DTOs\ProviderResponse;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;

class AnthropicProvider
{
    private const API_BASE = 'https://api.anthropic.com/v1';
    private const API_VERSION = '2023-06-01';

    /**
     * Model pricing per 1M tokens: [input, output].
     */
    private const MODEL_PRICING = [
        // Claude 4 family — latest generation
        'claude-opus-4-20250514'      => ['input' => 15.00, 'output' => 75.00],
        'claude-sonnet-4-20250514'    => ['input' => 3.00,  'output' => 15.00],
        'claude-sonnet-4'             => ['input' => 3.00,  'output' => 15.00],
        'claude-haiku-4-5-20251001'   => ['input' => 0.80,  'output' => 4.00],

        // Claude 3.5 family
        'claude-3-5-sonnet-20241022'  => ['input' => 3.00,  'output' => 15.00],
        'claude-3.5-sonnet-20241022'  => ['input' => 3.00,  'output' => 15.00],
        'claude-3-5-sonnet-latest'    => ['input' => 3.00,  'output' => 15.00],
        'claude-3-5-haiku-20241022'   => ['input' => 0.80,  'output' => 4.00],
        'claude-3-5-haiku-latest'     => ['input' => 0.80,  'output' => 4.00],

        // Claude 3 family — legacy
        'claude-3-opus-20240229'      => ['input' => 15.00, 'output' => 75.00],
        'claude-3-sonnet-20240229'    => ['input' => 3.00,  'output' => 15.00],
        'claude-3-haiku-20240307'     => ['input' => 0.25,  'output' => 1.25],
    ];

    /**
     * Available models with human-readable descriptions for UI display.
     */
    public const AVAILABLE_MODELS = [
        'claude-opus-4-20250514'      => ['name' => 'Claude Opus 4',       'description' => 'Most capable — complex tasks & analysis',    'tier' => 'premium'],
        'claude-sonnet-4-20250514'    => ['name' => 'Claude Sonnet 4',     'description' => 'Best balance of speed & intelligence',       'tier' => 'recommended'],
        'claude-haiku-4-5-20251001'   => ['name' => 'Claude Haiku 4.5',    'description' => 'Fastest — instant responses, low cost',      'tier' => 'value'],
        'claude-3.5-sonnet-20241022'  => ['name' => 'Claude 3.5 Sonnet',   'description' => 'Previous gen — proven reliability',          'tier' => 'standard'],
        'claude-3-opus-20240229'      => ['name' => 'Claude 3 Opus',       'description' => 'Legacy premium — deep reasoning',            'tier' => 'legacy'],
    ];

    private Client $httpClient;
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->httpClient = new Client([
            'base_uri' => self::API_BASE,
            'timeout' => 120,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Send a chat completion request to Anthropic's Messages API.
     *
     * @param string $systemPrompt  The system-level instructions.
     * @param string $userMessage   The user's message to respond to.
     * @param array  $config        Model parameters: model, temperature, max_tokens, etc.
     *
     * @throws \RuntimeException On API errors or timeouts.
     */
    public function chat(string $systemPrompt, string $userMessage, array $config = []): ProviderResponse
    {
        $model = $config['model'] ?? 'claude-sonnet-4-20250514';
        $temperature = (float) ($config['temperature'] ?? 0.3);
        $maxTokens = $this->resolveMaxTokens($config['max_reply_length'] ?? 'medium');
        $topP = (float) ($config['top_p'] ?? 0.9);

        $messages = [
            ['role' => 'user', 'content' => $userMessage],
        ];

        // Prepend conversation history if provided
        if (!empty($config['conversation_history']) && is_array($config['conversation_history'])) {
            // Anthropic requires alternating user/assistant messages
            $history = $config['conversation_history'];
            $messages = [...$history, ...$messages];
        }

        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => $messages,
            'temperature' => $temperature,
            'top_p' => $topP,
        ];

        try {
            $response = $this->httpClient->post('/v1/messages', [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Extract text content from the response blocks
            $content = '';
            if (!empty($data['content'])) {
                foreach ($data['content'] as $block) {
                    if (($block['type'] ?? '') === 'text') {
                        $content .= $block['text'];
                    }
                }
            }

            $tokensIn = $data['usage']['input_tokens'] ?? 0;
            $tokensOut = $data['usage']['output_tokens'] ?? 0;
            $stopReason = $data['stop_reason'] ?? 'end_turn';

            // FIX-079: Detect and flag truncated responses
            $truncated = $stopReason === 'max_tokens';
            if ($truncated) {
                $content = trim($content) . "\n\n[Response truncated]";
                Log::info('Anthropic response truncated due to max_tokens', [
                    'model' => $model,
                    'tokens_out' => $tokensOut,
                ]);
            }

            return new ProviderResponse(
                content: trim($content),
                tokens_in: $tokensIn,
                tokens_out: $tokensOut,
                model: $data['model'] ?? $model,
                finish_reason: $stopReason,
                truncated: $truncated,
            );
        } catch (ClientException $e) {
            $responseBody = $e->getResponse()?->getBody()?->getContents() ?? '';
            $errorData = json_decode($responseBody, true);
            $errorMessage = $errorData['error']['message'] ?? $e->getMessage();
            $errorType = $errorData['error']['type'] ?? 'unknown';

            Log::error('Anthropic API client error', [
                'type' => $errorType,
                'message' => $errorMessage,
                'status' => $e->getResponse()?->getStatusCode(),
                'model' => $model,
            ]);

            // Handle rate limiting with specific exception
            if ($e->getResponse()?->getStatusCode() === 429) {
                throw new \RuntimeException(
                    "Anthropic rate limit exceeded. Please retry after a short delay.",
                    429,
                    $e
                );
            }

            throw new \RuntimeException(
                "Anthropic API error ({$errorType}): {$errorMessage}",
                $e->getResponse()?->getStatusCode() ?? 0,
                $e
            );
        } catch (ServerException $e) {
            Log::error('Anthropic API server error', [
                'message' => $e->getMessage(),
                'status' => $e->getResponse()?->getStatusCode(),
                'model' => $model,
            ]);

            throw new \RuntimeException(
                "Anthropic server error: The API is temporarily unavailable.",
                $e->getResponse()?->getStatusCode() ?? 500,
                $e
            );
        } catch (ConnectException $e) {
            Log::error('Anthropic connection error', [
                'message' => $e->getMessage(),
                'model' => $model,
            ]);

            throw new \RuntimeException(
                "Anthropic connection error: {$e->getMessage()}",
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
        $pricing = self::MODEL_PRICING[$model] ?? self::MODEL_PRICING['claude-sonnet-4-20250514'];

        $inputCost = ($tokensIn / 1_000_000) * $pricing['input'];
        $outputCost = ($tokensOut / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
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
