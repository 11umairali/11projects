<?php

use App\Models\User;
use App\Models\Workspace;

/*
|--------------------------------------------------------------------------
| Legal & GDPR Tests
|--------------------------------------------------------------------------
| Tests that legal pages load, and GDPR data export works correctly
| with proper role-based authorization.
*/

// ---- Legal Pages ----

test('terms page loads with 200 status', function () {
    $response = $this->get('/terms');

    $response->assertOk();
});

test('privacy page loads with 200 status', function () {
    $response = $this->get('/privacy');

    $response->assertOk();
});

test('refund policy page loads with 200 status', function () {
    $response = $this->get('/refund-policy');

    $response->assertOk();
});

test('contact page loads with 200 status', function () {
    $response = $this->get('/contact');

    $response->assertOk();
});

// ---- GDPR Data Export ----

test('GDPR data export works for workspace owner', function () {
    $setup = createUserWithWorkspace('owner');

    $response = $this->actingAs($setup['user'])->get('/settings/export-data');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertHeader('Content-Disposition');

    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('exported_at');
    expect($data)->toHaveKey('user_profile');
    expect($data)->toHaveKey('contacts');
    expect($data)->toHaveKey('conversations');
    expect($data['workspace_id'])->toBe($setup['workspace']->id);
});

test('GDPR data export works for workspace admin', function () {
    $setup = createUserWithWorkspace('admin');

    $response = $this->actingAs($setup['user'])->get('/settings/export-data');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');
});

test('GDPR export denied for viewer role', function () {
    $setup = createUserWithWorkspace('viewer');

    $response = $this->actingAs($setup['user'])->get('/settings/export-data');

    $response->assertForbidden();
});

test('GDPR export denied for agent role', function () {
    $setup = createUserWithWorkspace('agent');

    $response = $this->actingAs($setup['user'])->get('/settings/export-data');

    $response->assertForbidden();
});

test('GDPR export includes correct user profile', function () {
    $setup = createUserWithWorkspace('owner');
    $user = $setup['user'];

    $response = $this->actingAs($user)->get('/settings/export-data');

    $data = json_decode($response->getContent(), true);
    expect($data['user_profile']['email'])->toBe($user->email);
    expect($data['user_profile']['name'])->toBe($user->name);
});

test('GDPR export includes workspace contacts', function () {
    $setup = createUserWithWorkspace('owner');

    \App\Models\Contact::factory()->count(3)->create([
        'workspace_id' => $setup['workspace']->id,
    ]);

    $response = $this->actingAs($setup['user'])->get('/settings/export-data');

    $data = json_decode($response->getContent(), true);
    expect($data['contacts'])->toHaveCount(3);
});
