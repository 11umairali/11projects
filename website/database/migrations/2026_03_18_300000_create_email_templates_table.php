<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('blocks');
            $table->string('thumbnail_path')->nullable();
            $table->string('category', 50)->default('general');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'category']);
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
