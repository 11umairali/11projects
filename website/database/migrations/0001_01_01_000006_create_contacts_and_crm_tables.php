<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tags
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#6B7280'); // hex color
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        // Contacts
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('timezone', 50)->nullable();
            $table->unsignedInteger('lead_score')->default(0);
            $table->json('custom_fields')->nullable();
            $table->enum('status', ['active', 'unsubscribed', 'bounced', 'spam'])->default('active');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribe_reason')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'email']);
            $table->index(['workspace_id', 'lead_score']);
            $table->index(['workspace_id', 'status']);
            if (config('database.default') !== 'sqlite') {
                $table->fullText(['first_name', 'last_name', 'email', 'company']);
            }
        });

        // Contact-Tag pivot
        Schema::create('contact_tag', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_id', 'tag_id']);
        });

        // Custom field definitions
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key'); // snake_case identifier
            $table->enum('type', ['text', 'number', 'date', 'dropdown', 'checkbox', 'url', 'email', 'phone'])->default('text');
            $table->json('options')->nullable(); // for dropdown type
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'key']);
        });

        // Segments (dynamic contact groups)
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('rules'); // AND/OR conditions
            $table->boolean('is_dynamic')->default(true);
            $table->unsignedInteger('contacts_count')->default(0); // cached count
            $table->timestamps();
        });

        // Static lists
        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('contacts_count')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_list_members', function (Blueprint $table) {
            $table->foreignId('contact_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_list_id', 'contact_id']);
            $table->timestamp('added_at')->useCurrent();
        });

        // Lead scoring rules
        Schema::create('lead_scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('event'); // email_opened, email_replied, link_clicked, etc.
            $table->integer('points'); // can be negative
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Deal pipelines
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Pipeline stages
        Schema::create('deal_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#6B7280');
            $table->unsignedInteger('win_probability')->default(0); // 0-100
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Deals
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('usd');
            $table->date('expected_close_date')->nullable();
            $table->enum('status', ['open', 'won', 'lost'])->default('open');
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->json('custom_fields')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'deal_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
        Schema::dropIfExists('deal_stages');
        Schema::dropIfExists('pipelines');
        Schema::dropIfExists('lead_scoring_rules');
        Schema::dropIfExists('contact_list_members');
        Schema::dropIfExists('contact_lists');
        Schema::dropIfExists('segments');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('contact_tag');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('tags');
    }
};
