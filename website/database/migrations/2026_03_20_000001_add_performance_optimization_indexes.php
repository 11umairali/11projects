<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Messages: speed up conversation polling and sync lookups
        Schema::table('messages', function (Blueprint $table) {
            // Used by pollMessages (MAX(id) WHERE conversation_id = ?)
            // and by GenerateAIReplyJob context building
            $table->index(['conversation_id', 'direction', 'created_at'], 'idx_messages_conv_direction_created');

            // Used by EmailSyncService duplicate check
            $table->index(['workspace_id', 'message_id_header'], 'idx_messages_ws_msgid_header');

            // Used by SyncEmailAccountJob to find new inbound messages
            $table->index(['workspace_id', 'direction', 'created_at'], 'idx_messages_ws_direction_created');
        });

        // Conversations: speed up inbox sorting and filtering
        Schema::table('conversations', function (Blueprint $table) {
            // Used by ConversationList getBaseQuery sorting
            $table->index(['workspace_id', 'status', 'last_message_at'], 'idx_conv_ws_status_lastmsg');

            // Used by ConversationList starred/snoozed filters
            $table->index(['workspace_id', 'is_starred'], 'idx_conv_ws_starred');

            // Used by analytics date range queries
            $table->index(['workspace_id', 'created_at'], 'idx_conv_ws_created');

            // Used by email threading subject match
            $table->index(['workspace_id', 'contact_id', 'email_account_id', 'last_message_at'], 'idx_conv_threading');
        });

        // Campaign recipients: speed up status-based queries
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->index(['campaign_id', 'status'], 'idx_camp_recip_campaign_status');
            $table->index(['campaign_id', 'variant'], 'idx_camp_recip_campaign_variant');
        });

        // Drip enrollments: speed up ProcessDripStepsCommand
        Schema::table('drip_enrollments', function (Blueprint $table) {
            $table->index(['status', 'next_step_at'], 'idx_drip_enroll_status_nextstep');
        });

        // Usage records: speed up billing lookups
        Schema::table('usage_records', function (Blueprint $table) {
            $table->index(['workspace_id', 'feature_key', 'period'], 'idx_usage_ws_feature_period');
        });

        // Subscriptions: speed up workspace subscription lookups
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['workspace_id', 'status'], 'idx_sub_ws_status');
        });

        // AI usage logs: speed up dashboard analytics
        if (Schema::hasTable('ai_usage_logs')) {
            Schema::table('ai_usage_logs', function (Blueprint $table) {
                $table->index(['workspace_id', 'created_at'], 'idx_ai_usage_ws_created');
            });
        }
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_conv_direction_created');
            $table->dropIndex('idx_messages_ws_msgid_header');
            $table->dropIndex('idx_messages_ws_direction_created');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('idx_conv_ws_status_lastmsg');
            $table->dropIndex('idx_conv_ws_starred');
            $table->dropIndex('idx_conv_ws_created');
            $table->dropIndex('idx_conv_threading');
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropIndex('idx_camp_recip_campaign_status');
            $table->dropIndex('idx_camp_recip_campaign_variant');
        });

        Schema::table('drip_enrollments', function (Blueprint $table) {
            $table->dropIndex('idx_drip_enroll_status_nextstep');
        });

        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropIndex('idx_usage_ws_feature_period');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_sub_ws_status');
        });

        if (Schema::hasTable('ai_usage_logs')) {
            Schema::table('ai_usage_logs', function (Blueprint $table) {
                $table->dropIndex('idx_ai_usage_ws_created');
            });
        }
    }
};
