<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // SQL-imported installations may already contain any/all of these columns.
        foreach (['voip_token', 'app_version', 'apns_sandbox'] as $column) {
            if (Schema::hasColumn('device_tokens', $column)) continue;
            Schema::table('device_tokens', function (Blueprint $table) use ($column) {
                match ($column) {
                    'voip_token' => $table->string('voip_token', 255)->nullable(),
                    'app_version' => $table->string('app_version', 20)->nullable(),
                    'apns_sandbox' => $table->boolean('apns_sandbox')->default(false),
                };
            });
        }
    }

    public function down(): void
    {
        // Preserve registrations and columns that may predate this migration via SQL import.
    }
};
