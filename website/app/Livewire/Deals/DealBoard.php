<?php

namespace App\Livewire\Deals;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Pipeline;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Rule;
use Livewire\Component;

class DealBoard extends Component
{
    use AuthorizesWorkspaceActions;

    public ?int $pipelineId = null;
    public bool $showDealForm = false;
    public ?int $editingDealId = null;
    public ?int $preselectedStageId = null;

    /** @var string Status filter: open, won, lost, all */
    public string $statusFilter = 'open';

    /** Pipeline creation form state */
    public bool $showPipelineForm = false;
    public string $newPipelineName = '';

    /** Contact search for deal form */
    public string $contactSearch = '';

    #[Rule('required|string|max:255')]
    public string $dealTitle = '';

    #[Rule('nullable|numeric|min:0')]
    public ?float $dealValue = null;

    #[Rule('nullable|integer|exists:contacts,id')]
    public ?int $dealContactId = null;

    #[Rule('required|integer|exists:deal_stages,id')]
    public ?int $dealStageId = null;

    #[Rule('nullable|date')]
    public ?string $dealExpectedClose = null;

    public function mount(): void
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $pipeline = Pipeline::where('workspace_id', $workspaceId)
            ->where('is_default', true)
            ->first();

        if (!$pipeline) {
            $pipeline = Pipeline::where('workspace_id', $workspaceId)->first();
        }

        if (!$pipeline) {
            $pipeline = $this->createDefaultPipeline($workspaceId);
        }

        $this->pipelineId = $pipeline->id;
    }

    protected function createDefaultPipeline(int $workspaceId): Pipeline
    {
        $pipeline = Pipeline::create([
            'workspace_id' => $workspaceId,
            'name' => 'Sales Pipeline',
            'is_default' => true,
        ]);

        $this->createDefaultStages($pipeline->id);

        return $pipeline;
    }

    /**
     * Create the default set of stages for a pipeline.
     */
    protected function createDefaultStages(int $pipelineId): void
    {
        $stages = [
            ['name' => 'Lead', 'color' => '#6B7280', 'win_probability' => 10, 'sort_order' => 1],
            ['name' => 'Qualified', 'color' => '#3B82F6', 'win_probability' => 25, 'sort_order' => 2],
            ['name' => 'Proposal', 'color' => '#EAB308', 'win_probability' => 50, 'sort_order' => 3],
            ['name' => 'Negotiation', 'color' => '#F97316', 'win_probability' => 75, 'sort_order' => 4],
            ['name' => 'Won', 'color' => '#22C55E', 'win_probability' => 100, 'sort_order' => 5],
            ['name' => 'Lost', 'color' => '#EF4444', 'win_probability' => 0, 'sort_order' => 6],
        ];

        foreach ($stages as $stage) {
            DealStage::create(array_merge($stage, ['pipeline_id' => $pipelineId]));
        }
    }

    // ─── Pipeline Management ───────────────────────────────────────

    /**
     * Switch to a different pipeline.
     */
    public function switchPipeline(int $pipelineId): void
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $pipeline = Pipeline::where('workspace_id', $workspaceId)
            ->where('id', $pipelineId)
            ->first();

        if ($pipeline) {
            $this->pipelineId = $pipeline->id;
            $this->statusFilter = 'open';
        }
    }

    /**
     * Create a new pipeline with default stages and switch to it.
     */
    public function createPipeline(): void
    {
        if (!$this->authorizeWorkspaceAction('create')) return;

        $this->validate([
            'newPipelineName' => 'required|string|max:255',
        ]);

        $workspaceId = auth()->user()->active_workspace_id;

        $pipeline = Pipeline::create([
            'workspace_id' => $workspaceId,
            'name' => $this->newPipelineName,
            'is_default' => false,
        ]);

        $this->createDefaultStages($pipeline->id);

        $this->pipelineId = $pipeline->id;
        $this->closePipelineForm();
        session()->flash('success', "Pipeline \"{$pipeline->name}\" created.");
    }

    /**
     * Close the pipeline creation form and reset state.
     */
    public function closePipelineForm(): void
    {
        $this->showPipelineForm = false;
        $this->newPipelineName = '';
        $this->resetValidation('newPipelineName');
    }

    /**
     * Delete a pipeline and all its deals. Cannot delete the last pipeline.
     */
    public function deletePipeline(int $pipelineId): void
    {
        if (!$this->authorizeWorkspaceAction('dangerous')) return;

        $workspaceId = auth()->user()->active_workspace_id;
        $pipelineCount = Pipeline::where('workspace_id', $workspaceId)->count();

        if ($pipelineCount <= 1) {
            session()->flash('error', 'Cannot delete the only pipeline. Create another pipeline first.');
            return;
        }

        $pipeline = Pipeline::where('workspace_id', $workspaceId)
            ->where('id', $pipelineId)
            ->first();

        if (!$pipeline) return;

        $pipelineName = $pipeline->name;

        // Delete all deals in this pipeline
        Deal::where('pipeline_id', $pipeline->id)->delete();

        // Delete all stages
        DealStage::where('pipeline_id', $pipeline->id)->delete();

        // Delete the pipeline itself
        $pipeline->delete();

        // Switch to another pipeline if we just deleted the current one
        if ($this->pipelineId === $pipelineId) {
            $fallback = Pipeline::where('workspace_id', $workspaceId)->first();
            $this->pipelineId = $fallback?->id;
        }

        session()->flash('success', "Pipeline \"{$pipelineName}\" deleted.");
    }

    // ─── Drag & Drop ──────────────────────────────────────────────

    /**
     * Update a deal's stage via drag-and-drop.
     * Same core logic as moveDeal but without flash messages for smoother UX.
     */
    public function updateDealPosition(int $dealId, int $newStageId, ?int $position = null): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $workspaceId = auth()->user()->active_workspace_id;

        // Verify stage belongs to this pipeline
        $stage = DealStage::where('id', $newStageId)
            ->where('pipeline_id', $this->pipelineId)
            ->first();

        if (!$stage) return;

        // Atomic update
        $affected = Deal::where('id', $dealId)
            ->where('workspace_id', $workspaceId)
            ->where('pipeline_id', $this->pipelineId)
            ->update(['deal_stage_id' => $newStageId, 'updated_at' => now()]);

        if ($affected === 0) return;

        // Handle Won/Lost status transitions
        $deal = Deal::where('workspace_id', $workspaceId)->find($dealId);
        if (!$deal) return;

        if ($stage->name === 'Won') {
            $deal->markAsWon();
        } elseif ($stage->name === 'Lost') {
            $deal->markAsLost();
        } else {
            // Re-opening a previously won/lost deal
            if ($deal->status !== 'open') {
                $deal->update([
                    'status' => 'open',
                    'won_at' => null,
                    'lost_at' => null,
                    'lost_reason' => null,
                ]);
            }
        }
    }

    // ─── Deal CRUD ─────────────────────────────────────────────────

    public function openDealForm(?int $dealId = null, ?int $stageId = null): void
    {
        $this->editingDealId = $dealId;
        $this->preselectedStageId = $stageId;

        if ($dealId) {
            $deal = Deal::where('workspace_id', auth()->user()->active_workspace_id)
                ->findOrFail($dealId);
            $this->dealTitle = $deal->title;
            $this->dealValue = (float) $deal->value;
            $this->dealContactId = $deal->contact_id;
            $this->dealStageId = $deal->deal_stage_id;
            $this->dealExpectedClose = $deal->expected_close_date?->format('Y-m-d');
        } else {
            $this->dealTitle = '';
            $this->dealValue = null;
            $this->dealContactId = null;
            $this->dealStageId = $stageId;
            $this->dealExpectedClose = null;
        }

        $this->showDealForm = true;
    }

    public function closeDealForm(): void
    {
        $this->showDealForm = false;
        $this->editingDealId = null;
        $this->resetValidation();
    }

    public function saveDeal(): void
    {
        if (!$this->authorizeWorkspaceAction('create')) return;

        $this->withOperationLock('save-deal-' . auth()->id(), function () {
            $this->validate();
            $workspaceId = auth()->user()->active_workspace_id;

            // Validate contact belongs to workspace if provided
            if ($this->dealContactId) {
                $contactExists = Contact::where('workspace_id', $workspaceId)
                    ->where('id', $this->dealContactId)
                    ->exists();

                if (!$contactExists) {
                    $this->addError('dealContactId', 'Selected contact does not belong to this workspace.');
                    return;
                }
            }

            // Validate stage belongs to the pipeline
            $stageExists = DealStage::where('id', $this->dealStageId)
                ->where('pipeline_id', $this->pipelineId)
                ->exists();

            if (!$stageExists) {
                $this->addError('dealStageId', 'Invalid stage for this pipeline.');
                return;
            }

            $data = [
                'title' => $this->dealTitle,
                'value' => $this->dealValue ?? 0,
                'contact_id' => $this->dealContactId,
                'deal_stage_id' => $this->dealStageId,
                'expected_close_date' => $this->dealExpectedClose,
            ];

            if ($this->editingDealId) {
                Deal::where('workspace_id', $workspaceId)
                    ->where('id', $this->editingDealId)
                    ->update($data);
                session()->flash('success', 'Deal updated.');
            } else {
                Deal::create(array_merge($data, [
                    'workspace_id' => $workspaceId,
                    'pipeline_id' => $this->pipelineId,
                    'assigned_to' => auth()->id(),
                    'status' => 'open',
                    'currency' => 'USD',
                ]));
                session()->flash('success', 'Deal created.');
            }

            $this->closeDealForm();
        });
    }

    public function moveDeal(int $dealId, int $stageId): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $workspaceId = auth()->user()->active_workspace_id;

        // Verify stage belongs to the same pipeline (prevents cross-pipeline injection)
        $stage = DealStage::where('id', $stageId)
            ->where('pipeline_id', $this->pipelineId)
            ->first();

        if (!$stage) {
            session()->flash('error', 'Invalid stage for this pipeline.');
            return;
        }

        // Atomic update to prevent race condition
        $affected = Deal::where('id', $dealId)
            ->where('workspace_id', $workspaceId)
            ->where('pipeline_id', $this->pipelineId)
            ->update(['deal_stage_id' => $stageId, 'updated_at' => now()]);

        if ($affected === 0) {
            session()->flash('error', 'Deal was modified by another user or not found. Please refresh.');
            return;
        }

        // Load deal fresh for status updates
        $deal = Deal::where('workspace_id', $workspaceId)->find($dealId);
        if (!$deal) return;

        if ($stage->name === 'Won') {
            $deal->markAsWon();
            session()->flash('success', "Deal \"{$deal->title}\" marked as Won!");
        } elseif ($stage->name === 'Lost') {
            $deal->markAsLost();
            session()->flash('success', "Deal \"{$deal->title}\" marked as Lost.");
        } else {
            if ($deal->status !== 'open') {
                $deal->update(['status' => 'open', 'won_at' => null, 'lost_at' => null, 'lost_reason' => null]);
            }
            session()->flash('success', "Deal moved to {$stage->name}.");
        }
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
    }

    /**
     * Search contacts for the deal form dropdown, limited to 20 results.
     */
    public function searchContacts(): array
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $query = Contact::where('workspace_id', $workspaceId);

        if ($this->contactSearch) {
            $term = '%' . $this->contactSearch . '%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('company', 'like', $term);
            });
        }

        return $query->orderBy('first_name')->limit(20)->get()->toArray();
    }

    // ─── Render ────────────────────────────────────────────────────

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $pipeline = Pipeline::where('workspace_id', $workspaceId)
            ->with(['dealStages' => function ($q) {
                $q->orderBy('sort_order');
            }])->find($this->pipelineId);

        $stages = $pipeline ? $pipeline->dealStages : collect();

        // All pipelines for the workspace (for the pipeline selector)
        $pipelines = Pipeline::where('workspace_id', $workspaceId)
            ->withCount('deals')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        // Build deals query with status filter
        $dealsQuery = Deal::where('workspace_id', $workspaceId)
            ->where('pipeline_id', $this->pipelineId)
            ->with(['contact', 'assignedTo', 'dealStage']);

        if ($this->statusFilter !== 'all') {
            $dealsQuery->where('status', $this->statusFilter);
        }

        $deals = $dealsQuery->get()->groupBy('deal_stage_id');

        // Status counts (unfiltered) for the tab badges
        $statusCounts = Deal::where('workspace_id', $workspaceId)
            ->where('pipeline_id', $this->pipelineId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_count,
                SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_count
            ")
            ->first();

        $contactQuery = Contact::where('workspace_id', $workspaceId);

        if ($this->contactSearch) {
            $term = '%' . $this->contactSearch . '%';
            $contactQuery->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('company', 'like', $term);
            });
        }

        $contacts = $contactQuery->orderBy('first_name')->limit(50)->get();

        $totalValue = Deal::where('workspace_id', $workspaceId)
            ->where('pipeline_id', $this->pipelineId)
            ->where('status', 'open')
            ->sum('value');

        // Revenue forecast: weighted by stage position
        $pipelineForecast = ['total' => 0, 'weighted' => 0, 'open_deals' => 0];
        if ($stages->isNotEmpty()) {
            $stageCount = $stages->count();
            foreach ($stages as $index => $stage) {
                $stageDeals = $deals->get($stage->id, collect());
                $stageValue = (float) $stageDeals->sum('value');
                $pipelineForecast['total'] += $stageValue;
                $pipelineForecast['open_deals'] += $stageDeals->count();

                // Weight by stage position: first stage = low weight, last = high weight
                $weight = $stageCount > 1 ? ($index + 1) / $stageCount : 1;
                // Won/last stage = 100% weight
                if (stripos($stage->name, 'won') !== false) $weight = 1.0;
                if (stripos($stage->name, 'lost') !== false) $weight = 0;
                $pipelineForecast['weighted'] += $stageValue * $weight;
            }
            $pipelineForecast['weighted'] = round($pipelineForecast['weighted'], 2);
        }

        return view('livewire.deals.deal-board', [
            'pipeline' => $pipeline,
            'pipelines' => $pipelines,
            'stages' => $stages,
            'deals' => $deals,
            'contacts' => $contacts,
            'totalValue' => $totalValue,
            'statusCounts' => $statusCounts,
            'pipelineForecast' => $pipelineForecast,
        ]);
    }
}
