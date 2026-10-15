<?php

namespace App\Livewire\Settings;

use App\Models\Workspace;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class WorkspaceSettings extends Component
{
    use AuthorizesWorkspaceActions;
    use WithFileUploads;

    public string $name = '';
    public string $slug = '';
    public string $industry = '';
    public string $timezone = '';
    public $logo = null;
    public ?string $currentLogoUrl = null;

    // Delete confirmation
    public string $deleteConfirmation = '';
    public string $deletePassword = '';
    public bool $showDeleteConfirm = false;

    /**
     * Reserved slugs that cannot be used for workspaces.
     */
    protected const RESERVED_SLUGS = [
        'admin', 'api', 'auth', 'login', 'register', 'dashboard', 'settings',
        'app', 'www', 'mail', 'ftp', 'smtp', 'imap', 'help', 'support',
        'billing', 'onboarding', 'invite', 'webhook', 'webhooks',
    ];

    public function mount(): void
    {
        $workspace = Workspace::findOrFail(auth()->user()->active_workspace_id);

        $this->name = $workspace->name ?? '';
        $this->slug = $workspace->slug ?? '';
        $this->industry = $workspace->industry ?? '';
        $this->timezone = $workspace->timezone ?? 'UTC';

        if ($workspace->logo_path) {
            $this->currentLogoUrl = Storage::url($workspace->logo_path);
        }
    }

    public function updatedLogo(): void
    {
        $this->validate([
            'logo' => 'image|max:2048|mimes:jpg,jpeg,png,svg',
        ]);
    }

    public function save(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                function ($attribute, $value, $fail) {
                    // Reject reserved slugs
                    if (in_array(strtolower($value), self::RESERVED_SLUGS)) {
                        $fail('This slug is reserved and cannot be used.');
                        return;
                    }

                    $exists = Workspace::where('slug', $value)
                        ->where('id', '!=', auth()->user()->active_workspace_id)
                        ->exists();
                    if ($exists) {
                        $fail('This slug is already taken.');
                    }
                },
            ],
            'industry' => 'nullable|string|max:100',
            'timezone' => 'required|string|max:50',
            'logo' => 'nullable|image|max:2048|mimes:jpg,jpeg,png,svg',
        ]);

        $workspace = Workspace::findOrFail(auth()->user()->active_workspace_id);

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'industry' => $this->industry,
            'timezone' => $this->timezone,
        ];

        // Handle logo upload
        if ($this->logo) {
            if ($workspace->logo_path && Storage::disk('public')->exists($workspace->logo_path)) {
                Storage::disk('public')->delete($workspace->logo_path);
            }

            $path = $this->logo->store('workspace-logos', 'public');
            $data['logo_path'] = $path;
            $this->currentLogoUrl = Storage::url($path);
            $this->logo = null;
        }

        $workspace->update($data);

        session()->flash('success', 'Workspace settings saved.');
    }

    public function showDeleteWorkspace(): void
    {
        if (! $this->authorizeWorkspaceAction('dangerous')) {
            return;
        }

        $this->showDeleteConfirm = true;
        $this->deleteConfirmation = '';
        $this->deletePassword = '';
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deleteConfirmation = '';
        $this->deletePassword = '';
    }

    public function deleteWorkspace(): void
    {
        if (! $this->authorizeWorkspaceAction('dangerous')) {
            return;
        }

        $workspace = Workspace::findOrFail(auth()->user()->active_workspace_id);

        // Verify confirmation matches workspace name
        if ($this->deleteConfirmation !== $workspace->name) {
            $this->addError('deleteConfirmation', 'Workspace name does not match. Please type the exact name.');
            return;
        }

        // Require password confirmation for this destructive action
        if (! Hash::check($this->deletePassword, auth()->user()->password)) {
            $this->addError('deletePassword', 'Incorrect password. Please confirm your account password.');
            return;
        }

        // Delete logo
        if ($workspace->logo_path && Storage::disk('public')->exists($workspace->logo_path)) {
            Storage::disk('public')->delete($workspace->logo_path);
        }

        // Clear active workspace for all members
        $memberIds = $workspace->members()->pluck('users.id');
        \App\Models\User::whereIn('id', $memberIds)
            ->where('active_workspace_id', $workspace->id)
            ->update(['active_workspace_id' => null]);

        // Soft delete workspace (cascading deletes handle related data)
        $workspace->delete();

        // Switch to another workspace if available, otherwise onboarding
        $user = auth()->user();
        $nextWorkspace = $user->workspaces()->first();

        if ($nextWorkspace) {
            $user->update(['active_workspace_id' => $nextWorkspace->id]);
            $this->redirect(url('/dashboard'), navigate: true);
        } else {
            $this->redirect(url('/onboarding/step-1'), navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.settings.workspace-settings');
    }
}
