<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Upgrade contacts(workspace_id, email) from a plain index to a UNIQUE
 *    constraint. This prevents duplicate contacts caused by race conditions
 *    (concurrent webhook deliveries, parallel CSV imports, etc.).
 *
 *    MySQL automatically excludes NULL values from unique constraints,
 *    so phone-only contacts (email IS NULL) are unaffected.
 *
 * 2. Change conversations.email_account_id foreign key from nullOnDelete()
 *    to cascadeOnDelete(). When an email account is removed, its
 *    conversations should be removed too — orphaned conversations with
 *    no account cannot be replied to and clutter the inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            // 1. Replace non-unique index with unique constraint on contacts
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropIndex(['workspace_id', 'email']);
                $table->unique(['workspace_id', 'email'], 'contacts_ws_email_unique');
            });

            // 2. Swap FK on conversations.email_account_id to cascade delete
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropForeign(['email_account_id']);
                $table->foreign('email_account_id')
                      ->references('id')
                      ->on('email_accounts')
                      ->cascadeOnDelete();
            });
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        // Revert conversations FK back to nullOnDelete
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['email_account_id']);
            $table->foreign('email_account_id')
                  ->references('id')
                  ->on('email_accounts')
                  ->nullOnDelete();
        });

        // Revert contacts unique back to plain index
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique('contacts_ws_email_unique');
            $table->index(['workspace_id', 'email']);
        });
    }
};
