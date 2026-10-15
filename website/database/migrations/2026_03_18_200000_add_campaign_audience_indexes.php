<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX-073: Add indexes for campaign audience resolution queries.
 * These queries run when building recipient lists for 10K+ contact workspaces.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            // Index for suppression lookups during campaign send
            if (!$this->hasIndex('email_suppressions', 'email_suppressions_email_index')) {
                Schema::table('email_suppressions', function (Blueprint $table) {
                    $table->index('email', 'email_suppressions_email_index');
                });
            }

            // Index for contact status filtering during audience resolution
            if (!$this->hasIndex('contacts', 'contacts_status_unsubscribed_index')) {
                Schema::table('contacts', function (Blueprint $table) {
                    $table->index(['workspace_id', 'status', 'unsubscribed_at'], 'contacts_status_unsubscribed_index');
                });
            }
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('email_suppressions', function (Blueprint $table) {
            $table->dropIndex('email_suppressions_email_index');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('contacts_status_unsubscribed_index');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }
        return false;
    }
};
