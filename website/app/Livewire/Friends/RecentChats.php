<?php

namespace App\Livewire\Friends;

use App\Services\Friends\FriendChatService;
use App\Support\FriendSettings;
use Livewire\Attributes\On;
use Livewire\Component;

class RecentChats extends Component
{
    #[On('realtime-refresh')]
    #[On('friends-updated')]
    public function refreshChats(): void
    {
        // Rendering reads fresh state for this authenticated viewer.
    }

    public function render()
    {
        $enabled = FriendSettings::chatEnabled();

        return view('livewire.friends.recent-chats', [
            'enabled' => $enabled,
            'recentChats' => $enabled ? app(FriendChatService::class)->recent(auth()->user(), 30) : [],
        ]);
    }
}
