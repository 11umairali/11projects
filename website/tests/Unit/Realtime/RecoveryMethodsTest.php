<?php

use App\Http\Controllers\Admin\FrontendSettingsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Mobile\AuthExtrasController;
use App\Http\Middleware\EmailPasswordResetAccess;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\AccountFeatures;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
    DB::statement('CREATE TABLE system_settings (key TEXT PRIMARY KEY, value TEXT, "group" TEXT, updated_at TEXT)');
    DB::statement('CREATE TABLE languages (id INTEGER PRIMARY KEY, is_active INTEGER, sort_order INTEGER)');
    view()->share('errors', new \Illuminate\Support\ViewErrorBag());
    Cache::flush();
});

test('superadmin can independently configure each recovery method', function (bool $phrase, bool $email) {
    $user = new User(); $user->is_admin = true; $user->admin_role = 'super_admin';
    $data = array_map(fn ($value) => (int) $value, AccountFeatures::all());
    $data['phrase_recovery_enabled'] = (int) $phrase;
    $data['email_recovery_enabled'] = (int) $email;
    $request = Request::create('/settings', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    app(FrontendSettingsController::class)->updateAccountFeatures($request);
    expect(AccountFeatures::phraseRecovery())->toBe($phrase)
        ->and(AccountFeatures::emailRecovery())->toBe($email);
    $html = view('auth.forgot-password')->render();
    expect(str_contains($html, 'href="'.route('password.phrase').'"'))->toBe($phrase)
        ->and(str_contains($html, 'action="'.route('password.email').'"'))->toBe($email);
})->with([[true, true], [true, false], [false, true], [false, false]]);

test('ordinary admins cannot change recovery methods', function () {
    $user = new User(); $user->is_admin = true; $user->admin_role = 'admin';
    $request = Request::create('/settings', 'PUT', ['phrase_recovery_enabled' => 0]);
    $request->setUserResolver(fn () => $user);
    try { app(FrontendSettingsController::class)->updateAccountFeatures($request); $this->fail('Expected forbidden'); }
    catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { expect($e->getStatusCode())->toBe(403); }
    expect(AccountFeatures::phraseRecovery())->toBeTrue();
});

test('disabled methods reject API requests before validating secrets', function () {
    SystemSetting::set('account.phrase_recovery_enabled', 'false', 'account');
    SystemSetting::set('account.email_recovery_enabled', 'false', 'account');
    $request = Request::create('/reset', 'POST');
    foreach ([
        [AuthExtrasController::class, 'recoverWithPhrase'],
        [AuthExtrasController::class, 'sendCode'],
        [AuthExtrasController::class, 'resetWithCode'],
        [AuthController::class, 'forgotPassword'],
        [AuthController::class, 'resetPassword'],
    ] as [$controller, $method]) {
        expect(app($controller)->$method($request)->getStatusCode())->toBe(403);
    }
});

test('disabled web reset routes return to the method chooser', function (string $name) {
    SystemSetting::set('account.phrase_recovery_enabled', 'false', 'account');
    SystemSetting::set('account.email_recovery_enabled', 'false', 'account');
    $request = Request::create('/reset', 'POST');
    $route = new Route('POST', '/reset', fn () => null);
    $route->name($name);
    $request->setRouteResolver(fn () => $route);
    $response = app(EmailPasswordResetAccess::class)->handle($request, fn () => response('Unexpected access', 500));
    expect($response->getTargetUrl())->toBe(route('password.request'));
})->with(['password.phrase', 'password.phrase.update', 'password.email', 'password.reset', 'password.update']);
