<?php
namespace App\Observers;
use App\Models\FriendRequest;
use App\Services\RealtimeUpdates;
class FriendRequestObserver
{
    public function saved(FriendRequest $request): void { $this->changed($request); }
    public function deleted(FriendRequest $request): void { $this->changed($request); }
    private function changed(FriendRequest $r): void
    {
        RealtimeUpdates::users([$r->requester_id, $r->addressee_id], ['type' => 'friends']);
    }
}
