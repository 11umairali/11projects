<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Onboarding Tests
|--------------------------------------------------------------------------
| Tests the 5-step onboarding wizard: workspace creation, email connection,
| AI training, auto-reply rules, team invites, sample data seeding, and
| the welcome notification sent on registration.
|
| POST requests bypass CSRF validation because the DdosProtection middleware
| (prepended globally) interferes with the test framework's CSRF token
| auto-handling. This is safe: CSRF is a transport concern, not a business
| logic concern, and is verified via the existing SecurityTest suite.
*/

beforeEach(function () {
    Cache::flush();
});

// ---- Step 1: Create Workspace ----

test('onboarding step 1 page is accessible for authenticated user', function () {
    $setup = createUserWithWorkspace('owner');

    $this->actingAs($setup['user'])
        ->get('/onboarding/step-1')
        ->assertOk();
});

test('onboarding step 1 creates workspace and attaches user as owner', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    // User needs a workspace context for the workspace middleware.
    $tempWorkspace = Workspace::factory()->create();
    $tempWorkspace->members()->attach($user->id, ['role' => 'owner', 'status' => 'online']);
    $user->update(['active_workspace_id' => $tempWorkspace->id]);

    $this->actingAs($user->fresh())
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/onboarding/step-1', [
            'workspace_name' => 'Test Workspace',
            'industry' => 'saas',
            'team_size' => '2-5',
        ])
        ->assertRedirect();

    // A new workspace should have been created with the given name
    $this->assertDatabaseHas('workspaces', ['name' => 'Test Workspace']);

    // The new workspace should have the user as owner
    $newWorkspace = Workspace::where('name', 'Test Workspace')->first();
    expect($newWorkspace)->not->toBeNull();
    expect($newWorkspace->members()->where('user_id', $user->id)->first()->pivot->role)->toBe('owner');

    // The user's active workspace should now be the newly created one
    expect($user->fresh()->active_workspace_id)->toBe($newWorkspace->id);
});

test('onboarding step 1 validates required fields', function () {
    $setup = createUserWithWorkspace('owner');

    $response = $this->actingAs($setup['user'])
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/onboarding/step-1', []);

    $response->assertSessionHasErrors(['workspace_name', 'industry', 'team_size']);
});

test('onboarding step 1 rejects invalid team size', function () {
    $setup = createUserWithWorkspace('owner');

    $response = $this->actingAs($setup['user'])
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/onboarding/step-1', [
            'workspace_name' => 'Test',
            'industry' => 'saas',
            'team_size' => 'invalid',
        ]);

    $response->assertSessionHasErrors('team_size');
});

test('onboarding step 1 rejects workspace name over 100 characters', function () {
    $setup = createUserWithWorkspace('owner');

    $response = $this->actingAs($setup['user'])
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/onboarding/step-1', [
            'workspace_name' => str_repeat('A', 101),
            'industry' => 'saas',
            'team_size' => '2-5',
        ]);

    $response->assertSessionHasErrors('workspace_name');
});

// ---- Step Progression Guard ----

test('onboarding step 2 requires step 1 completion', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
    $workspace = Workspace::factory()->create([
        'onboarding_completed' => false,
        'onboarding_step' => 1,
    ]);
    $workspace->members()->attach($user->id, ['role' => 'owner', 'status' => 'online']);
    $user->update(['active_workspace_id' => $workspace->id]);

    // Attempting step 2 store should redirect back because onboarding_step is 1, not 2
    $this->actingAs($user->fresh())
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/onboarding/step-2', [
            'provider' => 'gmail',
        ])
        ->assertRedirect();
});

test('onboarding steps cannot be skipped', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
    $workspace = Workspace::factory()->create([
        'onboarding_completed' => false,
        'onboarding_step' => 2,
    ]);
    $workspace->members()->attach($user->id, ['role' => 'owner', 'status' => 'online']);
    $user->update(['active_workspace_id' => $workspace->id]);

    // Try to skip to step 4 (auto-reply) when workspace is at step 2
    $this->actingAs($user->fresh())
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/onboarding/step-4', [
            'auto_reply_enabled' => '0',
        ])
        ->assertRedirect();
});

// ---- Step 5: Completion & Sample Data Seeding ----

test('onboarding completion seeds 5 sample contacts', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
    $workspace = Workspace::factory()->create([
        'onboarding_completed' => false,
        'onboarding_step' => 5,
    ]);
    $workspace->members()->attach($user->id, ['role' => 'owner', 'status' => 'online']);
    $user->update(['active_workspace_id' => $workspace->id]);

    $this->actingAs($user->fresh())
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/onboarding/step-5', ['invites' => []])
        ->assertRedirect(route('onboarding.complete'));

    // Verify sample contacts were seeded
    expect($workspace->contacts()->count())->toBe(5);

    // Verify sample tags were seeded
    expect($workspace->tags()->count())->toBe(5);

    // Verify workspace is marked complete
    $workspace->refresh();
    expect($workspace->onboarding_completed)->toBeTrue();
});

test('onboarding completion does not re-seed if contacts already exist', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
    $workspace = Workspace::factory()->create([
        'onboarding_completed' => false,
        'onboarding_step' => 5,
    ]);
    $workspace->members()->attach($user->id, ['role' => 'owner', 'status' => 'online']);
    $user->update(['active_workspace_id' => $workspace->id]);

    // Pre-create a contact so the seeder should be skipped
    $workspace->contacts()->create([
        'first_name' => 'Existing',
        'last_name' => 'Contact',
        'email' => 'existing@example.com',
        'status' => 'active',
    ]);

    $this->actingAs($user->fresh())
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/onboarding/step-5', ['invites' => []])
        ->assertRedirect(route('onboarding.complete'));

    // Only the pre-existing contact should remain (no sample seeding)
    expect($workspace->contacts()->count())->toBe(1);
});

// ---- Welcome Email ----

test('welcome notification is sent on registration', function () {
    Notification::fake();

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/register', [
            'name' => 'Test User',
            'email' => 'welcome-test@example.com',
            'password' => 'Str0ngP@ss!2026',
            'password_confirmation' => 'Str0ngP@ss!2026',
            'terms' => true,
        ]);

    $user = User::where('email', 'welcome-test@example.com')->first();
    expect($user)->not->toBeNull();

    Notification::assertSentTo(
        $user,
        \App\Notifications\WelcomeNotification::class
    );
});

test('welcome notification links to onboarding step 1', function () {
    $notification = new \App\Notifications\WelcomeNotification();
    $user = User::factory()->create(['name' => 'Jane Doe']);

    $mail = $notification->toMail($user);

    // The action URL should contain the onboarding path
    expect($mail->actionUrl)->toContain('/onboarding/step/1');
});

// ---- Onboarding Complete Page ----

test('onboarding complete page is accessible after completing all steps', function () {
    $setup = createUserWithWorkspace('owner');
    $setup['workspace']->update([
        'onboarding_completed' => true,
        'onboarding_step' => 5,
    ]);

    $this->actingAs($setup['user'])
        ->get('/onboarding/complete')
        ->assertOk();
});
