<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile captcha. When no secret key is configured
 * (local development) the check is skipped.
 */
class Turnstile
{
    public function enabled(): bool
    {
        return filled(config('dahify.turnstile.secret_key'));
    }

    public function verify(?string $token, ?string $ip): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        $response = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('dahify.turnstile.secret_key'),
            'response' => $token,
            'remoteip' => $ip,
        ]);

        return $response->ok() && $response->json('success') === true;
    }
}
