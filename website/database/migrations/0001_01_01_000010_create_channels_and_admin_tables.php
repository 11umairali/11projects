<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Channel integrations (WhatsApp, SMS, Telegram, Slack)
        Schema::create('channel_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['whatsapp', 'sms', 'telegram', 'slack', 'chat']);
            $table->string('provider')->nullable(); // twilio, vonage, meta, etc.
            $table->json('credentials')->nullable(); // encrypted JSON: api_key, token, etc.
            $table->json('config')->nullable(); // channel-specific settings
            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive');
            $table->string('error_message')->nullable();
            // Channel-specific identifiers
            $table->string('phone_number')->nullable(); // WhatsApp/SMS
            $table->string('bot_username')->nullable(); // Telegram
            $table->string('slack_team_id')->nullable();
            $table->string('slack_bot_token')->nullable(); // encrypted
            $table->string('slack_channel_id')->nullable();
            $table->boolean('ai_auto_reply')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['workspace_id', 'channel']);
        });

        // WhatsApp message templates
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('category', ['marketing', 'utility', 'authentication']);
            $table->string('language', 10)->default('en');
            $table->string('header_type', 20)->nullable(); // none, text, image, video, document
            $table->text('header_content')->nullable();
            $table->text('body'); // up to 1024 chars with {{N}} variables
            $table->string('footer')->nullable();
            $table->json('buttons')->nullable();
            $table->json('sample_values')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->string('meta_template_id')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });

        // Live chat widget config
        Schema::create('chat_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('public_id', 32)->unique(); // for embed script
            // Appearance
            $table->string('primary_color', 7)->default('#4F46E5');
            $table->string('position', 20)->default('bottom-right');
            $table->string('button_icon', 20)->default('chat');
            $table->unsignedSmallInteger('border_radius')->default(12);
            $table->unsignedSmallInteger('widget_width')->default(380);
            $table->string('company_name')->nullable();
            $table->string('agent_avatar_path')->nullable();
            $table->string('company_logo_path')->nullable();
            $table->boolean('show_branding')->default(true);
            // Behavior
            $table->string('welcome_message')->default('Hi there! How can we help you today?');
            $table->string('pre_chat_form', 30)->default('name_email'); // none, name, name_email, name_email_phone, custom
            $table->boolean('pre_chat_required')->default(true);
            $table->string('offline_mode', 30)->default('leave_message'); // hide, leave_message, ai_bot
            $table->string('offline_message')->nullable();
            $table->boolean('ai_auto_reply')->default(true);
            $table->boolean('file_sharing')->default(true);
            $table->boolean('typing_indicator')->default(true);
            $table->boolean('sound_notification')->default(true);
            $table->boolean('chat_rating')->default(true);
            $table->boolean('email_transcript')->default(true);
            $table->unsignedSmallInteger('auto_close_minutes')->default(30);
            $table->string('operating_hours', 20)->default('workspace'); // workspace, custom, 24_7
            $table->json('custom_hours')->nullable();
            $table->json('proactive_triggers')->nullable();
            $table->timestamps();

            $table->unique('workspace_id');
        });

        // Support tickets (admin panel)
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->enum('status', ['open', 'in_progress', 'waiting', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->string('category')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(); // can be user or admin
            $table->text('body');
            $table->boolean('is_admin_reply')->default(false);
            $table->timestamps();
        });

        // Admin users (separate from regular users)
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->json('ip_whitelist')->nullable();
            $table->enum('role', ['super_admin', 'admin', 'support'])->default('admin');
            $table->rememberToken();
            $table->timestamps();
        });

        // CMS pages
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->json('sections')->nullable(); // page builder sections
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_image')->nullable();
            $table->boolean('is_published')->default(false);
            $table->enum('type', ['landing', 'static', 'blog'])->default('static');
            $table->timestamps();
        });

        // System settings (key-value)
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // Audit logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('event'); // created, updated, deleted, login, impersonated, etc.
            $table->string('actor_type')->nullable(); // user, admin
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_type', 'actor_id']);
            $table->index('event');
            $table->index('created_at');
        });

        // Notifications (in-app)
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Email suppression list
        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->enum('reason', ['hard_bounce', 'spam_complaint', 'unsubscribe', 'manual'])->default('manual');
            $table->timestamps();

            $table->unique(['workspace_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('chat_widgets');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('channel_integrations');
    }
};
