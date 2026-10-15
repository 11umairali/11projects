<?php

namespace App\Services\Friends;

use App\Models\User;
use App\Services\FcmPush;
use Illuminate\Support\Facades\Log;

/**
 * The events of the Friends feature that must reach a phone / browser that is NOT looking at the app:
 * incoming call, call cancelled, missed call, new chat message.
 *
 * Call pushes are sent directly after the response that creates the event. They must not be put into the database queue: shared-host
 * deployments often do not have a worker running, and a queued call notification is useless after the ring window ends.
 */
class FriendPush
{
    /** stable id the phones use for the system call screen (CallKit needs a UUID) */
    public static function callUuid(int $callId): string
    {
        $h = md5('dahimail-call-' . $callId);
        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), '4' . substr($h, 13, 3), '8' . substr($h, 17, 3), substr($h, 20, 12));
    }

    private static function later(callable $fn, bool $afterResponse = false): void
    {
        $deliver = function () use ($fn) {
            try { $fn(); }
            catch (\Throwable $e) { Log::warning('push failed: ' . $e->getMessage()); }
        };
        if (!$afterResponse) { $deliver(); return; }
        // Register only after commit, then send directly after the HTTP response.
        // This does not enqueue a job and also works without a queue worker.
        \Illuminate\Support\Facades\DB::afterCommit(function () use ($deliver) {
            if (app()->runningInConsole() && !app()->runningUnitTests()) $deliver();
            else app()->terminating($deliver);
        });
    }

    private static function avatar(?User $u): string
    {
        return ($u && $u->avatar_path) ? asset('storage/' . $u->avatar_path) : '';
    }

    /** @param object $call a friend_calls row */
    public static function incomingCall(object $call): void
    {
        $callId = (int) $call->id; $callerId = (int) $call->caller_id; $calleeId = (int) $call->callee_id;
        $caller = User::find($callerId);
        $callee = User::find($calleeId);
        $incoming = [
            'id' => $callId, 'status' => 'ringing', 'role' => 'callee',
            'sent_at' => \Carbon\Carbon::parse($call->created_at)->getTimestamp(), 'ttl' => FriendCallService::RING_SECONDS,
            'video' => (bool) ($call->video ?? 0), 'audio_only' => (bool) ($call->audio_only ?? 0),
            'meeting' => $call->meeting_code ?? null, 'group' => (bool) ($call->group_invite ?? 0),
            'peer' => ['id' => $callerId, 'name' => $caller && $callee ? app(ContactNames::class)->fields($callee, $caller)['name'] : 'Friend',
                'avatar_url' => self::avatar($caller), 'chat_url' => $caller?->chatUrl()],
        ];
        // A private invitation can ring immediately, without an extra poll round trip.
        \App\Services\RealtimeUpdates::users([$calleeId], ['type' => 'call', 'incoming' => $incoming]);
        $video = (bool) ($call->video ?? 0); $audioOnly = (bool) ($call->audio_only ?? 0); $meeting = (string) ($call->meeting_code ?? ''); $group = (bool) ($call->group_invite ?? 0);
        self::later(function () use ($callId, $callerId, $calleeId, $video, $audioOnly, $meeting, $group) {
            $fresh = \Illuminate\Support\Facades\DB::table('friend_calls')->find($callId);
            if (!$fresh || $fresh->status !== 'ringing') return;
            $remaining = FriendCallService::RING_SECONDS - (time() - \Carbon\Carbon::parse($fresh->created_at)->getTimestamp());
            if ($remaining <= 0) return;
            $caller = User::find($callerId); $callee = User::find($calleeId);
            if (!$callee) return;
            app(FcmPush::class)->sendCall($callee, [
                'call_id' => $callId, 'uuid' => self::callUuid($callId),
                'caller_id' => $callerId, 'caller_name' => $caller?->name ?? 'Friend', 'caller_avatar' => self::avatar($caller),
                'video' => $video, 'audio_only' => $audioOnly, 'meeting' => $meeting, 'group' => $group,
                'sent_at' => time(),
            ], $remaining);
        }, afterResponse: true);
    }

    /** stops the ringing on every device of the callee. $why: cancelled | declined | answered | missed | ended */
    public static function stopRinging(object $call, string $why): void
    {
        \App\Services\RealtimeUpdates::users([$call->caller_id, $call->callee_id], ['type' => 'call_cancel', 'call_id' => (int) $call->id, 'reason' => $why,
            'code' => $call->meeting_code ?? null, 'group' => (bool) ($call->group_invite ?? false),
            'status' => $why === 'answered' ? 'active' : $why]);
        $callId = (int) $call->id;
        $recipients = $why === 'answered' ? [$call->callee_id] : (in_array($why, ['cancelled', 'missed'], true) ? [$call->callee_id, $call->caller_id] : [$call->caller_id, $call->callee_id]);
        self::later(function () use ($callId, $recipients, $why) {
            $users = User::whereIn('id', $recipients)->get()->keyBy('id');
            foreach ($recipients as $recipient) {
                $user = $users->get($recipient);
                if (!$user) continue;
                app(FcmPush::class)->sendSilent($user, ['type' => 'call_cancel', 'call_id' => $callId, 'uuid' => self::callUuid($callId), 'reason' => $why,
            'code' => $call->meeting_code ?? null, 'group' => (bool) ($call->group_invite ?? false),
            'status' => $why === 'answered' ? 'active' : $why]);
            }
        }, afterResponse: true);
    }

    /** a normal notification: "Missed call from …" */
    public static function missedCall(object $call): void
    {
        \App\Services\RealtimeUpdates::users([$call->caller_id, $call->callee_id], ['type' => 'call']);
        $callId = (int) $call->id; $callerId = (int) $call->caller_id; $calleeId = (int) $call->callee_id; $video = (bool) ($call->video ?? 0) && !($call->audio_only ?? 0);
        if ((int) ($call->group_invite ?? 0) === 1) return;
        self::later(function () use ($callId, $callerId, $calleeId, $video) {
            $caller = User::find($callerId); $callee = User::find($calleeId);
            if (!$callee) return;
            app(FcmPush::class)->sendToUser($callee, $caller?->name ?? 'Friend', $video ? 'Missed video call' : 'Missed call',
                ['type' => 'missed_call', 'sender_name' => $caller?->name ?? 'Friend', 'sender_avatar' => self::avatar($caller), 'video' => $video, 'from_id' => $callerId, 'call_id' => $callId, 'action_url' => $caller?->chatUrl() ?? '/people'], ['tag' => 'missed-' . $callerId, 'thread' => 'chat-' . $callerId]);
        }, afterResponse: true);
    }

    /** $row = the friend_messages row that was just stored */
    public static function message(object $row): void
    {
        \App\Services\RealtimeUpdates::users(!empty($row->visible_to) ? [$row->visible_to] : [$row->sender_id, $row->recipient_id], ['type' => 'chat', 'message_id' => (int) $row->id]);
        if (!in_array($row->kind, ['text', 'file', 'voice'], true)) return;
        $id = (int) $row->id; $from = (int) $row->sender_id; $to = (int) $row->recipient_id; $kind = (string) $row->kind;
        $body = (string) ($row->body ?? ''); $fileName = (string) ($row->file_name ?? ''); $mime = (string) ($row->file_mime ?? '');
        self::later(function () use ($id, $from, $to, $kind, $body, $fileName, $mime) {
            $sender = User::find($from); $rcpt = User::find($to);
            if (!$sender || !$rcpt || app(ChatPreferences::class)->muted($to, $from)) return;
            $text = match (true) {
                $kind === 'voice' => '🎤 Voice message',
                $kind === 'file' && str_starts_with($mime, 'image/') => '📷 ' . ($body !== '' ? $body : 'Photo'),
                $kind === 'file' && str_starts_with($mime, 'video/') => '🎥 ' . ($body !== '' ? $body : 'Video'),
                $kind === 'file' => '📎 ' . ($fileName !== '' ? $fileName : 'File'),
                default => mb_strcut($body, 0, 600, 'UTF-8'),
            };
            $accepted = app(FcmPush::class)->sendToUser($rcpt, $sender->name, $text,
                ['type' => 'chat', 'sender_name' => $sender->name, 'sender_avatar' => self::avatar($sender), 'from_id' => $from, 'message_id' => $id, 'action_url' => $sender->chatUrl()],
                ['tag' => 'chat-' . $from, 'thread' => 'chat-' . $from]);
            // FCM has accepted the push for at least one registered recipient device.
            // This is the correct point for the two grey delivery ticks; read_at is
            // still reserved for the recipient opening the conversation.
            if ($accepted) {
                \Illuminate\Support\Facades\DB::table('friend_messages')->where('id', $id)->whereNull('delivered_at')->update(['delivered_at' => now()]);
            }
        });
    }
}
