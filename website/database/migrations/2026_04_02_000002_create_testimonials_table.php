<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('testimonials')) return;

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('client_name', 150);
            $table->string('client_image')->nullable();
            $table->string('client_position', 150)->nullable();
            $table->text('review');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
