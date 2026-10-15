<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Support\FriendSettings;
use Illuminate\Http\Request;

/** Admin → Friends Settings (chat, files, audio calls). SUPER ADMIN ONLY. */
class FriendsSettingsController extends Controller
{
    private function guard(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->is_admin && ($u->admin_role === 'super_admin' || $u->hasRole('Super Admin')), 403, 'Only a super admin can change these settings.');
    }

    public function index()
    {
        $this->guard();
        return view('admin.friends-settings', [
            'chat' => FriendSettings::chatEnabled(),
            'files' => SystemSetting::get('friends_files_enabled', '1') === '1',
            'calls' => FriendSettings::callsEnabled(),
            'maxMb' => FriendSettings::fileMaxMb(),
            'recMode' => \App\Services\Recording\RecordingPolicy::mode(),
            'recCfg' => collect(\App\Services\Recording\RecordingPolicy::MODES)->map(fn ($_, $n) => \App\Services\Recording\RecordingPolicy::cfg($n))->all(),
            'recSeconds' => \App\Services\Recording\RecordingPolicy::consentSeconds(),
            'recMb' => \App\Services\Recording\RecordingPolicy::maxMb(),
            'stun' => SystemSetting::get('friends_stun_url', 'stun:stun.l.google.com:19302'),
            'turnUrl' => SystemSetting::get('friends_turn_url'),
            'turnUser' => SystemSetting::get('friends_turn_user'),
            'turnPassSet' => FriendSettings::turnPassword() !== '',
        ]);
    }

    public function update(Request $request)
    {
        $this->guard();
        $d = $request->validate([
            'friends_file_max_mb' => 'nullable|integer|min:1|max:50',
            'rec_mode' => 'nullable|integer|min:0|max:5',
            'rec_consent_seconds' => 'nullable|integer|min:10|max:120',
            'rec_max_mb' => 'nullable|integer|min:5|max:500',
            'rec' => 'nullable|array',
            'friends_stun_url' => 'nullable|string|max:200',
            'friends_turn_url' => 'nullable|string|max:300',
            'friends_turn_user' => 'nullable|string|max:120',
            'friends_turn_pass' => 'nullable|string|max:200',
        ]);
        foreach (['friends_chat_enabled', 'friends_files_enabled', 'friends_calls_enabled'] as $k) {
            SystemSetting::set($k, $request->boolean($k) ? '1' : '0', 'friends');
        }
        \App\Services\Recording\RecordingPolicy::save(['mode' => $d['rec_mode'] ?? 0, 'consent_seconds' => $d['rec_consent_seconds'] ?? 30, 'max_mb' => $d['rec_max_mb'] ?? 100, 'cfg' => $d['rec'] ?? []]);
        SystemSetting::set('friends_file_max_mb', (string) max(1, min(50, (int) ($d['friends_file_max_mb'] ?? 10))), 'friends');
        SystemSetting::set('friends_stun_url', trim((string) ($d['friends_stun_url'] ?? '')) ?: 'stun:stun.l.google.com:19302', 'friends');
        SystemSetting::set('friends_turn_url', trim((string) ($d['friends_turn_url'] ?? '')), 'friends');
        SystemSetting::set('friends_turn_user', trim((string) ($d['friends_turn_user'] ?? '')), 'friends');
        if ($request->boolean('friends_turn_pass_clear')) FriendSettings::saveTurnPassword('');
        elseif (trim((string) ($d['friends_turn_pass'] ?? '')) !== '') FriendSettings::saveTurnPassword(trim($d['friends_turn_pass'])); // empty box = keep the saved password
        return redirect()->route('admin.friends-settings')->with('success', 'Settings saved.');
    }
}
