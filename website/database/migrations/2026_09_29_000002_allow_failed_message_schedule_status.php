<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The stock SendScheduledEmailJob sets messages.schedule_status = 'failed' when an email cannot be sent,
 * but the column only allowed pending|sent|cancelled. MySQL rejected the update, so failed emails stayed
 * "pending" and were re-dispatched every minute forever (shown as "queued" in the app).
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE messages MODIFY schedule_status ENUM('pending','sent','cancelled','failed') NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("UPDATE messages SET schedule_status = 'cancelled' WHERE schedule_status = 'failed'");
            DB::statement("ALTER TABLE messages MODIFY schedule_status ENUM('pending','sent','cancelled') NULL DEFAULT NULL");
        }
    }
};
