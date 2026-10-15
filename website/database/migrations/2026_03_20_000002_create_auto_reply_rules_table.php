<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_reply_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('keywords');
            $table->enum('match_type', ['any', 'all', 'exact'])->default('any');
            $table->text('reply_body');
            $table->string('reply_subject')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('channel', ['all', 'email', 'whatsapp', 'sms', 'chat'])->default('all');
            $table->boolean('first_message_only')->default(true);
            $table->integer('priority')->default(0);
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_reply_rules');
    }
};
