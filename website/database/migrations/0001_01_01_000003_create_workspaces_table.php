<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('industry')->nullable();
            $table->string('team_size', 20)->nullable();
            $table->string('timezone', 50)->default('UTC');
            $table->json('settings')->nullable(); // general workspace settings
            $table->json('business_hours')->nullable(); // day-by-day schedule
            $table->json('holidays')->nullable(); // holiday calendar
            $table->boolean('onboarding_completed')->default(false);
            $table->unsignedTinyInteger('onboarding_step')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'admin', 'agent', 'viewer'])->default('agent');
            $table->enum('status', ['active', 'online', 'away', 'offline', 'vacation'])->default('active');
            $table->boolean('available_for_assignment')->default(true);
            $table->unsignedInteger('max_concurrent_conversations')->default(20);
            $table->json('assigned_channels')->nullable(); // which channels this agent handles
            $table->json('skill_tags')->nullable(); // for skill-based routing
            $table->json('business_hours_override')->nullable();
            $table->timestamp('vacation_until')->nullable();
            $table->string('vacation_reassign_to')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index(['workspace_id', 'role']);
            $table->index(['workspace_id', 'status']);
        });

        // Invitations
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->enum('role', ['admin', 'agent', 'viewer'])->default('agent');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'email']);
        });

        // Add foreign key for active_workspace_id on users
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('active_workspace_id')->references('id')->on('workspaces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_workspace_id']);
        });
        Schema::dropIfExists('invites');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
