<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-channel AI configuration override.
 *
 * One row per (workspace_id, channel). Acts as a behavior overlay on top
 * of the workspace's global AiConfig (provider/model/API key live there).
 *
 * Lookup is always via AiConfigResolver::for($workspace, $channel) which
 * handles the fallback waterfall:
 *   row in ai_channel_configs  →  ai_configs  →  hard-coded defaults
 */
class AiChannelConfig extends Model
{
    public const CHANNELS = ['email', 'whatsapp', 'sms', 'live_chat', 'telegram'];

    protected $fillable = [
        'workspace_id',
        'channel',
        'enabled',
        'personality_preset',
        'custom_prompt',
        'additional_instructions',
        'send_mode',
        'confidence_threshold',
        'max_reply_length',
        'temperature',
        'skip_filters',
        'first_message_only',
        'max_replies_per_conversation',
        'skip_own_threads',
        'reply_delay',
        'escalation_enabled',
        'escalate_below_confidence',
        'escalation_assignee_id',
        'escalation_tag',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'temperature' => 'decimal:2',
            'skip_filters' => 'array',
            'first_message_only' => 'boolean',
            'max_replies_per_conversation' => 'integer',
            'skip_own_threads' => 'boolean',
            'escalation_enabled' => 'boolean',
            'confidence_threshold' => 'integer',
            'escalate_below_confidence' => 'integer',
            'escalation_assignee_id' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Default skip_filters payload by channel. Used when seeding a brand-new
     * channel row. Email is strict by default; chat-like channels are
     * permissive. Users can change everything in the settings UI.
     */
    public static function defaultSkipFilters(string $channel): array
    {
        return match ($channel) {
            'email' => [
                'skip_noreply' => true,
                'skip_autoreply' => true,
                'skip_bounces' => true,
                'skip_promotional' => false,
                'skip_outbound' => true,
                'require_valid_from' => true,
                'custom_blocklist' => [],
            ],
            default => [
                'skip_noreply' => false,
                'skip_autoreply' => false,
                'skip_bounces' => false,
                'skip_promotional' => false,
                'skip_outbound' => true,
                'require_valid_from' => false,
                'custom_blocklist' => [],
            ],
        };
    }
}
