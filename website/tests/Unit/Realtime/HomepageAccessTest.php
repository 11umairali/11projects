<?php

use App\Http\Controllers\Admin\FrontendSettingsController;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
    DB::statement('CREATE TABLE system_settings (key TEXT PRIMARY KEY, value TEXT, "group" TEXT, updated_at TEXT)');
    Cache::flush();
});

test('only a superadmin can change public homepage access', function (bool $admin, string $role) {
    $user = new User(); $user->is_admin = $admin; $user->admin_role = $role;
    $request = Request::create('/admin/frontend-settings/homepage-access', 'PUT', ['frontend_homepage_enabled' => '0']);
    $request->setUserResolver(fn () => $user);
    try { app(FrontendSettingsController::class)->updateHomepageAccess($request); $this->fail('Expected forbidden'); }
    catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { expect($e->getStatusCode())->toBe(403); }
    expect(SystemSetting::enabled('frontend_homepage_enabled', false))->toBeFalse();
})->with([[false, 'super_admin'], [true, 'admin']]);

test('superadmin can disable and re-enable the homepage without a migration', function () {
    $user = new User(); $user->is_admin = true; $user->admin_role = 'super_admin';
    foreach (['0' => false, '1' => true] as $input => $expected) {
        $request = Request::create('/admin/frontend-settings/homepage-access', 'PUT', ['frontend_homepage_enabled' => (string) $input]);
        $request->setUserResolver(fn () => $user);
        app(FrontendSettingsController::class)->updateHomepageAccess($request);
        expect(SystemSetting::enabled('frontend_homepage_enabled', true))->toBe($expected);
    }
});

test('disabled homepage routes visitors to login and signed-in users to dashboard', function () {
    SystemSetting::set('frontend_homepage_enabled', 'false', 'frontend');
    $route = app('router')->getRoutes()->match(Request::create('/', 'GET'));
    $this->assertSame(url('/login'), $route->run()->getTargetUrl());
    $user = new User(); $user->id = 1; auth()->setUser($user);
    $this->assertSame(url('/dashboard'), $route->run()->getTargetUrl());
});


test('homepage defaults to login without a saved administrator choice', function () {
    $route = app('router')->getRoutes()->match(Request::create('/', 'GET'));
    expect($route->run()->getTargetUrl())->toBe(url('/login'));
    DB::statement('CREATE TABLE pages (id INTEGER PRIMARY KEY, type TEXT, content TEXT)');
    SystemSetting::set('frontend_homepage_enabled', 'true', 'frontend');
    expect($route->run())->toBeInstanceOf(\Illuminate\View\View::class);
});
