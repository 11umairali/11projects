<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SentimentAnalyzer
{
    private const CACHE_TTL = 86400; // 24 hours
    private const CACHE_PREFIX = 'sentiment:';

    public function __construct(
        private readonly AIManager $aiManager,
    ) {}

    /**
     * Analyze the sentiment of the given text.
     *
     * Returns cached results if available.
     *
     * @param string $text         The text to analyze.
     * @param int    $workspaceId  The workspace ID (for provider config).
     *
     * @return array{sentiment: string, confidence: int, key_phrases: string[]}
     */
    public function analyze(string $text, int $workspaceId): array
    {
        // FIX-078: Include workspace_id in cache key to prevent cross-workspace cache hits
        $cacheKey = self::CACHE_PREFIX . $workspaceId . ':' . md5($text);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->performAnalysis($text, $workspaceId);

            Cache::put($cacheKey, $result, self::CACHE_TTL);

            return $result;
        } catch (\Exception $e) {
            Log::error('Sentiment analysis failed', [
                'message' => $e->getMessage(),
                'workspace_id' => $workspaceId,
                'text_length' => mb_strlen($text),
            ]);

            // Return neutral as safe default
            return [
                'sentiment' => 'neutral',
                'confidence' => 0,
                'key_phrases' => [],
            ];
        }
    }

    /**
     * Perform the actual sentiment analysis via the configured AI provider.
     */
    private function performAnalysis(string $text, int $workspaceId): array
    {
        // Truncate to avoid excessive token usage on long messages
        $truncated = mb_substr($text, 0, 2000);

        $sentiment = $this->aiManager->analyzeSentiment($truncated);

        // Parse the structured response from the AI
        return $this->parseResponse($sentiment, $truncated);
    }

    /**
     * Parse the AI provider's sentiment response into a structured result.
     *
     * The AI is prompted to return JSON, but we handle both JSON and freeform text.
     */
    private function parseResponse(string $rawResponse, string $originalText): array
    {
        $response = trim($rawResponse);

        // FIX-077: Try json_decode on the full response first (handles clean JSON responses)
        $parsed = json_decode($response, true);
        if (is_array($parsed) && isset($parsed['sentiment'])) {
            $sentiment = $this->normalizeSentiment($parsed['sentiment']);
            return [
                'sentiment' => $sentiment,
                'confidence' => $this->clampConfidence((int) ($parsed['confidence'] ?? 70)),
                'key_phrases' => $this->extractPhrases($parsed['key_phrases'] ?? []),
            ];
        }

        // Fallback: use regex to extract JSON from mixed content (e.g. markdown-wrapped JSON)
        if (preg_match('/\{[\s\S]*\}/s', $response, $jsonMatch)) {
            $parsed = json_decode($jsonMatch[0], true);
            if (is_array($parsed) && json_last_error() === JSON_ERROR_NONE && isset($parsed['sentiment'])) {
                $sentiment = $this->normalizeSentiment($parsed['sentiment']);
                return [
                    'sentiment' => $sentiment,
                    'confidence' => $this->clampConfidence((int) ($parsed['confidence'] ?? 70)),
                    'key_phrases' => $this->extractPhrases($parsed['key_phrases'] ?? []),
                ];
            }
        }

        // Last resort: extract sentiment from freeform text
        $lowerResponse = strtolower($response);
        $sentiment = $this->detectSentimentFromText($lowerResponse);

        return [
            'sentiment' => $sentiment,
            'confidence' => 60, // Lower confidence for freeform parsing
            'key_phrases' => $this->extractPhrasesFromText($originalText),
        ];
    }

    /**
     * Normalize sentiment to one of the four allowed values.
     */
    private function normalizeSentiment(string $sentiment): string
    {
        $sentiment = strtolower(trim($sentiment));

        return match (true) {
            in_array($sentiment, ['positive', 'happy', 'satisfied', 'grateful', 'enthusiastic']) => 'positive',
            in_array($sentiment, ['negative', 'unhappy', 'dissatisfied', 'frustrated', 'disappointed']) => 'negative',
            in_array($sentiment, ['angry', 'furious', 'irate', 'hostile', 'aggressive']) => 'angry',
            default => 'neutral',
        };
    }

    /**
     * Detect sentiment from freeform AI response text.
     */
    private function detectSentimentFromText(string $text): string
    {
        if (str_contains($text, 'angry') || str_contains($text, 'furious') || str_contains($text, 'irate')) {
            return 'angry';
        }
        if (str_contains($text, 'negative') || str_contains($text, 'unhappy') || str_contains($text, 'frustrated')) {
            return 'negative';
        }
        if (str_contains($text, 'positive') || str_contains($text, 'happy') || str_contains($text, 'satisfied')) {
            return 'positive';
        }

        return 'neutral';
    }

    /**
     * Ensure key_phrases is an array of strings.
     */
    private function extractPhrases(mixed $phrases): array
    {
        if (!is_array($phrases)) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', $phrases),
            fn(string $phrase) => mb_strlen($phrase) > 0
        ));
    }

    /**
     * Extract simple key phrases from the original text as fallback.
     */
    private function extractPhrasesFromText(string $text): array
    {
        // Very basic: split into sentences, return first 3 short ones
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $phrases = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (mb_strlen($sentence) >= 5 && mb_strlen($sentence) <= 100) {
                $phrases[] = $sentence;
            }
            if (count($phrases) >= 3) {
                break;
            }
        }

        return $phrases;
    }

    /**
     * Clamp confidence between 0 and 100.
     */
    private function clampConfidence(int $confidence): int
    {
        return max(0, min(100, $confidence));
    }
}
