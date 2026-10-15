<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sends a PushKit "VoIP" push straight to Apple (APNs, HTTP/2, token based).
 *
 * Why this exists: Firebase (FCM) cannot deliver PushKit pushes. A VoIP push is the ONLY way an iPhone can ring like a normal
 * phone call (full-screen CallKit screen, also when the app is closed or the phone is locked) – the same as WhatsApp.
 *
 * .env  (all four are needed, otherwise this class does nothing and the iPhone falls back to a normal notification):
 *   APNS_KEY_PATH=/home/xxx/secure/AuthKey_ABC123DEFG.p8      (Apple Developer -> Keys -> "Apple Push Notifications service")
 *   APNS_KEY_ID=ABC123DEFG
 *   APNS_TEAM_ID=XXXXXXXXXX
 *   APNS_BUNDLE_ID=com.dahify.dahimail
 */
class ApnsVoip
{
    public function enabled(): bool
    {
        $c = config('services.apns');
        return !empty($c['key_path']) && is_readable($c['key_path']) && !empty($c['key_id']) && !empty($c['team_id']) && !empty($c['bundle_id'])
            && function_exists('curl_init') && defined('CURL_HTTP_VERSION_2_0');
    }

    private function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    /** ES256 JWT, valid 50 minutes (Apple wants a new one at least every 60 minutes, and not more than every 20). */
    private function jwt(): ?string
    {
        return Cache::remember('apns_jwt', 3000, function () {
            $c = config('services.apns');
            $key = openssl_pkey_get_private((string) file_get_contents($c['key_path']));
            if (!$key) return null;
            $head = $this->b64(json_encode(['alg' => 'ES256', 'kid' => $c['key_id']]));
            $body = $this->b64(json_encode(['iss' => $c['team_id'], 'iat' => time()]));
            if (!openssl_sign("$head.$body", $der, $key, OPENSSL_ALGO_SHA256)) return null;
            return "$head.$body." . $this->b64($this->derToRaw($der));
        });
    }

    /** OpenSSL returns an ASN.1 DER signature; JWT needs the raw 64 bytes R||S. */
    private function derToRaw(string $der): string
    {
        $o = 3;                                  // 30 len 02
        $rl = ord($der[$o]); $r = substr($der, $o + 1, $rl);
        $o = $o + 1 + $rl + 1;                   // skip to length byte of S
        $sl = ord($der[$o]); $s = substr($der, $o + 1, $sl);
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
        return $r . $s;
    }

    /**
     * @param string $voipToken  the PushKit token the app registered
     * @param array  $payload    anything the app needs to show the call (call_id, caller_name, …)
     * @return bool true when Apple accepted it; false when it was rejected (the caller then removes a dead token)
     */
    public function send(string $voipToken, array $payload, bool $sandbox = false, int $ttl = 45): bool
    {
        if (!$this->enabled()) return false;
        $jwt = $this->jwt();
        if (!$jwt) { Log::warning('APNs: could not sign the token – check APNS_KEY_PATH / the .p8 file'); return false; }
        $c = config('services.apns');

        foreach ($sandbox ? [true, false] : [false, true] as $useSandbox) {   // tokens from Xcode builds only work on the sandbox host
            $host = $useSandbox ? 'api.sandbox.push.apple.com' : 'api.push.apple.com';
            $ch = curl_init("https://$host/3/device/$voipToken");
            curl_setopt_array($ch, [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_HTTPHEADER => [
                    'authorization: bearer ' . $jwt,
                    'apns-topic: ' . $c['bundle_id'] . '.voip',
                    'apns-push-type: voip',
                    'apns-priority: 10',
                    'apns-expiration: ' . (time() + $ttl),
                    'content-type: application/json',
                ],
            ]);
            $res = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($code === 200) return true;
            $reason = is_string($res) ? (json_decode($res, true)['reason'] ?? '') : '';
            if ($code === 403 && $reason === 'ExpiredProviderToken') Cache::forget('apns_jwt');
            if ($code === 400 && $reason === 'BadDeviceToken') continue;          // wrong host for this token → try the other one
            Log::warning("APNs VoIP failed: HTTP $code $reason");
            return false;
        }
        return false;
    }
}
