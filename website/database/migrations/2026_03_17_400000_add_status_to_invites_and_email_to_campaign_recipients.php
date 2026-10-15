<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing columns required by application code:
 * 1. invites.status — referenced by InviteController::accept()
 * 2. campaign_recipients.email — inserted by CampaignService::resolveAudienceChunked()
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('invites', 'status')) {
            Schema::table('invites', function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->after('role');
            });
        }

        if (!Schema::hasColumn('campaign_recipients', 'email')) {
            Schema::table('campaign_recipients', function (Blueprint $table) {
                $table->string('email')->nullable()->after('contact_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invites', 'status')) {
            Schema::table('invites', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasColumn('campaign_recipients', 'email')) {
            Schema::table('campaign_recipients', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }
    }
};
