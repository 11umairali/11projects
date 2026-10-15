<?php

use App\Models\SystemSetting;
use App\Support\RealtimeSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
    DB::unprepared(file_get_contents(base_path('tests/Fixtures/realtime.sqlite.sql')));
    Cache::flush();
    config(['broadcasting.default' => 'null', 'services.pusher.app_id' => 'synthetic-app', 'services.pusher.key' => 'synthetic-key',
        'services.pusher.secret' => 'synthetic-secret', 'services.pusher.cluster' => 'eu']);
});

test('admin realtime settings enable the actual broadcasting and mobile socket transport', function () {
    SystemSetting::set('integration_pusher_enabled', '1');
    RealtimeSettings::apply();
    expect(config('broadcasting.default'))->toBe('pusher');
    expect(config('broadcasting.connections.pusher.key'))->toBe('synthetic-key');
    expect(config('broadcasting.connections.pusher.secret'))->toBe('synthetic-secret');
    expect(config('broadcasting.connections.pusher.app_id'))->toBe('synthetic-app');
    expect(config('broadcasting.connections.pusher.options.host'))->toBe('api-eu.pusher.com');
    $request = Illuminate\Http\Request::create('/realtime/config');
    $user = new App\Models\User; $user->id = 7;
    $request->setUserResolver(fn () => $user);
    $data = app(App\Http\Controllers\Api\Mobile\RealtimeController::class)->config($request)->getData(true);
    expect($data['enabled'])->toBeTrue()->and($data['host'])->toBe('ws-eu.pusher.com');
    expect(json_encode($data))->not->toContain('synthetic-secret');
});

test('an explicit disabled or incomplete admin integration does not advertise a working socket', function () {
    SystemSetting::set('integration_pusher_enabled', '0');
    config(['broadcasting.default' => 'pusher']);
    RealtimeSettings::apply();
    expect(config('broadcasting.default'))->toBe('null');
    SystemSetting::set('integration_pusher_enabled', '1');
    config(['services.pusher.secret' => null]);
    RealtimeSettings::apply();
    expect(config('broadcasting.default'))->toBe('null');
});

test('environment transport is retained when no admin override is set and custom hosts survive', function () {
    config(['broadcasting.default' => 'pusher', 'broadcasting.connections.pusher.options.host' => 'socket.example.test']);
    RealtimeSettings::apply();
    expect(config('broadcasting.default'))->toBe('pusher');
    SystemSetting::set('integration_pusher_enabled', '1');
    RealtimeSettings::apply();
    expect(config('broadcasting.connections.pusher.options.host'))->toBe('socket.example.test');
});
