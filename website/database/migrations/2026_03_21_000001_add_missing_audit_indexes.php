<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing indexes identified during product audit

        if (Schema::hasTable('workspaces') && !$this->hasIndex('workspaces', 'workspaces_slug_index')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->index('slug');
            });
        }

        if (Schema::hasTable('invites') && !$this->hasIndex('invites', 'invites_token_index')) {
            Schema::table('invites', function (Blueprint $table) {
                $table->index('token');
            });
        }

        if (Schema::hasTable('workflows') && !$this->hasIndex('workflows', 'workflows_webhook_token_index')) {
            Schema::table('workflows', function (Blueprint $table) {
                $table->index('webhook_token');
            });
        }

        if (Schema::hasTable('users') && !$this->hasIndex('users', 'users_referral_code_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('referral_code');
            });
        }

        if (Schema::hasTable('campaigns') && !$this->hasIndex('campaigns', 'campaigns_type_index')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->index('type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropIndex(['slug']);
        });
        Schema::table('invites', function (Blueprint $table) {
            $table->dropIndex(['token']);
        });
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropIndex(['webhook_token']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['referral_code']);
        });
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['type']);
        });
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
