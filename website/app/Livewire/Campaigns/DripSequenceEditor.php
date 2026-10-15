<?php

namespace App\Livewire\Campaigns;

use App\Models\Campaign;
use App\Models\DripSequence;
use App\Models\DripStep;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Component;

class DripSequenceEditor extends Component
{
    use AuthorizesWorkspaceActions;

    public int $campaignId;
    public ?int $sequenceId = null;
    public string $sequenceName = '';
    public array $steps = [];

    // Add step form
    public bool $showAddStep = false;
    public int $newDelayValue = 1;
    public string $newDelayUnit = 'days';
    public string $newActionType = 'send_email';
    public string $newActionSubject = '';
    public string $newActionBody = '';
    public string $newActionTagName = '';
    public string $newActionFieldName = '';
    public string $newActionFieldValue = '';

    // Edit step
    public ?int $editingStepIndex = null;

    protected function rules(): array
    {
        return [
            'sequenceName' => 'required|string|max:255',
            'newDelayValue' => 'required|integer|min:1|max:365',
            'newDelayUnit' => 'required|in:minutes,hours,days',
            'newActionType' => 'required|in:send_email,add_tag,remove_tag,update_field,wait_condition',
        ];
    }

    public function mount(int $campaignId): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->campaignId = $campaignId;

        $campaign = Campaign::where('workspace_id', $this->workspaceId())
            ->findOrFail($campaignId);

        $sequence = $campaign->dripSequences()->first();

        if ($sequence) {
            $this->sequenceId = $sequence->id;
            $this->sequenceName = $sequence->name;
            $this->loadSteps($sequence);
        } else {
            $this->sequenceName = $campaign->name . ' Drip Sequence';
        }
    }

    protected function loadSteps(DripSequence $sequence): void
    {
        $this->steps = $sequence->steps()
            ->orderBy('position')
            ->get()
            ->map(fn (DripStep $step) => [
                'id' => $step->id,
                'position' => $step->position,
                'delay_value' => $step->delay_value,
                'delay_unit' => $step->delay_unit,
                'action_type' => $step->action_type,
                'action_data' => $step->action_data ?? [],
            ])
            ->toArray();
    }

    public function saveSequence(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->validate(['sequenceName' => 'required|string|max:255']);

        $campaign = Campaign::where('workspace_id', $this->workspaceId())
            ->findOrFail($this->campaignId);

        if ($this->sequenceId) {
            $sequence = DripSequence::where('id', $this->sequenceId)
                ->whereHas('campaign', fn ($q) => $q->where('workspace_id', $this->workspaceId()))
                ->firstOrFail();
            $sequence->update(['name' => $this->sequenceName]);
        } else {
            $sequence = DripSequence::create([
                'campaign_id' => $this->campaignId,
                'name' => $this->sequenceName,
                'status' => 'draft',
            ]);
            $this->sequenceId = $sequence->id;
        }

        session()->flash('success', 'Drip sequence saved.');
    }

    public function toggleAddStep(): void
    {
        $this->showAddStep = ! $this->showAddStep;
        $this->resetStepForm();
    }

    public function addStep(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->validate([
            'newDelayValue' => 'required|integer|min:1|max:365',
            'newDelayUnit' => 'required|in:minutes,hours,days',
            'newActionType' => 'required|in:send_email,add_tag,remove_tag,update_field,wait_condition',
        ]);

        // Ensure sequence exists first
        if (! $this->sequenceId) {
            $this->saveSequence();
        }

        $actionData = $this->buildActionData();

        $position = count($this->steps) + 1;

        $step = DripStep::create([
            'sequence_id' => $this->sequenceId,
            'position' => $position,
            'delay_value' => $this->newDelayValue,
            'delay_unit' => $this->newDelayUnit,
            'action_type' => $this->newActionType,
            'action_data' => $actionData,
        ]);

        $this->steps[] = [
            'id' => $step->id,
            'position' => $position,
            'delay_value' => $step->delay_value,
            'delay_unit' => $step->delay_unit,
            'action_type' => $step->action_type,
            'action_data' => $step->action_data,
        ];

        $this->resetStepForm();
        $this->showAddStep = false;
        session()->flash('success', 'Step added to drip sequence.');
    }

    public function removeStep(int $index): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        if (! isset($this->steps[$index])) {
            return;
        }

        $stepId = $this->steps[$index]['id'] ?? null;

        if ($stepId) {
            DripStep::where('id', $stepId)
                ->where('sequence_id', $this->sequenceId)
                ->delete();
        }

        array_splice($this->steps, $index, 1);

        // Reorder positions
        foreach ($this->steps as $i => &$step) {
            $step['position'] = $i + 1;
            if (isset($step['id'])) {
                DripStep::where('id', $step['id'])->update(['position' => $i + 1]);
            }
        }
        unset($step);

        session()->flash('success', 'Step removed.');
    }

    public function moveStepUp(int $index): void
    {
        if ($index < 1 || ! isset($this->steps[$index])) {
            return;
        }

        [$this->steps[$index - 1], $this->steps[$index]] = [$this->steps[$index], $this->steps[$index - 1]];
        $this->reorderPositions();
    }

    public function moveStepDown(int $index): void
    {
        if ($index >= count($this->steps) - 1 || ! isset($this->steps[$index])) {
            return;
        }

        [$this->steps[$index], $this->steps[$index + 1]] = [$this->steps[$index + 1], $this->steps[$index]];
        $this->reorderPositions();
    }

    protected function reorderPositions(): void
    {
        foreach ($this->steps as $i => &$step) {
            $step['position'] = $i + 1;
            if (isset($step['id'])) {
                DripStep::where('id', $step['id'])->update(['position' => $i + 1]);
            }
        }
        unset($step);
    }

    public function activateSequence(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        if (! $this->sequenceId) {
            session()->flash('error', 'Save the sequence first before activating.');
            return;
        }

        if (empty($this->steps)) {
            session()->flash('error', 'Add at least one step before activating the drip sequence.');
            return;
        }

        DripSequence::where('id', $this->sequenceId)->update(['status' => 'active']);
        session()->flash('success', 'Drip sequence activated. Contacts will be enrolled automatically.');
    }

    public function pauseSequence(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        if ($this->sequenceId) {
            DripSequence::where('id', $this->sequenceId)->update(['status' => 'paused']);
            session()->flash('success', 'Drip sequence paused.');
        }
    }

    protected function buildActionData(): array
    {
        return match ($this->newActionType) {
            'send_email' => [
                'subject' => $this->newActionSubject,
                'body' => $this->newActionBody,
            ],
            'add_tag', 'remove_tag' => [
                'tag_name' => $this->newActionTagName,
            ],
            'update_field' => [
                'field' => $this->newActionFieldName,
                'value' => $this->newActionFieldValue,
            ],
            default => [],
        };
    }

    protected function resetStepForm(): void
    {
        $this->newDelayValue = 1;
        $this->newDelayUnit = 'days';
        $this->newActionType = 'send_email';
        $this->newActionSubject = '';
        $this->newActionBody = '';
        $this->newActionTagName = '';
        $this->newActionFieldName = '';
        $this->newActionFieldValue = '';
        $this->editingStepIndex = null;
    }

    public function getActionLabel(string $type): string
    {
        return match ($type) {
            'send_email' => 'Send Email',
            'add_tag' => 'Add Tag',
            'remove_tag' => 'Remove Tag',
            'update_field' => 'Update Contact Field',
            'wait_condition' => 'Wait for Condition',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function getDelayLabel(int $value, string $unit): string
    {
        $label = $value === 1 ? rtrim($unit, 's') : $unit;

        return "Wait {$value} {$label}";
    }

    protected function workspaceId(): ?int
    {
        return auth()->user()->active_workspace_id;
    }

    public function render()
    {
        $sequence = $this->sequenceId
            ? DripSequence::with('enrollments')->find($this->sequenceId)
            : null;

        return view('livewire.campaigns.drip-sequence-editor', [
            'sequence' => $sequence,
            'enrollmentCount' => $sequence?->enrollments()->count() ?? 0,
            'activeEnrollments' => $sequence?->enrollments()->where('status', 'active')->count() ?? 0,
        ]);
    }
}
