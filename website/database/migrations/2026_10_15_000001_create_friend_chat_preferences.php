<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('friend_chat_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('other_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('pinned')->default(false);
            $table->boolean('muted')->default(false);
            $table->boolean('archived')->default(false);
            $table->unsignedBigInteger('hidden_through_id')->nullable();
            $table->timestamps();
            $table->primary(['user_id', 'other_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('friend_chat_preferences'); }
};
