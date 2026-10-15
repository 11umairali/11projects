<?php

namespace App\Livewire\Settings;

use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Component;

class DataPrivacySettings extends Component
{
    use AuthorizesWorkspaceActions;

    public string $conversationRetention = '90';
    public string $contactRetention = '365';
    public bool $aiTraining = true;
    public bool $analytics = true;
    public bool $thirdParty = false;

    public function mount(): void
    {
        $workspace = auth()->user()->activeWorkspace;
        if (!$workspace) return;

        $settings = $workspace->settings ?? [];
        $this->conversationRetention = (string) ($settings['conversation_retention'] ?? '90');
        $this->contactRetention = (string) ($settings['contact_retention'] ?? '365');
        $this->aiTraining = $settings['ai_training_consent'] ?? true;
        $this->analytics = $settings['analytics_consent'] ?? true;
        $this->thirdParty = $settings['third_party_sharing'] ?? false;
    }

    public function save(): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        $workspace = auth()->user()->activeWorkspace;
        $settings = $workspace->settings ?? [];

        $settings['conversation_retention'] = $this->conversationRetention;
        $settings['contact_retention'] = $this->contactRetention;
        $settings['ai_training_consent'] = $this->aiTraining;
        $settings['analytics_consent'] = $this->analytics;
        $settings['third_party_sharing'] = $this->thirdParty;

        $workspace->update(['settings' => $settings]);

        session()->flash('success', 'Privacy settings saved.');
    }

    public function render()
    {
        return view('livewire.settings.data-privacy-settings');
    }
}
