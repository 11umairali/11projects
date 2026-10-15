<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes specifically for inbox sidebar counts and conversation listing.
     *
     * These cover the hot queries from InboxSidebar (channel/folder counts)
     * and ConversationList (filtered + sorted listing).
     */
    public function up(): void
    {
        try {
            // Sidebar channel count query: WHERE workspace_id AND is_read=false AND status NOT IN (...)
            Schema::table('conversations', function (Blueprint $table) {
                $table->index(
                    ['workspace_id', 'is_read', 'status'],
                    'conversations_ws_read_status_idx'
                );
            });

            // Sidebar starred filter + conversation list starred folder
            Schema::table('conversations', function (Blueprint $table) {
                $table->index(
                    ['workspace_id', 'is_starred'],
                    'conversations_ws_starred_idx'
                );
            });

            // ConversationList assigned_to filter
            Schema::table('conversations', function (Blueprint $table) {
                $table->index(
                    ['workspace_id', 'assigned_to', 'status'],
                    'conversations_ws_assigned_status_idx'
                );
            });

            // Messages direction filter (used by 'sent' folder count: whereHas messages.direction=outbound)
            Schema::table('messages', function (Blueprint $table) {
                $table->index(
                    ['conversation_id', 'direction'],
                    'messages_conv_direction_idx'
                );
            });

            // Fulltext on messages.body_text for inbox search (skip if already exists)
            if (config('database.default') === 'mysql') {
                $hasFulltext = collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM messages WHERE Key_name = 'messages_body_text_fulltext'"))->isNotEmpty();
                if (!$hasFulltext) {
                    Schema::table('messages', function (Blueprint $table) {
                        $table->fullText('body_text', 'messages_body_text_fulltext');
                    });
                }
            }
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_ws_read_status_idx');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_ws_starred_idx');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_ws_assigned_status_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conv_direction_idx');
        });

        if (config('database.default') === 'mysql') {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropFullText('messages_body_text_fulltext');
            });
        }
    }
};
