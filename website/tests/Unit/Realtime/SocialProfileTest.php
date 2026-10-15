<?php

use App\Models\User;
use App\Services\Friends\ContactNames;
use App\Services\Friends\FriendFinder;
use App\Services\Friends\FriendService;
use App\Support\PhoneKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::unprepared(file_get_contents(base_path('tests/Fixtures/realtime.sqlite.sql')));
    DB::statement('CREATE TABLE friend_requests (id INTEGER PRIMARY KEY, requester_id INTEGER, addressee_id INTEGER, status TEXT, created_at TEXT, updated_at TEXT, responded_at TEXT, unfriended_at TEXT, unfriended_by INTEGER)');
    DB::statement('CREATE TABLE workspace_members (workspace_id INTEGER, user_id INTEGER, role TEXT)');
    DB::statement('CREATE TABLE user_presence (user_id INTEGER, findable INTEGER, show_presence INTEGER, last_seen_at TEXT)');
    DB::statement('CREATE TABLE friend_dismissals (user_id INTEGER, other_user_id INTEGER)');
    Cache::flush();
    foreach ([1 => 'Viewer', 2 => 'Alex', 3 => 'Other viewer'] as $id => $name) {
        DB::table('users')->insert(['notification_preferences' => '{}', 'id' => $id, 'uuid' => 'synthetic-'.$id, 'name' => $name, 'email' => $id.'@example.test', 'username' => 'user'.$id]);
    }
    DB::table('users')->where('id', 2)->update(['phone' => '+12025550199', 'phone_key' => PhoneKey::of('+12025550199'), 'phone_verified_at' => now(), 'discoverable' => 1]);
});

test('contact sync names are viewer private and replace removed or renamed contacts', function () {
    $names = app(ContactNames::class);
    $viewer = User::find(1); $alex = User::find(2);
    $names->sync($viewer, [['key' => '2025550199', 'name' => 'William']]);
    expect($names->fields($viewer, $alex))->toBe(['name' => 'William', 'profile_name' => 'Alex', 'contact_name' => 'William']);
    expect($names->fields(User::find(3), $alex)['name'])->toBe('Alex');
    expect(app(FriendFinder::class)->search($viewer, 'user2')['results'][0]['name'])->toBe('William');
    expect(app(FriendService::class)->suggestionsForKeys($viewer, ['2025550199'])[0]['name'])->toBe('William');
    $names->sync($viewer, [['key' => '2025550199', 'name' => 'Will']]);
    expect($names->fields($viewer, $alex)['name'])->toBe('Will');
    $names->sync($viewer, []);
    expect($names->fields($viewer, $alex)['name'])->toBe('Alex');
    expect($alex->fresh()->name)->toBe('Alex');
});

test('phone migration rejects existing formatted duplicates without modifying accounts', function () {
    DB::table('users')->where('id', 3)->update(['phone' => '+1 (202) 555-0199']);
    $migration = require database_path('migrations/2026_10_13_000002_enforce_unique_user_phone.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Duplicate mobile numbers exist');
    expect(User::find(3)->phone)->toBe('+1 (202) 555-0199');
});

test('phone uniqueness covers formatted edits and concurrent database writes', function () {
    $migration = require database_path('migrations/2026_10_13_000002_enforce_unique_user_phone.php');
    $migration->up();
    expect(fn () => User::find(3)->update(['phone' => '+1 (202) 555-0199']))
        ->toThrow(ValidationException::class, 'This mobile number is already registered to another account.');
    expect(fn () => DB::table('users')->where('id', 3)->update(['phone' => '+12025550199']))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
    User::find(1)->update(['phone' => null]);
    User::find(3)->update(['phone' => null]);
    expect(User::whereNull('phone')->count())->toBe(2);
});

test('mobile tokens survive years while ordinary tokens expire and explicit revocation still works', function () {
    DB::statement('CREATE TABLE personal_access_tokens (id INTEGER PRIMARY KEY, tokenable_type TEXT, tokenable_id INTEGER, name TEXT, token TEXT, abilities TEXT, last_used_at TEXT, expires_at TEXT, created_at TEXT, updated_at TEXT)');
    $user = User::find(1);
    $mobile = $user->createToken('Synthetic phone', ['*', 'mobile-session']);
    $ordinary = $user->createToken('Integration', ['*']);
    $guard = new Laravel\Sanctum\Guard(app('auth'), 240, 'users');
    $valid = new ReflectionMethod($guard, 'isValidAccessToken');
    $this->travel(5)->years();
    expect($valid->invoke($guard, $mobile->accessToken->fresh()))->toBeTrue();
    expect($valid->invoke($guard, $ordinary->accessToken->fresh()))->toBeFalse();
    $mobile->accessToken->update(['expires_at' => now()->subMinute()]);
    expect($valid->invoke($guard, $mobile->accessToken->fresh()))->toBeFalse();
    $mobile->accessToken->delete();
    expect(Laravel\Sanctum\PersonalAccessToken::findToken($mobile->plainTextToken))->toBeNull();
});

test('web lifetime uses the administrator setting without changing token expiration', function () {
    App\Support\SecuritySettings::setValue('session_lifetime_minutes', 45);
    $middleware = new App\Http\Middleware\WebSessionLifetime;
    $middleware->handle(Illuminate\Http\Request::create('/'), fn () => response('ok'));
    expect(config('session.lifetime'))->toBe(45);
    expect(config('sanctum.expiration'))->toBe(240);
});

test('registration rejects a registered phone before provisioning an account', function () {
    App\Models\SystemSetting::set('phone_registration', 'optional');
    // Use the public validation path shared by both web and mobile registration.
    expect(fn () => app(App\Services\Friends\RegistrationPhone::class)->validated(['phone_country' => 'US', 'phone_national' => '(202) 555-0199']))
        ->toThrow(ValidationException::class, 'This mobile number is already registered to another account.');
});

test('legacy mobile sessions are upgraded without changing integration tokens', function () {
    DB::statement('CREATE TABLE personal_access_tokens (id INTEGER PRIMARY KEY, name TEXT, abilities TEXT, expires_at TEXT)');
    DB::table('personal_access_tokens')->insert([
        ['id' => 1, 'name' => 'Dahimail mobile app', 'abilities' => '["*"]'],
        ['id' => 2, 'name' => 'Integration', 'abilities' => '["*"]'],
    ]);
    $migration = require database_path('migrations/2026_10_13_000003_mark_existing_mobile_sessions.php');
    $migration->up(); $migration->up();
    expect(json_decode(DB::table('personal_access_tokens')->where('id', 1)->value('abilities'), true))->toBe(['*', 'mobile-session']);
    expect(json_decode(DB::table('personal_access_tokens')->where('id', 2)->value('abilities'), true))->toBe(['*']);
});

test('friendship history retains timestamped additions and removals across refriending', function () {
    Illuminate\Support\Facades\Notification::fake();
    DB::statement('CREATE TABLE friendship_events (id INTEGER PRIMARY KEY, user_low_id INTEGER, user_high_id INTEGER, actor_id INTEGER, event TEXT, created_at TEXT)');
    $id = DB::table('friend_requests')->insertGetId(['requester_id' => 1, 'addressee_id' => 2, 'status' => 'pending', 'created_at' => now()]);
    $service = app(FriendService::class);
    expect($service->respond(User::find(2), $id, 'accept')[0])->toBeTrue();
    expect($service->remove(User::find(1), 2)[0])->toBeTrue();
    expect($service->send(User::find(1), 2)[0])->toBeTrue();
    expect($service->respond(User::find(2), $id, 'accept')[0])->toBeTrue();
    $history = $service->history(User::find(1), 2);
    expect(array_column($history, 'event'))->toBe(['became_friends', 'requested', 'unfriended', 'became_friends']);
    foreach ($history as $event) expect($event['at'])->not->toBeEmpty();
});

test('mobile sync accepts contact names and an empty address book clears them', function () {
    $controller = app(App\Http\Controllers\Api\Mobile\FriendsController::class);
    $request = Illuminate\Http\Request::create('/friends/sync-contacts', 'POST', ['keys' => ['2025550199'], 'contacts' => [['key' => '2025550199', 'name' => 'William']]]);
    $request->setUserResolver(fn () => User::find(1));
    $response = $controller->syncContacts($request)->getData(true);
    expect($response['data'][0]['name'])->toBe('William');
    $empty = Illuminate\Http\Request::create('/friends/sync-contacts', 'POST', ['keys' => [], 'contacts' => []]);
    $empty->setUserResolver(fn () => User::find(1));
    expect($controller->syncContacts($empty)->getData(true)['data'])->toBe([]);
    expect(DB::table('device_contact_names')->count())->toBe(0);
});

test('duplicate phone report shows account IDs without changing any phone assignments', function () {
    DB::table('users')->where('id', 3)->update(['phone' => '+1 (202) 555-0199']);
    $before = DB::table('users')->orderBy('id')->get()->toJson();
    $this->artisan('phones:duplicates')->expectsOutputToContain('Alex')->expectsOutputToContain('Other viewer')->assertSuccessful();
    expect(DB::table('users')->orderBy('id')->get()->toJson())->toBe($before);
});

test('duplicate phone cleanup clears only selected assignments and lets the migration finish', function () {
    DB::table('users')->where('id', 3)->update(['phone' => '+1 (202) 555-0199', 'phone_key' => '2025550199', 'phone_verified_at' => now(), 'discoverable' => 1]);
    Cache::put('phone_code:3', ['phone' => '+12025550199']);
    $this->artisan('phones:duplicates', ['--clear' => ['3']])
        ->expectsConfirmation('Remove the phone number and phone verification from account IDs 3?', 'yes')->assertSuccessful();
    $cleared = DB::table('users')->find(3);
    expect($cleared->phone)->toBeNull()->and($cleared->phone_key)->toBeNull()->and($cleared->phone_verified_at)->toBeNull()->and((int) $cleared->discoverable)->toBe(0);
    expect(User::find(2)->phone)->toBe('+12025550199');
    expect(Cache::has('phone_code:3'))->toBeFalse();
    $migration = require database_path('migrations/2026_10_13_000002_enforce_unique_user_phone.php');
    $migration->up();
    expect(fn () => DB::table('users')->where('id', 3)->update(['phone' => '+12025550199']))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

test('duplicate phone cleanup cannot remove every owner of a number', function () {
    DB::table('users')->where('id', 3)->update(['phone' => '+1 (202) 555-0199']);
    $this->artisan('phones:duplicates', ['--clear' => ['2', '3']])
        ->expectsConfirmation('Remove the phone number and phone verification from account IDs 2, 3?', 'yes')->assertFailed();
    expect(User::find(2)->phone)->toBe('+12025550199')->and(User::find(3)->phone)->toBe('+1 (202) 555-0199');
});

test('duplicate phone cleanup rejects unrelated IDs atomically', function () {
    DB::table('users')->where('id', 1)->update(['phone' => '+12025550200']);
    DB::table('users')->where('id', 3)->update(['phone' => '+1 (202) 555-0199']);
    $this->artisan('phones:duplicates', ['--clear' => ['1', '3']])
        ->expectsConfirmation('Remove the phone number and phone verification from account IDs 1, 3?', 'yes')->assertFailed();
    expect(User::find(1)->phone)->toBe('+12025550200')->and(User::find(3)->phone)->toBe('+1 (202) 555-0199');
});

test('duplicate phone cleanup cancellation makes no changes', function () {
    DB::table('users')->where('id', 3)->update(['phone' => '+1 (202) 555-0199']);
    $this->artisan('phones:duplicates', ['--clear' => ['3']])
        ->expectsConfirmation('Remove the phone number and phone verification from account IDs 3?', 'no')->assertSuccessful();
    expect(User::find(3)->phone)->toBe('+1 (202) 555-0199');
});
