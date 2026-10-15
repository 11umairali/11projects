<?php

use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->agent = User::factory()->create();
    $this->viewer = User::factory()->create();

    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'owner_id' => $this->owner->id,
    ]);

    $this->workspace->members()->attach($this->owner->id, ['role' => 'owner', 'status' => 'offline']);
    $this->workspace->members()->attach($this->admin->id, ['role' => 'admin', 'status' => 'offline']);
    $this->workspace->members()->attach($this->agent->id, ['role' => 'agent', 'status' => 'offline']);
    $this->workspace->members()->attach($this->viewer->id, ['role' => 'viewer', 'status' => 'offline']);

    foreach ([$this->owner, $this->admin, $this->agent, $this->viewer] as $user) {
        $user->update(['active_workspace_id' => $this->workspace->id]);
    }
});

test('viewer cannot access admin routes', function () {
    $response = $this->actingAs($this->viewer)->get('/admin/dashboard');
    expect($response->status())->toBe(403);
});

test('agent cannot access admin routes', function () {
    $response = $this->actingAs($this->agent)->get('/admin/dashboard');
    expect($response->status())->toBe(403);
});

test('API endpoints require authentication', function () {
    $endpoints = [
        ['GET', '/api/contacts'],
        ['GET', '/api/conversations'],
        ['POST', '/api/ai/generate-reply'],
        ['GET', '/api/analytics/overview'],
    ];

    foreach ($endpoints as [$method, $url]) {
        $response = $this->json($method, $url);
        expect($response->status())->toBe(401);
    }
});

test('workspace isolation prevents cross-workspace access', function () {
    $otherWorkspace = Workspace::create([
        'name' => 'Other Workspace',
        'owner_id' => $this->owner->id,
    ]);

    $contact = \App\Models\Contact::create([
        'workspace_id' => $otherWorkspace->id,
        'email' => 'secret@other.com',
        'first_name' => 'Secret',
        'status' => 'active',
    ]);

    $this->actingAs($this->owner);
    $token = $this->owner->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/contacts/{$contact->id}");

    expect($response->status())->toBe(404);
});
