<?php

namespace App\Services\AI\Providers;

use App\DTOs\ProviderResponse;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;

class MistralProvider
{
    private const API_BASE = 'https://api.mistral.ai/v1';

    /**
     * Model pricing per 1M tokens: [input, output].
     */
    private const MODEL_PRICING = [
        // Mistral flagship models
        'mistral-large-latest'    => ['input' => 2.00, 'output' => 6.00],
        'mistral-large-2411'      => ['input' => 2.00, 'output' => 6.00],
        'mistral-medium-latest'   => ['input' => 2.70, 'output' => 8.10],
        'mistral-small-latest'    => ['input' => 0.20, 'output' => 0.60],
        'mistral-small-2501'      => ['input' => 0.20, 'output' => 0.60],

        // Open models
        'open-mistral-nemo'       => ['input' => 0.15, 'output' => 0.15],
        'open-mixtral-8x22b'      => ['input' => 2.00, 'output' => 6.00],
        'open-mixtral-8x7b'       => ['input' => 0.70, 'output' => 0.70],

        // Specialized models
        'codestral-latest'        => ['input' => 0.30, 'output' => 0.90],
        'pixtral-large-latest'    => ['input' => 2.00, 'output' => 6.00],
    ];

    /**
     * Available models with human-readable descriptions for UI display.
     */
    public const AVAILABLE_MODELS = [
        'mistral-large-latest'    => ['name' => 'Mistral Large',    'description' => 'Best quality — complex reasoning & generation', 'tier' => 'premium'],
        'mistral-medium-latest'   => ['name' => 'Mistral Medium',   'description' => 'Balanced — good quality at moderate cost',      'tier' => 'standard'],
        'mistral-small-latest'    => ['name' => 'Mistral Small',    'description' => 'Fast & affordable — everyday tasks',            'tier' => 'value'],
        'open-mistral-nemo'       => ['name' => 'Mistral Nemo',     'description' => 'Open model — cheapest option',                  'tier' => 'economy'],
        'codestral-latest'        => ['name' => 'Codestral',        'description' => 'Code-specialized — programming tasks',          'tier' => 'specialized'],
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
     * Send a chat completion request to Mistral AI (OpenAI-compatible format).
     *
     * @param string $systemPrompt  The system-level instructions.
     * @param string $userMessage   The user's message to respond to.
     * @param array  $config        Model parameters: model, temperature, max_tokens, etc.
     *
     * @throws \RuntimeException On API errors or timeouts.
     */
    public function chat(string $systemPrompt, string $userMessage, array $config = []): ProviderResponse
    {
        $model = $config['model'] ?? 'mistral-large-latest';
        $temperature = (float) ($config['temperature'] ?? 0.3);
        $maxTokens = $this->resolveMaxTokens($config['max_reply_length'] ?? 'medium');
        $topP = (float) ($config['top_p'] ?? 0.9);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ];

        // Insert conversation history between system and last user message
        if (!empty($config['conversation_history']) && is_array($config['conversation_history'])) {
            $systemMsg = array_shift($messages);
            $lastUserMsg = array_pop($messages);
            $messages = [$systemMsg, ...$config['conversation_history'], $lastUserMsg];
        }

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'top_p' => $topP,
        ];

        try {
            $response = $this->httpClient->post('/v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            $content = $data['choices'][0]['message']['content'] ?? '';
            $finishReason = $data['choices'][0]['finish_reason'] ?? 'stop';
            $tokensIn = $data['usage']['prompt_tokens'] ?? 0;
            $tokensOut = $data['usage']['completion_tokens'] ?? 0;

            // FIX-079: Detect and flag truncated responses
            $truncated = $finishReason === 'length';
            if ($truncated) {
                $content = trim($content) . "\n\n[Response truncated]";
                Log::info('Mistral response truncated due to max_tokens', [
                    'model' => $model,
                    'tokens_out' => $tokensOut,
                ]);
            }

            return new ProviderResponse(
                content: trim($content),
                tokens_in: $tokensIn,
                tokens_out: $tokensOut,
                model: $data['model'] ?? $model,
                finish_reason: $finishReason,
                truncated: $truncated,
            );
        } catch (ClientException $e) {
            $responseBody = $e->getResponse()?->getBody()?->getContents() ?? '';
            $errorData = json_decode($responseBody, true);
            $errorMessage = $errorData['message'] ?? $errorData['detail'] ?? $e->getMessage();

            Log::error('Mistral API client error', [
                'message' => $errorMessage,
                'http_code' => $e->getResponse()?->getStatusCode(),
                'model' => $model,
            ]);

            if ($e->getResponse()?->getStatusCode() === 429) {
                throw new \RuntimeException(
                    "Mistral rate limit exceeded. Please retry after a short delay.",
                    429,
                    $e
                );
            }

            if ($e->getResponse()?->getStatusCode() === 401) {
                throw new \RuntimeException(
                    "Mistral authentication failed. Check your API key.",
                    401,
                    $e
                );
            }

            throw new \RuntimeException(
                "Mistral API error: {$errorMessage}",
                $e->getResponse()?->getStatusCode() ?? 0,
                $e
            );
        } catch (ServerException $e) {
            Log::error('Mistral API server error', [
                'message' => $e->getMessage(),
                'status' => $e->getResponse()?->getStatusCode(),
                'model' => $model,
            ]);

            throw new \RuntimeException(
                "Mistral server error: The API is temporarily unavailable.",
                $e->getResponse()?->getStatusCode() ?? 500,
                $e
            );
        } catch (ConnectException $e) {
            Log::error('Mistral connection error', [
                'message' => $e->getMessage(),
                'model' => $model,
            ]);

            throw new \RuntimeException(
                "Mistral connection error: {$e->getMessage()}",
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
        $pricing = self::MODEL_PRICING[$model] ?? self::MODEL_PRICING['mistral-large-latest'];

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
