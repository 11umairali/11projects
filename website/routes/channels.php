<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', fn ($user, $userId) => (int) $user->id === (int) $userId);

Broadcast::channel('workspace.{workspaceId}', function ($user, $workspaceId) {
    return $user->workspaces()->where('workspaces.id', $workspaceId)->exists();
});
