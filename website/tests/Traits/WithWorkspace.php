<?php

namespace Tests\Traits;

use App\Models\User;
use App\Models\Workspace;

trait WithWorkspace
{
    /**
     * Create a verified user with an active workspace membership.
     *
     * @return array{user: User, workspace: Workspace}
     */
    protected function createUserWithWorkspace(string $role = 'owner'): array
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user->id, [
            'role' => $role,
            'status' => 'online',
            'available_for_assignment' => true,
        ]);
        $user->update(['active_workspace_id' => $workspace->id]);

        return ['user' => $user->fresh(), 'workspace' => $workspace];
    }
}
