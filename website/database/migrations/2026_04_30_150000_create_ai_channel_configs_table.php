<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-channel AI configuration override table.
 *
 * Architecture:
 *   ai_configs           = "Provider/billing layer" — provider, model, API key,
 *                          monthly cost cap, default temperature.
 *   ai_channel_configs   = "Behavior layer" — one row per workspace+channel.
 *                          Holds prompt, send_mode, confidence threshold,
 *                          escalation, reliability filters, etc.
 *
 *   AiConfigResolver::for($workspace, $channel) reads channel row first,
 *   falls back to ai_configs where missing, then to hard-coded defaults.
 *
 * Channels: email, whatsapp, sms, live_chat, telegram.
 *
 * skip_filters is a JSON document — its shape is owned by AIReplyFilters
 * (skip_noreply, skip_autoreply, skip_bounces, skip_promotional,
 *  custom_blocklist[], etc.)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_channel_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['email', 'whatsapp', 'sms', 'live_chat', 'telegram']);

            // Master enable for this channel
            $table->boolean('enabled')->default(false);

            // Behavior knobs (mirror AiConfig but per-channel overrides)
            $table->string('personality_preset', 32)->default('friendly');
            $table->text('custom_prompt')->nullable();
            $table->text('additional_instructions')->nullable();
            $table->enum('send_mode', ['autonomous', 'approval', 'suggestions'])->default('approval');
            $table->unsignedTinyInteger('confidence_threshold')->default(75);
            $table->enum('max_reply_length', ['short', 'medium', 'long', 'very_long'])->default('medium');
            $table->decimal('temperature', 3, 2)->nullable(); // null = inherit from ai_configs

            // Per-channel reliability filters (JSON document)
            // Default shape: see AIReplyFilters::decide()
            $table->json('skip_filters')->nullable();

            // Conversation-level limits
            $table->boolean('first_message_only')->default(false);
            $table->unsignedTinyInteger('max_replies_per_conversation')->default(3);
            $table->boolean('skip_own_threads')->default(true);

            // Reply timing
            $table->enum('reply_delay', ['none', '30s', '1m', '2m', '5m', 'random'])->default('none');

            // Auto-escalation (mirrors what we built in Phase 2 but per-channel)
            $table->boolean('escalation_enabled')->default(false);
            $table->unsignedTinyInteger('escalate_below_confidence')->default(50);
            $table->unsignedBigInteger('escalation_assignee_id')->nullable();
            $table->string('escalation_tag', 64)->default('needs_human');

            $table->timestamps();

            $table->unique(['workspace_id', 'channel'], 'ai_channel_workspace_unique');
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_channel_configs');
    }
};
