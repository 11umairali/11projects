<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Cheap checks that stop most signup bots without bothering people:
 * a hidden field only bots fill in, a minimum time to fill the form, and
 * a site-wide hourly cap. Ported from Dahify; the admin-email alert on
 * flood/abuse has been simplified to a log entry (wire it into the SaaS
 * app's own alerting/notifications system if you want an email/Slack
 * ping instead).
 */
class SignupGuard
{
    public const HONEYPOT = 'website';

    public const TOKEN = 'form_token';

    /** Put in the form when it's shown. */
    public function token(): string
    {
        return Crypt::encryptString((string) time());
    }

    public function checkBot(Request $request): void
    {
        if (filled($request->input(self::HONEYPOT))) {
            Log::notice('Signup blocked: hidden field filled', ['ip' => $request->ip()]);
            $this->fail('Something went wrong. Please try again.');
        }

        try {
            $shownAt = (int) Crypt::decryptString((string) $request->input(self::TOKEN));
        } catch (DecryptException) {
            $this->fail('This form has expired. Please reload the page and try again.');
        }

        $age = time() - $shownAt;
        if ($age < config('dahify.signup.min_seconds')) {
            Log::notice('Signup blocked: form sent too fast', ['ip' => $request->ip(), 'seconds' => $age]);
            $this->fail('That was quick! Please check your details and press Create account again.');
        }
        if ($age > 2 * 3600) {
            $this->fail('This form has expired. Please reload the page and try again.');
        }
    }

    public function checkSiteWideRate(): void
    {
        $limit = config('dahify.signup.per_hour');
        $lastHour = User::where('created_at', '>=', now()->subHour())->count();

        if ($lastHour >= $limit) {
            Log::warning('Signup limit reached', ['count' => $lastHour, 'limit' => $limit]);
            $this->fail("We're getting a lot of signups right now. Please try again in a little while.");
        }
    }

    public function afterSignup(User $user): void
    {
        if (! $user->signup_ip) {
            return;
        }

        $fromIp = User::where('signup_ip', $user->signup_ip)->where('created_at', '>=', now()->subWeek())->count();

        if ($fromIp >= 3) {
            Log::warning('Many signups from one IP', ['ip' => $user->signup_ip, 'count' => $fromIp, 'latest' => $user->email]);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['username' => $message]);
    }
}
