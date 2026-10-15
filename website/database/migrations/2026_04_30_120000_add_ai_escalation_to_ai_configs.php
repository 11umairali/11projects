<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Adds AI auto-escalation settings to ai_configs:
     *   - escalation_enabled: master switch
     *   - escalate_below_confidence: 0-100 threshold
     *   - escalation_assignee_id: specific fallback agent (NULL = round-robin)
     *   - escalation_tag: tag name applied to conversation when escalating
     *
     * When confidence falls below the threshold and escalation is enabled,
     * GenerateAIReplyJob assigns the conversation, tags it, and notifies the
     * assignee — instead of sending the reply autonomously.
     */
    public function up(): void
    {
        Schema::table('ai_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_configs', 'escalation_enabled')) {
                $table->boolean('escalation_enabled')->default(false)->after('confidence_threshold');
            }
            if (!Schema::hasColumn('ai_configs', 'escalate_below_confidence')) {
                $table->unsignedTinyInteger('escalate_below_confidence')->default(50)->after('escalation_enabled');
            }
            if (!Schema::hasColumn('ai_configs', 'escalation_assignee_id')) {
                $table->unsignedBigInteger('escalation_assignee_id')->nullable()->after('escalate_below_confidence');
            }
            if (!Schema::hasColumn('ai_configs', 'escalation_tag')) {
                $table->string('escalation_tag', 64)->default('needs_human')->after('escalation_assignee_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_configs', function (Blueprint $table) {
            $cols = ['escalation_enabled', 'escalate_below_confidence', 'escalation_assignee_id', 'escalation_tag'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('ai_configs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
