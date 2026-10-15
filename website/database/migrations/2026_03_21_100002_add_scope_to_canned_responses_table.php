<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        try {
            Schema::table('canned_responses', function (Blueprint $table) {
                $table->enum('scope', ['personal', 'team'])->default('personal')->after('content');

                // Add unique constraint on workspace + shortcut to prevent collisions
                $table->unique(['workspace_id', 'shortcut'], 'canned_responses_ws_shortcut_unique');
            });
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('canned_responses', function (Blueprint $table) {
            $table->dropUnique('canned_responses_ws_shortcut_unique');
            $table->dropColumn('scope');
        });
    }
};
