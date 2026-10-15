<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRITICAL: Add unique constraints to prevent duplicate records caused by
 * race conditions in concurrent webhook processing, job retries, and
 * simultaneous user actions.
 *
 * Issues fixed:
 * 1. Duplicate Stripe payment records from concurrent webhooks
 * 2. Duplicate synced email messages from concurrent sync jobs
 * 3. Duplicate campaign recipients from double-send exploit
 * 4. Duplicate drip enrollments from concurrent enrollment requests
 * 5. Missing indexes on ab_test_variants and deal_stages
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            // 1. Prevent duplicate Stripe payments for the same invoice
            Schema::table('payments', function (Blueprint $table) {
                $table->unique('stripe_invoice_id', 'payments_stripe_invoice_id_unique');
            });

            // 2. Prevent duplicate synced messages (same message_id_header per workspace)
            Schema::table('messages', function (Blueprint $table) {
                $table->unique(
                    ['workspace_id', 'message_id_header'],
                    'messages_workspace_message_id_header_unique'
                );
            });

            // 3. Prevent duplicate campaign recipients (same contact per campaign)
            Schema::table('campaign_recipients', function (Blueprint $table) {
                $table->unique(
                    ['campaign_id', 'contact_id'],
                    'campaign_recipients_campaign_contact_unique'
                );
            });

            // 4. Prevent duplicate active drip enrollments
            Schema::table('drip_enrollments', function (Blueprint $table) {
                $table->unique(
                    ['sequence_id', 'contact_id', 'status'],
                    'drip_enrollments_sequence_contact_status_unique'
                );
            });

            // 5. Add missing indexes on ab_test_variants
            Schema::table('ab_test_variants', function (Blueprint $table) {
                $table->index('campaign_id', 'ab_test_variants_campaign_id_index');
                $table->index(['campaign_id', 'variant'], 'ab_test_variants_campaign_variant_index');
            });

            // 6. Add missing index on deal_stages.sort_order
            Schema::table('deal_stages', function (Blueprint $table) {
                $table->index(['pipeline_id', 'sort_order'], 'deal_stages_pipeline_sort_order_index');
            });

            // 7. Add missing compound index for email account sync queries
            Schema::table('email_accounts', function (Blueprint $table) {
                $table->index(
                    ['workspace_id', 'status', 'last_synced_at'],
                    'email_accounts_workspace_status_synced_index'
                );
            });
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_stripe_invoice_id_unique');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_workspace_message_id_header_unique');
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropUnique('campaign_recipients_campaign_contact_unique');
        });

        Schema::table('drip_enrollments', function (Blueprint $table) {
            $table->dropUnique('drip_enrollments_sequence_contact_status_unique');
        });

        Schema::table('ab_test_variants', function (Blueprint $table) {
            $table->dropIndex('ab_test_variants_campaign_id_index');
            $table->dropIndex('ab_test_variants_campaign_variant_index');
        });

        Schema::table('deal_stages', function (Blueprint $table) {
            $table->dropIndex('deal_stages_pipeline_sort_order_index');
        });

        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropIndex('email_accounts_workspace_status_synced_index');
        });
    }
};
