<?php

use App\Models\User;
use App\Models\Workspace;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| AuthorizesWorkspaceActions Trait Unit Tests
|--------------------------------------------------------------------------
| Tests the trait directly: role hierarchy, permission mapping, operation
| locking, cache key format, and role cache flushing.
*/

beforeEach(function () {
    // Create a fresh anonymous class instance that uses the trait
    $this->component = new class {
        use AuthorizesWorkspaceActions;

        public function testAuthorize(string $level): bool
        {
            return $this->authorizeWorkspaceAction($level);
        }

        public function testIsAtLeast(string $role): bool
        {
            return $this->isAtLeast($role);
        }

        public function testGetRole(): string
        {
            return $this->getWorkspaceRole();
        }

        public function testFlushCache(): void
        {
            $this->flushRoleCache();
        }

        public function testLock(string $op, callable $cb, int $ttl = 30): mixed
        {
            return $this->withOperationLock($op, $cb, $ttl);
        }
    };
});

// ---- Role Hierarchy ----

test('role hierarchy: owner > admin > agent > viewer', function () {
    $roles = createWorkspaceWithRoles();

    // Owner
    Auth::login($roles['owner']);
    Cache::flush();
    expect($this->component->testAuthorize('dangerous'))->toBeTrue();
    expect($this->component->testAuthorize('manage'))->toBeTrue();
    expect($this->component->testAuthorize('interact'))->toBeTrue();
    expect($this->component->testAuthorize('view'))->toBeTrue();

    // Admin
    Auth::login($roles['admin']);
    Cache::flush();
    expect($this->component->testAuthorize('dangerous'))->toBeFalse();
    expect($this->component->testAuthorize('manage'))->toBeTrue();
    expect($this->component->testAuthorize('interact'))->toBeTrue();
    expect($this->component->testAuthorize('view'))->toBeTrue();

    // Agent
    Auth::login($roles['agent']);
    Cache::flush();
    expect($this->component->testAuthorize('dangerous'))->toBeFalse();
    expect($this->component->testAuthorize('manage'))->toBeFalse();
    expect($this->component->testAuthorize('interact'))->toBeTrue();
    expect($this->component->testAuthorize('view'))->toBeTrue();

    // Viewer
    Auth::login($roles['viewer']);
    Cache::flush();
    expect($this->component->testAuthorize('dangerous'))->toBeFalse();
    expect($this->component->testAuthorize('manage'))->toBeFalse();
    expect($this->component->testAuthorize('interact'))->toBeFalse();
    expect($this->component->testAuthorize('view'))->toBeTrue();
});

// ---- Permission Levels ----

test('permission levels map to correct weights', function () {
    $roles = createWorkspaceWithRoles();

    // Owner (weight 4) can do everything
    Auth::login($roles['owner']);
    Cache::flush();

    expect($this->component->testIsAtLeast('owner'))->toBeTrue();
    expect($this->component->testIsAtLeast('admin'))->toBeTrue();
    expect($this->component->testIsAtLeast('agent'))->toBeTrue();
    expect($this->component->testIsAtLeast('viewer'))->toBeTrue();
});

test('viewer is not at least agent', function () {
    $roles = createWorkspaceWithRoles();

    Auth::login($roles['viewer']);
    Cache::flush();

    expect($this->component->testIsAtLeast('viewer'))->toBeTrue();
    expect($this->component->testIsAtLeast('agent'))->toBeFalse();
    expect($this->component->testIsAtLeast('admin'))->toBeFalse();
    expect($this->component->testIsAtLeast('owner'))->toBeFalse();
});

test('agent is at least agent but not admin', function () {
    $roles = createWorkspaceWithRoles();

    Auth::login($roles['agent']);
    Cache::flush();

    expect($this->component->testIsAtLeast('agent'))->toBeTrue();
    expect($this->component->testIsAtLeast('viewer'))->toBeTrue();
    expect($this->component->testIsAtLeast('admin'))->toBeFalse();
});

// ---- Operation Lock ----

test('operation lock prevents concurrent execution', function () {
    $roles = createWorkspaceWithRoles();
    Auth::login($roles['owner']);
    Cache::flush();

    $counter = 0;

    // First lock succeeds
    $result = $this->component->testLock('test-op', function () use (&$counter) {
        $counter++;
        return 'success';
    });

    expect($result)->toBe('success');
    expect($counter)->toBe(1);
});

test('operation lock releases after completion', function () {
    $roles = createWorkspaceWithRoles();
    Auth::login($roles['owner']);
    Cache::flush();

    // First execution
    $this->component->testLock('test-release', function () {
        return 'first';
    });

    // Second execution should also work (lock was released)
    $result = $this->component->testLock('test-release', function () {
        return 'second';
    });

    expect($result)->toBe('second');
});

// ---- Cache Key Format ----

test('cache key format is ws_role:userId:workspaceId', function () {
    $roles = createWorkspaceWithRoles();
    $user = $roles['owner'];
    $workspace = $roles['workspace'];

    Auth::login($user);
    Cache::flush();

    // Trigger role caching
    $this->component->testGetRole();

    $expectedKey = "ws_role:{$user->id}:{$workspace->id}";
    expect(Cache::has($expectedKey))->toBeTrue();
    expect(Cache::get($expectedKey))->toBe('owner');
});

// ---- Role Cache Flush ----

test('role cache flush removes cached role', function () {
    $roles = createWorkspaceWithRoles();
    $user = $roles['owner'];
    $workspace = $roles['workspace'];

    Auth::login($user);
    Cache::flush();

    // Populate cache
    $this->component->testGetRole();

    $cacheKey = "ws_role:{$user->id}:{$workspace->id}";
    expect(Cache::has($cacheKey))->toBeTrue();

    // Flush
    $this->component->testFlushCache();

    expect(Cache::has($cacheKey))->toBeFalse();
});

// ---- Default Role ----

test('user without workspace defaults to viewer role', function () {
    $user = User::factory()->create(['active_workspace_id' => null]);
    Auth::login($user);
    Cache::flush();

    $role = $this->component->testGetRole();
    expect($role)->toBe('viewer');
});

test('unknown permission level defaults to owner-only (weight 4)', function () {
    $roles = createWorkspaceWithRoles();

    Auth::login($roles['admin']);
    Cache::flush();

    // 'unknown_level' should require weight 4 (owner only)
    expect($this->component->testAuthorize('unknown_level'))->toBeFalse();

    Auth::login($roles['owner']);
    Cache::flush();

    expect($this->component->testAuthorize('unknown_level'))->toBeTrue();
});
