<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Mailbox\MailboxCredentials;
use App\Models\EmailAccount;
use App\Models\SecurityAuditLog;
use App\Services\CyberPanelMailbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PragmaRX\Google2FA\Google2FA;

/**
 * Account-level endpoints for the mobile app: profile, password, 2FA,
 * signed-in devices, notification preferences, push tokens, data export,
 * deactivation and deletion. Mirrors Livewire\Settings\ProfileForm,
 * settings/security, NotificationPreferences and DataExportController.
 */
class MeController extends Controller
{
    private const DEFAULT_EVENTS = [
        'announcement_general' => ['email' => true, 'inApp' => true, 'slack' => false],
        'announcement_system' => ['email' => true, 'inApp' => true, 'slack' => false],
        'announcement_marketing' => ['email' => true, 'inApp' => true, 'slack' => false],
        'announcement_advertising' => ['email' => true, 'inApp' => true, 'slack' => false],
        'announcement_misc' => ['email' => true, 'inApp' => true, 'slack' => false],

        'newConversation'  => ['email' => true,  'inApp' => true,  'slack' => false],
        'assignment'       => ['email' => true,  'inApp' => true,  'slack' => false],
        'aiDraftReady'     => ['email' => false, 'inApp' => true,  'slack' => false],
        'teamMention'      => ['email' => true,  'inApp' => true,  'slack' => false],
        'contactReply'     => ['email' => true,  'inApp' => true,  'slack' => false],
        'campaignComplete' => ['email' => true,  'inApp' => true,  'slack' => false],
        'weeklyDigest'     => ['email' => true,  'inApp' => false, 'slack' => false],
        'billingAlerts'    => ['email' => true,  'inApp' => true,  'slack' => false],
    ];

    private function payload($user): array
    {
        $user->loadMissing('activeWorkspace');
        return [
            'id'                 => $user->id,
            'uuid'               => $user->uuid,
            'name'               => $user->name,
            'email'              => $user->email,
            'username'           => $user->username,
            'phone'              => $user->phone,
            'timezone'           => $user->timezone,
            'avatar_url'         => $user->avatar_path ? asset('storage/' . $user->avatar_path) : null,
            'billing_enabled' => !\App\Support\BillingPolicy::isFree(),
            'is_admin'           => (bool) $user->is_admin,
            'two_factor_enabled' => (bool) ($user->two_factor_secret && $user->two_factor_confirmed_at),
            'role'               => $user->active_workspace_id ? $user->workspaceRole($user->active_workspace_id) : null,
            'active_workspace'   => $user->activeWorkspace ? ['id' => $user->activeWorkspace->id, 'name' => $user->activeWorkspace->name] : null,
        ];
    }

    private function fail(string $field, string $message, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], $status);
    }

    // ── Profile ─────────────────────────────────────────────────────────

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone'    => 'nullable|string|max:30',
            'timezone' => 'nullable|string|max:100',
            'avatar'   => 'nullable|image|max:2048',
        ]);

        $fill = [
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'timezone' => $data['timezone'] ?? null,
        ];

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $fill['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($fill);

        return response()->json(['data' => $this->payload($user->fresh()), 'message' => 'Profile updated.']);
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->update(['avatar_path' => null]);

        return response()->json(['data' => $this->payload($user->fresh())]);
    }

    // ── Password ────────────────────────────────────────────────────────

    public function password(Request $request, CyberPanelMailbox $mailbox, MailboxCredentials $credentials): JsonResponse
    {
        $user = $request->user();

        $v = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password'         => \App\Support\CredentialPolicy::passwordRules(),
        ]);
        if ($v->fails()) {
            return response()->json(['message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }
        if (!Hash::check($request->input('current_password'), $user->password)) {
            return $this->fail('current_password', 'The current password is incorrect.');
        }

        $new = $request->input('password');
        $user->forceFill(['password' => Hash::make($new)])->save();

        // Same side effects as Actions\Fortify\UpdateUserPassword: keep the
        // mail server (Dovecot/Postfix) and the auto-provisioned mailbox in sync.
        if ($user->username) {
            try {
                $mailbox->updatePassword($user->username, $new);
                $credentials->remember($new);
            } catch (\Throwable $e) {
                report($e);
            }
            EmailAccount::where('user_id', $user->id)
                ->where('email', $user->email)
                ->update(['imap_password' => $new, 'smtp_password' => $new]);
        }

        // Sign out every other device.
        $current = $request->user()->currentAccessToken();
        $user->tokens()->when($current, fn ($q) => $q->where('id', '!=', $current->id))->delete();

        return response()->json(['message' => 'Password changed.']);
    }

    // ── Two-factor (TOTP) ───────────────────────────────────────────────

    public function twoFactorSetup(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return $this->fail('two_factor', 'Two-factor authentication is already enabled.', 409);
        }
        $g2fa = new Google2FA();
        $secret = $g2fa->generateSecretKey(32);
        Cache::put('2fa_setup:' . $user->id, Crypt::encryptString($secret), now()->addMinutes(10));

        return response()->json(['data' => [
            'secret'      => $secret,
            'otpauth_url' => $g2fa->getQRCodeUrl(config('app.name', 'MailTrixy'), $user->email, $secret),
        ]]);
    }

    public function twoFactorEnable(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);
        $user = $request->user();

        $enc = Cache::get('2fa_setup:' . $user->id);
        if (!$enc) {
            return $this->fail('code', 'Setup expired. Start again.');
        }
        $secret = Crypt::decryptString($enc);
        if (!(new Google2FA())->verifyKey($secret, $request->input('code'))) {
            return $this->fail('code', 'The verification code is invalid.');
        }

        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtolower(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)));
        }
        $hashed = array_map(fn ($c) => hash('sha256', strtolower(trim($c))), $codes);

        $user->forceFill([
            'two_factor_enabled'        => true,
            'two_factor_secret'         => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($hashed)),
            'two_factor_method'         => 'totp',
            'two_factor_confirmed_at'   => now(),
        ])->save();
        Cache::forget('2fa_setup:' . $user->id);

        return response()->json(['data' => ['recovery_codes' => $codes], 'message' => 'Two-factor authentication enabled. Save your recovery codes now.']);
    }

    public function twoFactorDisable(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();
        if (!Hash::check($request->input('password'), $user->password)) {
            return $this->fail('password', 'The password you entered is incorrect.');
        }
        $user->forceFill([
            'two_factor_enabled'        => false,
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_method'         => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }

    // ── Signed-in devices (Sanctum tokens) & security log ───────────────

    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $user->currentAccessToken()?->id;

        $rows = $user->tokens()->orderByDesc('last_used_at')->get()->map(fn ($t) => [
            'id'           => $t->id,
            'name'         => $t->name,
            'last_used_at' => $t->last_used_at?->toIso8601String(),
            'created_at'   => $t->created_at?->toIso8601String(),
            'current'      => $t->id === $currentId,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function revokeSession(Request $request, int $id): JsonResponse
    {
        $request->user()->tokens()->where('id', $id)->delete();
        return response()->json(['message' => 'Device signed out.']);
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();
        $request->user()->tokens()->when($current, fn ($q) => $q->where('id', '!=', $current->id))->delete();
        return response()->json(['message' => 'All other devices signed out.']);
    }

    public function securityLog(Request $request): JsonResponse
    {
        $rows = SecurityAuditLog::where('user_id', $request->user()->id)
            ->orderByDesc('logged_at')->limit(50)->get()
            ->map(fn ($l) => [
                'id'         => $l->id,
                'event_type' => $l->event_type,
                'status'     => $l->status,
                'ip_address' => $l->ip_address,
                'user_agent' => $l->user_agent,
                'logged_at'  => $l->logged_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    // ── Notification preferences ────────────────────────────────────────

    public function notificationPrefs(Request $request): JsonResponse
    {
        $p = $request->user()->notification_preferences ?? [];
        return response()->json(['data' => [
            'emailNotifs' => $p['emailNotifs'] ?? true,
            'inAppNotifs' => $p['inAppNotifs'] ?? true,
            'slackNotifs' => $p['slackNotifs'] ?? false,
            'events'      => array_replace_recursive(self::DEFAULT_EVENTS, $p['events'] ?? []),
        ]]);
    }

    public function updateNotificationPrefs(Request $request): JsonResponse
    {
        $data = $request->validate([
            'emailNotifs' => 'required|boolean',
            'inAppNotifs' => 'required|boolean',
            'slackNotifs' => 'required|boolean',
            'events'      => 'required|array',
        ]);
        // Only accept known event keys / channels.
        $events = [];
        foreach (self::DEFAULT_EVENTS as $key => $default) {
            $in = $data['events'][$key] ?? [];
            $events[$key] = [
                'email' => (bool) ($in['email'] ?? $default['email']),
                'inApp' => (bool) ($in['inApp'] ?? $default['inApp']),
                'slack' => (bool) ($in['slack'] ?? $default['slack']),
            ];
        }
        $request->user()->update(['notification_preferences' => [
            'emailNotifs' => (bool) $data['emailNotifs'],
            'inAppNotifs' => (bool) $data['inAppNotifs'],
            'slackNotifs' => (bool) $data['slackNotifs'],
            'events'      => $events,
        ]]);

        return response()->json(['message' => 'Notification preferences saved.']);
    }

    // ── Push tokens (FCM / APNs) ────────────────────────────────────────

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'        => 'required|string|max:500',
            'firebase_project_id' => 'nullable|string|max:128',
            'platform'     => 'required|in:android,ios,web',
            'voip_token'   => 'nullable|string|max:255',   // iOS PushKit token (rings like a phone call)
            'app_version'  => 'nullable|string|max:20',
            'apns_sandbox' => 'nullable|boolean',          // true for builds installed from Xcode / TestFlight-internal
        ]);
        $row = ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'updated_at' => now()];
        // Core FCM registration must survive an upgrade where optional columns are not migrated yet.
        $columns = Schema::getColumnListing('device_tokens');
        foreach (['voip_token', 'app_version'] as $k) if (array_key_exists($k, $data) && in_array($k, $columns, true)) $row[$k] = $data[$k];
        if (array_key_exists('apns_sandbox', $data) && in_array('apns_sandbox', $columns, true)) $row['apns_sandbox'] = $data['apns_sandbox'] ? 1 : 0;
        if (!empty($data['voip_token']) && in_array('voip_token', $columns, true)) {   // the same phone signed in as somebody else before: its calls must not ring for the old account
            DB::table('device_tokens')->where('voip_token', $data['voip_token'])->where('token', '!=', $data['token'])->update(['voip_token' => null]);
        }
        $exists = DB::table('device_tokens')->where('token', $data['token'])->exists();
        DB::table('device_tokens')->updateOrInsert(['token' => $data['token']], $row + ($exists ? [] : ['created_at' => now()]));
        $fcm = app(\App\Services\FcmPush::class);
        $configured = $fcm->enabled();
        $projectMatches = empty($data['firebase_project_id']) || $data['firebase_project_id'] === $fcm->projectId();
        return response()->json([
            'message' => 'Device registered.',
            'push_configured' => $configured && $projectMatches,
            'push_status' => !$configured ? 'server_unconfigured' : (!$projectMatches ? 'project_mismatch' : 'registered'),
            'registration_needs_migration' => count(array_diff(['voip_token', 'app_version', 'apns_sandbox'], $columns)) > 0,
            'voip_configured' => $data['platform'] === 'ios' && in_array('voip_token', $columns, true) && !empty($data['voip_token']) && app(\App\Services\ApnsVoip::class)->enabled(),
        ]);
    }

    /** Check only the authenticated device; sending a real alert requires explicit opt-in. */
    public function checkPush(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:500', 'send_test' => 'sometimes|boolean']);
        $device = DB::table('device_tokens')->where('user_id', $request->user()->id)->where('token', $data['token'])->first();
        abort_unless($device, 404);
        $push = app(\App\Services\FcmPush::class);
        $send = $request->boolean('send_test');
        $result = $send ? $push->testToken($device->token, $device->platform) : $push->probeToken($device->token, $device->platform);
        return response()->json($result + ['test_sent' => $send && ($result['accepted'] ?? false)]);
    }

    public function unregisterDevice(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);
        DB::table('device_tokens')->where('user_id', $request->user()->id)->where('token', $request->input('token'))->delete();
        return response()->json(['message' => 'Device removed.']);
    }

    // ── Data export / deactivate / delete ───────────────────────────────

    public function export(Request $request)
    {
        return app(\App\Http\Controllers\DataExportController::class)->export($request);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate(['password' => 'required|string']);
        if (!Hash::check($request->input('password'), $user->password)) {
            return $this->fail('password', 'The password you entered is incorrect.');
        }
        $this->audit($user, $request, 'account_deactivated', ['status' => $user->status], ['status' => 'suspended']);
        $user->update(['status' => 'suspended', 'suspended_at' => now()]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Account deactivated. Contact support to reactivate.']);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate([
            'confirmation' => 'required|string|in:DELETE',
            'password'     => 'required|string',
        ]);
        if (!Hash::check($request->input('password'), $user->password)) {
            return $this->fail('password', 'The password you entered is incorrect.');
        }
        $this->audit($user, $request, 'deletion_requested', null, ['deletion_requested_at' => now()->toIso8601String()]);
        $user->update(['deletion_requested_at' => now(), 'status' => 'pending_deletion']);
        $user->tokens()->delete();

        return response()->json(['message' => 'Account deletion scheduled.']);
    }

    private function audit($user, Request $request, string $event, ?array $old, ?array $new): void
    {
        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\User',
            'auditable_id'   => $user->id,
            'event'          => $event,
            'actor_type'     => 'user',
            'actor_id'       => $user->id,
            'actor_name'     => $user->name,
            'old_values'     => $old ? json_encode($old) : null,
            'new_values'     => $new ? json_encode($new) : null,
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
