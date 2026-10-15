<?php

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\CredentialPolicy;
use App\Support\SecuritySettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
    DB::purge('sqlite');
    DB::unprepared(file_get_contents(base_path('tests/Fixtures/realtime.sqlite.sql')));
    Cache::flush();
    $this->user = new User(['name' => 'Synthetic User', 'email' => 'synthetic@example.test', 'password' => 'PreviousPassword1!']);
    $this->user->id = 1;
    $this->actingAs($this->user);
});

function validatesCredential(string $password): bool {
    return Validator::make(['password' => $password, 'password_confirmation' => $password], ['password' => CredentialPolicy::passwordRules()])->passes();
}

test('password length and character requirements can be tightened or relaxed independently', function () {
    expect(validatesCredential('ValidPassword1!'))->toBeTrue();
    foreach (['alllowercase1!', 'ALLUPPERCASE1!', 'NoNumbersHere!', 'NoSymbolsHere1'] as $value) expect(validatesCredential($value))->toBeFalse();
    SecuritySettings::update(['password_min_length' => 6, 'password_max_length' => 8, 'password_require_uppercase' => false, 'password_require_lowercase' => false, 'password_require_numbers' => false, 'password_require_symbols' => false]);
    expect(validatesCredential('abcdef'))->toBeTrue()->and(validatesCredential('12345678'))->toBeTrue();
    expect(validatesCredential('12345'))->toBeFalse()->and(validatesCredential('123456789'))->toBeFalse();
    SecuritySettings::update(['password_require_lowercase' => true]);
    expect(validatesCredential('ABCDEF'))->toBeFalse()->and(validatesCredential('abcDEF'))->toBeTrue();
});

test('unicode password lengths cannot bypass the hash byte limit', function () {
    SecuritySettings::update(['password_require_uppercase' => false, 'password_require_lowercase' => false, 'password_require_numbers' => false, 'password_require_symbols' => false]);
    expect(validatesCredential(str_repeat('é', 36)))->toBeTrue();
    expect(validatesCredential(str_repeat('é', 37)))->toBeFalse();
    expect(validatesCredential("Abcdefgh1!\0"))->toBeFalse();
});

test('username policy preserves email syntax while allowing admin character restrictions', function () {
    expect(CredentialPolicy::usernameProblem('Alice.Name-1'))->toBeNull();
    foreach (['ab', 'a..b', '.alice', 'alice-', 'alice@other.test', 'ali ce'] as $u) expect(CredentialPolicy::usernameProblem($u))->not->toBeNull();
    SecuritySettings::update(['username_min_length' => 4, 'username_max_length' => 6, 'username_allow_uppercase' => false, 'username_allow_numbers' => false, 'username_allow_dots' => false, 'username_allow_dashes' => false, 'username_allow_underscores' => false]);
    expect(CredentialPolicy::usernameProblem('alice'))->toBeNull();
    foreach (['Alice', 'alice1', 'a.lice', 'a-lice', 'a_lice', 'ali', 'alicely'] as $u) expect(CredentialPolicy::usernameProblem($u))->not->toBeNull();
});

test('admin group validation accepts the policy form without unrelated settings and rejects invalid ranges', function () {
    $data = array_filter(CredentialPolicy::publicRules(), fn ($k) => !str_ends_with($k, '_help'), ARRAY_FILTER_USE_KEY);
    $request = App\Http\Requests\Admin\UpdateSecuritySettingsRequest::create('/', 'POST', ['_settings_group' => 'password'] + $data);
    $request->setUserResolver(fn () => $this->user);
    expect($request->authorize())->toBeFalse();
    $this->user->is_admin = true;
    expect($request->authorize())->toBeTrue();
    $validator = Validator::make($request->all() + ['captcha_enabled' => true], $request->rules());
    expect($validator->passes())->toBeTrue()->and($validator->validated())->not->toHaveKey('captcha_enabled');
    foreach ([['password_min_length' => 73], ['password_min_length' => 20, 'password_max_length' => 10], ['username_min_length' => 8, 'username_max_length' => 4], ['username_max_length' => 65]] as $invalid) {
        expect(Validator::make(array_replace($request->all(), $invalid), $request->rules())->fails())->toBeTrue();
    }
});

test('public mobile policy includes current hints but never security secrets', function () {
    SystemSetting::set('security.captcha_secret_key', 'synthetic-private-value');
    SecuritySettings::update(['password_min_length' => 15, 'username_max_length' => 22]);
    $payload = app(App\Http\Controllers\Api\Mobile\AuthExtrasController::class)->phoneConfig()->getData(true)['data']['credential_policy'];
    expect($payload['password_min_length'])->toBe(15)->and($payload['username_max_length'])->toBe(22);
    expect($payload['password_help'])->toContain('15–72');
    expect(json_encode($payload))->not->toContain('synthetic-private-value')->not->toContain('captcha');
});

test('web and mobile password entry points enforce an updated policy before making changes', function (string $flow) {
    SecuritySettings::update(['password_min_length' => 25]);
    $data = ['name' => 'Synthetic User', 'username' => 'synthetic', 'email' => 'synthetic@example.test', 'terms' => true,
        'password' => 'ValidPassword1!', 'password_confirmation' => 'ValidPassword1!', 'current_password' => 'PreviousPassword1!',
        'token' => 'synthetic', 'code' => '123456', 'phrase' => implode(' ', array_fill(0, 12, 'test'))];
    $request = Request::create('/', 'POST', $data);
    $request->setUserResolver(fn () => $this->user);
    app()->instance('request', $request);
    $this->mock(App\Services\CyberPanelMailbox::class)->shouldNotReceive('updatePassword');
    $this->mock(App\Services\SignupGuard::class)->shouldReceive('checkBot')->zeroOrMoreTimes();
    try {
        $result = match ($flow) {
            'web signup' => app(App\Actions\Fortify\CreateNewUser::class)->create($data),
            'web change' => app(App\Actions\Fortify\UpdateUserPassword::class)->update($this->user, $data),
            'web reset' => app(App\Actions\Fortify\ResetUserPassword::class)->reset($this->user, $data),
            'mobile signup' => app(App\Http\Controllers\Api\AuthController::class)->register($request),
            'mobile reset' => app(App\Http\Controllers\Api\AuthController::class)->resetPassword($request),
            'mobile phrase' => app(App\Http\Controllers\Api\Mobile\AuthExtrasController::class)->recoverWithPhrase($request),
            'mobile code' => app(App\Http\Controllers\Api\Mobile\AuthExtrasController::class)->resetWithCode($request),
            'mobile change' => app(App\Http\Controllers\Api\Mobile\MeController::class)->password($request, app(App\Services\CyberPanelMailbox::class), app(App\Mailbox\MailboxCredentials::class)),
        };
        expect($result->getStatusCode())->toBe(422)->and($result->getData(true)['errors'])->toHaveKey('password');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('password');
    }
})->with(['web signup', 'web change', 'web reset', 'mobile signup', 'mobile reset', 'mobile phrase', 'mobile code', 'mobile change']);

test('saving credential rules persists toggles without resetting another security tab', function () {
    DB::statement('CREATE TABLE security_audit_logs (id INTEGER PRIMARY KEY, user_id INTEGER, event_type TEXT, status TEXT, ip_address TEXT, user_agent TEXT, metadata TEXT, logged_at TEXT, created_at TEXT, updated_at TEXT)');
    SecuritySettings::update(['captcha_enabled' => true]);
    $data = array_filter(CredentialPolicy::publicRules(), fn ($k) => !str_ends_with($k, '_help'), ARRAY_FILTER_USE_KEY);
    $data['password_min_length'] = 14;
    $data['password_require_uppercase'] = 'false';
    $data['password_require_lowercase'] = 'true';
    $this->user->is_admin = true;
    $request = App\Http\Requests\Admin\UpdateSecuritySettingsRequest::create('/', 'POST', ['_settings_group' => 'password', 'captcha_enabled' => false] + $data);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $this->user);
    $request->validateResolved();
    $response = app(App\Http\Controllers\Admin\SecuritySettingsController::class)->update($request);
    expect($response->getStatusCode())->toBe(302);
    expect(CredentialPolicy::publicRules()['password_min_length'])->toBe(14);
    expect(CredentialPolicy::publicRules()['password_require_uppercase'])->toBeFalse();
    expect(CredentialPolicy::publicRules()['password_require_lowercase'])->toBeTrue();
    expect(SystemSetting::enabled('security.captcha_enabled'))->toBeTrue();
    expect(DB::table('security_audit_logs')->count())->toBe(1);
});
