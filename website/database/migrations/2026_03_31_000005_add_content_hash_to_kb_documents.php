<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('kb_documents', function (Blueprint $table) {
                $table->string('content_hash', 64)->nullable()->after('content');
                $table->index(['workspace_id', 'content_hash']);
            });
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('kb_documents', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'content_hash']);
            $table->dropColumn('content_hash');
        });
    }
};
