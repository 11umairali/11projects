<?php

namespace App\Services;

class PushPresentation
{
    /** Ordinary alerts only; ringing calls keep the separate native-call payload. */
    public static function for(array $data, string $body): array
    {
        $type = strtolower((string) ($data['type'] ?? ''));
        if (str_contains($type, 'meeting')) return ['Meeting', 'dm_meeting', 'ic_notify_meeting'];
        if (str_contains($type, 'call')) {
            $video = in_array($data['video'] ?? false, [true, 1, '1', 'true'], true) || str_contains($type, 'video') || str_contains(strtolower($body), 'video');
            $label = str_starts_with($type, 'missed') ? 'Missed ' : '';
            return [$label.($video ? 'video call' : 'audio call'), 'dm_call_alerts', $video ? 'ic_notify_video' : 'ic_notify_call'];
        }
        if (str_contains($type, 'email') || str_contains($type, 'mail') || str_contains($type, 'smtp')) return ['Email', 'dm_email', 'ic_notify_email'];
        if (preg_match('/chat|message|reply|conversation/', $type)) return ['Chat', 'dm_chat', 'ic_notify_chat'];
        if (str_starts_with($type, 'friend')) return ['Friends', 'dm_social', 'ic_stat_notify'];
        if (str_contains($type, 'security')) return ['Security', 'dm_push', 'ic_stat_notify'];
        if (preg_match('/billing|plan|trial|payment|invoice/', $type)) return ['Billing', 'dm_push', 'ic_stat_notify'];
        if (str_contains($type, 'workflow')) return ['Automation', 'dm_push', 'ic_stat_notify'];
        if (str_contains($type, 'campaign') || $type === 'admin_notice') return ['Announcement', 'dm_push', 'ic_stat_notify'];
        if (str_contains($type, 'team')) return ['Team', 'dm_social', 'ic_stat_notify'];
        return ['Activity', 'dm_push', 'ic_stat_notify'];
    }
}
