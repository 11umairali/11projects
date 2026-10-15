<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->enum('schedule_status', ['pending', 'sent', 'cancelled'])
                    ->nullable()
                    ->after('scheduled_at');

                $table->index(['schedule_status', 'scheduled_at'], 'idx_messages_schedule');
            });
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_schedule');
            $table->dropColumn('schedule_status');
        });
    }
};
