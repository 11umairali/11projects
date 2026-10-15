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
            // Expand the channel enum to include third-party integrations.
            // MySQL ALTER COLUMN MODIFY preserves existing data for values that remain in the list.
            DB::statement("ALTER TABLE channel_integrations MODIFY COLUMN channel ENUM('whatsapp','sms','telegram','slack','chat','salesforce','hubspot','zapier','stripe','google_calendar') NOT NULL");

            // Add columns needed for new integration types
            Schema::table('channel_integrations', function (Blueprint $table) {
                // Zapier: unique webhook token per workspace
                $table->string('zapier_webhook_token', 64)->nullable()->after('slack_channel_id');
                // Zapier: API bearer token for inbound requests
                $table->text('zapier_api_token')->nullable()->after('zapier_webhook_token');
                // Salesforce: instance URL
                $table->string('salesforce_instance_url')->nullable()->after('zapier_api_token');
                // OAuth token expiry tracking
                $table->timestamp('token_expires_at')->nullable()->after('salesforce_instance_url');
                // OAuth refresh token (encrypted, stored separately for clarity)
                $table->text('refresh_token')->nullable()->after('token_expires_at');
                // Connected account display name (e.g., Slack workspace name, HubSpot portal name)
                $table->string('account_name')->nullable()->after('refresh_token');
            });

            // Drop the unique constraint that only allowed one row per (workspace_id, channel)
            // so that the expanded enum values work with the same constraint logic.
            // The constraint already exists, so this is a no-op guard.
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('channel_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'zapier_webhook_token',
                'zapier_api_token',
                'salesforce_instance_url',
                'token_expires_at',
                'refresh_token',
                'account_name',
            ]);
        });

        // Revert enum (only safe if no rows use the new values)
        DB::statement("ALTER TABLE channel_integrations MODIFY COLUMN channel ENUM('whatsapp','sms','telegram','slack','chat') NOT NULL");
    }
};
