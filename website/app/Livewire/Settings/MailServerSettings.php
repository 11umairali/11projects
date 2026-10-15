<?php

namespace App\Livewire\Settings;

use App\Support\MailServerInfo;
use Livewire\Component;

class MailServerSettings extends Component
{
    public array $info = [];

    public function mount(): void
    {
        $this->info = MailServerInfo::for(auth()->user());
    }

    public function render()
    {
        return view('livewire.settings.mail-server-settings');
    }
}
