<?php

namespace App\Services\Friends;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\PhoneKey;
use App\Support\PhoneSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

/**
 * Phone numbers: optional verification by SMS (Twilio) or WhatsApp (Cloud API), controlled by the super admin
 * (Admin → Phone Verification).
 *   • verification ON  → a number counts only after a code sent to it is entered; only then can the person be discovered.
 *   • verification OFF → a number is simply saved on the account (unverified). It is NEVER used for friend discovery,
 *     otherwise anyone could type another person's number to find out who has them saved.
 */
class PhoneVerifier
{
    private static array $hasCol = [];

    private function hasCol(string $c): bool
    {
        return self::$hasCol[$c] ??= Schema::hasColumn('users', $c);
    }

    /** Saves attributes, skipping columns that do not exist yet; a database problem is logged, never thrown at the person. */
    private function persist(User $u, array $attrs): bool
    {
        $attrs = array_filter($attrs, fn ($v, $k) => $k === 'phone' || $this->hasCol($k), ARRAY_FILTER_USE_BOTH);
        try {
            $u->forceFill($attrs)->save();
            return true;
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['phone_national' => 'This mobile number is already registered to another account.']);
        } catch (\Throwable $e) {
            Log::error('Phone number could not be saved: ' . $e->getMessage());
            return false;
        }
    }

    public function channels(): array { return PhoneSettings::channels(); }

    /** Is code verification possible right now (switched on by the super admin AND at least one channel works)? */
    public function available(): bool { return count($this->channels()) > 0; }

    /** @deprecated use RegistrationPhone::build() – kept for older callers (full international number only) */
    public static function normalize(string $input): ?string
    {
        $p = preg_replace('/[^\d+]/', '', trim($input));
        if (!str_starts_with($p, '+')) return null;
        $digits = substr($p, 1);
        if (!ctype_digit($digits) || strlen($digits) < 8 || strlen($digits) > 15) return null;
        return '+' . $digits;
    }

    public static function mask(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);
        if (strlen($d) <= 5) return $phone;
        return '+' . substr($d, 0, 2) . str_repeat('•', strlen($d) - 5) . substr($d, -3);
    }

    /** The stored number must be the verified one – editing it elsewhere (e.g. the profile form) revokes verification. */
    public function isVerified(User $u): bool
    {
        return $u->phone_verified_at !== null && $u->phone_key !== null && PhoneKey::of($u->phone) === $u->phone_key;
    }

    /** May this person be found by phone number? Verified, or unverified when the super admin allows it. */
    public function canDiscover(User $u): bool
    {
        if ($this->isVerified($u)) return true;
        return PhoneSettings::unverifiedDiscovery() && $u->phone && $u->phone_key !== null && PhoneKey::of($u->phone) === $u->phone_key;
    }

    public function status(User $u): array
    {
        $verified = $this->isVerified($u);
        $channels = $this->channels();
        return [
            'number' => $u->phone ? self::mask($u->phone) : null,
            'has_number' => (bool) $u->phone,
            'verified' => $verified,
            'discoverable' => $this->canDiscover($u) && (bool) $u->discoverable,
            'can_discover' => $verified || PhoneSettings::unverifiedDiscovery(),
            'discovery_available' => PhoneSettings::discoveryAvailable(),
            'unverified_discovery' => PhoneSettings::unverifiedDiscovery(),
            'verification_enabled' => $channels !== [],
            'channels' => $channels,
            'sms_available' => $channels !== [],      // older app versions
            'pending' => Cache::has("phone_code:{$u->id}"),
            'registration_mode' => PhoneSettings::registrationMode(),
        ];
    }

    /** Saves a number WITHOUT verification (used when the super admin switched verification off). */
    public function saveUnverified(User $u, string $e164): array
    {
        Cache::forget("phone_code:{$u->id}");
        return $this->persist($u, ['phone' => $e164, 'phone_key' => null, 'phone_verified_at' => null, 'discoverable' => false])
            ? [true, 'Phone number saved.']
            : [false, 'Your number could not be saved right now. Please try again later.'];
    }

    /** @return array{0:bool,1:string} */
    public function sendCode(User $u, string $e164, ?string $channel = null): array
    {
        $channels = $this->channels();
        if (!$channels) return [false, 'Phone verification is not available right now.'];
        $channel = $channel ?: $channels[0];
        if (!in_array($channel, $channels, true)) return [false, 'That way of receiving the code is not available.'];

        $limiter = "phone-send:{$u->id}";
        if (RateLimiter::tooManyAttempts($limiter, 3)) {
            return [false, 'Too many codes requested. Try again in ' . max(1, (int) ceil(RateLimiter::availableIn($limiter) / 60)) . ' minutes.'];
        }
        RateLimiter::hit($limiter, 3600);

        $ttl = (int) config('friends.code_ttl_minutes', 10);
        $code = (string) random_int(100000, 999999);
        Cache::put("phone_code:{$u->id}", ['phone' => $e164, 'channel' => $channel, 'hash' => Hash::make($code), 'tries' => 0], now()->addMinutes($ttl));

        try {
            if (PhoneSettings::testMode()) {
                Log::info("PHONE VERIFICATION CODE ({$channel}) for user {$u->id} ({$e164}): {$code}");
                return [true, 'Test mode: the code was written to the server log (storage/logs).'];
            }
            $ok = $channel === 'whatsapp' ? $this->sendWhatsApp($e164, $code) : $this->sendSms($e164, $code, $ttl);
            if (!$ok) {
                Cache::forget("phone_code:{$u->id}");
                return [false, 'The code could not be sent. Check the number and try again.'];
            }
        } catch (\Throwable $e) {
            Log::warning("Phone code ({$channel}) error: " . $e->getMessage());
            Cache::forget("phone_code:{$u->id}");
            return [false, 'The code could not be sent right now. Please try again later.'];
        }
        return [true, 'We sent a 6-digit code by ' . ($channel === 'whatsapp' ? 'WhatsApp' : 'SMS') . ' to ' . self::mask($e164) . '.'];
    }

    private function sendSms(string $e164, string $code, int $ttl): bool
    {
        $sid = SystemSetting::get('twilio_sid');
        $res = Http::asForm()->withBasicAuth($sid, SystemSetting::get('twilio_auth_token'))->timeout(15)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $e164,
                'From' => SystemSetting::get('twilio_phone_number'),
                'Body' => config('app.name') . " verification code: {$code}. It expires in {$ttl} minutes.",
            ]);
        if (!$res->successful()) Log::warning('Phone code SMS failed: HTTP ' . $res->status());
        return $res->successful();
    }

    private function sendWhatsApp(string $e164, string $code): bool
    {
        $id = SystemSetting::get('whatsapp_phone_number_id');
        $tpl = trim(SystemSetting::get('whatsapp_otp_template'));
        $payload = ['messaging_product' => 'whatsapp', 'to' => ltrim($e164, '+')];
        if ($tpl !== '') {
            // WhatsApp only lets a business start a conversation with an APPROVED template (an "authentication" template).
            $components = [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]]];
            if (SystemSetting::get('whatsapp_otp_button', '1') === '1') {
                $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]];
            }
            $payload += ['type' => 'template', 'template' => ['name' => $tpl, 'language' => ['code' => SystemSetting::get('whatsapp_otp_language', 'en_US') ?: 'en_US'], 'components' => $components]];
        } else {
            $payload += ['type' => 'text', 'text' => ['body' => config('app.name') . " verification code: {$code}"]];
        }
        $res = Http::withToken(SystemSetting::get('whatsapp_access_token'))->timeout(15)->post("https://graph.facebook.com/v20.0/{$id}/messages", $payload);
        if (!$res->successful()) Log::warning('Phone code WhatsApp failed: HTTP ' . $res->status() . ' ' . substr($res->body(), 0, 300));
        return $res->successful();
    }

    /** @return array{0:bool,1:string} */
    public function verify(User $u, string $code): array
    {
        $key = "phone_code:{$u->id}";
        $rec = Cache::get($key);
        if (!$rec) return [false, 'The code has expired. Request a new one.'];
        if (($rec['tries'] ?? 0) >= 5) {
            Cache::forget($key);
            return [false, 'Too many wrong codes. Request a new one.'];
        }
        if (!Hash::check(trim($code), $rec['hash'])) {
            $rec['tries'] = ($rec['tries'] ?? 0) + 1;
            Cache::put($key, $rec, now()->addMinutes((int) config('friends.code_ttl_minutes', 10)));
            return [false, 'That code is not correct.'];
        }
        if (!$this->hasCol('phone_key') || !$this->hasCol('phone_verified_at')) {
            return [false, 'Verification needs a database update. The administrator must run install_friends.php.'];
        }
        Cache::forget($key);
        // Verified. Discovery stays OFF until the person switches it on themselves.
        if (!$this->persist($u, ['phone' => $rec['phone'], 'phone_key' => PhoneKey::of($rec['phone']), 'phone_verified_at' => now(), 'discoverable' => false])) {
            return [false, 'Your number could not be saved right now. Please try again later.'];
        }
        return [true, 'Phone number verified. Now choose whether people who have your number can find you.'];
    }

    /** @return array{0:bool,1:string} */
    public function setDiscoverable(User $u, bool $on): array
    {
        if ($on && !$this->isVerified($u)) {
            if (!PhoneSettings::unverifiedDiscovery()) return [false, 'Verify your phone number first.'];
            if (!$u->phone || PhoneKey::of($u->phone) === null) return [false, 'Add your phone number first.'];
            if (!$this->hasCol('phone_key')) return [false, 'Discovery needs a database update. The administrator must run install_friends.php.'];
            $this->persist($u, ['phone_key' => PhoneKey::of($u->phone)]); // lets friends' address books match the number
        }
        if (!$this->hasCol('discoverable')) return [false, 'Discovery needs a database update. The administrator must run install_friends.php.'];
        if (!$this->persist($u, ['discoverable' => $on])) return [false, 'Could not change this setting right now.'];
        if ($on) app(FriendService::class)->onPhoneActivated($u);
        return [true, $on ? 'People who have your number saved can now find you and send a request.' : 'You can no longer be found by phone number.'];
    }

    public function remove(User $u): void
    {
        $this->persist($u, ['phone' => null, 'phone_key' => null, 'phone_verified_at' => null, 'discoverable' => false]);
        Cache::forget("phone_code:{$u->id}");
    }
}
