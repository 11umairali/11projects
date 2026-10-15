<?php

namespace App\Services\Friends;

use App\Models\User;
use App\Notifications\FriendNotification;
use App\Support\PhoneSettings;
use Illuminate\Validation\ValidationException;

/** Phone number at sign-up: country picked from a list + the national number typed by the person. */
class RegistrationPhone
{
    public static function countries(): array
    {
        return config('phone_countries', []);
    }

    public static function dial(?string $iso): ?string
    {
        $iso = strtoupper(trim((string) $iso));
        foreach (self::countries() as $c) {
            if ($c['iso'] === $iso) return $c['dial'];
        }
        return null;
    }

    /**
     * Country code (ISO) + typed number → international format.
     * @return array{0:?string,1:?string} [e164, error]  ([null,null] = nothing typed)
     */
    public static function build(?string $iso, ?string $national): array
    {
        $national = trim((string) $national);
        if ($national === '') return [null, null];
        $dial = self::dial($iso);
        if (!$dial) return [null, 'Choose the country code.'];
        $dialDigits = ltrim($dial, '+');
        $digits = preg_replace('/\D+/', '', $national);
        // typed with the country code already (+92 300 …) → remove it
        if (str_starts_with($national, '+') && str_starts_with($digits, $dialDigits)) $digits = substr($digits, strlen($dialDigits));
        $digits = ltrim($digits, '0'); // national trunk prefix (0300… → 300…)
        if (strlen($digits) < 4 || strlen($digits) > 14 || strlen($dialDigits . $digits) > 15) return [null, 'Enter a valid phone number.'];
        return [$dial . $digits, null];
    }

    /** Sign-up validation. Returns the international number (or null) – throws a validation error on bad input. */
    public function validated(array $input): ?string
    {
        $mode = PhoneSettings::registrationMode();
        if ($mode === 'off') return null;
        [$e164, $err] = self::build($input['phone_country'] ?? null, $input['phone_national'] ?? null);
        if ($err) throw ValidationException::withMessages(['phone_national' => $err]);
        if ($mode === 'required' && !$e164) throw ValidationException::withMessages(['phone_national' => 'Please enter your phone number.']);
        if ($e164 && User::where('phone', $e164)->exists()) throw ValidationException::withMessages(['phone_national' => 'This mobile number is already registered to another account.']);
        return $e164;
    }

    /** Saves the number (NOT verified). If verification is available the person gets a reminder to verify it. */
    public function attach(User $user, ?string $e164): void
    {
        if (!$e164) return;
        $user->forceFill(['phone' => $e164])->save();
        try {
            if (app(PhoneVerifier::class)->available()) $user->notify(FriendNotification::verifyPhone());
        } catch (\Throwable $e) {
            // a reminder must never break sign-up
        }
    }
}
