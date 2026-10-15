<?php

namespace App\Livewire;

use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class WorkspaceSwitcher extends Component
{
    public bool $showCreateModal = false;
    public string $newWorkspaceName = '';
    public string $newWorkspaceIndustry = '';

    #[On('switch-workspace')]
    public function switchWorkspace(int $id): void
    {
        $user = auth()->user();

        // Verify user is a member of the target workspace
        $membership = $user->workspaces()
            ->where('workspaces.id', $id)
            ->first();

        if (! $membership) {
            session()->flash('error', 'You do not have access to that workspace.');
            return;
        }

        // Clear cached workspace context for old workspace
        Cache::forget("workspace:{$user->id}:{$user->active_workspace_id}");

        // Switch active workspace
        $user->update(['active_workspace_id' => $id]);

        // Clear cached workspace context for new workspace to force fresh load
        Cache::forget("workspace:{$user->id}:{$id}");

        $this->redirect(url('/dashboard'), navigate: true);
    }

    public function openCreateModal(): void
    {
        $this->newWorkspaceName = '';
        $this->newWorkspaceIndustry = '';
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function createWorkspace(): void
    {
        $this->validate([
            'newWorkspaceName' => 'required|string|max:100',
            'newWorkspaceIndustry' => 'nullable|string|max:50',
        ]);

        $user = auth()->user();

        $workspace = Workspace::create([
            'name' => $this->newWorkspaceName,
            'industry' => $this->newWorkspaceIndustry ?: null,
            'onboarding_step' => 2,
            'onboarding_completed' => false,
        ]);

        // Attach user as owner
        $workspace->members()->attach($user->id, [
            'role' => 'owner',
            'status' => 'online',
        ]);

        // Clear old workspace cache
        Cache::forget("workspace:{$user->id}:{$user->active_workspace_id}");

        // Switch to the new workspace
        $user->update(['active_workspace_id' => $workspace->id]);

        $this->showCreateModal = false;

        // Redirect to onboarding step 2 (connect email) for the new workspace
        $this->redirect(url('/onboarding/step-2'), navigate: true);
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.workspace-switcher', [
            'workspaces' => $user->workspaces()->withPivot('role')->get(),
            'activeWorkspace' => $user->activeWorkspace,
        ]);
    }
}
