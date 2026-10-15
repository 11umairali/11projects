<?php

namespace App\Services\AI;

use App\DTOs\AIResponse;
use App\DTOs\ProviderResponse;
use App\Models\AiConfig;
use App\Models\Workspace;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\MistralProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AIManager
{
    /**
     * Personality presets mapped to system prompt fragments.
     */
    private const PERSONALITY_PRESETS = [
        'professional' => 'You are a polished, formal professional assistant. Use clear, precise language. Maintain a respectful and authoritative tone. Avoid slang and contractions.',
        'friendly' => 'You are a warm, approachable assistant. Be conversational and helpful. Use a natural, friendly tone while remaining professional.',
        'casual' => 'You are a relaxed, easygoing assistant. Use casual language, contractions, and a conversational style. Be approachable and personable.',
        'sales' => 'You are an enthusiastic sales professional. Be persuasive, highlight benefits, create urgency when appropriate, and maintain a positive, energetic tone.',
        'support' => 'You are a patient, empathetic customer support specialist. Acknowledge concerns, offer clear solutions, and ensure the customer feels heard and valued.',
        'custom' => '', // Uses custom_prompt field instead
    ];

    /**
     * Generate an AI reply based on workspace config and incoming message.
     *
     * @param Workspace $workspace       The workspace (loads its AiConfig).
     * @param string    $incomingMessage  The message to reply to.
     * @param array     $context          Additional context: conversation_history, sender_name, subject, etc.
     *
     * @return AIResponse Complete response with content, cost, tokens, and metadata.
     */
    public function generateReply(Workspace $workspace, string $incomingMessage, array $context = []): AIResponse
    {
        // FIX-017: Removed redundant canUse() check — rely solely on atomic tryReserveUsage()
        // This eliminates the race condition window between canUse() and tryReserveUsage()
        $stripeService = app(\App\Services\Billing\StripeService::class);
        $reserved = $stripeService->tryReserveUsage($workspace, 'ai_replies');
        if (!$reserved) {
            return new AIResponse(
                content: 'AI reply limit reached for your current plan. Please upgrade to continue using AI features.',
                confidence: 0,
                model: 'none',
                provider: 'none',
                tokens_in: 0,
                tokens_out: 0,
                cost: 0,
                response_time_ms: 0,
            );
        }

        $startTime = microtime(true);

        $aiConfig = $workspace->aiConfig ?? $this->getDefaultConfig();

        // Retrieve knowledge base context via RAG
        $ragService = app(RAGService::class);
        $kbChunks = $ragService->retrieveContext($workspace->id, $incomingMessage);

        // Build the full system prompt
        $systemPrompt = $this->buildSystemPrompt($aiConfig, $kbChunks, $context);

        // Build config array from workspace settings
        $providerConfig = [
            'model' => $aiConfig->model,
            'temperature' => (float) $aiConfig->temperature,
            'max_reply_length' => $aiConfig->max_reply_length,
            'top_p' => (float) $aiConfig->top_p,
            'frequency_penalty' => (float) $aiConfig->frequency_penalty,
            'presence_penalty' => (float) $aiConfig->presence_penalty,
            'conversation_history' => $context['conversation_history'] ?? [],
        ];

        // Route to the correct provider
        $provider = $aiConfig->provider;
        $providerInstance = $this->resolveProvider($provider, $aiConfig);

        // FIX-074: Retry with exponential backoff on network/timeout errors only
        $maxRetries = 2;
        $providerResponse = null;
        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $providerResponse = $providerInstance->chat($systemPrompt, $incomingMessage, $providerConfig);
                break;
            } catch (\RuntimeException $e) {
                $lastException = $e;
                $code = $e->getCode();
                $message = strtolower($e->getMessage());

                // Only retry on network/timeout/server errors — not on validation/auth/rate-limit errors
                $isRetryable = $code >= 500
                    || $code === 0 // connection errors (no HTTP status)
                    || str_contains($message, 'timeout')
                    || str_contains($message, 'connection')
                    || str_contains($message, 'temporarily unavailable');

                // Never retry auth (401), validation (400/422), or rate-limit (429) errors
                if (!$isRetryable || in_array($code, [400, 401, 403, 422, 429], true)) {
                    Log::error('AI generation failed (non-retryable)', [
                        'provider' => $provider,
                        'model' => $aiConfig->model,
                        'workspace_id' => $workspace->id,
                        'error' => $e->getMessage(),
                        'code' => $code,
                    ]);
                    throw $e;
                }

                if ($attempt < $maxRetries) {
                    $backoffSeconds = $attempt === 0 ? 1 : 3;
                    Log::warning('AI generation failed, retrying', [
                        'provider' => $provider,
                        'model' => $aiConfig->model,
                        'workspace_id' => $workspace->id,
                        'attempt' => $attempt + 1,
                        'max_retries' => $maxRetries,
                        'backoff_seconds' => $backoffSeconds,
                        'error' => $e->getMessage(),
                    ]);
                    sleep($backoffSeconds);
                }
            }
        }

        if ($providerResponse === null) {
            Log::error('AI generation failed after all retries', [
                'provider' => $provider,
                'model' => $aiConfig->model,
                'workspace_id' => $workspace->id,
                'error' => $lastException?->getMessage(),
            ]);
            throw $lastException;
        }

        $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);

        // Calculate cost
        $cost = $this->calculateCost($providerInstance, $provider, $providerResponse);

        // Calculate confidence (heuristic based on response quality signals)
        $confidence = $this->estimateConfidence($providerResponse, $kbChunks);

        // Collect source references
        $sourcesUsed = array_map(
            fn(array $chunk) => $chunk['document_title'] ?? 'Unknown',
            $kbChunks
        );

        // Log usage (usage already incremented at the start of the method)
        $this->logUsage($workspace->id, $provider, $providerResponse->model, 'reply', $providerResponse, $cost, $elapsedMs);

        return new AIResponse(
            content: $providerResponse->content,
            confidence: $confidence,
            model: $providerResponse->model,
            provider: $provider,
            tokens_in: $providerResponse->tokens_in,
            tokens_out: $providerResponse->tokens_out,
            cost: $cost,
            response_time_ms: $elapsedMs,
            sources_used: array_unique($sourcesUsed),
        );
    }

    /**
     * Generate an AI-composed email from a prompt and tone.
     *
     * @param Workspace $workspace  The workspace (loads its AiConfig).
     * @param string    $prompt     What the user wants to write about.
     * @param string    $tone       One of: professional, friendly, casual, persuasive.
     * @param array     $context    Optional context: subject, recipient_name, etc.
     *
     * @return AIResponse Complete response with generated email content.
     */
    public function generateCompose(Workspace $workspace, string $prompt, string $tone = 'professional', array $context = []): AIResponse
    {
        $startTime = microtime(true);

        $aiConfig = $workspace->aiConfig ?? $this->getDefaultConfig();

        $toneDescriptions = [
            'professional' => 'Write in a formal, polished, and business-appropriate tone. Use clear and precise language.',
            'friendly' => 'Write in a warm, approachable, and conversational tone. Be helpful and personable.',
            'casual' => 'Write in a relaxed, casual tone. Use contractions and conversational language.',
            'persuasive' => 'Write in a compelling, persuasive tone. Highlight benefits, create interest, and include a clear call to action.',
        ];

        $toneInstruction = $toneDescriptions[$tone] ?? $toneDescriptions['professional'];

        $systemPrompt = implode("\n\n", array_filter([
            'You are an expert email writer. Your task is to compose a complete, well-structured email based on the user\'s instructions.',
            $toneInstruction,
            !empty($context['recipient_name']) ? "The recipient's name is: {$context['recipient_name']}" : null,
            !empty($context['subject']) ? "The email subject is: {$context['subject']}" : null,
            !empty($context['sender_name']) ? "Sign the email as: {$context['sender_name']}" : null,
            'Current date: ' . now()->format('l, F j, Y') . '.',
            $aiConfig->use_html_formatting
                ? 'Format the email using HTML tags (paragraphs, lists, bold) for email rendering.'
                : 'Write the email in plain text only, no HTML.',
            'Generate only the email body. Do not include subject line, email headers, or meta-commentary.',
        ]));

        $providerConfig = [
            'model' => $aiConfig->model,
            'temperature' => 0.7, // Slightly creative for compose
            'max_reply_length' => 'long',
            'top_p' => 0.9,
            'frequency_penalty' => 0.3,
            'presence_penalty' => 0.1,
        ];

        $provider = $aiConfig->provider;
        $providerInstance = $this->resolveProvider($provider, $aiConfig);

        try {
            $providerResponse = $providerInstance->chat($systemPrompt, "Write an email about: {$prompt}", $providerConfig);
        } catch (\RuntimeException $e) {
            Log::error('AI compose failed', [
                'provider' => $provider,
                'model' => $aiConfig->model,
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
        $cost = $this->calculateCost($providerInstance, $provider, $providerResponse);

        $this->logUsage($workspace->id, $provider, $providerResponse->model, 'compose', $providerResponse, $cost, $elapsedMs);

        return new AIResponse(
            content: $providerResponse->content,
            confidence: 90,
            model: $providerResponse->model,
            provider: $provider,
            tokens_in: $providerResponse->tokens_in,
            tokens_out: $providerResponse->tokens_out,
            cost: $cost,
            response_time_ms: $elapsedMs,
        );
    }

    /**
     * Get all available models organized by provider for UI display.
     *
     * @return array<string, array<string, array{name: string, description: string, tier: string}>>
     */
    public static function getAvailableModels(): array
    {
        return [
            'openai' => OpenAIProvider::AVAILABLE_MODELS,
            'anthropic' => AnthropicProvider::AVAILABLE_MODELS,
            'gemini' => GeminiProvider::AVAILABLE_MODELS,
            'mistral' => MistralProvider::AVAILABLE_MODELS,
        ];
    }

    /**
     * Analyze the sentiment of a text string.
     *
     * Uses the platform's default OpenAI key (not workspace-specific).
     *
     * @return string The raw AI response (expected JSON with sentiment, confidence, key_phrases).
     */
    public function analyzeSentiment(string $text): string
    {
        $systemPrompt = <<<'PROMPT'
You are a sentiment analysis engine. Analyze the following message and return ONLY valid JSON with no additional text.

Response format:
{"sentiment": "positive|neutral|negative|angry", "confidence": 0-100, "key_phrases": ["phrase1", "phrase2"]}

Rules:
- "positive": grateful, happy, satisfied, complimentary
- "neutral": factual, informational, no strong emotion
- "negative": dissatisfied, frustrated, disappointed, complaining
- "angry": hostile, threatening, using aggressive language, demanding
- Confidence should reflect how certain you are of the classification
- key_phrases: extract 1-3 phrases that indicate the sentiment
PROMPT;

        $provider = $this->resolveDefaultProvider();

        try {
            $response = $provider->chat($systemPrompt, $text, [
                'model' => $this->getDefaultSentimentModel(),
                'temperature' => 0.1,
                'max_reply_length' => 'short',
                'top_p' => 0.9,
                'frequency_penalty' => 0.0,
                'presence_penalty' => 0.0,
            ]);

            return $response->content;
        } catch (\RuntimeException $e) {
            Log::error('Sentiment analysis API call failed', [
                'error' => $e->getMessage(),
                'text_length' => mb_strlen($text),
            ]);

            // Return a safe default JSON
            return '{"sentiment": "neutral", "confidence": 0, "key_phrases": []}';
        }
    }

    /**
     * Generate an embedding vector for the given text using OpenAI Embeddings API.
     *
     * @return float[] The embedding vector.
     */
    public function generateEmbedding(string $text): array
    {
        $apiKey = config('services.openai.api_key');
        if (!$apiKey) {
            throw new \RuntimeException('OpenAI API key not configured. Set OPENAI_API_KEY in environment.');
        }

        $provider = new OpenAIProvider($apiKey);

        return $provider->embed($text);
    }

    /**
     * Build the complete system prompt from workspace AI config and KB context.
     */
    private function buildSystemPrompt(AiConfig $aiConfig, array $kbChunks, array $context): string
    {
        $parts = [];

        // 1. Base personality preset text
        $preset = $aiConfig->personality_preset ?? 'friendly';
        $personalityPrompt = self::PERSONALITY_PRESETS[$preset] ?? self::PERSONALITY_PRESETS['friendly'];

        if ($preset === 'custom' && !empty($aiConfig->custom_prompt)) {
            $personalityPrompt = $aiConfig->custom_prompt;
        }

        $parts[] = $personalityPrompt;

        // 2. Custom prompt (in addition to preset, if not 'custom' mode)
        if ($preset !== 'custom' && !empty($aiConfig->custom_prompt)) {
            $parts[] = "Custom instructions from workspace configuration:\n{$aiConfig->custom_prompt}";
        }

        // 3. Additional instructions
        if (!empty($aiConfig->additional_instructions)) {
            $parts[] = "Additional instructions:\n{$aiConfig->additional_instructions}";
        }

        // 4. Current date/time context
        $parts[] = 'Current date and time: ' . now()->format('l, F j, Y \a\t g:i A T') . '.';

        // 5. Language instruction / reply language preference
        $language = $aiConfig->reply_language ?? 'auto';
        if ($language === 'auto') {
            $parts[] = 'Reply language preference: Auto-detect the language of the incoming message and reply in the same language.';
        } else {
            $languageNames = [
                'en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German',
                'pt' => 'Portuguese', 'it' => 'Italian', 'nl' => 'Dutch', 'ja' => 'Japanese',
                'zh' => 'Chinese', 'ko' => 'Korean', 'ar' => 'Arabic', 'hi' => 'Hindi',
                'ru' => 'Russian', 'tr' => 'Turkish',
            ];
            $langName = $languageNames[$language] ?? $language;
            $parts[] = "Reply language preference: Always reply in {$langName}.";
        }

        // 6. Formatting instructions
        $formatParts = [];
        if ($aiConfig->include_greeting === 'always') {
            $formatParts[] = 'Always include a greeting at the start of the reply.';
        } elseif ($aiConfig->include_greeting === 'never') {
            $formatParts[] = 'Do not include a greeting.';
        }

        if ($aiConfig->include_signoff === 'always' && !empty($aiConfig->signoff_text)) {
            $formatParts[] = "End the reply with the sign-off: \"{$aiConfig->signoff_text}\"";
            if ($aiConfig->include_sender_name) {
                $senderName = $context['agent_name'] ?? '';
                if ($senderName) {
                    $formatParts[] = "Include the sender name \"{$senderName}\" after the sign-off.";
                }
            }
        }

        if ($aiConfig->use_bullet_points) {
            $formatParts[] = 'Use bullet points where appropriate to structure the reply.';
        }

        if ($aiConfig->use_html_formatting) {
            $formatParts[] = 'Format the reply using HTML tags (paragraphs, lists, bold) for email rendering.';
        } else {
            $formatParts[] = 'Reply in plain text only, no HTML.';
        }

        if (!empty($formatParts)) {
            $parts[] = "Formatting rules:\n- " . implode("\n- ", $formatParts);
        }

        // 7. Context: sender info
        if (!empty($context['sender_name'])) {
            $parts[] = "The sender's name is: {$context['sender_name']}";
        }
        if (!empty($context['subject'])) {
            $parts[] = "The email subject is: {$context['subject']}";
        }

        // 8. Contact history — last 5 messages in conversation for context continuity
        if (!empty($context['conversation_history']) && is_array($context['conversation_history'])) {
            $recentHistory = array_slice($context['conversation_history'], -5);
            $historyText = "Recent conversation history (last " . count($recentHistory) . " messages):\n";
            foreach ($recentHistory as $msg) {
                $role = ($msg['role'] ?? 'user') === 'user' ? 'Customer' : 'Agent';
                $content = \Illuminate\Support\Str::limit($msg['content'] ?? '', 500);
                $historyText .= "  [{$role}]: {$content}\n";
            }
            $parts[] = $historyText;
        }

        // 9. Knowledge base context (RAG)
        if (!empty($kbChunks)) {
            $kbContext = "Use the following knowledge base information to answer the query. If the information is not relevant, ignore it and reply based on your general knowledge.\n\n";
            foreach ($kbChunks as $i => $chunk) {
                $idx = $i + 1;
                $source = $chunk['document_title'] ?? 'KB';
                $kbContext .= "--- Source {$idx}: {$source} ---\n{$chunk['content']}\n\n";
            }
            $parts[] = $kbContext;
        }

        // 10. Reply constraints
        $parts[] = 'Important: Generate only the reply content. Do not include the subject line. Do not repeat the incoming message.';

        return implode("\n\n", array_filter($parts));
    }

    /**
     * Resolve the correct provider instance based on provider name and config.
     */
    private function resolveProvider(string $provider, AiConfig $aiConfig): OpenAIProvider|AnthropicProvider|GeminiProvider|MistralProvider
    {
        // Plan gate: even if the workspace stored use_own_key=true, the plan
        // must include the `ai_own_key` feature for it to take effect.
        // Otherwise we silently fall back to the platform-paid keys. This
        // prevents users from downgrading to a cheaper plan and continuing
        // to use their old custom-key setting.
        $useOwnKey = (bool) $aiConfig->use_own_key && !empty($aiConfig->api_key);
        if ($useOwnKey) {
            try {
                $workspace = \App\Models\Workspace::find($aiConfig->workspace_id);
                if ($workspace) {
                    $useOwnKey = app(\App\Services\PlanLimitService::class)
                        ->hasFeature($workspace, 'ai_own_key');
                }
            } catch (\Throwable $e) {
                // If the plan check explodes, default to platform keys (safer)
                $useOwnKey = false;
            }
        }

        $apiKey = $useOwnKey ? $aiConfig->api_key : null;

        return match ($provider) {
            'openai' => new OpenAIProvider(
                $apiKey ?? config('services.openai.api_key') ?? throw new \RuntimeException('OpenAI API key not configured.'),
                config('services.openai.organization'),
            ),
            'anthropic' => new AnthropicProvider(
                $apiKey ?? config('services.anthropic.api_key') ?? throw new \RuntimeException('Anthropic API key not configured.'),
            ),
            'gemini' => new GeminiProvider(
                $apiKey ?? config('services.gemini.api_key') ?? throw new \RuntimeException('Gemini API key not configured.'),
            ),
            'mistral' => new MistralProvider(
                $apiKey ?? config('services.mistral.api_key') ?? throw new \RuntimeException('Mistral API key not configured.'),
            ),
            default => throw new \RuntimeException("Unsupported AI provider: {$provider}"),
        };
    }

    /**
     * Resolve the default platform-level provider for internal operations (sentiment, etc.).
     */
    private function resolveDefaultProvider(): OpenAIProvider|AnthropicProvider|GeminiProvider|MistralProvider
    {
        // Prefer OpenAI for internal tasks as it supports the widest range of operations
        $openaiKey = config('services.openai.api_key');
        if ($openaiKey) {
            return new OpenAIProvider($openaiKey, config('services.openai.organization'));
        }

        $anthropicKey = config('services.anthropic.api_key');
        if ($anthropicKey) {
            return new AnthropicProvider($anthropicKey);
        }

        $geminiKey = config('services.gemini.api_key');
        if ($geminiKey) {
            return new GeminiProvider($geminiKey);
        }

        $mistralKey = config('services.mistral.api_key');
        if ($mistralKey) {
            return new MistralProvider($mistralKey);
        }

        throw new \RuntimeException('No AI provider API key configured. Set at least OPENAI_API_KEY in environment.');
    }

    /**
     * Get the default model for sentiment analysis (cheap and fast).
     */
    private function getDefaultSentimentModel(): string
    {
        $provider = $this->resolveDefaultProvider();

        return match (true) {
            $provider instanceof OpenAIProvider => 'gpt-4o-mini',
            $provider instanceof AnthropicProvider => 'claude-3-5-haiku-latest',
            $provider instanceof GeminiProvider => 'gemini-2.0-flash',
            $provider instanceof MistralProvider => 'mistral-small-latest',
        };
    }

    /**
     * Calculate cost using the provider's pricing logic.
     */
    private function calculateCost(
        OpenAIProvider|AnthropicProvider|GeminiProvider|MistralProvider $providerInstance,
        string $provider,
        ProviderResponse $response,
    ): float {
        return $providerInstance->calculateCost(
            $response->model,
            $response->tokens_in,
            $response->tokens_out,
        );
    }

    /**
     * Estimate confidence score (0-100) based on response quality signals.
     */
    private function estimateConfidence(ProviderResponse $response, array $kbChunks): int
    {
        $confidence = 50; // Base confidence

        // Boost if KB context was available and likely used
        if (!empty($kbChunks)) {
            $confidence += 20;

            // Further boost for high-scoring chunks
            $topScore = max(array_column($kbChunks, 'score') ?: [0]);
            if ($topScore > 0.85) {
                $confidence += 10;
            } elseif ($topScore > 0.7) {
                $confidence += 5;
            }
        }

        // Boost if response is substantial (not too short, not truncated)
        $contentLength = mb_strlen($response->content);
        if ($contentLength > 50 && $contentLength < 3000) {
            $confidence += 10;
        } elseif ($contentLength <= 50) {
            $confidence -= 10; // Very short responses are suspicious
        }

        // Reduce if response was truncated
        if ($response->finish_reason === 'length') {
            $confidence -= 15;
        }

        return max(0, min(100, $confidence));
    }

    /**
     * Log AI usage to the ai_usage_logs table for cost tracking.
     */
    private function logUsage(
        int $workspaceId,
        string $provider,
        string $model,
        string $type,
        ProviderResponse $response,
        float $cost,
        int $responseTimeMs,
    ): void {
        try {
            DB::table('ai_usage_logs')->insert([
                'workspace_id' => $workspaceId,
                'provider' => $provider,
                'model' => $model,
                'type' => $type,
                'tokens_in' => $response->tokens_in,
                'tokens_out' => $response->tokens_out,
                'cost' => $cost,
                'response_time_ms' => $responseTimeMs,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Never let logging failures break the main flow
            Log::warning('Failed to log AI usage', [
                'error' => $e->getMessage(),
                'workspace_id' => $workspaceId,
            ]);
        }
    }

    /**
     * Return a default AiConfig when workspace has none configured.
     *
     * Priority when no workspace AiConfig row exists:
     *  1. Admin-saved defaults (SystemSetting::ai_default_provider / _model)
     *     so the admin panel choice is actually honored at runtime.
     *  2. Whatever provider has an API key configured, in this order:
     *     mistral → anthropic → openai → gemini.
     *  3. Final fallback: gemini-2.0-flash (historical default).
     */
    private function getDefaultConfig(): AiConfig
    {
        $config = new AiConfig();

        $adminProvider = \App\Models\SystemSetting::get('ai_default_provider', '') ?: null;
        $adminModel = \App\Models\SystemSetting::get('ai_default_model', '') ?: null;

        if ($adminProvider) {
            $config->provider = $adminProvider;
            $config->model = $adminModel ?: $this->pickDefaultModelFor($adminProvider);
        } elseif (config('services.mistral.api_key')) {
            $config->provider = 'mistral';
            $config->model = 'mistral-small-latest';
        } elseif (config('services.anthropic.api_key')) {
            $config->provider = 'anthropic';
            $config->model = 'claude-sonnet-4-6-20250514';
        } elseif (config('services.openai.api_key')) {
            $config->provider = 'openai';
            $config->model = 'gpt-4o';
        } else {
            $config->provider = 'gemini';
            $config->model = 'gemini-2.0-flash';
        }
        $config->temperature = 0.30;
        $config->max_reply_length = 'medium';
        $config->top_p = 0.90;
        $config->frequency_penalty = 0.30;
        $config->presence_penalty = 0.10;
        $config->personality_preset = 'friendly';
        $config->reply_language = 'auto';
        $config->include_greeting = 'ai_decides';
        $config->include_signoff = 'always';
        $config->signoff_text = 'Best regards,';
        $config->include_sender_name = true;
        $config->use_html_formatting = true;
        $config->use_bullet_points = true;
        $config->confidence_threshold = 75;
        $config->send_mode = 'approval';

        return $config;
    }

    /**
     * Pick a sensible default model name for a given provider when the admin
     * set a provider but not an explicit model. Falls back to the first
     * widely-available model for each known vendor.
     */
    private function pickDefaultModelFor(string $provider): string
    {
        return match ($provider) {
            'openai' => 'gpt-4o',
            'anthropic' => 'claude-sonnet-4-6-20250514',
            'mistral' => 'mistral-small-latest',
            'gemini' => 'gemini-2.0-flash',
            default => 'gpt-4o',
        };
    }
}
