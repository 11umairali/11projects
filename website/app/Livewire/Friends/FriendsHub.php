<?php

namespace App\Livewire\Friends;

use App\Services\Friends\FriendService;
use App\Services\Friends\PeopleService;
use App\Services\Friends\PhoneVerifier;
use Livewire\Component;

/** People: friends, teammates, requests and suggestions on one page. */
class FriendsHub extends Component
{
    public string $tab = 'all';
    public bool $compact = false;
    public int $selectedId = 0;
    public array $data = [];
    public array $phone = [];
    public ?string $notice = null;
    public bool $ok = true;

    public function mount(): void
    {
        $this->setTab((string) request()->query('tab', 'friends'));
        $this->reload();
    }

    #[\Livewire\Attributes\On('realtime-refresh')]
    public function reload(): void
    {
        $u = auth()->user();
        $this->data = app(PeopleService::class)->directory($u);
        $this->phone = app(PhoneVerifier::class)->status($u);
    }

    private function done(array $res): void
    {
        [$this->ok, $this->notice] = $res;
        $this->reload();
        $this->dispatch('friends-updated');
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['all', 'friends', 'team', 'requests', 'suggestions', 'former', 'sent', 'find'], true) ? $tab : 'all';
        $this->notice = null;
    }

    public function sendRequest(int $id): void { $this->done(app(FriendService::class)->send(auth()->user(), $id)); }
    public function respond(int $id, string $action): void { $this->done(app(FriendService::class)->respond(auth()->user(), $id, $action)); }
    public function cancel(int $id): void { $this->done(app(FriendService::class)->cancel(auth()->user(), $id)); }
    public function dismiss(int $userId): void { $this->done(app(FriendService::class)->dismiss(auth()->user(), $userId)); }
    // Removing a friend is no longer possible from this list: it is on the person's profile.

    /** friends and teammates in one alphabetical list (a person who is both appears once) */
    private function everyone(): array
    {
        $by = [];
        foreach ($this->data['friends'] ?? [] as $f) $by[$f['id']] = $f + ['is_friend' => true, 'is_team' => false];
        foreach ($this->data['team'] ?? [] as $t) $by[$t['id']] = array_merge($by[$t['id']] ?? [], $t, ['is_team' => true, 'is_friend' => ($by[$t['id']]['is_friend'] ?? false) || ($t['is_friend'] ?? false)]);
        $list = array_values($by);
        usort($list, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return $list;
    }

    public function render()
    {
        $friends = array_map(fn ($f) => $f + ['is_friend' => true, 'is_team' => false], $this->data['friends'] ?? []);
        return view('livewire.friends.friends-hub', ['everyone' => $this->everyone(), 'friendList' => $friends]);
    }
}
