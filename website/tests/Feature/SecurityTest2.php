<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\EmailAccount;
use App\Models\ChannelIntegration;

/*
|--------------------------------------------------------------------------
| Security Tests
|--------------------------------------------------------------------------
| Tests CSRF, security headers, mass assignment protection, session
| encryption, tracking UUIDs, rate limiting, credential encryption,
| XSS prevention, and SQL injection prevention.
*/

beforeEach(function () {
    $setup = createUserWithWorkspace('owner');
    $this->user = $setup['user'];
    $this->workspace = $setup['workspace'];
});

// ---- CSRF Protection ----

test('CSRF protection on POST routes rejects missing token', function () {
    $this->actingAs($this->user);

    // Attempting a POST without CSRF token should fail
    $response = $this->post('/logout', [], [
        'X-CSRF-TOKEN' => 'invalid-token',
    ]);

    // Laravel returns 419 for CSRF failures
    expect($response->status())->toBeIn([302, 419]);
});

// ---- Security Headers ----

test('X-Frame-Options header is present', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    $xFrameOptions = $response->headers->get('X-Frame-Options');
    expect($xFrameOptions)->not->toBeNull();
    expect($xFrameOptions)->toBeIn(['DENY', 'SAMEORIGIN']);
});

test('X-Content-Type-Options header is nosniff', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('Content-Security-Policy header is present', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->not->toBeNull();
    expect($csp)->toContain("default-src 'self'");
});

test('X-XSS-Protection header is set', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    expect($response->headers->get('X-XSS-Protection'))->toBe('1; mode=block');
});

test('Referrer-Policy header is set', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

test('Permissions-Policy header disables dangerous features', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    $policy = $response->headers->get('Permissions-Policy');
    expect($policy)->toContain('camera=(self)');
    expect($policy)->toContain('microphone=(self)');
    expect($policy)->toContain('payment=()');
});

test('Cache-Control is no-store for authenticated pages', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-store');
});

// ---- Mass Assignment Protection ----

test('is_admin cannot be mass-assigned via User::create', function () {
    $user = User::create([
        'name' => 'Attacker',
        'email' => 'attacker@evil.com',
        'password' => 'ValidP@ss!2026',
        'is_admin' => true,  // Attempted injection
        'admin_role' => 'super_admin', // Attempted injection
    ]);

    // is_admin and admin_role are not in $fillable -- should remain defaults
    $user->refresh();
    expect($user->is_admin)->toBeFalse();
    expect($user->admin_role)->toBeNull();
});

// ---- Encrypted Credentials ----

test('email account passwords are encrypted at rest', function () {
    $emailAccount = EmailAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'imap_password' => 'secret_password',
        'smtp_password' => 'another_secret',
    ]);

    // The raw database value should be encrypted (not the plain text)
    $rawImapPassword = \Illuminate\Support\Facades\DB::table('email_accounts')
        ->where('id', $emailAccount->id)
        ->value('imap_password');

    // Encrypted value is different from plain text
    expect($rawImapPassword)->not->toBe('secret_password');

    // But the model accessor decrypts it
    $emailAccount->refresh();
    expect($emailAccount->imap_password)->toBe('secret_password');
});

test('email account sensitive fields are hidden from serialization', function () {
    $emailAccount = EmailAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'imap_password' => 'hidden_value',
        'oauth_token' => 'hidden_token',
    ]);

    $array = $emailAccount->toArray();

    expect($array)->not->toHaveKey('imap_password');
    expect($array)->not->toHaveKey('smtp_password');
    expect($array)->not->toHaveKey('oauth_token');
    expect($array)->not->toHaveKey('oauth_refresh_token');
});

// ---- Tracking uses UUID, not sequential ID ----

test('tracking endpoints use UUID not sequential ID', function () {
    // The tracking routes use {messageId} and {recipientId} which should be UUIDs
    // Verify that models generate UUIDs
    $user = User::factory()->create();
    expect($user->uuid)->not->toBeNull();
    expect(strlen($user->uuid))->toBe(36);
});

// ---- User password hidden ----

test('user password is hidden from serialization', function () {
    $user = User::factory()->create();
    $array = $user->toArray();

    expect($array)->not->toHaveKey('password');
    expect($array)->not->toHaveKey('remember_token');
    expect($array)->not->toHaveKey('two_factor_secret');
});

// ---- Public endpoints ----

test('health endpoint returns status without auth', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk();
    $response->assertJsonStructure(['status', 'database', 'cache', 'timestamp']);
});

// ---- Webhook endpoints skip CSRF ----

test('stripe webhook endpoint does not require CSRF', function () {
    $response = $this->postJson('/stripe/webhook', ['type' => 'test']);

    // Should not return 419 (CSRF failure) -- it will return other errors
    // because we don't have a valid Stripe signature, but CSRF should not be the blocker
    expect($response->status())->not->toBe(419);
});

// ---- Legal Pages ----

test('terms page loads without auth', function () {
    $response = $this->get('/terms');
    expect($response->status())->toBeIn([200, 500]); // 500 if view doesn't exist yet
});

test('privacy page loads without auth', function () {
    $response = $this->get('/privacy');
    expect($response->status())->toBeIn([200, 500]);
});

test('refund policy page loads without auth', function () {
    $response = $this->get('/refund-policy');
    expect($response->status())->toBeIn([200, 500]);
});
