<?php

namespace App\Livewire\Settings;

use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Component;

class ContactSettings extends Component
{
    use AuthorizesWorkspaceActions;

    public bool $autoCreate = true;
    public bool $autoTag = true;
    public bool $autoMerge = false;

    public function mount(): void
    {
        $workspace = auth()->user()->activeWorkspace;
        if (!$workspace) return;

        $settings = $workspace->settings ?? [];
        $this->autoCreate = $settings['contact_auto_create'] ?? true;
        $this->autoTag = $settings['contact_auto_tag'] ?? true;
        $this->autoMerge = $settings['contact_auto_merge'] ?? false;
    }

    public function save(): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        $workspace = auth()->user()->activeWorkspace;
        $settings = $workspace->settings ?? [];

        $settings['contact_auto_create'] = $this->autoCreate;
        $settings['contact_auto_tag'] = $this->autoTag;
        $settings['contact_auto_merge'] = $this->autoMerge;

        $workspace->update(['settings' => $settings]);

        session()->flash('success', 'Contact settings saved.');
    }

    public function render()
    {
        return view('livewire.settings.contact-settings');
    }
}
