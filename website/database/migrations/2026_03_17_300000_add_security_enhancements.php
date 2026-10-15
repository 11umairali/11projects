<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security and performance enhancements:
 * 1. Index on email_accounts.email for faster lookup during sync and webhook routing
 * 2. Fulltext index on conversations.subject for inbox search
 *
 * Note: invites.accepted_at already exists in base migration.
 * Note: workflows.webhook_token already has a unique index from base migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add index on email_accounts.email for faster lookup
        // Used during email sync, webhook processing, and admin search
        if (Schema::hasColumn('email_accounts', 'email') && !$this->hasIndex('email_accounts', 'email_accounts_email_index')) {
            Schema::table('email_accounts', function (Blueprint $table) {
                $table->index('email', 'email_accounts_email_index');
            });
        }

        // Add fulltext index on conversations.subject for search
        // (body_text fulltext already exists on messages table)
        if (config('database.default') !== 'sqlite'
            && Schema::hasColumn('conversations', 'subject')
            && !$this->hasIndex('conversations', 'conversations_subject_fulltext')) {
            try {
                Schema::table('conversations', function (Blueprint $table) {
                    $table->fullText('subject', 'conversations_subject_fulltext');
                });
            } catch (\Exception $e) {
                // Index may already exist from a previous partial run
            }
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('email_accounts', 'email_accounts_email_index')) {
            Schema::table('email_accounts', function (Blueprint $table) {
                $table->dropIndex('email_accounts_email_index');
            });
        }

        if ($this->hasIndex('conversations', 'conversations_subject_fulltext')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropFullText('conversations_subject_fulltext');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);

        return collect($indexes)->contains(fn ($i) => $i['name'] === $indexName);
    }
};
