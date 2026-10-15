<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds 'processing' to the campaign_recipients.status enum.
 *
 * SendCampaignEmailJob atomically claims a recipient by updating
 *   status = 'pending' -> 'processing'
 * to prevent duplicate sends when two workers pick up the same row. Without
 * this value in the enum, MySQL raises "Data truncated for column 'status'"
 * and the campaign never actually sends.
 *
 * Uses a raw ALTER because Doctrine's change() doesn't play well with MySQL
 * enums and we'd have to add doctrine/dbal as a dependency otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campaign_recipients')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `campaign_recipients` MODIFY COLUMN `status` " .
            "ENUM('pending','processing','sent','delivered','opened','clicked','bounced','unsubscribed','failed') " .
            "NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('campaign_recipients')) {
            return;
        }

        // Normalise any 'processing' rows back to 'pending' so the rollback
        // doesn't lose data to enum truncation.
        DB::table('campaign_recipients')
            ->where('status', 'processing')
            ->update(['status' => 'pending']);

        DB::statement(
            "ALTER TABLE `campaign_recipients` MODIFY COLUMN `status` " .
            "ENUM('pending','sent','delivered','opened','clicked','bounced','unsubscribed','failed') " .
            "NOT NULL DEFAULT 'pending'"
        );
    }
};
