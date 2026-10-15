<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * email_signatures — per-account email signature blocks that
 * EmailSendService auto-appends to outgoing mail (already wired in
 * EmailSendService::getSignature()).
 *
 * One default per account is the recommended setup. The two
 * append_to_new / append_to_replies flags let an admin toggle
 * "use this signature on new emails" vs "use it on replies" so a
 * shorter signature can ride replies while a richer one rides
 * brand-new outbound campaigns.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')
                ->constrained('email_accounts')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->longText('content_html');
            $table->boolean('is_default')->default(false);
            $table->boolean('append_to_new')->default(true);
            $table->boolean('append_to_replies')->default(true);
            $table->timestamps();

            $table->index(['email_account_id', 'is_default'], 'email_signatures_account_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_signatures');
    }
};
