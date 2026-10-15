<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(Tests\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Refresh Database
|--------------------------------------------------------------------------
| Automatically migrate and reset the database before each test.
| Uses SQLite :memory: (set in phpunit.xml) for speed.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a user with an active workspace membership.
 *
 * @return array{user: \App\Models\User, workspace: \App\Models\Workspace}
 */
function createUserWithWorkspace(string $role = 'owner'): array
{
    $user = \App\Models\User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
    $workspace = \App\Models\Workspace::factory()->create();
    $workspace->members()->attach($user->id, [
        'role' => $role,
        'status' => 'online',
        'available_for_assignment' => true,
    ]);
    $user->update(['active_workspace_id' => $workspace->id]);

    return ['user' => $user->fresh(), 'workspace' => $workspace];
}

/**
 * Create the full role hierarchy in a single workspace.
 *
 * @return array{owner: \App\Models\User, admin: \App\Models\User, agent: \App\Models\User, viewer: \App\Models\User, workspace: \App\Models\Workspace}
 */
function createWorkspaceWithRoles(): array
{
    $workspace = \App\Models\Workspace::factory()->create();
    $roles = [];

    foreach (['owner', 'admin', 'agent', 'viewer'] as $role) {
        $user = \App\Models\User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $workspace->members()->attach($user->id, [
            'role' => $role,
            'status' => 'online',
        ]);
        $user->update(['active_workspace_id' => $workspace->id]);
        $roles[$role] = $user->fresh();
    }

    $roles['workspace'] = $workspace;

    return $roles;
}
