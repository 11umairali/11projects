<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX-013: Add 2FA attempt tracking to prevent brute-force on session restart.
 * FIX-015: Add impersonation tracking in DB instead of session.
 */
return new class extends Migration
{
    public function up(): void
    {
        // FIX-013: Track 2FA attempts persistently
        Schema::create('two_factor_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('ip_address', 45);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'ip_address']);
        });

        // FIX-015: Track admin impersonation in DB
        Schema::create('admin_impersonations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('user_id');
            $table->string('session_id', 100);
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['session_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_impersonations');
        Schema::dropIfExists('two_factor_attempts');
    }
};
