<?php

namespace App\Livewire\Deals;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Pipeline;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class DealPipeline extends Component
{
    use AuthorizesWorkspaceActions;

    public ?int $activePipelineId = null;
    public ?int $expandedDealId = null;

    // Inline add deal
    public ?int $addingToStageId = null;
    public string $newDealTitle = '';
    public string $newDealValue = '0';
    public ?int $newDealContactId = null;

    // Filters
    public string $statusFilter = 'open';

    // Pagination per stage
    public int $dealsPerStage = 50;

    public function mount(): void
    {
        if (!$this->authorizeWorkspaceAction('view')) return;

        $workspaceId = auth()->user()->active_workspace_id;
        $pipeline = Pipeline::where('workspace_id', $workspaceId)
            ->where('is_default', true)
            ->first();

        if (!$pipeline) {
            $pipeline = Pipeline::where('workspace_id', $workspaceId)->first();
        }

        $this->activePipelineId = $pipeline?->id;
    }

    #[Computed]
    public function pipelines()
    {
        return Pipeline::where('workspace_id', auth()->user()->active_workspace_id)->get();
    }

    /**
     * Create a default pipeline with standard sales stages.
     */
    public function createNewPipeline(string $name): void
    {
        if (!$this->authorizeWorkspaceAction('create')) return;

        $name = trim($name);
        if (!$name || strlen($name) > 255) return;

        $workspaceId = auth()->user()->active_workspace_id;

        $pipeline = Pipeline::create([
            'workspace_id' => $workspaceId,
            'name' => $name,
            'is_default' => false,
        ]);

        $stages = [
            ['name' => 'Lead', 'color' => '#6366F1', 'sort_order' => 0],
            ['name' => 'Qualified', 'color' => '#3B82F6', 'sort_order' => 1],
            ['name' => 'Proposal', 'color' => '#F59E0B', 'sort_order' => 2],
            ['name' => 'Negotiation', 'color' => '#F97316', 'sort_order' => 3],
            ['name' => 'Won', 'color' => '#10B981', 'sort_order' => 4],
            ['name' => 'Lost', 'color' => '#EF4444', 'sort_order' => 5],
        ];

        foreach ($stages as $stage) {
            DealStage::create(array_merge($stage, ['pipeline_id' => $pipeline->id]));
        }

        $this->activePipelineId = $pipeline->id;
        unset($this->pipelines, $this->stages);
        session()->flash('success', "Pipeline \"{$name}\" created.");
    }

    public function createDefaultPipeline(): void
    {
        if (!$this->authorizeWorkspaceAction('create')) return;

        $workspaceId = auth()->user()->active_workspace_id;

        $pipeline = Pipeline::create([
            'workspace_id' => $workspaceId,
            'name' => 'Sales Pipeline',
            'is_default' => true,
        ]);

        $stages = [
            ['name' => 'Lead', 'color' => '#6366F1', 'sort_order' => 0],
            ['name' => 'Qualified', 'color' => '#3B82F6', 'sort_order' => 1],
            ['name' => 'Proposal', 'color' => '#F59E0B', 'sort_order' => 2],
            ['name' => 'Negotiation', 'color' => '#F97316', 'sort_order' => 3],
            ['name' => 'Won', 'color' => '#10B981', 'sort_order' => 4],
        ];

        foreach ($stages as $stage) {
            DealStage::create(array_merge($stage, ['pipeline_id' => $pipeline->id]));
        }

        $this->activePipelineId = $pipeline->id;
        unset($this->pipelines, $this->stages);
        session()->flash('success', 'Sales pipeline created with default stages.');
    }

    #[Computed]
    public function stages()
    {
        if (!$this->activePipelineId) {
            return collect();
        }

        $workspaceId = auth()->user()->active_workspace_id;

        // Verify pipeline belongs to this workspace
        $pipelineExists = Pipeline::where('id', $this->activePipelineId)
            ->where('workspace_id', $workspaceId)
            ->exists();

        if (!$pipelineExists) {
            $this->activePipelineId = null;
            return collect();
        }

        // Load all stages first
        $stages = DealStage::where('pipeline_id', $this->activePipelineId)
            ->orderBy('sort_order')
            ->get();

        // Step 1: Get aggregates per stage (counts + totals) in ONE query — no full load
        $stageIds = $stages->pluck('id');
        $aggregates = Deal::where('workspace_id', $workspaceId)
            ->whereIn('deal_stage_id', $stageIds)
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->groupBy('deal_stage_id')
            ->selectRaw('deal_stage_id, COUNT(*) as deal_count, COALESCE(SUM(value), 0) as total_value')
            ->get()
            ->keyBy('deal_stage_id');

        // Step 2: Load only limited deals per stage (N small queries, N = number of stages, typically 4-8)
        $limitedDeals = collect();
        foreach ($stageIds as $stageId) {
            $stageDeals = Deal::where('workspace_id', $workspaceId)
                ->where('deal_stage_id', $stageId)
                ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
                ->with(['contact', 'assignedTo'])
                ->orderBy('updated_at', 'desc')
                ->limit($this->dealsPerStage)
                ->get();
            $limitedDeals[$stageId] = $stageDeals;
        }

        // Step 3: Attach aggregates and limited deals to stages
        return $stages->map(function ($stage) use ($aggregates, $limitedDeals) {
            $agg = $aggregates->get($stage->id);
            $stage->deal_count = $agg?->deal_count ?? 0;
            $stage->total_value = $agg?->total_value ?? 0;
            $stage->loaded_deals = $limitedDeals->get($stage->id, collect());
            return $stage;
        });
    }

    #[Computed]
    public function pipelineForecast(): array
    {
        $stages = $this->stages;
        if ($stages->isEmpty()) return ['total' => 0, 'weighted' => 0];

        $total = 0;
        $weighted = 0;
        $stageCount = $stages->count();

        foreach ($stages as $index => $stage) {
            $stageValue = (float) $stage->total_value;
            $total += $stageValue;
            // Weight by stage position: first stage = low weight, last = high weight
            $weight = $stageCount > 1 ? ($index + 1) / $stageCount : 1;
            // Won/last stage = 100% weight
            if (stripos($stage->name, 'won') !== false) $weight = 1.0;
            if (stripos($stage->name, 'lost') !== false) { $weight = 0; }
            $weighted += $stageValue * $weight;
        }

        return ['total' => $total, 'weighted' => round($weighted, 2)];
    }

    #[Computed]
    public function contacts()
    {
        return Contact::where('workspace_id', auth()->user()->active_workspace_id)
            ->orderBy('first_name')
            ->limit(200)
            ->get(['id', 'first_name', 'last_name', 'company']);
    }

    public function switchPipeline(int $pipelineId): void
    {
        $pipeline = Pipeline::where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($pipelineId);

        $this->activePipelineId = $pipeline->id;
        unset($this->stages);
    }

    public function startAddDeal(int $stageId): void
    {
        $this->addingToStageId = $stageId;
        $this->newDealTitle = '';
        $this->newDealValue = '0';
        $this->newDealContactId = null;
    }

    public function cancelAddDeal(): void
    {
        $this->addingToStageId = null;
        $this->newDealTitle = '';
        $this->newDealValue = '0';
        $this->newDealContactId = null;
    }

    public function createDeal(): void
    {
        if (!$this->authorizeWorkspaceAction('create')) return;

        $this->validate([
            'newDealTitle' => 'required|string|max:255',
            'newDealValue' => 'required|numeric|min:0',
            'newDealContactId' => 'nullable|integer|exists:contacts,id',
            'addingToStageId' => 'required|integer|exists:deal_stages,id',
        ]);

        $workspaceId = auth()->user()->active_workspace_id;

        // Verify contact belongs to workspace if provided
        if ($this->newDealContactId) {
            $contactExists = Contact::where('workspace_id', $workspaceId)
                ->where('id', $this->newDealContactId)
                ->exists();
            if (!$contactExists) {
                $this->addError('newDealContactId', 'Contact not found in this workspace.');
                return;
            }
        }

        // Verify stage belongs to active pipeline in this workspace
        $stage = DealStage::where('id', $this->addingToStageId)
            ->where('pipeline_id', $this->activePipelineId)
            ->firstOrFail();

        Deal::create([
            'workspace_id' => $workspaceId,
            'pipeline_id' => $this->activePipelineId,
            'deal_stage_id' => $this->addingToStageId,
            'title' => $this->newDealTitle,
            'value' => $this->newDealValue,
            'contact_id' => $this->newDealContactId,
            'assigned_to' => auth()->id(),
            'status' => 'open',
        ]);

        $this->cancelAddDeal();
        unset($this->stages);
        session()->flash('success', 'Deal created.');
    }

    public function updateDealStage(int $dealId, int $newStageId): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $workspaceId = auth()->user()->active_workspace_id;

        // Verify new stage belongs to same pipeline
        $stage = DealStage::where('pipeline_id', $this->activePipelineId)
            ->where('id', $newStageId)
            ->first();

        if (!$stage) {
            session()->flash('error', 'Invalid stage.');
            return;
        }

        // Atomic update to prevent race condition
        $affected = Deal::where('workspace_id', $workspaceId)
            ->where('id', $dealId)
            ->update(['deal_stage_id' => $newStageId, 'updated_at' => now()]);

        if ($affected === 0) {
            session()->flash('error', 'Deal was modified by another user. Please refresh.');
            return;
        }

        unset($this->stages);
        session()->flash('success', "Deal moved to {$stage->name}.");
    }

    public function markAsWon(int $dealId): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $deal = Deal::where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($dealId);
        $deal->markAsWon();
        $this->expandedDealId = null;
        unset($this->stages);
        session()->flash('success', "Deal \"{$deal->title}\" marked as Won!");
    }

    public function markAsLost(int $dealId, string $reason = ''): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $deal = Deal::where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($dealId);
        $deal->markAsLost($reason ?: null);
        $this->expandedDealId = null;
        unset($this->stages);
        session()->flash('success', "Deal \"{$deal->title}\" marked as Lost.");
    }

    public function deleteDeal(int $dealId): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $deal = Deal::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $dealId)
            ->first();

        if ($deal) {
            $deal->delete();
            session()->flash('success', "Deal \"{$deal->title}\" deleted.");
        }

        $this->expandedDealId = null;
        unset($this->stages);
    }

    public function expandDeal(?int $dealId): void
    {
        $this->expandedDealId = $this->expandedDealId === $dealId ? null : $dealId;
    }

    #[Computed]
    public function expandedDeal()
    {
        if (!$this->expandedDealId) {
            return null;
        }

        return Deal::where('workspace_id', auth()->user()->active_workspace_id)
            ->with(['contact', 'assignedTo', 'dealStage', 'pipeline'])
            ->find($this->expandedDealId);
    }

    public function render()
    {
        return view('livewire.deals.deal-pipeline');
    }
}
