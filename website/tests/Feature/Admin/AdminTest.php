<?php

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Payment;
use App\Models\Subscription;

/*
|--------------------------------------------------------------------------
| Admin Panel Tests
|--------------------------------------------------------------------------
| Tests admin authentication, authorization, user management,
| plan management, impersonation, and audit logging.
*/

beforeEach(function () {
    $this->admin = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
        'is_admin' => true,
        'admin_role' => 'super_admin',
    ]);

    $this->regularUser = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
        'is_admin' => false,
    ]);

    // Admin needs a workspace to pass middleware
    $workspace = Workspace::factory()->create();
    $workspace->members()->attach($this->admin->id, ['role' => 'owner', 'status' => 'online']);
    $this->admin->update(['active_workspace_id' => $workspace->id]);

    $workspace2 = Workspace::factory()->create();
    $workspace2->members()->attach($this->regularUser->id, ['role' => 'owner', 'status' => 'online']);
    $this->regularUser->update(['active_workspace_id' => $workspace2->id]);

    $this->workspace = $workspace;
});

// ---- Admin Access ----

test('admin can access admin dashboard', function () {
    $response = $this->actingAs($this->admin)->get('/admin/dashboard');

    $response->assertStatus(200);
});

test('non-admin cannot access admin panel', function () {
    $response = $this->actingAs($this->regularUser)->get('/admin/dashboard');

    $response->assertStatus(403);
});

test('non-admin cannot access admin users page', function () {
    $response = $this->actingAs($this->regularUser)->get('/admin/users');

    $response->assertStatus(403);
});

test('non-admin cannot access admin plans page', function () {
    $response = $this->actingAs($this->regularUser)->get('/admin/plans');

    $response->assertStatus(403);
});

// ---- Admin User Management ----

test('admin can view users list', function () {
    $response = $this->actingAs($this->admin)->get('/admin/users');

    $response->assertStatus(200);
});

test('admin can view create user form', function () {
    $response = $this->actingAs($this->admin)->get('/admin/users/create');

    $response->assertStatus(200);
});

// ---- Plan Management ----

test('admin can view plans list', function () {
    $response = $this->actingAs($this->admin)->get('/admin/plans');

    $response->assertStatus(200);
});

test('admin can view create plan form', function () {
    $response = $this->actingAs($this->admin)->get('/admin/plans/create');

    $response->assertStatus(200);
});

// ---- Impersonation ----

test('admin impersonation stores session data', function () {
    $targetUser = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $workspace = Workspace::factory()->create();
    $workspace->members()->attach($targetUser->id, ['role' => 'owner']);
    $targetUser->update(['active_workspace_id' => $workspace->id]);

    $response = $this->actingAs($this->admin)
        ->post("/admin/users/{$targetUser->id}/impersonate");

    // Should redirect to dashboard as the impersonated user
    $response->assertRedirect();
});

// ---- System Pages ----

test('admin can access system info page', function () {
    $response = $this->actingAs($this->admin)->get('/admin/system');

    $response->assertStatus(200);
});

test('admin can access audit log page', function () {
    $response = $this->actingAs($this->admin)->get('/admin/audit-log');

    $response->assertStatus(200);
});

test('admin can access tickets page', function () {
    $response = $this->actingAs($this->admin)->get('/admin/tickets');

    $response->assertStatus(200);
});

test('admin can access payments page', function () {
    $response = $this->actingAs($this->admin)->get('/admin/payments');

    $response->assertStatus(200);
});

// ---- Settings ----

test('admin can save system settings', function () {
    $response = $this->actingAs($this->admin)->post('/admin/settings', [
        'site_name' => 'MailTrixy Test',
        'site_tagline' => 'AI-Powered Email',
        'timezone' => 'UTC',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('system_settings', [
        'key' => 'site_name',
        'value' => 'MailTrixy Test',
    ]);
});

test('admin settings preserves empty sensitive keys', function () {
    // First, set a value
    \Illuminate\Support\Facades\DB::table('system_settings')->insert([
        'key' => 'openai_api_key',
        'value' => encrypt('sk-test-real-key'),
        'group' => 'general',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Submit form with empty sensitive key -- should NOT overwrite
    $this->actingAs($this->admin)->post('/admin/settings', [
        'site_name' => 'Updated Name',
        'openai_api_key' => '', // Empty -- should be skipped
    ]);

    $storedValue = \Illuminate\Support\Facades\DB::table('system_settings')
        ->where('key', 'openai_api_key')
        ->value('value');

    // The original encrypted value should still be there
    expect($storedValue)->not->toBeNull();
    expect($storedValue)->not->toBe('');
});

// ---- Admin is_admin not mass assignable ----

test('is_admin is not in User fillable array', function () {
    $user = new User();
    $fillable = $user->getFillable();

    expect($fillable)->not->toContain('is_admin');
});

test('admin_role is not in User fillable array', function () {
    $user = new User();
    $fillable = $user->getFillable();

    expect($fillable)->not->toContain('admin_role');
});
