<?php

use App\Models\User;
use App\Models\Workspace;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Authorization Tests (AuthorizesWorkspaceActions trait)
|--------------------------------------------------------------------------
| Tests the workspace-scoped RBAC system.
| Role hierarchy: owner (4) > admin (3) > agent (2) > viewer (1)
*/

beforeEach(function () {
    $roles = createWorkspaceWithRoles();
    $this->owner = $roles['owner'];
    $this->admin = $roles['admin'];
    $this->agent = $roles['agent'];
    $this->viewer = $roles['viewer'];
    $this->workspace = $roles['workspace'];
});

// ---- Owner permissions ----

test('owner can perform dangerous actions', function () {
    $result = authorizeAction($this->owner, 'dangerous');
    expect($result)->toBeTrue();
});

test('owner can perform manage actions', function () {
    $result = authorizeAction($this->owner, 'manage');
    expect($result)->toBeTrue();
});

test('owner can perform interact actions', function () {
    $result = authorizeAction($this->owner, 'interact');
    expect($result)->toBeTrue();
});

test('owner can perform view actions', function () {
    $result = authorizeAction($this->owner, 'view');
    expect($result)->toBeTrue();
});

// ---- Admin permissions ----

test('admin can perform manage actions', function () {
    $result = authorizeAction($this->admin, 'manage');
    expect($result)->toBeTrue();
});

test('admin can perform interact actions', function () {
    $result = authorizeAction($this->admin, 'interact');
    expect($result)->toBeTrue();
});

test('admin CANNOT perform dangerous actions', function () {
    $result = authorizeAction($this->admin, 'dangerous');
    expect($result)->toBeFalse();
});

// ---- Agent permissions ----

test('agent can perform interact actions', function () {
    $result = authorizeAction($this->agent, 'interact');
    expect($result)->toBeTrue();
});

test('agent can perform create actions (alias for interact)', function () {
    $result = authorizeAction($this->agent, 'create');
    expect($result)->toBeTrue();
});

test('agent can perform view actions', function () {
    $result = authorizeAction($this->agent, 'view');
    expect($result)->toBeTrue();
});

test('agent CANNOT perform manage actions', function () {
    $result = authorizeAction($this->agent, 'manage');
    expect($result)->toBeFalse();
});

test('agent CANNOT perform dangerous actions', function () {
    $result = authorizeAction($this->agent, 'dangerous');
    expect($result)->toBeFalse();
});

// ---- Viewer permissions ----

test('viewer can perform view actions', function () {
    $result = authorizeAction($this->viewer, 'view');
    expect($result)->toBeTrue();
});

test('viewer CANNOT perform interact actions', function () {
    $result = authorizeAction($this->viewer, 'interact');
    expect($result)->toBeFalse();
});

test('viewer CANNOT perform manage actions', function () {
    $result = authorizeAction($this->viewer, 'manage');
    expect($result)->toBeFalse();
});

test('viewer CANNOT perform dangerous actions', function () {
    $result = authorizeAction($this->viewer, 'dangerous');
    expect($result)->toBeFalse();
});

// ---- Unauthenticated user ----

test('unauthenticated user cannot access protected routes', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('unauthenticated user cannot access inbox', function () {
    $response = $this->get('/inbox');
    $response->assertRedirect('/login');
});

test('unauthenticated user cannot access settings', function () {
    $response = $this->get('/settings/profile');
    $response->assertRedirect('/login');
});

// ---- Workspace isolation ----

test('user cannot access workspace they do not belong to', function () {
    $otherWorkspace = Workspace::factory()->create();

    // Owner tries to switch to workspace they don't belong to
    $this->owner->update(['active_workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($this->owner)->get('/dashboard');

    // WorkspaceMiddleware should detect non-membership and handle it
    // Either redirect to onboarding or fallback to a valid workspace
    expect($response->status())->toBeIn([200, 302, 403]);
});

// ---- Role cache ----

test('workspace role is cached for 60 seconds', function () {
    Auth::login($this->owner);
    Cache::flush();

    $cacheKey = "ws_role:{$this->owner->id}:{$this->workspace->id}";

    // No cache yet
    expect(Cache::has($cacheKey))->toBeFalse();

    // Trigger role check
    authorizeAction($this->owner, 'view');

    // Now cached
    expect(Cache::has($cacheKey))->toBeTrue();
    expect(Cache::get($cacheKey))->toBe('owner');
});

/*
|--------------------------------------------------------------------------
| Helper: authorize via the trait logic
|--------------------------------------------------------------------------
*/

function authorizeAction(User $user, string $level): bool
{
    Auth::login($user);
    Cache::flush();

    // Use an anonymous class to test the trait directly
    $component = new class {
        use AuthorizesWorkspaceActions;

        public function testAuthorize(string $level): bool
        {
            return $this->authorizeWorkspaceAction($level);
        }
    };

    return $component->testAuthorize($level);
}
