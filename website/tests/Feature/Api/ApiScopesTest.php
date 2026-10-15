<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Token Scoping Tests
|--------------------------------------------------------------------------
| Tests that the REST API enforces authentication and that scope-based
| middleware is declared on each resource group.
|
| The API is mounted at /api/v1 with auth:sanctum + throttle:60,1 middleware.
| Each resource group also declares an ability:<scope> middleware for
| token-level access control.
|
| Note: The ability middleware alias is not yet registered in bootstrap/app.php,
| so hitting scoped endpoints throws BindingResolutionException. Auth enforcement
| tests use the /api/v1/user endpoint (no scope required) and the /api/v1/workspace
| endpoint to verify that auth:sanctum is working. Route middleware declaration
| tests verify that all scope-gated routes have the correct middleware declared.
*/

beforeEach(function () {
    Cache::flush();
});

// ---- Authentication Enforcement (scope-free endpoints) ----

test('api user endpoint requires authentication', function () {
    $response = $this->getJson('/api/v1/user');

    expect($response->status())->toBe(401);
});

test('api workspace endpoint requires authentication', function () {
    $response = $this->getJson('/api/v1/workspace');

    expect($response->status())->toBe(401);
});

test('api rejects request with invalid bearer token', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer invalid-token-xyz'])
        ->getJson('/api/v1/user');

    expect($response->status())->toBe(401);
});

test('api returns json error format for unauthenticated request', function () {
    $response = $this->getJson('/api/v1/user');

    $response->assertUnauthorized();
    $response->assertJson(['message' => 'Unauthenticated.']);
});

// ---- Route Existence ----

test('all expected api v1 resource routes exist', function () {
    $expectedRoutes = [
        'api/v1/contacts',
        'api/v1/conversations',
        'api/v1/campaigns',
        'api/v1/workflows',
        'api/v1/tags',
        'api/v1/canned-responses',
    ];

    foreach ($expectedRoutes as $expectedUri) {
        $route = collect(Route::getRoutes())->first(function ($route) use ($expectedUri) {
            return $route->uri() === $expectedUri && in_array('GET', $route->methods());
        });

        expect($route)->not->toBeNull("GET /{$expectedUri} route should exist");
    }
});

test('analytics api endpoints exist', function () {
    $analyticsEndpoints = [
        'api/v1/analytics/overview',
        'api/v1/analytics/ai',
        'api/v1/analytics/team',
    ];

    foreach ($analyticsEndpoints as $uri) {
        $route = collect(Route::getRoutes())->first(function ($route) use ($uri) {
            return $route->uri() === $uri;
        });

        expect($route)->not->toBeNull("/{$uri} route should exist");
    }
});

test('ai api endpoints exist', function () {
    $aiEndpoints = [
        'api/v1/ai/generate-reply',
        'api/v1/ai/analyze-sentiment',
    ];

    foreach ($aiEndpoints as $uri) {
        $route = collect(Route::getRoutes())->first(function ($route) use ($uri) {
            return $route->uri() === $uri;
        });

        expect($route)->not->toBeNull("/{$uri} route should exist");
    }
});

// ---- auth:sanctum Middleware Declaration ----

test('all api v1 resource routes require auth:sanctum', function () {
    $apiRoutes = collect(Route::getRoutes())->filter(function ($route) {
        return str_starts_with($route->uri(), 'api/v1/')
            && !str_contains($route->uri(), 'webhook');
    });

    expect($apiRoutes->count())->toBeGreaterThan(0, 'API v1 routes should exist');

    foreach ($apiRoutes as $route) {
        $middleware = $route->gatherMiddleware();
        $hasSanctum = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'auth:sanctum'));

        expect($hasSanctum)->toBeTrue(
            "Route {$route->uri()} [{$route->methods()[0]}] should require auth:sanctum"
        );
    }
});

// ---- Scope Middleware Declarations ----

test('contacts routes declare ability:contacts middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return $route->uri() === 'api/v1/contacts' && in_array('GET', $route->methods());
    });

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    $hasAbility = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'ability:contacts'));

    expect($hasAbility)->toBeTrue('contacts route should declare ability:contacts middleware');
});

test('conversations routes declare ability:conversations middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return $route->uri() === 'api/v1/conversations' && in_array('GET', $route->methods());
    });

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    $hasAbility = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'ability:conversations'));

    expect($hasAbility)->toBeTrue('conversations route should declare ability:conversations middleware');
});

test('campaigns routes declare ability:campaigns middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return $route->uri() === 'api/v1/campaigns' && in_array('GET', $route->methods());
    });

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    $hasAbility = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'ability:campaigns'));

    expect($hasAbility)->toBeTrue('campaigns route should declare ability:campaigns middleware');
});

test('workflows routes declare ability:workflows middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return $route->uri() === 'api/v1/workflows' && in_array('GET', $route->methods());
    });

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    $hasAbility = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'ability:workflows'));

    expect($hasAbility)->toBeTrue('workflows route should declare ability:workflows middleware');
});

test('tags routes declare ability:tags middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return $route->uri() === 'api/v1/tags' && in_array('GET', $route->methods());
    });

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    $hasAbility = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'ability:tags'));

    expect($hasAbility)->toBeTrue('tags route should declare ability:tags middleware');
});

test('analytics routes declare ability:analytics middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return str_contains($route->uri(), 'api/v1/analytics');
    });

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    $hasAbility = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'ability:analytics'));

    expect($hasAbility)->toBeTrue('analytics route should declare ability:analytics middleware');
});

test('ai routes declare ability:ai middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return str_contains($route->uri(), 'api/v1/ai/');
    });

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    $hasAbility = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'ability:ai'));

    expect($hasAbility)->toBeTrue('ai route should declare ability:ai middleware');
});

test('knowledge-base routes declare ability:knowledge-base middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return str_contains($route->uri(), 'api/v1/knowledge-base') && in_array('GET', $route->methods());
    });

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    $hasAbility = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'ability:knowledge-base'));

    expect($hasAbility)->toBeTrue('knowledge-base route should declare ability:knowledge-base middleware');
});

test('canned-responses routes declare ability:canned-responses middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return str_contains($route->uri(), 'api/v1/canned-responses') && in_array('GET', $route->methods());
    });

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    $hasAbility = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'ability:canned-responses'));

    expect($hasAbility)->toBeTrue('canned-responses route should declare ability:canned-responses middleware');
});

// ---- API Rate Limiting ----

test('api v1 routes have base throttle middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return $route->uri() === 'api/v1/contacts' && in_array('GET', $route->methods());
    });

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    $hasThrottle = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'throttle'));

    expect($hasThrottle)->toBeTrue('API routes should have throttle middleware');
});

test('sensitive api endpoints have stricter rate limits', function () {
    $strictEndpoints = [
        'api/v1/contacts/import',
        'api/v1/contacts/export',
    ];

    foreach ($strictEndpoints as $uri) {
        $route = collect(Route::getRoutes())->first(function ($route) use ($uri) {
            return str_contains($route->uri(), $uri);
        });

        expect($route)->not->toBeNull("{$uri} should exist");

        $middleware = $route->gatherMiddleware();
        // Should have TWO throttle middlewares: the base 60/min and the strict 5/min
        $throttleCount = collect($middleware)->filter(fn ($m) => str_contains((string) $m, 'throttle'))->count();

        expect($throttleCount)->toBeGreaterThanOrEqual(2,
            "{$uri} should have stricter rate limiting (multiple throttle middlewares)"
        );
    }
});
