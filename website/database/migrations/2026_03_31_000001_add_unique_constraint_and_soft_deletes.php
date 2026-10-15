<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MN-008: Add deleted_at (soft deletes) to critical tables.
 * MN-007: Add unique constraint on invites(workspace_id, email, status).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Soft deletes for critical tables
        $tables = ['email_accounts', 'drip_sequences', 'email_templates', 'custom_fields'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }

        // Unique constraint on invites to prevent duplicate pending invites
        if (Schema::hasTable('invites') && Schema::hasColumn('invites', 'status')) {
            Schema::table('invites', function (Blueprint $table) {
                $table->unique(['workspace_id', 'email', 'status'], 'invites_workspace_email_status_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invites')) {
            Schema::table('invites', function (Blueprint $table) {
                $table->dropUnique('invites_workspace_email_status_unique');
            });
        }

        $tables = ['email_accounts', 'drip_sequences', 'email_templates', 'custom_fields'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
