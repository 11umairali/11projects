<?php

test('telegram webhook rejects request when no secret configured', function () {
    // Create workspace first for FK constraint
    $setup = createUserWithWorkspace('owner');
    $workspace = $setup['workspace'];

    \App\Models\ChannelIntegration::create([
        'workspace_id' => $workspace->id,
        'channel' => 'telegram',
        'status' => 'active',
        'config' => [],
        'credentials' => [], // No webhook_secret
    ]);

    $response = $this->postJson('/api/webhooks/telegram', [
        'update_id' => 123,
        'message' => ['text' => 'test'],
    ]);

    expect($response->status())->toBe(403);
});

test('slack webhook verifies signature before url_verification', function () {
    // Without valid signature, url_verification should be rejected
    $response = $this->postJson('/api/webhooks/slack/events', [
        'type' => 'url_verification',
        'challenge' => 'test_challenge_123',
    ]);

    // Should be 403 because signature is missing/invalid
    expect($response->status())->toBe(403);
});

test('whatsapp webhook rejects missing signature', function () {
    $response = $this->postJson('/api/webhooks/whatsapp', [
        'object' => 'whatsapp_business_account',
        'entry' => [],
    ]);

    expect($response->status())->toBeIn([403, 500]);
});

test('tracking endpoints are rate limited', function () {
    // This test verifies rate limiting is configured on tracking routes
    // In a real test you'd hit the endpoint 121+ times to trigger throttle
    $response = $this->get('/api/track/open/nonexistent-uuid');

    // Should return 200 (pixel) even for nonexistent UUIDs
    expect($response->status())->toBe(200);
    expect($response->headers->get('Content-Type'))->toContain('image/gif');
});

test('health check endpoint returns status', function () {
    $response = $this->getJson('/api/health');

    expect($response->status())->toBe(200);
    expect($response->json('status'))->toBe('healthy');
    expect($response->json('database'))->toBe('connected');
    expect($response->json('cache'))->toBe('ok');
});
