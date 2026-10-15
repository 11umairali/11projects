<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nuclear Audit Fixes — March 2026
 *
 * Addresses findings from the comprehensive code audit:
 * 1. Missing workspace_id columns for tenant isolation
 * 2. Missing unique constraints to prevent data duplication
 * 3. Missing indexes for performance
 * 4. Missing workspace_id index on audit_logs for scoped queries
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add workspace_id to attachments for tenant isolation
        if (Schema::hasTable('attachments') && !Schema::hasColumn('attachments', 'workspace_id')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->foreignId('workspace_id')->nullable()->after('message_id')->constrained()->cascadeOnDelete();
                $table->index('workspace_id', 'attachments_workspace_id_index');
            });

            // Backfill workspace_id from related message
            DB::statement('
                UPDATE attachments a
                JOIN messages m ON a.message_id = m.id
                SET a.workspace_id = m.workspace_id
                WHERE a.workspace_id IS NULL
            ');
        }

        // 2. Add workspace_id to conversation_viewers for tenant isolation
        if (Schema::hasTable('conversation_viewers') && !Schema::hasColumn('conversation_viewers', 'workspace_id')) {
            Schema::table('conversation_viewers', function (Blueprint $table) {
                $table->foreignId('workspace_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                $table->index('workspace_id', 'conversation_viewers_workspace_id_index');
            });

            // Backfill workspace_id from related conversation
            DB::statement('
                UPDATE conversation_viewers cv
                JOIN conversations c ON cv.conversation_id = c.id
                SET cv.workspace_id = c.workspace_id
                WHERE cv.workspace_id IS NULL
            ');
        }

        // 3. Add unique constraint on ab_test_variants (campaign_id, variant)
        // Prevents duplicate variant labels per campaign
        if (Schema::hasTable('ab_test_variants')) {
            Schema::table('ab_test_variants', function (Blueprint $table) {
                // Drop existing non-unique index if it exists, replace with unique
                try {
                    $table->dropIndex('ab_test_variants_campaign_variant_index');
                } catch (\Exception $e) {
                    // Index may not exist
                }
                $table->unique(['campaign_id', 'variant'], 'ab_test_variants_campaign_variant_unique');
            });
        }

        // 4. Add workspace_id index on audit_logs for scoped queries
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'workspace_id')) {
            try {
                Schema::table('audit_logs', function (Blueprint $table) {
                    $table->index(['workspace_id', 'auditable_type', 'auditable_id'], 'audit_logs_workspace_auditable_idx');
                });
            } catch (\Throwable $e) {
                // Index may already exist
            }
        }

        // 5. Add (workspace_id, period) index on usage_records for monthly queries
        if (Schema::hasTable('usage_records') && Schema::hasColumn('usage_records', 'period')) {
            try {
                Schema::table('usage_records', function (Blueprint $table) {
                    $table->index(['workspace_id', 'period'], 'idx_usage_ws_period');
                });
            } catch (\Throwable $e) {
                // Index may already exist
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attachments') && Schema::hasColumn('attachments', 'workspace_id')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->dropForeign(['workspace_id']);
                $table->dropColumn('workspace_id');
            });
        }

        if (Schema::hasTable('conversation_viewers') && Schema::hasColumn('conversation_viewers', 'workspace_id')) {
            Schema::table('conversation_viewers', function (Blueprint $table) {
                $table->dropForeign(['workspace_id']);
                $table->dropColumn('workspace_id');
            });
        }

        if (Schema::hasTable('ab_test_variants')) {
            Schema::table('ab_test_variants', function (Blueprint $table) {
                try {
                    $table->dropUnique('ab_test_variants_campaign_variant_unique');
                } catch (\Exception $e) {}
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                try {
                    $table->dropIndex('audit_logs_workspace_auditable_idx');
                } catch (\Exception $e) {}
            });
        }

        if (Schema::hasTable('usage_records')) {
            Schema::table('usage_records', function (Blueprint $table) {
                try {
                    $table->dropIndex('idx_usage_ws_period');
                } catch (\Exception $e) {}
            });
        }
    }
};
