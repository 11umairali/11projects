<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX-069: Add retry_count column to drip_enrollments for tracking
 * failed step send retries (max 2 attempts before skipping).
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('drip_enrollments', function (Blueprint $table) {
                $table->unsignedTinyInteger('retry_count')->default(0)->after('current_step');
            });
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('drip_enrollments', function (Blueprint $table) {
            $table->dropColumn('retry_count');
        });
    }
};
