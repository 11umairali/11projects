<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('drip_enrollments', function (Blueprint $table) {
                $table->string('exit_reason')->nullable()->after('status');
                $table->timestamp('exited_at')->nullable()->after('completed_at');
            });
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('drip_enrollments', function (Blueprint $table) {
            $table->dropColumn(['exit_reason', 'exited_at']);
        });
    }
};
