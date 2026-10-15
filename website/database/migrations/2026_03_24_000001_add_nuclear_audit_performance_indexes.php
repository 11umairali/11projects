<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes identified during Nuclear Audit v2.0 (March 2026).
 * These compound indexes optimize the most common query patterns at scale.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Messages — AI analytics queries (workspace + ai_status + time range)
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'ai_status')) {
            if (!$this->hasIndex('messages', 'messages_ws_ai_status_created_index')) {
                Schema::table('messages', function (Blueprint $table) {
                    $table->index(['workspace_id', 'ai_status', 'created_at'], 'messages_ws_ai_status_created_index');
                });
            }
        }

        // Conversations — channel breakdown analytics (workspace + channel + time range)
        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'channel')) {
            if (!$this->hasIndex('conversations', 'conversations_ws_channel_created_index')) {
                Schema::table('conversations', function (Blueprint $table) {
                    $table->index(['workspace_id', 'channel', 'created_at'], 'conversations_ws_channel_created_index');
                });
            }
        }

        // Deal stages — pipeline rendering order
        if (Schema::hasTable('deal_stages') && Schema::hasColumn('deal_stages', 'sort_order')) {
            if (!$this->hasIndex('deal_stages', 'deal_stages_pipeline_sort_index')) {
                Schema::table('deal_stages', function (Blueprint $table) {
                    $table->index(['pipeline_id', 'sort_order'], 'deal_stages_pipeline_sort_index');
                });
            }
        }

        // Conversations — email account sync queries
        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'email_account_id')) {
            if (!$this->hasIndex('conversations', 'conversations_email_account_created_index')) {
                Schema::table('conversations', function (Blueprint $table) {
                    $table->index(['email_account_id', 'created_at'], 'conversations_email_account_created_index');
                });
            }
        }

        // Conversations — starred inbox filter with sort
        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'is_starred')) {
            if (!$this->hasIndex('conversations', 'conversations_ws_starred_lastmsg_index')) {
                Schema::table('conversations', function (Blueprint $table) {
                    $table->index(['workspace_id', 'is_starred', 'last_message_at'], 'conversations_ws_starred_lastmsg_index');
                });
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'messages' => ['messages_ws_ai_status_created_index'],
            'conversations' => [
                'conversations_ws_channel_created_index',
                'conversations_email_account_created_index',
                'conversations_ws_starred_lastmsg_index',
            ],
            'deal_stages' => ['deal_stages_pipeline_sort_index'],
        ];

        foreach ($indexes as $table => $indexNames) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($indexNames) {
                    foreach ($indexNames as $indexName) {
                        try {
                            $table->dropIndex($indexName);
                        } catch (\Throwable) {
                            // Index may not exist
                        }
                    }
                });
            }
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $index) {
                if ($index['name'] === $indexName) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Fall through
        }
        return false;
    }
};
