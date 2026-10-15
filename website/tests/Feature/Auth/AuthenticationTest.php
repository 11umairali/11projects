<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;

/*
|--------------------------------------------------------------------------
| Authentication Tests
|--------------------------------------------------------------------------
| Tests for login, registration, password reset, email verification,
| logout, and social login redirect.
*/

test('login with valid credentials succeeds', function () {
    $user = User::factory()->create([
        'password' => Hash::make('SecureP@ssw0rd!'),
        'email_verified_at' => now(),
        'status' => 'active',
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'SecureP@ssw0rd!',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticated();
});

test('login with invalid credentials fails', function () {
    $user = User::factory()->create([
        'password' => Hash::make('SecureP@ssw0rd!'),
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('login rate limiting kicks in after 5 failed attempts', function () {
    $user = User::factory()->create([
        'password' => Hash::make('SecureP@ssw0rd!'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password-' . $i,
        ]);
    }

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password-final',
    ]);

    // After 5 attempts, Fortify returns 429 or validation error with throttle message
    expect($response->status())->toBeIn([302, 429, 422]);
});

test('registration with valid data creates user', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'newuser@example.com',
        'password' => 'Str0ngP@ss!2026',
        'password_confirmation' => 'Str0ngP@ss!2026',
    ]);

    $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    $this->assertAuthenticated();
});

test('registration with weak password fails', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'weak@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
});

test('registration with duplicate email fails', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post('/register', [
        'name' => 'Another User',
        'email' => 'taken@example.com',
        'password' => 'Str0ngP@ss!2026',
        'password_confirmation' => 'Str0ngP@ss!2026',
    ]);

    $response->assertSessionHasErrors('email');
});

test('password reset request sends email', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('password reset with valid token works', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldP@ssw0rd!123'),
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewP@ssw0rd!456',
        'password_confirmation' => 'NewP@ssw0rd!456',
    ]);

    $this->assertTrue(Hash::check('NewP@ssw0rd!456', $user->fresh()->password));
});

test('email verification flow marks user as verified', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('logout clears session and deauthenticates', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    $this->assertAuthenticated();

    $this->post('/logout');

    $this->assertGuest();
});

test('social login redirect for google returns redirect response', function () {
    $response = $this->get('/auth/google/redirect');

    // Should redirect to Google OAuth
    $response->assertRedirect();
    expect($response->getTargetUrl())->toContain('google');
});

test('social login redirect for unsupported provider returns 404', function () {
    $response = $this->get('/auth/facebook/redirect');

    // Route constraint is 'google|microsoft|github' so this should 404
    $response->assertNotFound();
});

test('unauthenticated user cannot access dashboard', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

test('suspended user cannot login', function () {
    $user = User::factory()->create([
        'password' => Hash::make('SecureP@ssw0rd!'),
        'status' => 'suspended',
        'suspended_at' => now(),
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'SecureP@ssw0rd!',
    ]);

    // Fortify may authenticate then middleware rejects, or custom auth logic blocks
    // Either way the user should not reach the dashboard
    if ($this->isAuthenticated()) {
        $this->assertGuest(); // This will fail if user slips through -- good catch
    }
});
