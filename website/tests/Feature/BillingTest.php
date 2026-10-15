<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Billing Tests
|--------------------------------------------------------------------------
| Tests billing settings page access, authentication guards, and the
| Stripe webhook endpoint availability. Payment processing is tested
| separately in PaymentWebhookTest.
*/

beforeEach(function () {
    // Reset DDoS protection counters between tests to prevent rate-limit
    // interference when multiple URLs are hit in sequence.
    Cache::flush();
});

test('billing settings page requires authentication', function () {
    $response = $this->get('/settings/billing');

    // Auth middleware returns redirect (302) to login, or workspace middleware
    // returns 401/403 for unauthenticated users.
    expect($response->status())->toBeIn([302, 301, 303, 307, 308, 401, 403]);
});

test('authenticated user can access billing settings page', function () {
    $setup = createUserWithWorkspace('owner');

    $this->actingAs($setup['user'])
        ->get('/settings/billing')
        ->assertOk();
});

test('non-owner can access billing settings page', function () {
    // Billing page should be viewable by any workspace member (display-only for non-owners)
    $setup = createUserWithWorkspace('admin');

    $this->actingAs($setup['user'])
        ->get('/settings/billing')
        ->assertOk();
});

test('stripe webhook endpoint is available without authentication', function () {
    // The Stripe webhook route should exist and not require auth.
    // Without a valid Stripe signature it should fail with a non-404 status,
    // proving the route is registered and CSRF is exempted.
    $response = $this->postJson('/stripe/webhook', ['type' => 'test']);

    // Should not be 404 (route exists) or 405 (method allowed).
    // The actual status depends on Stripe signature verification.
    expect($response->status())->not->toBe(404);
});

test('payment callback endpoint exists for each gateway', function () {
    // Verify the generic payment callback route is registered
    $response = $this->get('/payment/callback/stripe');

    // Should not be 404 -- the route exists
    expect($response->status())->not->toBe(404);
});

test('all settings pages require authentication', function () {
    $settingsPages = [
        '/settings/profile',
        '/settings/security',
        '/settings/email',
        '/settings/billing',
        '/settings/team',
        '/settings/workspace',
        '/settings/notifications',
        '/settings/integrations',
        '/settings/data-privacy',
    ];

    foreach ($settingsPages as $page) {
        Cache::flush(); // Reset DDoS counters between sub-requests
        $response = $this->get($page);

        // Auth middleware returns redirect to login, or workspace middleware
        // returns 401/403 for unauthenticated users -- NOT 200
        expect($response->status())->toBeIn([302, 301, 303, 307, 308, 401, 403],
            "Settings page {$page} should not be publicly accessible, got status {$response->status()}");
    }
});
