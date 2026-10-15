<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Admin-authored tickets and admin replies have no associated end-user,
        // so user_id needs to be nullable on both tables.
        DB::statement('ALTER TABLE tickets MODIFY user_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE ticket_replies MODIFY user_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tickets MODIFY user_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE ticket_replies MODIFY user_id BIGINT UNSIGNED NOT NULL');
    }
};
