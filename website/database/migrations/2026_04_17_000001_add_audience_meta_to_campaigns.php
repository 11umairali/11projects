<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a flexible JSON column to `campaigns` for audience types that don't
 * fit a single foreign key — specifically the new "specific contacts" option
 * which needs to persist an array of contact IDs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'audience_meta')) {
                $table->json('audience_meta')->nullable()->after('audience_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'audience_meta')) {
                $table->dropColumn('audience_meta');
            }
        });
    }
};
