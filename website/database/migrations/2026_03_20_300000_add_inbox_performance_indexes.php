<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            // Unread counts by channel — used by InboxSidebar::getChannelCounts()
            Schema::table('conversations', function (Blueprint $table) {
                $table->index(
                    ['workspace_id', 'is_read', 'status', 'channel'],
                    'idx_conv_unread_counts'
                );
            });

            Schema::table('messages', function (Blueprint $table) {
                // Conversation message listing — used by ConversationDetail
                $table->index(
                    ['conversation_id', 'created_at'],
                    'idx_messages_conv_listing'
                );

                // Latest message lookup — used by poll check (MAX(id) per conversation)
                $table->index(
                    ['conversation_id', 'id'],
                    'idx_messages_conv_latest'
                );
            });
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('idx_conv_unread_counts');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_conv_listing');
            $table->dropIndex('idx_messages_conv_latest');
        });
    }
};
