<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('temp_mail_domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('domain')->unique();
            $table->string('display_name');
            $table->text('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->text('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_encryption', 10)->default('ssl');
            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive');
            $table->string('error_message', 500)->nullable();
            $table->unsignedInteger('max_addresses')->nullable();
            $table->unsignedInteger('default_lifetime_hours')->default(24);
            $table->json('blocked_patterns')->nullable();
            $table->json('allowed_patterns')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_synced_uid')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('temp_mail_addresses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('temp_mail_domain_id')->constrained()->cascadeOnDelete();
            $table->string('local_part', 64);
            $table->string('full_address', 320)->unique();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('messages_count')->default(0);
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('last_received_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'is_active']);
            $table->index('full_address');
            $table->index('expires_at');
            $table->unique(['temp_mail_domain_id', 'local_part']);
        });

        // Expand conversations channel enum to include temp_mail
        // Using raw SQL because Laravel doesn't support modifying enums cleanly
        DB::statement("ALTER TABLE conversations MODIFY COLUMN channel VARCHAR(20) DEFAULT 'email'");
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_mail_addresses');
        Schema::dropIfExists('temp_mail_domains');
    }
};
