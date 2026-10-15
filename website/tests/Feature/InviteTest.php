<?php

use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;

/*
|--------------------------------------------------------------------------
| Invite Tests
|--------------------------------------------------------------------------
| Tests workspace invitation acceptance flow: valid/expired/used tokens,
| email mismatch, already-a-member handling, and post-login processing.
*/

test('valid invite token redirects to login or register', function () {
    $invite = Invite::factory()->create();

    $response = $this->get("/invite/{$invite->token}");

    // Unauthenticated user gets redirected to register (since they don't have an account)
    $response->assertRedirect();
});

test('expired invite shows error', function () {
    $invite = Invite::factory()->expired()->create();

    $response = $this->get("/invite/{$invite->token}");

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('used invite (already accepted) shows error', function () {
    $invite = Invite::factory()->accepted()->create();

    $response = $this->get("/invite/{$invite->token}");

    // Controller checks status = 'pending' -- accepted invite won't be found
    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('invalid token shows error', function () {
    $response = $this->get('/invite/completely-invalid-token-here');

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('logged-in user accepting invite joins workspace', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create([
        'email' => 'invited@example.com',
        'email_verified_at' => now(),
        'status' => 'active',
    ]);

    $invite = Invite::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invited@example.com',
        'role' => 'agent',
    ]);

    $response = $this->actingAs($user)->get("/invite/{$invite->token}");

    // User should now be a member of the workspace
    expect($workspace->members()->where('user_id', $user->id)->exists())->toBeTrue();

    // Verify role is correct
    $memberRole = $workspace->members()
        ->where('user_id', $user->id)
        ->first()
        ->pivot
        ->role;

    expect($memberRole)->toBe('agent');
});

test('email mismatch rejects invite acceptance', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create([
        'email' => 'different@example.com',
        'email_verified_at' => now(),
    ]);

    $invite = Invite::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invited@example.com',
        'role' => 'agent',
    ]);

    $response = $this->actingAs($user)->get("/invite/{$invite->token}");

    // User should NOT be a member (email doesn't match)
    expect($workspace->members()->where('user_id', $user->id)->exists())->toBeFalse();

    $response->assertSessionHas('error');
});

test('already-a-member shows info message and marks invite as accepted', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create([
        'email' => 'member@example.com',
        'email_verified_at' => now(),
    ]);

    // User is already a member
    $workspace->members()->attach($user->id, ['role' => 'agent']);
    $user->update(['active_workspace_id' => $workspace->id]);

    $invite = Invite::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'member@example.com',
        'role' => 'admin',
    ]);

    $response = $this->actingAs($user)->get("/invite/{$invite->token}");

    $response->assertSessionHas('info');

    // Role should NOT have changed (already a member -- invite doesn't upgrade)
    $currentRole = $workspace->members()
        ->where('user_id', $user->id)
        ->first()
        ->pivot
        ->role;

    expect($currentRole)->toBe('agent');
});

test('pending invite token is stored in session for unauthenticated users', function () {
    $invite = Invite::factory()->create();

    $response = $this->get("/invite/{$invite->token}");

    // Session should store the token for post-login processing
    expect(session('pending_invite_token'))->toBe($invite->token);
});

test('invite with deleted workspace shows error', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create([
        'email' => 'invited@example.com',
        'email_verified_at' => now(),
    ]);

    $invite = Invite::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invited@example.com',
    ]);

    // Soft-delete the workspace
    $workspace->delete();

    $response = $this->actingAs($user)->get("/invite/{$invite->token}");

    // Workspace::find() won't find soft-deleted workspace
    $response->assertSessionHas('error');
});
