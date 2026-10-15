<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds per-campaign send-rate controls so outgoing blasts can be throttled to
 * reduce the risk of being flagged as spam by the destination MTA (Gmail's
 * per-account daily limit, SMTP provider rate limits, WhatsApp throttling, etc).
 *
 *   emails_per_minute     — cap on how fast we dispatch jobs (60 = 1/s)
 *   batch_size            — send N emails, then pause (0 = disabled)
 *   batch_delay_seconds   — pause duration between batches
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'emails_per_minute')) {
                $table->unsignedSmallInteger('emails_per_minute')->default(60)->after('recipients_count');
            }
            if (!Schema::hasColumn('campaigns', 'batch_size')) {
                $table->unsignedSmallInteger('batch_size')->default(0)->after('emails_per_minute');
            }
            if (!Schema::hasColumn('campaigns', 'batch_delay_seconds')) {
                $table->unsignedSmallInteger('batch_delay_seconds')->default(0)->after('batch_size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            foreach (['emails_per_minute', 'batch_size', 'batch_delay_seconds'] as $col) {
                if (Schema::hasColumn('campaigns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
