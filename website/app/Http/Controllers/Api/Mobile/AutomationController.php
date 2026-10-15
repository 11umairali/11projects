<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Exceptions\PlanLimitReachedException;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignLink;
use App\Models\CampaignRecipient;
use App\Models\ContactList;
use App\Models\Segment;
use App\Models\Tag;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Models\Workspace;
use App\Services\PlanLimitService;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Workflow step editor (same linear model as Livewire\Workflows\WorkflowBuilder),
 * workflow execution logs, campaign report and audience pickers.
 */
class AutomationController extends Controller
{
    use AuthorizesApiActions;

    private const TRIGGERS = [
        'email_received' => 'New Email Received', 'contact_created' => 'Contact Created', 'contact_updated' => 'Contact Updated',
        'deal_stage_changed' => 'Deal Stage Changed', 'tag_added' => 'Tag Added', 'tag_removed' => 'Tag Removed',
        'campaign_opened' => 'Campaign Opened', 'campaign_clicked' => 'Campaign Link Clicked', 'form_submitted' => 'Form Submitted',
        'webhook_received' => 'Webhook', 'scheduled' => 'Scheduled / Recurring',
    ];
    private const ACTIONS = [
        'send_email' => 'Send Email', 'send_notification' => 'Send Notification', 'add_tag' => 'Add Tag', 'remove_tag' => 'Remove Tag',
        'update_contact' => 'Update Contact Field', 'assign_agent' => 'Assign to Agent', 'create_deal' => 'Create Deal',
        'move_deal' => 'Move Deal Stage', 'wait_delay' => 'Wait / Delay', 'webhook_call' => 'Send data to external app (Webhook)',
        'ai_reply' => 'Generate AI Reply', 'add_to_group' => 'Add to Group', 'remove_from_group' => 'Remove from Group',
        'create_calendar_event' => 'Create Calendar Event',
    ];
    private const CONDITIONS = [
        'if_else' => 'If / Else', 'has_tag' => 'Has Tag', 'contact_field' => 'Contact Field Check',
        'email_opened' => 'Email Opened', 'email_clicked' => 'Email Clicked', 'in_group' => 'Is in Group',
    ];
    private const ALLOWED = [
        'send_email' => ['subject', 'body', 'body_html', 'from_name', 'email_template_id'],
        'send_notification' => ['message', 'channel', 'title', 'email', 'icon', 'action_url', 'notify_user_ids'],
        'add_tag' => ['tag_name'], 'remove_tag' => ['tag_name'], 'has_tag' => ['tag_name'],
        'update_contact' => ['field', 'value'], 'assign_agent' => ['agent_id', 'round_robin'],
        'create_deal' => ['deal_name', 'pipeline_id', 'value'], 'move_deal' => ['stage_id'],
        'wait_delay' => ['duration', 'unit'], 'webhook_call' => ['url', 'method'], 'ai_reply' => ['instructions', 'max_tokens'],
        'create_calendar_event' => ['summary', 'description', 'location', 'start_offset_minutes', 'duration_minutes', 'attendees', 'timezone'],
        'if_else' => ['field', 'operator', 'value'], 'contact_field' => ['field', 'operator', 'value'],
        'email_opened' => ['within_hours'], 'email_clicked' => ['within_hours'],
        'add_to_group' => ['group_id'], 'remove_from_group' => ['group_id'], 'in_group' => ['group_id'],
    ];

    private function wid(Request $r): int { return (int) $r->user()->active_workspace_id; }

    /** GET workflow-catalog — everything the step editor needs to render forms. */
    public function catalog(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'triggers' => self::TRIGGERS, 'actions' => self::ACTIONS, 'conditions' => self::CONDITIONS, 'config_keys' => self::ALLOWED,
        ]]);
    }

    /** GET workflows/{id}/steps → trigger + ordered nodes */
    public function steps(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $w = Workflow::where('workspace_id', $this->wid($request))->with(['workflowNodes' => fn ($q) => $q->orderBy('position_y')])->findOrFail($id);
        $trigger = $w->workflowNodes->firstWhere('type', 'trigger');

        return response()->json(['data' => [
            'id' => $w->id, 'name' => $w->name, 'description' => $w->description, 'status' => $w->status, 'version' => $w->version,
            'webhook_url' => $w->webhook_token ? url('/api/workflow-webhook/' . $w->webhook_token) : null,
            'trigger' => ['subtype' => $trigger?->subtype ?? '', 'config' => $trigger?->config ?? (object) []],
            'nodes' => $w->workflowNodes->where('type', '!=', 'trigger')->values()->map(fn ($n) => [
                'id' => $n->id, 'type' => $n->type, 'subtype' => $n->subtype, 'config' => $n->config ?? (object) [],
            ]),
        ]]);
    }

    /** PUT workflows/{id}/steps  (id = 0 creates) */
    public function saveSteps(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $wid = $this->wid($request);
        $d = $request->validate([
            'name' => 'required|string|max:255', 'description' => 'nullable|string|max:1000',
            'trigger.subtype' => 'required|string', 'trigger.config' => 'nullable|array',
            'nodes' => 'present|array|max:60', 'nodes.*.type' => 'required|in:action,condition', 'nodes.*.subtype' => 'required|string', 'nodes.*.config' => 'nullable|array',
        ]);
        if (!array_key_exists($d['trigger']['subtype'], self::TRIGGERS)) {
            return response()->json(['message' => 'Invalid trigger type.', 'errors' => ['trigger.subtype' => ['Invalid trigger type.']]], 422);
        }
        foreach ($d['nodes'] as $n) {
            $ok = $n['type'] === 'action' ? isset(self::ACTIONS[$n['subtype']]) : isset(self::CONDITIONS[$n['subtype']]);
            if (!$ok) return response()->json(['message' => "Unknown step type: {$n['subtype']}"], 422);
        }

        if ($id > 0) {
            $w = Workflow::where('workspace_id', $wid)->findOrFail($id);
            $w->update(['name' => $d['name'], 'description' => $d['description'] ?? null, 'version' => $w->version + 1]);
        } else {
            try {
                app(PlanLimitService::class)->assertCanCreate(Workspace::findOrFail($wid), 'workflows');
            } catch (PlanLimitReachedException $e) {
                return response()->json(['message' => $e->getMessage(), 'code' => 'PLAN_LIMIT'], 403);
            }
            $w = Workflow::create(['workspace_id' => $wid, 'created_by' => $request->user()->id, 'name' => $d['name'], 'description' => $d['description'] ?? null, 'status' => 'draft']);
        }

        DB::transaction(function () use ($w, $d) {
            $w->workflowEdges()->delete();
            $w->workflowNodes()->delete();
            $trig = WorkflowNode::create(['workflow_id' => $w->id, 'type' => 'trigger', 'subtype' => $d['trigger']['subtype'],
                'config' => $d['trigger']['config'] ?? [], 'position_x' => 0, 'position_y' => 0]);
            $prev = $trig->id;
            $saved = [];
            foreach ($d['nodes'] as $i => $n) {
                $allowed = self::ALLOWED[$n['subtype']] ?? [];
                $cfg = $allowed ? array_intersect_key($n['config'] ?? [], array_flip($allowed)) : [];
                $node = WorkflowNode::create(['workflow_id' => $w->id, 'type' => $n['type'], 'subtype' => $n['subtype'], 'config' => $cfg, 'position_x' => 0, 'position_y' => ($i + 1) * 100]);
                WorkflowEdge::create(['workflow_id' => $w->id, 'from_node_id' => $prev, 'to_node_id' => $node->id, 'label' => 'default']);
                $prev = $node->id;
                $saved[] = ['type' => $n['type'], 'subtype' => $n['subtype'], 'config' => $cfg];
            }
            $w->update(['canvas_data' => ['trigger' => $d['trigger']['subtype'], 'trigger_config' => $d['trigger']['config'] ?? [], 'nodes' => $saved]]);
            if ($d['trigger']['subtype'] === 'webhook_received' && !$w->webhook_token) {
                $w->update(['webhook_token' => Str::random(40)]);
            }
        });

        return response()->json(['data' => ['id' => $w->id]], $id > 0 ? 200 : 201);
    }

    /** GET workflows/{id}/executions */
    public function executions(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $w = Workflow::where('workspace_id', $this->wid($request))->findOrFail($id);
        $p = DB::table('workflow_executions')->where('workflow_id', $w->id)->orderByDesc('id')->paginate(min((int) $request->input('per_page', 25), 100));
        $contacts = DB::table('contacts')->whereIn('id', collect($p->items())->pluck('contact_id')->filter())->pluck(DB::raw("TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')))"), 'id');
        $rows = collect($p->items())->map(fn ($e) => ['id' => $e->id, 'status' => $e->status, 'contact_id' => $e->contact_id,
            'contact_name' => $contacts[$e->contact_id] ?? null, 'started_at' => $e->started_at, 'completed_at' => $e->completed_at]);
        return response()->json(['data' => $rows, 'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }

    /** GET workflow-executions/{id} → per-step log */
    public function execution(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $ex = DB::table('workflow_executions')->find($id);
        if (!$ex || !Workflow::where('workspace_id', $this->wid($request))->where('id', $ex->workflow_id)->exists()) {
            return response()->json(['message' => 'Execution not found.'], 404);
        }
        $nodes = DB::table('workflow_nodes')->where('workflow_id', $ex->workflow_id)->get(['id', 'type', 'subtype'])->keyBy('id');
        $logs = DB::table('workflow_step_logs')->where('execution_id', $id)->orderBy('id')->get()->map(fn ($l) => [
            'id' => $l->id, 'status' => $l->status, 'step' => ($nodes[$l->node_id]->subtype ?? null), 'type' => ($nodes[$l->node_id]->type ?? null),
            'error_message' => $l->error_message, 'duration_ms' => $l->duration_ms, 'executed_at' => $l->executed_at]);
        return response()->json(['data' => ['id' => $ex->id, 'status' => $ex->status, 'started_at' => $ex->started_at, 'completed_at' => $ex->completed_at, 'steps' => $logs]]);
    }

    // ═════════════════════════ Campaigns ═════════════════════════

    /** GET campaign-audiences → lists, segments, tags to pick from */
    public function audiences(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $wid = $this->wid($request);
        return response()->json(['data' => [
            'lists'    => ContactList::where('workspace_id', $wid)->orderBy('name')->get(['id', 'name', 'contacts_count']),
            'segments' => Segment::where('workspace_id', $wid)->orderBy('name')->get(['id', 'name', 'contacts_count']),
            'tags'     => Tag::where('workspace_id', $wid)->orderBy('name')->get(['id', 'name', 'color']),
        ]]);
    }

    /** GET campaigns/{id}/report */
    public function report(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $c = Campaign::where('workspace_id', $this->wid($request))->findOrFail($id);

        $links = CampaignLink::where('campaign_id', $c->id)->orderByDesc('clicks_count')->limit(20)->get(['id', 'original_url', 'clicks_count']);
        $counts = CampaignRecipient::where('campaign_id', $c->id)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $sent = (int) $c->sent_count;

        return response()->json(['data' => [
            'id' => $c->id, 'name' => $c->name, 'subject' => $c->subject, 'status' => $c->status, 'type' => $c->type,
            'sent_at' => $c->sent_at?->toIso8601String() ?? null,
            'stats' => [
                'recipients' => (int) $c->recipients_count, 'sent' => $sent, 'delivered' => (int) $c->delivered_count,
                'opened' => (int) $c->opened_count, 'clicked' => (int) $c->clicked_count, 'bounced' => (int) $c->bounced_count,
                'unsubscribed' => (int) $c->unsubscribed_count,
                'open_rate' => $sent ? round($c->opened_count / $sent * 100, 1) : 0,
                'click_rate' => $sent ? round($c->clicked_count / $sent * 100, 1) : 0,
            ],
            'by_status' => $counts, 'top_links' => $links,
        ]]);
    }

    /**
     * POST campaign-save / PUT campaign-save/{id}
     * Replacement for the stock POST/PUT /campaigns: the stock validator accepts "broadcast" but the
     * database enum is regular|ab_test|drip, so creating a broadcast campaign fails on MySQL.
     */
    public function saveCampaign(Request $request, ?int $id = null): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, $id ? 'interact' : 'create')) return $deny;
        $wid = (int) $request->user()->active_workspace_id;

        $rules = [
            'name' => ($id ? 'sometimes|' : '') . 'required|string|max:255',
            'type' => ($id ? 'sometimes|' : '') . 'required|string|in:regular,broadcast,drip,ab_test',
            'subject' => 'nullable|string|max:500',
            'body_html' => 'nullable|string',
            'preview_text' => 'nullable|string|max:255',
            'audience_type' => 'nullable|string|in:all,segment,list',
            'audience_id' => 'nullable|integer',
            'email_account_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('email_accounts', 'id')->where('workspace_id', $wid)],
            'scheduled_at' => 'nullable|date|after:now',
        ];
        $d = $request->validate($rules);
        if (($d['type'] ?? null) === 'broadcast') $d['type'] = 'regular';

        if ($id) {
            $c = Campaign::where('workspace_id', $wid)->find($id);
            if (!$c) return response()->json(['message' => 'Campaign not found.'], 404);
            if (!in_array($c->status, ['draft', 'scheduled'])) return response()->json(['message' => 'Only draft or scheduled campaigns can be edited.'], 422);
            $c->update($d);
            return response()->json(['data' => $c->fresh()], 200);
        }

        if ($deny = $this->denyUnlessPlanAllows($request, 'campaigns_per_month')) return $deny;
        $c = Campaign::create($d + ['workspace_id' => $wid, 'created_by' => $request->user()->id, 'status' => 'draft']);
        return response()->json(['data' => $c->fresh()], 201);
    }

    /** GET campaigns/{id}/content → editable fields incl. body_html (not in CampaignResource). */
    public function campaignContent(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $c = Campaign::where('workspace_id', $this->wid($request))->findOrFail($id);
        return response()->json(['data' => [
            'id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'status' => $c->status, 'subject' => $c->subject,
            'preview_text' => $c->preview_text, 'body_html' => $c->body_html, 'audience_type' => $c->audience_type,
            'audience_id' => $c->audience_id, 'email_account_id' => $c->email_account_id,
            'scheduled_at' => $c->scheduled_at?->toIso8601String(),
        ]]);
    }

    /** GET campaigns/{id}/recipients?status= */
    public function recipients(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $c = Campaign::where('workspace_id', $this->wid($request))->findOrFail($id);
        $q = CampaignRecipient::where('campaign_id', $c->id);
        if ($s = $request->input('status')) $q->where('status', $s);
        $p = $q->orderByDesc('id')->paginate(min((int) $request->input('per_page', 30), 100));
        return response()->json(['data' => $p->getCollection()->map(fn ($r) => ['id' => $r->id, 'email' => $r->email, 'phone' => $r->phone, 'status' => $r->status,
            'sent_at' => $r->sent_at?->toIso8601String(), 'opened_at' => $r->opened_at?->toIso8601String(), 'clicked_at' => $r->clicked_at?->toIso8601String(),
            'error_message' => $r->error_message])->values(),
            'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }
}
