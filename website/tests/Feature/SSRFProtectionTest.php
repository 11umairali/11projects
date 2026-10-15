<?php

use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'Test',
        'owner_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => 'owner', 'status' => 'offline']);
    $this->user->update(['active_workspace_id' => $this->workspace->id]);
    $this->token = $this->user->createToken('test')->plainTextToken;
});

test('KB scraper blocks localhost', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/knowledge-base/scrape', [
            'url' => 'http://127.0.0.1/admin',
        ]);

    expect($response->status())->toBe(422);
    expect($response->json('message'))->toContain('private');
});

test('KB scraper blocks cloud metadata endpoint', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/knowledge-base/scrape', [
            'url' => 'http://169.254.169.254/latest/meta-data/',
        ]);

    expect($response->status())->toBe(422);
});

test('KB scraper blocks internal network 10.x', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/knowledge-base/scrape', [
            'url' => 'http://10.0.0.1/internal-api',
        ]);

    expect($response->status())->toBe(422);
});

test('KB scraper blocks internal network 192.168.x', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/knowledge-base/scrape', [
            'url' => 'http://192.168.1.1/admin',
        ]);

    expect($response->status())->toBe(422);
});

test('KB scraper blocks FTP protocol', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/knowledge-base/scrape', [
            'url' => 'ftp://files.example.com/secret.txt',
        ]);

    expect($response->status())->toBe(422);
    expect($response->json('message'))->toContain('HTTP');
});

test('KB scraper blocks file protocol', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/knowledge-base/scrape', [
            'url' => 'file:///etc/passwd',
        ]);

    expect($response->status())->toBe(422);
});
