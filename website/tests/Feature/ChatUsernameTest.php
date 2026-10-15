<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () { Cache::flush(); });

test('legacy chat links redirect to usernames without weakening relationship checks', function () {
    $s = createUserWithWorkspace();
    $friend = User::factory()->create(['username' => 'alice.example', 'status' => 'active']);
    DB::table('friend_requests')->insert(['requester_id' => $s['user']->id, 'addressee_id' => $friend->id, 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($s['user'])->get('/friends/chat/'.$friend->id)->assertRedirect($friend->chatUrl());
    $this->get('/people/'.$friend->id)->assertOk()->assertSee($friend->chatUrl(), false)->assertSee('aria-label="Audio call"', false)->assertSee('aria-label="Meetings"', false);
    $this->get('/people')->assertOk()->assertSee($friend->chatUrl(), false);
    $found = app(\App\Services\Friends\FriendFinder::class)->search($s['user'], 'alice.example');
    expect($found['results'][0]['chat_url'])->toBe($friend->chatUrl());
    $this->get('/friends/chat/alice.example')->assertOk()->assertSee('data-user-id="'.$friend->id.'"', false);
    $outsider = User::factory()->create(['username' => 'outsider', 'status' => 'active']);
    $this->get('/friends/chat/@outsider')->assertNotFound();
    $this->get('/friends/chat/'.$outsider->id)->assertNotFound();
});

test('numeric usernames have unambiguous URLs and accounts without usernames use UUIDs', function () {
    $s = createUserWithWorkspace();
    $friend = User::factory()->create(['username' => '123456', 'status' => 'active']);
    DB::table('friend_requests')->insert(['requester_id' => $s['user']->id, 'addressee_id' => $friend->id, 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($s['user'])->get('/friends/chat/u/123456')->assertOk();
    $friend->update(['username' => null]);
    $this->get('/friends/chat/'.$friend->id)->assertRedirect($friend->chatUrl());
    $this->get($friend->chatUrl())->assertOk();
});

test('former friends retain read only history at username URL', function () {
    $s = createUserWithWorkspace();
    $friend = User::factory()->create(['username' => 'former', 'status' => 'active']);
    DB::table('friend_requests')->insert(['requester_id' => $s['user']->id, 'addressee_id' => $friend->id, 'status' => 'unfriended', 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($s['user'])->get('/friends/chat/former')->assertOk()->assertViewHas('relation', fn ($r) => $r['read_only'] === true);
});


test('plain and prefixed searches and legacy links resolve stored prefixed usernames', function () {
    $s = createUserWithWorkspace();
    $friend = User::factory()->create(['username' => '@legacy.name', 'status' => 'active']);
    DB::table('friend_requests')->insert(['requester_id' => $s['user']->id, 'addressee_id' => $friend->id, 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
    foreach (['legacy.name', '@legacy.name', ' @LEGACY.NAME '] as $query) {
        $result = app(\App\Services\Friends\FriendFinder::class)->search($s['user'], $query);
        expect($result['results'][0]['id'])->toBe($friend->id);
        expect($result['results'][0]['username'])->toBe('legacy.name');
    }
    expect($friend->chatUrl())->toEndWith('/friends/chat/legacy.name');
    $this->actingAs($s['user'])->get('/friends/chat/@legacy.name')->assertRedirect($friend->chatUrl());
    $this->get($friend->chatUrl())->assertOk();
    $this->get('/people/u/legacy.name')->assertOk()->assertDontSee('@legacy.name');
    User::factory()->create(['username' => 'legacy.name', 'status' => 'active']);
    $this->get($friend->chatUrl())->assertNotFound(); // ambiguous legacy identity must not choose an account
});
