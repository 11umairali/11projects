<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Pipeline;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pipelines, stages and deals. Mirrors Livewire\Deals\DealBoard / DealPipeline. */
class DealsController extends Controller
{
    use AuthorizesApiActions;

    private const DEFAULT_STAGES = [
        ['name' => 'Lead',        'color' => '#6366F1', 'sort_order' => 0],
        ['name' => 'Qualified',   'color' => '#3B82F6', 'sort_order' => 1],
        ['name' => 'Proposal',    'color' => '#F59E0B', 'sort_order' => 2],
        ['name' => 'Negotiation', 'color' => '#F97316', 'sort_order' => 3],
        ['name' => 'Won',         'color' => '#10B981', 'sort_order' => 4],
        ['name' => 'Lost',        'color' => '#EF4444', 'sort_order' => 5],
    ];

    private function wid(Request $r): int { return (int) $r->user()->active_workspace_id; }

    private function dealRow(Deal $d): array
    {
        return [
            'id'                  => $d->id,
            'title'               => $d->title,
            'value'               => (float) $d->value,
            'currency'            => $d->currency,
            'status'              => $d->status,
            'pipeline_id'         => $d->pipeline_id,
            'deal_stage_id'       => $d->deal_stage_id,
            'contact_id'          => $d->contact_id,
            'contact_name'        => $d->contact?->full_name,
            'assigned_to'         => $d->assigned_to,
            'expected_close_date' => $d->expected_close_date?->toDateString(),
            'lost_reason'         => $d->lost_reason,
            'notes'               => $d->notes,
            'updated_at'          => $d->updated_at?->toIso8601String(),
        ];
    }

    // ── Pipelines ───────────────────────────────────────────────────────

    public function pipelines(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $rows = Pipeline::where('workspace_id', $this->wid($request))->with('dealStages')->orderByDesc('is_default')->orderBy('id')->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'is_default' => (bool) $p->is_default,
                'stages' => $p->dealStages->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name, 'color' => $s->color,
                    'win_probability' => $s->win_probability, 'sort_order' => $s->sort_order,
                ])->values(),
            ]);
        return response()->json(['data' => $rows]);
    }

    public function createPipeline(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'create')) return $deny;
        $d = $request->validate(['name' => 'required|string|max:255']);
        $wid = $this->wid($request);
        $first = !Pipeline::where('workspace_id', $wid)->exists();
        $p = Pipeline::create(['workspace_id' => $wid, 'name' => $d['name'], 'is_default' => $first]);
        foreach (self::DEFAULT_STAGES as $s) {
            DealStage::create($s + ['pipeline_id' => $p->id]);
        }
        return response()->json(['data' => ['id' => $p->id, 'name' => $p->name]], 201);
    }

    public function deletePipeline(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $p = Pipeline::where('workspace_id', $this->wid($request))->findOrFail($id);
        if ($p->is_default && Pipeline::where('workspace_id', $p->workspace_id)->count() > 1) {
            return response()->json(['message' => 'Set another pipeline as default before deleting this one.'], 422);
        }
        Deal::where('pipeline_id', $p->id)->delete();
        DealStage::where('pipeline_id', $p->id)->delete();
        $p->delete();
        return response()->json(['message' => 'Pipeline deleted.']);
    }

    // ── Deals ───────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $q = Deal::where('workspace_id', $this->wid($request))->with('contact');
        if ($p = $request->input('pipeline_id')) $q->where('pipeline_id', $p);
        if ($s = $request->input('status')) $q->where('status', $s);
        if ($t = $request->input('search')) $q->where('title', 'like', "%{$t}%");
        $rows = $q->orderByDesc('updated_at')->limit(500)->get()->map(fn ($d) => $this->dealRow($d));
        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'create')) return $deny;
        $wid = $this->wid($request);
        $d = $request->validate([
            'title'               => 'required|string|max:255',
            'value'               => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|size:3',
            'contact_id'          => 'nullable|integer',
            'pipeline_id'         => 'required|integer',
            'deal_stage_id'       => 'required|integer',
            'expected_close_date' => 'nullable|date',
            'notes'               => 'nullable|string|max:5000',
        ]);
        if (!Pipeline::where('workspace_id', $wid)->where('id', $d['pipeline_id'])->exists()) {
            return response()->json(['message' => 'Pipeline not found.'], 404);
        }
        if (!DealStage::where('id', $d['deal_stage_id'])->where('pipeline_id', $d['pipeline_id'])->exists()) {
            return response()->json(['message' => 'Invalid stage for this pipeline.', 'errors' => ['deal_stage_id' => ['Invalid stage.']]], 422);
        }
        if (!empty($d['contact_id']) && !Contact::where('workspace_id', $wid)->where('id', $d['contact_id'])->exists()) {
            return response()->json(['message' => 'Selected contact does not belong to this workspace.', 'errors' => ['contact_id' => ['Invalid contact.']]], 422);
        }
        $deal = Deal::create([
            'workspace_id' => $wid, 'assigned_to' => $request->user()->id, 'status' => 'open',
            'currency' => strtoupper($d['currency'] ?? 'USD'), 'value' => $d['value'] ?? 0,
        ] + collect($d)->only(['title', 'contact_id', 'pipeline_id', 'deal_stage_id', 'expected_close_date', 'notes'])->all());

        return response()->json(['data' => $this->dealRow($deal->load('contact'))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $wid = $this->wid($request);
        $deal = Deal::where('workspace_id', $wid)->findOrFail($id);
        $d = $request->validate([
            'title'               => 'sometimes|required|string|max:255',
            'value'               => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|size:3',
            'contact_id'          => 'nullable|integer',
            'expected_close_date' => 'nullable|date',
            'notes'               => 'nullable|string|max:5000',
            'assigned_to'         => 'nullable|integer',
        ]);
        if (!empty($d['contact_id']) && !Contact::where('workspace_id', $wid)->where('id', $d['contact_id'])->exists()) {
            return response()->json(['message' => 'Selected contact does not belong to this workspace.'], 422);
        }
        $deal->update($d);
        return response()->json(['data' => $this->dealRow($deal->fresh('contact'))]);
    }

    /** POST deals/{id}/move {deal_stage_id} — mirrors DealBoard::moveDeal (Won/Lost stage names flip status). */
    public function move(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $d = $request->validate(['deal_stage_id' => 'required|integer']);
        $deal = Deal::where('workspace_id', $this->wid($request))->findOrFail($id);
        $stage = DealStage::where('id', $d['deal_stage_id'])->where('pipeline_id', $deal->pipeline_id)->first();
        if (!$stage) return response()->json(['message' => 'Invalid stage for this pipeline.'], 422);

        $deal->update(['deal_stage_id' => $stage->id]);
        if ($stage->name === 'Won') {
            $deal->markAsWon();
        } elseif ($stage->name === 'Lost') {
            $deal->markAsLost();
        } elseif ($deal->status !== 'open') {
            $deal->update(['status' => 'open', 'won_at' => null, 'lost_at' => null, 'lost_reason' => null]);
        }
        return response()->json(['data' => $this->dealRow($deal->fresh('contact'))]);
    }

    public function won(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $deal = Deal::where('workspace_id', $this->wid($request))->findOrFail($id);
        $deal->markAsWon();
        return response()->json(['data' => $this->dealRow($deal->fresh('contact'))]);
    }

    public function lost(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $d = $request->validate(['reason' => 'nullable|string|max:500']);
        $deal = Deal::where('workspace_id', $this->wid($request))->findOrFail($id);
        $deal->markAsLost($d['reason'] ?? null);
        return response()->json(['data' => $this->dealRow($deal->fresh('contact'))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        Deal::where('workspace_id', $this->wid($request))->findOrFail($id)->delete();
        return response()->json(['message' => 'Deal deleted.']);
    }
}
