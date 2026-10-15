<?php

namespace App\Services\AI;

use App\Models\AiChannelConfig;
use App\Models\AiConfig;
use App\Models\Workspace;

/**
 * Read-only DTO returned by AiConfigResolver::for(). Exposes flat
 * accessors that hide the channel-vs-global fallback waterfall from
 * the calling code.
 *
 * Every accessor follows the same pattern:
 *   1. Look at the channel row first
 *   2. Fall back to the global ai_configs row
 *   3. Final fallback to a hard-coded sensible default
 */
class ResolvedAiConfig
{
    public function __construct(
        public readonly Workspace $workspace,
        public readonly string $channel,
        public readonly ?AiConfig $globalConfig,
        public readonly ?AiChannelConfig $channelConfig,
    ) {}

    public function enabled(): bool
    {
        if ($this->channelConfig) {
            return (bool) $this->channelConfig->enabled;
        }
        return (bool) ($this->globalConfig?->auto_reply_enabled ?? false);
    }

    public function personalityPreset(): string
    {
        return $this->channelConfig?->personality_preset
            ?? $this->globalConfig?->personality_preset
            ?? 'friendly';
    }

    public function customPrompt(): ?string
    {
        return $this->channelConfig?->custom_prompt
            ?? $this->globalConfig?->custom_prompt
            ?? null;
    }

    public function additionalInstructions(): ?string
    {
        return $this->channelConfig?->additional_instructions
            ?? $this->globalConfig?->additional_instructions
            ?? null;
    }

    public function sendMode(): string
    {
        return $this->channelConfig?->send_mode
            ?? $this->globalConfig?->send_mode
            ?? 'approval';
    }

    public function confidenceThreshold(): int
    {
        return (int) ($this->channelConfig?->confidence_threshold
            ?? $this->globalConfig?->confidence_threshold
            ?? 75);
    }

    public function maxReplyLength(): string
    {
        return $this->channelConfig?->max_reply_length
            ?? $this->globalConfig?->max_reply_length
            ?? 'medium';
    }

    public function temperature(): float
    {
        return (float) ($this->channelConfig?->temperature
            ?? $this->globalConfig?->temperature
            ?? 0.3);
    }

    public function firstMessageOnly(): bool
    {
        return (bool) ($this->channelConfig?->first_message_only
            ?? $this->globalConfig?->first_message_only
            ?? false);
    }

    public function maxRepliesPerConversation(): int
    {
        return (int) ($this->channelConfig?->max_replies_per_conversation
            ?? $this->globalConfig?->max_ai_replies_per_conversation
            ?? 3);
    }

    public function skipOwnThreads(): bool
    {
        return (bool) ($this->channelConfig?->skip_own_threads
            ?? $this->globalConfig?->skip_own_threads
            ?? true);
    }

    public function replyDelay(): string
    {
        return $this->channelConfig?->reply_delay
            ?? $this->globalConfig?->reply_delay
            ?? 'none';
    }

    public function escalationEnabled(): bool
    {
        return (bool) ($this->channelConfig?->escalation_enabled
            ?? $this->globalConfig?->escalation_enabled
            ?? false);
    }

    public function escalateBelowConfidence(): int
    {
        return (int) ($this->channelConfig?->escalate_below_confidence
            ?? $this->globalConfig?->escalate_below_confidence
            ?? 50);
    }

    public function escalationAssigneeId(): ?int
    {
        $val = $this->channelConfig?->escalation_assignee_id
            ?? $this->globalConfig?->escalation_assignee_id;
        return $val ? (int) $val : null;
    }

    public function escalationTag(): string
    {
        return $this->channelConfig?->escalation_tag
            ?? $this->globalConfig?->escalation_tag
            ?? 'needs_human';
    }

    /**
     * Per-channel skip filter set, merged with sensible defaults so older
     * rows (created before a new filter key was added) still behave correctly.
     *
     * @return array<string,mixed>
     */
    public function skipFilters(): array
    {
        $stored = $this->channelConfig?->skip_filters ?? [];
        $defaults = AiChannelConfig::defaultSkipFilters($this->channel);
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * Monthly cost limit comes from the GLOBAL config — billing isn't
     * something we want to fragment across channels.
     */
    public function monthlyCostLimit(): float
    {
        return (float) ($this->globalConfig?->monthly_cost_limit ?? 0);
    }
}
