<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConfig extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'provider',
        'model',
        'use_own_key',
        'api_key',
        'custom_endpoint',
        'temperature',
        'max_reply_length',
        'top_p',
        'frequency_penalty',
        'presence_penalty',
        'personality_preset',
        'custom_prompt',
        'additional_instructions',
        'reply_language',
        'multi_language_greeting',
        'include_greeting',
        'include_signoff',
        'signoff_text',
        'include_sender_name',
        'include_company_name',
        'use_html_formatting',
        'use_bullet_points',
        'auto_reply_enabled',
        'monthly_cost_limit',
        'business_hours_only',
        'outside_hours_message',
        'confidence_threshold',
        'send_mode',
        'reply_delay',
        'first_message_only',
        'max_ai_replies_per_conversation',
        'skip_own_threads',
        'auto_action_hours',
        'auto_action_type',
        // AI auto-escalation: assigns conversation + tags + notifies the assignee
        // when confidence falls below the configured threshold.
        'escalation_enabled',
        'escalate_below_confidence',
        'escalation_assignee_id',
        'escalation_tag',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'use_own_key' => 'boolean',
            'temperature' => 'decimal:2',
            'top_p' => 'decimal:2',
            'frequency_penalty' => 'decimal:2',
            'presence_penalty' => 'decimal:2',
            'multi_language_greeting' => 'boolean',
            'include_sender_name' => 'boolean',
            'include_company_name' => 'boolean',
            'use_html_formatting' => 'boolean',
            'use_bullet_points' => 'boolean',
            'auto_reply_enabled' => 'boolean',
            'monthly_cost_limit' => 'decimal:2',
            'business_hours_only' => 'boolean',
            'confidence_threshold' => 'integer',
            'first_message_only' => 'boolean',
            'max_ai_replies_per_conversation' => 'integer',
            'skip_own_threads' => 'boolean',
            'auto_action_hours' => 'integer',
            'escalation_enabled' => 'boolean',
            'escalate_below_confidence' => 'integer',
            'escalation_assignee_id' => 'integer',
        ];
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
