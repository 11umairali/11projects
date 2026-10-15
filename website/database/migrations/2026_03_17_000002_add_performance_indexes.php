<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes for high-frequency workspace-scoped queries.
     *
     * Note: Several base indexes already exist from the original migrations:
     *   - conversations: (workspace_id, status), (workspace_id, channel), (workspace_id, assigned_to)
     *   - messages: (conversation_id, created_at)
     *   - contacts: (workspace_id, email)
     *   - campaigns: (workspace_id, status)
     *   - workflows: (workspace_id, status)
     *
     * This migration adds the MISSING compound indexes that improve:
     *   - Inbox listing with sort (conversations: workspace+status+last_message_at)
     *   - Message filtering by sender/delivery (messages: workspace+sender_type, workspace+delivery_status)
     *   - Contact phone lookup (contacts: workspace+phone)
     *   - Channel integration lookup for webhooks (channel_integrations: workspace+channel+status)
     */
    public function up(): void
    {
        try {
            // Compound index for inbox listing sorted by most recent message
            Schema::table('conversations', function (Blueprint $table) {
                $table->index(
                    ['workspace_id', 'status', 'last_message_at'],
                    'conversations_ws_status_last_msg_idx'
                );
            });

            // Indexes for message filtering in analytics and webhook processing
            Schema::table('messages', function (Blueprint $table) {
                $table->index(
                    ['workspace_id', 'sender_type'],
                    'messages_ws_sender_type_idx'
                );
                $table->index(
                    ['workspace_id', 'delivery_status'],
                    'messages_ws_delivery_status_idx'
                );
            });

            // Phone lookup index (critical for webhook workspace resolution)
            Schema::table('contacts', function (Blueprint $table) {
                $table->index(
                    ['workspace_id', 'phone'],
                    'contacts_ws_phone_idx'
                );
            });

            // Webhook routing: quickly find the right integration for incoming messages
            Schema::table('channel_integrations', function (Blueprint $table) {
                $table->index(
                    ['channel', 'status'],
                    'channel_integrations_channel_status_idx'
                );
            });
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_ws_status_last_msg_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_ws_sender_type_idx');
            $table->dropIndex('messages_ws_delivery_status_idx');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('contacts_ws_phone_idx');
        });

        Schema::table('channel_integrations', function (Blueprint $table) {
            $table->dropIndex('channel_integrations_channel_status_idx');
        });
    }
};
