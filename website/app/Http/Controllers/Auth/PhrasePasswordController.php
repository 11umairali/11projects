<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RecoveryPhrase;
use App\Support\CredentialPolicy;
use App\Support\MailDomains;
use App\Support\Username;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PhrasePasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password-phrase');
    }

    public function store(Request $request, RecoveryPhrase $phrases, ResetUserPassword $reset): RedirectResponse
    {
        // Only the identifier may be flashed. Recovery words and passwords are secrets.
        $fail = fn (array $errors) => redirect()->route('password.phrase')
            ->withErrors($errors)->withInput($request->only('username'));
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:255'],
            'phrase' => ['required', 'string', 'max:400'],
            'password' => CredentialPolicy::passwordRules(),
        ]);
        if ($validator->fails()) return $fail($validator->errors()->toArray());
        $data = $validator->validated();
        $login = strtolower(trim($data['username']));
        $username = MailDomains::username($login);
        $key = 'recover:'.sha1(($username ?? $login).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $fail(['phrase' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        RateLimiter::hit($key, 900);

        $matches = User::where('status', 'active')->where(function ($query) use ($login, $username) {
            $query->whereRaw('LOWER(email) = ?', [$login]);
            if ($username !== null) $query->orWhereRaw(Username::SQL.' = ?', [$username]);
        })->limit(2)->get();
        $user = $matches->count() === 1 ? $matches->first() : null;
        if (!$user || !$phrases->check($data['phrase'], $user->recovery_phrase_hash)) {
            return $fail(['phrase' => __('Username and recovery phrase do not match.')]);
        }
        $plainUsername = Username::plain($user->username);
        if ($plainUsername !== '' && str_contains(strtolower($data['password']), strtolower($plainUsername))) {
            return $fail(['password' => __('Your password must not contain your username.')]);
        }

        try {
            DB::transaction(function () use ($reset, $user, $request) {
                $reset->reset($user, $request->only('password', 'password_confirmation'));
                $user->forceFill(['remember_token' => Str::random(60), 'force_password_reset' => false])->save();
                $user->tokens()->delete();
                Password::broker()->deleteToken($user);
            });
        } catch (ValidationException $e) {
            return $fail($e->errors());
        } catch (Throwable $e) {
            report($e);
            return $fail(['password' => __('Unable to reset your password right now. Please try again later.')]);
        }

        RateLimiter::clear($key);
        return redirect()->route('login')->with('status', __('Password changed. Sign in with your new password.'));
    }
}
