<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('display_name');
            $table->enum('provider', ['gmail', 'outlook', 'imap'])->default('imap');
            // IMAP settings (encrypted)
            $table->text('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable();
            $table->text('imap_username')->nullable();
            $table->text('imap_password')->nullable(); // encrypted
            $table->string('imap_encryption', 10)->nullable();
            // SMTP settings (encrypted)
            $table->text('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->text('smtp_username')->nullable();
            $table->text('smtp_password')->nullable(); // encrypted
            $table->string('smtp_encryption', 10)->nullable();
            // OAuth tokens (for Gmail/Outlook)
            $table->text('oauth_token')->nullable(); // encrypted
            $table->text('oauth_refresh_token')->nullable(); // encrypted
            $table->timestamp('oauth_token_expires_at')->nullable();
            // Status
            $table->enum('status', ['connected', 'disconnected', 'syncing', 'error'])->default('disconnected');
            $table->string('error_message')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('ai_auto_reply')->default(false);
            $table->json('sync_folders')->nullable(); // inbox, sent, drafts, etc.
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_synced_uid')->nullable(); // IMAP UID for incremental sync
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'is_default']);
        });

        // Email signatures
        Schema::create('email_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('content_html');
            $table->boolean('is_default')->default(false);
            $table->boolean('append_to_new')->default(true);
            $table->boolean('append_to_replies')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_signatures');
        Schema::dropIfExists('email_accounts');
    }
};
