<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('ai_configs', function (Blueprint $table) {
                $table->decimal('monthly_cost_limit', 8, 2)->default(10.00)->after('auto_reply_enabled');
            });
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('ai_configs', function (Blueprint $table) {
            $table->dropColumn('monthly_cost_limit');
        });
    }
};
