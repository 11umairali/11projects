<?php

use App\Http\Controllers\Api\Mobile\MeController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'services.fcm.credentials' => null]);
    DB::purge('sqlite');
    Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('name'); });
    DB::table('users')->insert(['id' => 1, 'name' => 'Synthetic user']);
    (require database_path('migrations/2026_09_29_000001_create_device_tokens_table.php'))->up();
});

test('Android token registration survives a database created by the shipped base migration', function () {
    $user = User::find(1);
    $request = Request::create('/api/v1/me/devices', 'POST', ['token' => 'synthetic-device', 'platform' => 'android', 'app_version' => '1.0.0+2']);
    $request->setUserResolver(fn () => $user);
    $response = app(MeController::class)->registerDevice($request);
    expect($response->getStatusCode())->toBe(200);
    expect(DB::table('device_tokens')->where('token', 'synthetic-device')->value('user_id'))->toBe(1);
});

test('push metadata migration is repeatable and preserves existing device registrations', function () {
    DB::table('device_tokens')->insert(['token' => 'before-update', 'user_id' => 1, 'platform' => 'android']);
    $migration = require database_path('migrations/2026_10_08_000001_complete_device_push_registration.php');
    $migration->up();
    $migration->up();
    expect(Schema::hasColumns('device_tokens', ['voip_token', 'app_version', 'apns_sandbox']))->toBeTrue();
    expect(DB::table('device_tokens')->where('token', 'before-update')->value('user_id'))->toBe(1);
    $request = Request::create('/api/v1/me/devices', 'POST', ['token' => 'after-update', 'platform' => 'ios', 'app_version' => '1.0.0+2', 'voip_token' => 'synthetic-voip', 'apns_sandbox' => true]);
    $request->setUserResolver(fn () => User::find(1));
    app(MeController::class)->registerDevice($request);
    $device = DB::table('device_tokens')->where('token', 'after-update')->first();
    expect($device->app_version)->toBe('1.0.0+2')->and($device->voip_token)->toBe('synthetic-voip')->and($device->apns_sandbox)->toBe(1);
});

test('legacy schemas never claim native iOS calls are configured when the VoIP token cannot be saved', function () {
    $apns = Mockery::mock(App\Services\ApnsVoip::class);
    $apns->shouldReceive('enabled')->zeroOrMoreTimes()->andReturn(true);
    app()->instance(App\Services\ApnsVoip::class, $apns);
    $request = Request::create('/api/v1/me/devices', 'POST', ['token' => 'synthetic-ios', 'platform' => 'ios', 'voip_token' => 'synthetic-voip', 'apns_sandbox' => true]);
    $request->setUserResolver(fn () => User::find(1));
    $response = app(MeController::class)->registerDevice($request)->getData(true);
    expect($response['voip_configured'])->toBeFalse();
    expect($response['registration_needs_migration'])->toBeTrue();
});

test('push connection check cannot validate another accounts token', function () {
    DB::table('users')->insert(['id' => 2, 'name' => 'Other synthetic user']);
    DB::table('device_tokens')->insert(['user_id' => 2, 'token' => 'other-private-token', 'platform' => 'android']);
    $request = Request::create('/api/v1/me/devices/check-push', 'POST', ['token' => 'other-private-token']);
    $request->setUserResolver(fn () => User::find(1));
    expect(fn () => app(MeController::class)->checkPush($request))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});
