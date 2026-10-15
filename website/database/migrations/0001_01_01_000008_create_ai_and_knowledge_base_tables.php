<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI provider configs per workspace
        Schema::create('ai_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // Provider & model
            $table->string('provider')->default('openai'); // openai, anthropic, gemini, mistral, custom
            $table->string('model')->default('gpt-4o');
            $table->boolean('use_own_key')->default(false);
            $table->text('api_key')->nullable(); // encrypted
            $table->string('custom_endpoint')->nullable();
            // Model settings
            $table->decimal('temperature', 3, 2)->default(0.30);
            $table->string('max_reply_length', 20)->default('medium');
            $table->decimal('top_p', 3, 2)->default(0.90);
            $table->decimal('frequency_penalty', 3, 2)->default(0.30);
            $table->decimal('presence_penalty', 3, 2)->default(0.10);
            // Personality & tone
            $table->string('personality_preset')->default('friendly'); // professional, friendly, casual, sales, support, custom
            $table->text('custom_prompt')->nullable();
            $table->text('additional_instructions')->nullable();
            // Language
            $table->string('reply_language', 20)->default('auto'); // auto = detect & match
            $table->boolean('multi_language_greeting')->default(false);
            // Formatting
            $table->string('include_greeting', 20)->default('ai_decides');
            $table->string('include_signoff', 20)->default('always');
            $table->string('signoff_text')->default('Best regards,');
            $table->boolean('include_sender_name')->default(true);
            $table->boolean('include_company_name')->default(false);
            $table->boolean('use_html_formatting')->default(true);
            $table->boolean('use_bullet_points')->default(true);
            // Auto-reply settings
            $table->boolean('auto_reply_enabled')->default(false);
            $table->boolean('business_hours_only')->default(false);
            $table->text('outside_hours_message')->nullable();
            $table->unsignedTinyInteger('confidence_threshold')->default(75); // 0-100
            $table->enum('send_mode', ['autonomous', 'approval', 'suggestions'])->default('approval');
            $table->string('reply_delay', 20)->default('none'); // none, 30s, 1m, 2m, 5m, random
            $table->boolean('first_message_only')->default(false);
            $table->unsignedTinyInteger('max_ai_replies_per_conversation')->default(3);
            $table->boolean('skip_own_threads')->default(true);
            $table->unsignedInteger('auto_action_hours')->nullable(); // auto-send/discard after X hours
            $table->string('auto_action_type', 20)->nullable(); // auto_send, auto_discard, auto_assign
            $table->timestamps();

            $table->unique('workspace_id');
        });

        // AI excluded senders
        Schema::create('ai_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['email', 'domain', 'keyword']);
            $table->string('value');
            $table->string('match_type', 20)->default('contains'); // contains, exact, regex
            $table->timestamps();

            $table->index(['workspace_id', 'type']);
        });

        // AI auto-reply exclusion rules
        Schema::create('ai_exclusion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key'); // no_reply_address, auto_reply, mailing_list, etc.
            $table->boolean('enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'rule_key']);
        });

        // Knowledge base documents
        Schema::create('kb_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['document', 'website', 'qa'])->default('document');
            $table->string('title');
            $table->text('content')->nullable(); // extracted text (for documents/websites)
            $table->string('question')->nullable(); // for Q&A type
            $table->text('answer')->nullable(); // for Q&A type
            $table->string('category')->nullable();
            $table->boolean('is_priority')->default(false); // "always prefer" for Q&A
            // Document-specific
            $table->string('file_path')->nullable();
            $table->string('file_type', 10)->nullable(); // pdf, docx, txt, csv, xlsx, md
            $table->unsignedBigInteger('file_size')->nullable();
            // Website-specific
            $table->string('source_url')->nullable();
            // Processing
            $table->enum('status', ['uploading', 'extracting', 'chunking', 'embedding', 'ready', 'failed', 'paused'])->default('uploading');
            $table->string('error_message')->nullable();
            $table->unsignedInteger('chunks_count')->default(0);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'type']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'category']);
        });

        // Knowledge base chunks (for RAG)
        Schema::create('kb_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('kb_documents')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->unsignedInteger('chunk_index')->default(0);
            $table->string('vector_id')->nullable(); // Pinecone vector ID
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['workspace_id']);
            if (config('database.default') !== 'sqlite') {
                $table->fullText(['content']);
            }
        });

        // Knowledge base websites (scraping config)
        Schema::create('kb_websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('domain');
            $table->string('scrape_depth', 20)->default('single'); // single, full
            $table->unsignedInteger('max_pages')->default(50);
            $table->json('include_patterns')->nullable();
            $table->json('exclude_patterns')->nullable();
            $table->string('rescrape_schedule', 20)->default('never'); // never, daily, weekly, monthly
            $table->unsignedInteger('pages_count')->default(0);
            $table->enum('status', ['active', 'paused', 'error'])->default('active');
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id']);
        });

        // AI usage logs (cost tracking)
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->enum('type', ['reply', 'sentiment', 'embedding', 'playground', 'classification'])->default('reply');
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost', 8, 5)->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('kb_websites');
        Schema::dropIfExists('kb_chunks');
        Schema::dropIfExists('kb_documents');
        Schema::dropIfExists('ai_exclusion_rules');
        Schema::dropIfExists('ai_exclusions');
        Schema::dropIfExists('ai_configs');
    }
};
