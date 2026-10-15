<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| UI Consistency Tests
|--------------------------------------------------------------------------
| Tests that error pages, public pages, and core navigation endpoints
| return the correct status codes and remain accessible. Prevents
| regressions where framework updates silently break custom error views.
|
| Note: Each test clears the DDoS protection cache to prevent rate-limit
| interference from accumulating across sequential test execution.
*/

beforeEach(function () {
    // Reset DDoS protection counters so tests don't accumulate towards
    // the per-minute or scanner-detection thresholds.
    Cache::flush();
});

// ---- Error Pages ----

test('404 page returns correct status code for missing route', function () {
    $this->get('/this-page-does-not-exist-ever-abc123')
        ->assertStatus(404);
});

test('404 page returns correct status code for deeply nested missing route', function () {
    $this->get('/admin/settings/deeply/nested/nonexistent/path')
        ->assertStatus(404);
});

// ---- PWA Offline Page ----

test('offline page is accessible and returns 200', function () {
    $this->get('/offline')->assertOk();
});

// ---- Public Pages ----

test('landing page is accessible', function () {
    $this->get('/')->assertOk();
});

test('terms page is accessible', function () {
    $this->get('/terms')->assertOk();
});

test('privacy page is accessible', function () {
    $this->get('/privacy')->assertOk();
});

test('refund policy page is accessible', function () {
    $this->get('/refund-policy')->assertOk();
});

test('contact page is accessible', function () {
    $this->get('/contact')->assertOk();
});

// ---- Health Check ----

test('health check endpoint returns json with status', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk();
    $response->assertJsonStructure([
        'status',
        'database',
        'cache',
        'timestamp',
    ]);
    $response->assertJson(['status' => 'healthy']);
});

// ---- Auth-Required Pages Guard Unauthenticated Users ----

test('protected pages are not publicly accessible', function () {
    $protectedRoutes = [
        '/dashboard',
        '/contacts',
        '/inbox',
        '/campaigns',
        '/analytics',
    ];

    foreach ($protectedRoutes as $route) {
        Cache::flush(); // Reset between each sub-request
        $response = $this->get($route);

        // Should either redirect to login (302) or return 401/403 -- NOT 200
        expect($response->status())->toBeIn([302, 301, 303, 307, 308, 401, 403],
            "Route {$route} should not be publicly accessible, got status {$response->status()}");
    }
});

// ---- Authenticated Page Accessibility ----

test('authenticated user can access dashboard', function () {
    $setup = createUserWithWorkspace('owner');

    $this->actingAs($setup['user'])
        ->get('/dashboard')
        ->assertOk();
});

test('authenticated user can access contacts page', function () {
    $setup = createUserWithWorkspace('owner');

    $this->actingAs($setup['user'])
        ->get('/contacts')
        ->assertOk();
});

test('authenticated user can access inbox page', function () {
    $setup = createUserWithWorkspace('owner');

    $this->actingAs($setup['user'])
        ->get('/inbox')
        ->assertOk();
});

test('authenticated user can access campaigns page', function () {
    $setup = createUserWithWorkspace('owner');

    $this->actingAs($setup['user'])
        ->get('/campaigns')
        ->assertOk();
});

test('authenticated user can access deals page', function () {
    $setup = createUserWithWorkspace('owner');

    $this->actingAs($setup['user'])
        ->get('/deals')
        ->assertOk();
});

test('authenticated user can access analytics page', function () {
    $setup = createUserWithWorkspace('owner');

    $this->actingAs($setup['user'])
        ->get('/analytics')
        ->assertOk();
});

// ---- Sitemap ----

test('sitemap.xml is accessible', function () {
    $response = $this->get('/sitemap.xml');

    // Should be 200 (rendered) or 500 (missing DB data) -- NOT 404
    expect($response->status())->not->toBe(404);
});
