<?php

namespace App\Services;

use App\Models\User;
use App\Support\PushFailure;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;

/**
 * Firebase Cloud Messaging (HTTP v1) – every phone and browser a person is signed in on (table device_tokens).
 *
 *  • sendToUser()  → a normal, visible notification (messages, friend requests, …). Android shows it by itself even when the
 *                    app is closed.
 *  • sendCall()    → an INCOMING CALL. Android gets a high-priority DATA-ONLY message (the app turns it into the full-screen ringing
 *                    screen – a message that carries a "notification" block can NOT do that when the app is closed).
 *                    iPhone gets a PushKit VoIP push (ApnsVoip) when the app registered one, otherwise a loud time-sensitive alert.
 *  • sendSilent()  → data only, for "the caller hung up" / "answered on another device".
 *
 * Setup: Firebase console → Project settings → Service accounts → "Generate new private key". Upload the JSON OUTSIDE public/
 * and set  FIREBASE_CREDENTIALS=/full/path/key.json  in .env.  iOS also needs the APNs key uploaded in Firebase console →
 * Project settings → Cloud Messaging → Apple app configuration.
 */
class FcmPush
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private bool $skipTokenCache = false;

    private function credentialsPath(): ?string
    {
        $path = config('services.fcm.credentials');
        if (!is_string($path) || trim($path) === '') return null;
        // HTTP requests and artisan have different working directories.
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);
    }

    public function enabled(): bool
    {
        return $this->configurationError() === null;
    }

    /** Local configuration only: this does not imply that Google accepted a push. */
    public function configurationError(): ?string
    {
        $p = $this->credentialsPath();
        if (!$p || !is_file($p) || !is_readable($p)) return 'FIREBASE_CREDENTIALS must point to a readable service-account JSON file.';
        $json = json_decode((string) file_get_contents($p), true);
        foreach (['project_id', 'client_email', 'private_key'] as $field) {
            if (!is_array($json) || !is_string($json[$field] ?? null) || trim($json[$field]) === '') {
                return 'Firebase service-account JSON is invalid or missing required fields.';
            }
        }
        if (!openssl_pkey_get_private($json['private_key'])) return 'Firebase service-account private key is invalid.';
        return null;
    }

    /** @return array<int,object> one row per signed-in device */
    private function devices(int $userId): array
    {
        try {
            return DB::table('device_tokens')->where('user_id', $userId)->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function auth(): ?array
    {
        $json = json_decode((string) file_get_contents($this->credentialsPath()), true);
        $project = $json['project_id'] ?? null;
        if (!$project) return null;
        // The cache is an optimisation: a missing cache table or unavailable
        // Redis must not prevent an otherwise valid phone from receiving calls.
        $access = null;
        try { if (!$this->skipTokenCache) $access = Cache::get('fcm_access_token'); }
        catch (\Throwable $e) { $this->failure($e, 'token_cache_read'); }
        if (!$access) {
            $creds = new ServiceAccountCredentials(self::SCOPE, $json);
            // Use the same bounded HTTP transport as delivery. The default
            // Google auth transport had no deadline and escaped Http::fake().
            $result = $creds->fetchAuthToken(function (RequestInterface $request) {
                return Http::connectTimeout(5)->timeout(10)
                    ->withHeaders($request->getHeaders())
                    ->withBody((string) $request->getBody(), $request->getHeaderLine('Content-Type'))
                    ->send($request->getMethod(), (string) $request->getUri())
                    ->throw()->toPsrResponse();
            });
            $access = $result['access_token'] ?? null;
            if ($access) {
                $lifetime = min(3000, max(0, (int) ($result['expires_in'] ?? 3600) - 60));
                if ($lifetime > 0) {
                    try { Cache::put('fcm_access_token', $access, $lifetime); }
                    catch (\Throwable $e) { $this->failure($e, 'token_cache_write'); }
                }
            }
        }
        return $access ? ['project' => $project, 'token' => $access] : null;
    }

    /** FCM data values must all be strings. */
    private function strings(array $data): array
    {
        return array_map(fn ($v) => is_bool($v) ? ($v ? '1' : '0') : (is_scalar($v) ? (string) $v : json_encode($v)), $data);
    }

    public function projectId(): ?string
    {
        if (!$this->enabled()) return null;
        return json_decode((string) file_get_contents($this->credentialsPath()), true)['project_id'];
    }

    /** Provider validation only: accepted does not prove receipt on a phone. No tokens/keys in the result. */
    public function probeToken(string $token, string $platform): array
    {
        if (!$this->enabled()) return ['accepted' => false, 'code' => 'server_unconfigured'];
        $stage = 'authentication';
        try {
            $auth = $this->auth();
            if (!$auth) return ['accepted' => false, 'code' => 'authentication_unavailable'];
            $stage = 'delivery';
            $message = ['token' => $token, 'data' => ['type' => 'push_connection_check']];
            if ($platform === 'android') $message['android'] = ['priority' => 'HIGH', 'ttl' => '30s'];
            return $this->post($auth, $message, $token, true);
        } catch (\Throwable $e) {
            return $this->failure($e, $stage);
        }
    }

    /** A real, harmless alert for diagnosing delivery while the app is closed. */
    public function testToken(string $token, string $platform): array
    {
        if (!$this->enabled()) return ['accepted' => false, 'code' => 'server_unconfigured'];
        try {
            $auth = $this->auth();
            if (!$auth) return ['accepted' => false, 'code' => 'authentication_unavailable'];
            $data = ['type' => 'push_test', 'title' => 'Dahimail push delivery test',
                'body' => 'This notification arrived from the server.', 'notification_id' => (string) \Illuminate\Support\Str::uuid()];
            $message = ['token' => $token, 'data' => $data];
            if ($platform === 'android') {
                $message['data'] += ['notification_layout' => 'v2', 'category_label' => 'Connection test'];
                $message['android'] = ['priority' => 'HIGH', 'ttl' => '60s'];
            } else {
                $message['notification'] = ['title' => $data['title'], 'body' => $data['body']];
            }
            return $this->post($auth, $message, $token);
        } catch (\Throwable $e) { return $this->failure($e, 'delivery_test'); }
    }

    private function forgetAccessToken(): void
    {
        $this->skipTokenCache = true;
        try { Cache::forget('fcm_access_token'); }
        catch (\Throwable $e) { $this->failure($e, 'token_cache_forget'); }
    }

    private function failure(\Throwable $error, string $stage): array
    {
        $code = PushFailure::code($error);
        // Never log exception messages, HTTP bodies, JWT assertions or tokens.
        Log::warning('FCM operation failed', ['stage' => $stage, 'code' => $code, 'exception' => get_class($error)]);
        return ['accepted' => false, 'code' => $code, 'stage' => $stage, 'hint' => PushFailure::hint($code)];
    }

    private function post(array $auth, array $message, string $token, bool $validateOnly = false): array
    {
        $payload = ['message' => $message];
        if ($validateOnly) $payload['validate_only'] = true;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $res = Http::withToken($auth['token'])->connectTimeout(5)->timeout(10)->post("https://fcm.googleapis.com/v1/projects/{$auth['project']}/messages:send", $payload);
            if ($res->status() !== 401 || $attempt === 1) break;
            // Renew and retry this alert; waiting for the next call loses the current invitation.
            $this->forgetAccessToken();
            $renewed = $this->auth();
            if (!$renewed) break;
            $auth = $renewed;
        }
        if ($res->successful()) return ['accepted' => true, 'code' => 'accepted'];
        $code = collect($res->json('error.details', []))->pluck('errorCode')->filter()->first() ?? $res->json('error.status', 'UNKNOWN');
        // Return only known provider codes, never the provider's free-form response/body.
        if (!in_array($code, ['UNREGISTERED', 'SENDER_ID_MISMATCH', 'INVALID_ARGUMENT', 'THIRD_PARTY_AUTH_ERROR', 'QUOTA_EXCEEDED', 'UNAVAILABLE', 'INTERNAL', 'UNAUTHENTICATED', 'PERMISSION_DENIED', 'NOT_FOUND'], true)) $code = 'UNKNOWN';
        if ($code === 'UNREGISTERED' && !$validateOnly) {
            DB::table('device_tokens')->where('token', $token)->delete();
        }
        if ($res->status() === 401) $this->forgetAccessToken();
        Log::warning('FCM send rejected', ['http_status' => $res->status(), 'code' => $code, 'validation_only' => $validateOnly]);
        return ['accepted' => false, 'code' => $code];
    }

    // ───────────────────────── visible notification ─────────────────────────

    /** Provider acceptance means the recipient device/token accepted the message for delivery. */
    public function sendToUser(User $user, string $title, string $body = '', array $data = [], array $opts = []): bool
    {
        if (!$this->enabled()) return false;
        $devices = $this->devices($user->id);
        if (!$devices) return false;
        $accepted = false;
        try {
            $auth = $this->auth();
            if (!$auth) return false;
            $data = $this->strings(['recipient_user_id' => $user->id, 'sent_at' => (string) now()->timestamp] + \App\Support\PushPresentation::replyTarget($data) + $data);
            [$label, $icon] = \App\Support\PushPresentation::for($data);
            $body = mb_strcut(\App\Support\PushPresentation::body($data, $body), 0, 600, 'UTF-8');
            $data['body'] = $body;
            $channel = $opts['channel'] ?? 'dm_push';
            foreach ($devices as $d) {
                try {
                    $message = ['token' => $d->token, 'data' => $data + ['title' => $title, 'body' => $body]];
                    if (($d->platform ?? '') === 'web') {
                        // browsers: the service worker (firebase-messaging-sw.js) shows it, so it must stay data-only + a "webpush" hint
                        $message['data'] += ['title' => $title, 'body' => $body];
                        $message['webpush'] = ['headers' => ['Urgency' => 'high', 'TTL' => (string) ($opts['ttl'] ?? 86400)]];
                    } elseif (($d->platform ?? '') === 'android' && version_compare(explode('+', $d->app_version ?? '0')[0], '1.0.2', '>=')) {
                        // v1.0.2+ posts natively before starting Flutter. Data-only
                        // lets Android show our View/Reply actions consistently.
                        $message['data'] += ['notification_layout' => 'v2', 'category_label' => $label];
                        $message['android'] = ['priority' => 'HIGH', 'ttl' => ((int) ($opts['ttl'] ?? 86400)).'s'];
                    } else {
                        $systemTitle = str_starts_with(strtolower($title), strtolower($label)) ? $title : $label.' · '.$title;
                        $hasSender = trim($data['sender_name'] ?? '') !== '';
                        if ($hasSender) $systemTitle = $data['sender_name'];
                        $systemBody = $body;
                        $message['notification'] = ['title' => $systemTitle, 'body' => $systemBody];
                        $message['android'] = [
                            'priority' => 'HIGH',
                            'ttl' => ((int) ($opts['ttl'] ?? 86400)) . 's',
                            'notification' => array_filter(['channel_id' => $channel, 'icon' => $hasSender ? 'ic_stat_notify' : 'ic_stat_'.$icon, 'image' => filter_var($data['sender_avatar'] ?? '', FILTER_VALIDATE_URL) && str_starts_with($data['sender_avatar'], 'https://') ? $data['sender_avatar'] : null, 'tag' => $opts['tag'] ?? null, 'notification_priority' => 'PRIORITY_HIGH', 'visibility' => 'PRIVATE']),
                        ];
                        $message['apns'] = [
                            'headers' => ['apns-priority' => '10', 'apns-push-type' => 'alert'],
                            'payload' => ['aps' => array_filter(['sound' => 'default', 'thread-id' => $opts['thread'] ?? null, 'mutable-content' => 1, 'category' => isset($data['reply_kind']) ? (($data['type'] ?? '') === 'chat' && version_compare(explode('+', $d->app_version ?? '0')[0], '1.0.13', '>=') ? 'CHAT_REPLY' : 'MESSAGE_REPLY') : 'MESSAGE_VIEW'])],
                        ];
                        if (($d->platform ?? '') === 'ios' && version_compare(explode('+', $d->app_version ?? '0')[0], '1.0.2', '>=')) {
                            // Match flutter_local_notifications' remote action envelope.
                            // Firebase opens are ignored for this envelope by the new app.
                            $message['apns']['payload'] += [
                                'dm_local_actions' => '1', 'NotificationId' => (int) (crc32($data['notification_id'] ?? $data['message_id'] ?? \Illuminate\Support\Str::uuid()->toString()) & 0x7fffffff),
                                'presentAlert' => false, 'presentSound' => false, 'presentBadge' => true,
                                'payload' => json_encode($message['data'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                            ];
                            $message['apns']['payload']['aps']['alert'] = [
                                'title' => trim($data['sender_name'] ?? '') ?: $title, 'body' => $body,
                            ];
                        }
                    }
                    $result = $this->post($auth, $message, $d->token);
                    $accepted = $accepted || (($result['accepted'] ?? false) === true);
                } catch (\Throwable $e) {
                    $this->failure($e, 'device_delivery');
                }
            }
        } catch (\Throwable $e) {
            $this->failure($e, 'notification');
        }
        return $accepted;
    }

    // ───────────────────────── incoming call ─────────────────────────

    /** $data: call_id, caller_id, caller_name, caller_avatar, video, audio_only, meeting, group, uuid … */
    public function sendCall(User $callee, array $data, int $ttl = 45): void
    {
        if (!$this->enabled() && !app(ApnsVoip::class)->enabled()) {
            Log::warning('Incoming call push unavailable: configure Firebase for Android or APNs VoIP for iOS.', ['user_id' => $callee->id]);
            return;
        }
        $devices = $this->devices($callee->id);
        if (!$devices) {
            Log::warning('Incoming call push has no registered recipient devices.', ['user_id' => $callee->id]);
            return;
        }
        // Old registrations must not consume the ringing window before the current phone.
        usort($devices, fn ($a, $b) => strcmp((string) ($b->updated_at ?? ''), (string) ($a->updated_at ?? ''))
            ?: ((int) $b->id <=> (int) $a->id));
        $data = $this->strings(['type' => 'call', 'ttl' => $ttl, 'recipient_user_id' => $callee->id] + $data);
        $video = ($data['video'] ?? '0') === '1' && ($data['audio_only'] ?? '0') !== '1';
        $title = $video ? 'Incoming video call' : 'Incoming call';
        $body = ($data['caller_name'] ?? 'Someone') . ' is calling you…';
        try {
            $auth = null;
            $authAttempted = false;
            foreach ($devices as $d) {
                try {
                    if (($d->platform ?? '') === 'ios' && !empty($d->voip_token) && app(ApnsVoip::class)->enabled()) {
                        $ok = app(ApnsVoip::class)->send($d->voip_token, $data + ['aps' => ['alert' => $title]], (bool) ($d->apns_sandbox ?? 0), $ttl);
                        if ($ok) continue;                                                       // PushKit delivered → CallKit rings
                    }
                    // Native iPhone calls use Apple directly and do not wait for Google login.
                    if (!$authAttempted) {
                        $authAttempted = true;
                        try { $auth = $this->enabled() ? $this->auth() : null; }
                        catch (\Throwable $e) { $this->failure($e, 'authentication'); }
                    }
                    if (!$auth) continue;
                    if (($d->platform ?? '') === 'ios') {
                        // no PushKit token: loud, time-sensitive banner that shows over other apps and the lock screen
                        $this->post($auth, [
                            'token' => $d->token, 'data' => $data,
                            'notification' => ['title' => $title, 'body' => $body],
                            'apns' => [
                                'headers' => ['apns-priority' => '10', 'apns-push-type' => 'alert', 'apns-expiration' => (string) (time() + $ttl)],
                                'payload' => ['aps' => ['sound' => 'ringtone.caf', 'interruption-level' => 'time-sensitive', 'category' => 'INCOMING_CALL', 'thread-id' => 'calls', 'content-available' => 1]],
                            ],
                        ], $d->token);
                    } elseif (($d->platform ?? '') === 'web') {
                        $this->post($auth, ['token' => $d->token, 'data' => $data + ['title' => $title, 'body' => $body],
                            'webpush' => ['headers' => ['Urgency' => 'high', 'TTL' => (string) $ttl]]], $d->token);
                    } else {
                        // Android: DATA-ONLY + high priority → the app's background handler shows the full-screen call screen
                        $this->post($auth, ['token' => $d->token, 'data' => $data, 'android' => ['priority' => 'HIGH', 'ttl' => $ttl . 's']], $d->token);
                    }
                } catch (\Throwable $e) {
                    $this->failure($e, 'device_delivery');
                }
            }
        } catch (\Throwable $e) {
            $this->failure($e, 'call');
        }
    }

    // ───────────────────────── silent (data only) ─────────────────────────

    /** e.g. type=call_cancel – stops the ringing on every device of this person (silent: no sound, no banner) */
    public function sendSilent(User $user, array $data): void
    {
        if (!$this->enabled() && !app(ApnsVoip::class)->enabled()) return;
        $devices = $this->devices($user->id);
        if (!$devices) return;
        // Stop the current phone ringing before trying stale registrations.
        usort($devices, fn ($a, $b) => strcmp((string) ($b->updated_at ?? ''), (string) ($a->updated_at ?? ''))
            ?: ((int) $b->id <=> (int) $a->id));
        $data = $this->strings($data);
        try {
            $auth = $this->enabled() ? $this->auth() : null;
            foreach ($devices as $d) {
                try {
                    // NOT via PushKit: Apple requires every VoIP push to be reported to CallKit as a NEW call, so a "cancel" sent that way
                    // would show a ghost call and, repeated, gets the app throttled. iPhones get a silent background push instead.
                    if (!$auth) continue;
                    $m = ['token' => $d->token, 'data' => $data];
                    if (($d->platform ?? '') === 'ios') $m['apns'] = ['headers' => ['apns-priority' => '5', 'apns-push-type' => 'background'], 'payload' => ['aps' => ['content-available' => 1]]];
                    elseif (($d->platform ?? '') === 'web') $m['webpush'] = ['headers' => ['Urgency' => 'high', 'TTL' => '30']];
                    else $m['android'] = ['priority' => 'HIGH', 'ttl' => '30s'];
                    $this->post($auth, $m, $d->token);
                } catch (\Throwable $e) {
                    $this->failure($e, 'device_delivery');
                }
            }
        } catch (\Throwable $e) {
            $this->failure($e, 'silent');
        }
    }
}
