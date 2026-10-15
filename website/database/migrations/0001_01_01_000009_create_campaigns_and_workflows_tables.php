<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campaigns
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('email_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('type', ['regular', 'ab_test', 'drip'])->default('regular');
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'paused', 'canceled'])->default('draft');
            $table->string('subject')->nullable();
            $table->text('body_html')->nullable();
            $table->json('body_json')->nullable(); // drag-and-drop builder data
            $table->string('preview_text')->nullable();
            // Audience
            $table->string('audience_type', 20)->nullable(); // segment, list, all
            $table->unsignedBigInteger('audience_id')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            // Stats (cached)
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->unsignedInteger('bounced_count')->default(0);
            $table->unsignedInteger('unsubscribed_count')->default(0);
            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status']);
        });

        // Campaign recipients
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'sent', 'delivered', 'opened', 'clicked', 'bounced', 'unsubscribed', 'failed'])->default('pending');
            $table->string('variant', 1)->nullable(); // A, B, C for A/B testing
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });

        // Campaign links (for click tracking)
        Schema::create('campaign_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->text('original_url');
            $table->string('tracking_hash', 32)->unique();
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamps();
        });

        // A/B test variants
        Schema::create('ab_test_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('variant', 1); // A, B, C
            $table->string('subject');
            $table->text('body_html')->nullable();
            $table->unsignedTinyInteger('percentage')->default(50);
            $table->boolean('is_winner')->default(false);
            $table->timestamps();
        });

        // Drip sequences
        Schema::create('drip_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->timestamps();
        });

        Schema::create('drip_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('drip_sequences')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedInteger('delay_value')->default(1);
            $table->enum('delay_unit', ['minutes', 'hours', 'days'])->default('days');
            $table->enum('action_type', ['send_email', 'wait_condition', 'add_tag', 'remove_tag', 'update_field'])->default('send_email');
            $table->json('action_data'); // subject, body, tag_id, etc.
            $table->timestamps();
        });

        Schema::create('drip_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('drip_sequences')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_step')->default(0);
            $table->enum('status', ['active', 'completed', 'exited', 'paused'])->default('active');
            $table->timestamp('next_step_at')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['sequence_id', 'status']);
            $table->index(['next_step_at']);
        });

        // Workflows (visual builder)
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'paused'])->default('draft');
            $table->json('canvas_data')->nullable(); // full builder state (nodes, edges, positions)
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('executions_count')->default(0);
            // Webhook trigger URL token
            $table->string('webhook_token', 64)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status']);
        });

        // Workflow nodes
        Schema::create('workflow_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['trigger', 'condition', 'action']);
            $table->string('subtype'); // email_received, if_else, send_email, etc.
            $table->json('config')->nullable(); // node-specific configuration
            $table->float('position_x')->default(0);
            $table->float('position_y')->default(0);
            $table->timestamps();
        });

        // Workflow edges (connections between nodes)
        Schema::create('workflow_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_node_id')->constrained('workflow_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('workflow_nodes')->cascadeOnDelete();
            $table->string('label')->nullable(); // 'yes', 'no', 'default'
            $table->timestamps();
        });

        // Workflow executions
        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['running', 'completed', 'failed', 'waiting', 'canceled'])->default('running');
            $table->json('trigger_data')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'status']);
        });

        // Workflow step logs
        Schema::create('workflow_step_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('workflow_executions')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('workflow_nodes')->cascadeOnDelete();
            $table->enum('status', ['success', 'failed', 'skipped', 'waiting'])->default('success');
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('resume_at')->nullable(); // for wait/delay nodes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_logs');
        Schema::dropIfExists('workflow_executions');
        Schema::dropIfExists('workflow_edges');
        Schema::dropIfExists('workflow_nodes');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('drip_enrollments');
        Schema::dropIfExists('drip_steps');
        Schema::dropIfExists('drip_sequences');
        Schema::dropIfExists('ab_test_variants');
        Schema::dropIfExists('campaign_links');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
