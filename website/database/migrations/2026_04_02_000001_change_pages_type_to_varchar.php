<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE pages MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'static'");
        } catch (\Throwable $e) {
            // Column may already be varchar
        }
    }

    public function down(): void
    {
        // No rollback — keeping varchar is safe
    }
};
