<?php
namespace App\Livewire\Friends;

use App\Services\Friends\ChatPreferences;
use App\Services\Friends\FriendChatService;
use App\Services\Friends\FriendService;
use Livewire\Component;

class ChatList extends Component
{
    public array $selected = [];
    public bool $newChat = false;
    public string $search = ''; 
    public bool $archived = false;
    public ?string $notice = null;

    public function toggle(int $id): void
    {
        $this->selected = in_array($id, $this->selected, true)
            ? array_values(array_diff($this->selected, [$id])) : array_slice([...$this->selected, $id], 0, 100);
    }

    public function startChat(): void { $this->newChat = true; $this->search = ''; $this->selected = []; }
    public function closePicker(): void { $this->newChat = false; $this->search = ''; }

    public function folder(bool $archived): void { $this->archived = $archived; $this->selected = []; }

    public function organize(string $action): void
    {
        $this->validate(['selected' => 'required|array|min:1|max:100', 'selected.*' => 'integer|min:1']);
        app(ChatPreferences::class)->apply(auth()->user(), $this->selected, $action);
        $this->selected = [];
        $this->notice = __('Chats updated.');
    }

    public function render()
    {
        $all = app(FriendChatService::class)->recent(auth()->user(), 200);
        $contacts = $this->newChat ? app(FriendService::class)->chatContacts(auth()->user()) : [];
        return view('livewire.friends.chat-list', [
            'contacts' => array_values(array_filter($contacts, fn ($p) => $this->search === '' || mb_stripos($p['name'], $this->search) !== false)),
            'allPinned' => count($this->selected) > 0 && count(array_filter($all, fn ($p) => in_array($p['id'], $this->selected) && $p['pinned'])) === count($this->selected),
            'allMuted' => count($this->selected) > 0 && count(array_filter($all, fn ($p) => in_array($p['id'], $this->selected) && $p['muted'])) === count($this->selected),
            'chats' => array_values(array_filter($all, fn ($p) => $p['archived'] === $this->archived && ($this->search === '' || mb_stripos($p['name'].' '.$p['preview'], $this->search) !== false))),
            'archiveCount' => count(array_filter($all, fn ($p) => $p['archived'])),
        ]);
    }
}
