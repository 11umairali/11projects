<?php

namespace App\Services\AI\Providers;

use App\DTOs\ProviderResponse;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;

class GeminiProvider
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1';

    /**
     * Model pricing per 1M tokens: [input, output].
     */
    private const MODEL_PRICING = [
        // Gemini 2.0 family — latest generation
        'gemini-2.0-flash'        => ['input' => 0.10,   'output' => 0.40],
        'gemini-2.0-flash-lite'   => ['input' => 0.025,  'output' => 0.10],

        // Gemini 1.5 family
        'gemini-1.5-pro'          => ['input' => 1.25,   'output' => 5.00],
        'gemini-1.5-pro-latest'   => ['input' => 1.25,   'output' => 5.00],
        'gemini-1.5-flash'        => ['input' => 0.075,  'output' => 0.30],
        'gemini-1.5-flash-latest' => ['input' => 0.075,  'output' => 0.30],
        'gemini-1.5-flash-8b'     => ['input' => 0.0375, 'output' => 0.15],

        // Legacy
        'gemini-1.0-pro'          => ['input' => 0.50,   'output' => 1.50],
    ];

    /**
     * Available models with human-readable descriptions for UI display.
     */
    public const AVAILABLE_MODELS = [
        'gemini-2.0-flash'    => ['name' => 'Gemini 2.0 Flash',   'description' => 'Latest — fast multimodal model',             'tier' => 'recommended'],
        'gemini-1.5-pro'      => ['name' => 'Gemini 1.5 Pro',     'description' => 'Best quality — 1M token context window',     'tier' => 'premium'],
        'gemini-1.5-flash'    => ['name' => 'Gemini 1.5 Flash',   'description' => 'Fast & efficient — great for most tasks',    'tier' => 'value'],
        'gemini-1.5-flash-8b' => ['name' => 'Gemini 1.5 Flash 8B','description' => 'Ultra-fast — lowest cost, simple tasks',     'tier' => 'economy'],
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
     * Send a generateContent request to Google Gemini.
     *
     * @param string $systemPrompt  The system-level instructions.
     * @param string $userMessage   The user's message to respond to.
     * @param array  $config        Model parameters: model, temperature, max_tokens, etc.
     *
     * @throws \RuntimeException On API errors or timeouts.
     */
    public function chat(string $systemPrompt, string $userMessage, array $config = []): ProviderResponse
    {
        $model = $config['model'] ?? 'gemini-1.5-pro';
        $temperature = (float) ($config['temperature'] ?? 0.3);
        $maxTokens = $this->resolveMaxTokens($config['max_reply_length'] ?? 'medium');
        $topP = (float) ($config['top_p'] ?? 0.9);

        $contents = [
            [
                'role' => 'user',
                'parts' => [['text' => $userMessage]],
            ],
        ];

        // Prepend conversation history if provided
        if (!empty($config['conversation_history']) && is_array($config['conversation_history'])) {
            $history = [];
            foreach ($config['conversation_history'] as $msg) {
                $role = ($msg['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
                $history[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg['content'] ?? '']],
                ];
            }
            $contents = [...$history, ...$contents];
        }

        // Gemini's `system_instruction` lives on the v1beta API surface.
        // The /v1/ endpoint rejects BOTH the snake_case and camelCase form
        // because the field is genuinely not part of the v1 schema:
        //   "Unknown name 'system_instruction': Cannot find field."
        //   "Unknown name 'systemInstruction': Cannot find field."
        // We use /v1beta/ + snake_case which is the documented path:
        //   https://ai.google.dev/api/generate-content
        $body = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'topP' => $topP,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        $endpoint = "/v1beta/models/{$model}:generateContent";

        try {
            $response = $this->httpClient->post($endpoint, [
                'query' => ['key' => $this->apiKey],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Extract text from candidates
            $content = '';
            if (!empty($data['candidates'][0]['content']['parts'])) {
                foreach ($data['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        $content .= $part['text'];
                    }
                }
            }

            $finishReason = $data['candidates'][0]['finishReason'] ?? 'STOP';

            // Extract token counts from usageMetadata
            $tokensIn = $data['usageMetadata']['promptTokenCount'] ?? 0;
            $tokensOut = $data['usageMetadata']['candidatesTokenCount'] ?? 0;

            // Check for safety blocks
            if (empty($content) && ($finishReason === 'SAFETY' || $finishReason === 'RECITATION')) {
                Log::warning('Gemini response blocked by safety filter', [
                    'finish_reason' => $finishReason,
                    'model' => $model,
                    'safety_ratings' => $data['candidates'][0]['safetyRatings'] ?? [],
                ]);

                throw new \RuntimeException(
                    "Gemini blocked the response due to safety filters ({$finishReason})."
                );
            }

            // FIX-079: Detect and flag truncated responses
            $truncated = $finishReason === 'MAX_TOKENS';
            if ($truncated) {
                $content = trim($content) . "\n\n[Response truncated]";
                Log::info('Gemini response truncated due to MAX_TOKENS', [
                    'model' => $model,
                    'tokens_out' => $tokensOut,
                ]);
            }

            return new ProviderResponse(
                content: trim($content),
                tokens_in: $tokensIn,
                tokens_out: $tokensOut,
                model: $model,
                finish_reason: strtolower($finishReason),
                truncated: $truncated,
            );
        } catch (ClientException $e) {
            $responseBody = $e->getResponse()?->getBody()?->getContents() ?? '';
            $errorData = json_decode($responseBody, true);
            $errorMessage = $errorData['error']['message'] ?? $e->getMessage();
            $errorStatus = $errorData['error']['status'] ?? 'UNKNOWN';

            Log::error('Gemini API client error', [
                'status' => $errorStatus,
                'message' => $errorMessage,
                'http_code' => $e->getResponse()?->getStatusCode(),
                'model' => $model,
            ]);

            if ($e->getResponse()?->getStatusCode() === 429) {
                throw new \RuntimeException(
                    "Gemini rate limit exceeded. Please retry after a short delay.",
                    429,
                    $e
                );
            }

            throw new \RuntimeException(
                "Gemini API error ({$errorStatus}): {$errorMessage}",
                $e->getResponse()?->getStatusCode() ?? 0,
                $e
            );
        } catch (ServerException $e) {
            Log::error('Gemini API server error', [
                'message' => $e->getMessage(),
                'status' => $e->getResponse()?->getStatusCode(),
                'model' => $model,
            ]);

            throw new \RuntimeException(
                "Gemini server error: The API is temporarily unavailable.",
                $e->getResponse()?->getStatusCode() ?? 500,
                $e
            );
        } catch (ConnectException $e) {
            Log::error('Gemini connection error', [
                'message' => $e->getMessage(),
                'model' => $model,
            ]);

            throw new \RuntimeException(
                "Gemini connection error: {$e->getMessage()}",
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
        $pricing = self::MODEL_PRICING[$model] ?? self::MODEL_PRICING['gemini-1.5-pro'];

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
