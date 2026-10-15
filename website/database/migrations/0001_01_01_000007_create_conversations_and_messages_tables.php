<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversations (unified inbox)
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('channel', ['email', 'whatsapp', 'sms', 'telegram', 'slack', 'chat', 'live_chat'])->default('email');
            $table->enum('status', ['open', 'pending', 'closed', 'snoozed', 'spam'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->string('subject')->nullable();
            $table->text('preview')->nullable(); // first ~200 chars of last message
            $table->enum('sentiment', ['positive', 'neutral', 'negative', 'angry'])->nullable();
            $table->boolean('is_starred')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_read')->default(false);
            $table->boolean('is_ai_handled')->default(false);
            $table->unsignedInteger('messages_count')->default(0);
            $table->unsignedInteger('ai_replies_count')->default(0);
            $table->json('tags')->nullable(); // cached tag IDs for quick filtering
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            // Channel-specific IDs
            $table->string('channel_conversation_id')->nullable(); // WhatsApp/Telegram/Slack thread ID
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'channel']);
            $table->index(['workspace_id', 'assigned_to']);
            $table->index(['workspace_id', 'priority']);
            $table->index(['workspace_id', 'last_message_at']);
            $table->index(['workspace_id', 'is_read']);
        });

        // Conversation-Tag pivot
        Schema::create('conversation_tag', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['conversation_id', 'tag_id']);
        });

        // Messages
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->enum('sender_type', ['contact', 'agent', 'ai', 'system'])->default('contact');
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete(); // agent user ID
            $table->enum('type', ['message', 'note', 'ai_draft', 'system_event'])->default('message');
            $table->text('body_html')->nullable();
            $table->text('body_text')->nullable();
            // Email-specific
            $table->string('subject')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->json('to_emails')->nullable();
            $table->json('cc_emails')->nullable();
            $table->json('bcc_emails')->nullable();
            $table->string('message_id_header')->nullable(); // email Message-ID header
            $table->string('in_reply_to')->nullable(); // email threading
            $table->json('references_header')->nullable();
            // AI fields
            $table->unsignedTinyInteger('ai_confidence')->nullable(); // 0-100
            $table->string('ai_model')->nullable(); // gpt-4o, claude-sonnet, etc.
            $table->string('ai_provider')->nullable();
            $table->unsignedInteger('ai_tokens_in')->nullable();
            $table->unsignedInteger('ai_tokens_out')->nullable();
            $table->decimal('ai_cost', 8, 5)->nullable();
            $table->unsignedInteger('ai_response_time_ms')->nullable();
            $table->json('ai_sources_used')->nullable(); // KB chunk IDs
            $table->enum('ai_status', ['draft', 'approved', 'sent', 'rejected', 'edited'])->nullable();
            // Sentiment
            $table->enum('sentiment', ['positive', 'neutral', 'negative', 'angry'])->nullable();
            $table->string('detected_language', 10)->nullable();
            // Tracking (outbound)
            $table->enum('delivery_status', ['queued', 'sent', 'delivered', 'failed', 'bounced'])->nullable();
            $table->string('delivery_error')->nullable();
            $table->unsignedInteger('opens_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_type')->nullable(); // hard, soft
            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            // Channel-specific
            $table->string('channel_message_id')->nullable(); // WhatsApp/Telegram/Slack msg ID
            $table->unsignedInteger('imap_uid')->nullable(); // IMAP UID for direct body fetch
            $table->string('imap_folder', 100)->nullable(); // IMAP folder name (INBOX, etc.)
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['workspace_id', 'ai_status']);
            $table->index(['workspace_id', 'type']);
            if (config('database.default') !== 'sqlite') {
                $table->fullText(['body_text']);
            }
        });

        // Message attachments
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size'); // bytes
            $table->string('storage_path');
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_inline')->default(false);
            $table->string('content_id')->nullable(); // for inline images
            $table->timestamps();
        });

        // Email tracking events
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['open', 'click', 'bounce', 'unsubscribe', 'spam_complaint']);
            $table->string('url')->nullable(); // for clicks
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device')->nullable(); // desktop, mobile, tablet
            $table->string('email_client')->nullable(); // Gmail, Outlook, etc.
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();

            $table->index(['message_id', 'type']);
        });

        // Conversation viewers (collision detection via DB polling)
        Schema::create('conversation_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_seen_at')->nullable();

            $table->unique(['conversation_id', 'user_id']);
        });

        // Conversation typing indicators (DB-based)
        Schema::create('conversation_typing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['conversation_id', 'user_id']);
        });

        // Canned responses
        Schema::create('canned_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = team shared
            $table->string('title');
            $table->string('shortcut')->nullable(); // /shipping-delay
            $table->text('content');
            $table->string('category')->nullable();
            $table->json('channels')->nullable(); // null = all channels
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canned_responses');
        Schema::dropIfExists('conversation_typing');
        Schema::dropIfExists('conversation_viewers');
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_tag');
        Schema::dropIfExists('conversations');
    }
};
