<?php
use Laravel\Sanctum\Sanctum;

test('mobile websocket auth grants only the current private user channel', function () {
    $s = createUserWithWorkspace();
    config(['broadcasting.default' => 'pusher', 'broadcasting.connections.pusher.key' => 'synthetic-key', 'broadcasting.connections.pusher.secret' => 'synthetic-secret']);
    Sanctum::actingAs($s['user']);
    $channel = 'private-user.'.$s['user']->id;
    $this->getJson('/api/v1/realtime/config')->assertOk()->assertJson(['enabled' => true, 'channel' => $channel])->assertDontSee('synthetic-secret');
    $this->postJson('/api/v1/realtime/auth', ['socket_id' => '12.34', 'channel_name' => $channel])->assertOk()
        ->assertJson(['auth' => 'synthetic-key:'.hash_hmac('sha256', '12.34:'.$channel, 'synthetic-secret')]);
    $this->postJson('/api/v1/realtime/auth', ['socket_id' => '12.34', 'channel_name' => 'private-user.999999'])->assertForbidden();
    $this->postJson('/api/v1/realtime/auth', ['socket_id' => '12.34:injected', 'channel_name' => $channel])->assertUnprocessable();
    config(['broadcasting.default' => 'null']);
    $this->getJson('/api/v1/realtime/config')->assertOk()->assertJson(['enabled' => false]);
    $this->postJson('/api/v1/realtime/auth', ['socket_id' => '12.34', 'channel_name' => $channel])->assertStatus(503);
});
