<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add targeted composite indexes for query patterns not covered by earlier migrations.
     *
     * Existing indexes (already present — NOT duplicated here):
     *   conversations: (workspace_id, status), (workspace_id, channel), (workspace_id, assigned_to),
     *                  (workspace_id, assigned_to, status)  [from 2026_03_18 inbox migration]
     *   messages:      (conversation_id, created_at), (workspace_id, ai_status), (workspace_id, type)
     *   campaign_recipients: (campaign_id, status)
     *   drip_enrollments:    (sequence_id, status), (next_step_at)
     *   workflow_executions: (workflow_id, status)
     *   tags:                unique(workspace_id, name)  — already covers workspace_id scans
     *
     * NEW indexes added by this migration:
     *
     * 1. conversations(contact_id, workspace_id, channel)
     *    Query: "show all conversations for this contact, optionally filtered by channel"
     *    Used by: contact timeline / profile drawer
     *
     * 2. campaign_recipients(campaign_id, created_at)
     *    Query: "campaign analytics over time — delivery curve, cohort charts"
     *    The existing (campaign_id, status) covers status filters but not time-series aggregation.
     *
     * 3. drip_enrollments(contact_id, sequence_id)
     *    Query: "is this contact enrolled in this sequence? show their journey."
     *    Existing indexes are sequence-first; this one is contact-first for CRM lookups.
     *
     * 4. workflow_executions(contact_id, workflow_id, status, created_at)
     *    Query: "automation history for a contact — which workflows ran, outcomes, timeline"
     *    Existing (workflow_id, status) is workflow-centric; this is contact-centric.
     *
     * 5. messages(workspace_id, created_at)
     *    Query: "recent messages across all conversations in a workspace"
     *    Used by: dashboard activity feed, workspace-level search by date range.
     *
     * 6. attachments(message_id, mime_type)
     *    Query: "list attachments for a message, filter by type (image/*, application/pdf)"
     *    The foreign key on message_id alone doesn't cover type filtering.
     */
    public function up(): void
    {
        try {
            // Contact timeline — "all conversations for contact X in workspace Y, optionally by channel"
            Schema::table('conversations', function (Blueprint $table) {
                $table->index(
                    ['contact_id', 'workspace_id', 'channel'],
                    'conversations_contact_ws_channel_idx'
                );
            });

            // Campaign analytics time-series — delivery curve, hourly/daily aggregation
            Schema::table('campaign_recipients', function (Blueprint $table) {
                $table->index(
                    ['campaign_id', 'created_at'],
                    'campaign_recipients_campaign_created_idx'
                );
            });

            // Contact journey lookup — "is contact X in sequence Y?"
            Schema::table('drip_enrollments', function (Blueprint $table) {
                $table->index(
                    ['contact_id', 'sequence_id'],
                    'drip_enrollments_contact_sequence_idx'
                );
            });

            // Contact automation history — workflow runs for a specific contact
            Schema::table('workflow_executions', function (Blueprint $table) {
                $table->index(
                    ['contact_id', 'workflow_id', 'status', 'created_at'],
                    'workflow_exec_contact_wf_status_created_idx'
                );
            });

            // Workspace activity feed — recent messages across all conversations
            Schema::table('messages', function (Blueprint $table) {
                $table->index(
                    ['workspace_id', 'created_at'],
                    'messages_ws_created_idx'
                );
            });

            // Attachment type filtering — "show only images" or "show only PDFs"
            Schema::table('attachments', function (Blueprint $table) {
                $table->index(
                    ['message_id', 'mime_type'],
                    'attachments_message_mime_idx'
                );
            });
        } catch (\Throwable $e) {
            // Column/index may already exist on fresh install
        }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_contact_ws_channel_idx');
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropIndex('campaign_recipients_campaign_created_idx');
        });

        Schema::table('drip_enrollments', function (Blueprint $table) {
            $table->dropIndex('drip_enrollments_contact_sequence_idx');
        });

        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->dropIndex('workflow_exec_contact_wf_status_created_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_ws_created_idx');
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex('attachments_message_mime_idx');
        });
    }
};
