<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds SMS support to the campaigns module:
 *
 *  campaigns:
 *    + channel       enum('email','sms') default 'email'
 *    + from_number   varchar(32) nullable      // E.164 Twilio number for SMS
 *    + body_text     text nullable             // raw SMS body (no HTML)
 *    ~ subject       NULL allowed              // SMS has no subject
 *    ~ body_html     NULL allowed              // SMS has no HTML body
 *    ~ email_account_id NULL allowed           // SMS uses a Twilio number, not Gmail/Outlook
 *
 *  campaign_recipients:
 *    + phone         varchar(32) nullable      // recipient phone for SMS path
 *
 * Throttling fields (emails_per_minute / batch_size / batch_delay_seconds)
 * are channel-agnostic — the existing columns are reused for SMS rate
 * control without renaming.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'channel')) {
                $table->enum('channel', ['email', 'sms'])->default('email')->after('type')->index();
            }
            if (!Schema::hasColumn('campaigns', 'from_number')) {
                $table->string('from_number', 32)->nullable()->after('email_account_id');
            }
            if (!Schema::hasColumn('campaigns', 'body_text')) {
                $table->text('body_text')->nullable()->after('body_html');
            }
        });

        // Relax NOT-NULL on email-only columns so SMS rows can be inserted
        // without dummy values. Use raw SQL — Schema::change() needs doctrine/dbal
        // and we want this to work on minimal hosts.
        DB::statement("ALTER TABLE campaigns MODIFY subject VARCHAR(255) NULL");
        DB::statement("ALTER TABLE campaigns MODIFY body_html MEDIUMTEXT NULL");
        DB::statement("ALTER TABLE campaigns MODIFY email_account_id BIGINT UNSIGNED NULL");

        Schema::table('campaign_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_recipients', 'phone')) {
                $table->string('phone', 32)->nullable()->after('email')->index();
            }
        });

        // Email recipients still REQUIRE an email; only SMS rows skip it.
        // Loosen NOT-NULL on the recipient.email column so the SMS path
        // doesn't have to invent fake email values.
        DB::statement("ALTER TABLE campaign_recipients MODIFY email VARCHAR(255) NULL");
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_recipients', 'phone')) {
                $table->dropColumn('phone');
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            foreach (['channel', 'from_number', 'body_text'] as $col) {
                if (Schema::hasColumn('campaigns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
