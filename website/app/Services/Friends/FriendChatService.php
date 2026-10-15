<?php

namespace App\Services\Friends;

use App\Models\User;
use App\Support\FriendSettings;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Messages, voice messages and file attachments between FRIENDS.
 * Files are stored privately and only the two people can download them.
 * "Clear chat" empties the conversation for the person who clears it (and deletes the files once BOTH have cleared);
 * it never touches the friendship. "Delete for both" removes everything for both people.
 */
class FriendChatService
{
    /** never accepted – programs and web pages that could do harm when opened */
    public const BLOCKED_EXT = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'com', 'msi', 'sh', 'js', 'jar', 'apk', 'scr', 'vbs', 'ps1', 'dll', 'html', 'htm', 'svg'];

    /** accepted for voice messages (phones record m4a/aac, browsers record webm/ogg/mp4) */
    public const VOICE_EXT = ['m4a', 'aac', 'mp3', 'wav', 'ogg', 'oga', 'opus', 'webm', 'mp4', '3gp'];

    private static ?bool $hasClears = null;
    private array $rcptMemo = [];

    private const EXT_MIME = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm', '3gp' => 'video/3gpp',
        'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'opus' => 'audio/ogg',
        'pdf' => 'application/pdf', 'txt' => 'text/plain'];

    /** phones and browsers often send "application/octet-stream" for photos / videos: trust the file extension then */
    public static function mimeFor(?string $mime, ?string $name): string
    {
        $mime = strtolower(trim(explode(';', (string) $mime)[0]));
        if ($mime !== '' && $mime !== 'application/octet-stream' && $mime !== 'binary/octet-stream') return $mime;
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        return self::EXT_MIME[$ext] ?? ($mime ?: 'application/octet-stream');
    }

    /** image | video | audio | pdf | other – what the app / website can show inside the chat */
    public static function typeOf(string $mime): string
    {
        if (preg_match('~^image/(jpeg|png|gif|webp)$~', $mime)) return 'image';
        if (preg_match('~^video/(mp4|webm|quicktime|3gpp)$~', $mime)) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        if ($mime === 'application/pdf') return 'pdf';
        return 'other';
    }

    /** may this person's read receipts (blue ticks) be used? (WhatsApp rule: needs BOTH people to have them on) */
    private function receipts(int $id): bool
    {
        return $this->rcptMemo[$id] ??= app(PresenceService::class)->receiptsEnabled($id);
    }

    /** Who may chat and call: accepted friends, and members of the same workspace (no friend request needed between teammates). */
    public function areFriends(int $a, int $b): bool
    {
        if ($this->wasFriends($a, $b)) return false;
        return DB::table('friend_requests')->where('status', 'accepted')->where(fn ($q) => $q
            ->where(fn ($w) => $w->where('requester_id', $a)->where('addressee_id', $b))
            ->orWhere(fn ($w) => $w->where('requester_id', $b)->where('addressee_id', $a)))->exists()
            || $this->teammates($a, $b);
    }

    public function teammates(int $a, int $b): bool
    {
        return DB::table('workspace_members as x')->join('workspace_members as y', 'x.workspace_id', '=', 'y.workspace_id')->where('x.user_id', $a)->where('y.user_id', $b)->exists();
    }

    public function friendOrFail(User $me, int $otherId): User
    {
        abort_unless($otherId !== $me->id && $this->areFriends($me->id, $otherId), 404);
        return User::where('status', 'active')->findOrFail($otherId);
    }

    /** the friendship row of two people, whatever its state (accepted / unfriended / …) */
    public function pairRow(int $a, int $b): ?object
    {
        return DB::table('friend_requests')->where(fn ($q) => $q->whereIn('status', ['accepted', 'unfriended'])->orWhereNotNull('unfriended_at'))->where(fn ($q) => $q
            ->where(fn ($w) => $w->where('requester_id', $a)->where('addressee_id', $b))
            ->orWhere(fn ($w) => $w->where('requester_id', $b)->where('addressee_id', $a)))->first();
    }

    /** were these two friends once (and not any more)? Their old chat stays readable. */
    public function wasFriends(int $a, int $b): bool
    {
        $r = $this->pairRow($a, $b);
        return $r && $r->status !== 'accepted' && ($r->status === 'unfriended' || $r->unfriended_at !== null);
    }

    /** may $me OPEN this chat? current friends, teammates and FORMER friends (read-only) */
    public function viewableOrFail(User $me, int $otherId): User
    {
        abort_unless($otherId !== $me->id && ($this->areFriends($me->id, $otherId) || $this->wasFriends($me->id, $otherId)), 404);
        return User::where('status', 'active')->findOrFail($otherId);
    }

    /** what the screen needs to know about the relationship: can I write? since / until when? */
    public function relation(int $meId, int $otherId): array
    {
        $r = $this->pairRow($meId, $otherId);
        $current = $this->areFriends($meId, $otherId);
        $fmt = fn ($v) => $v ? \Carbon\Carbon::parse($v)->toIso8601String() : null;
        return [
            'state' => $current ? ($r && $r->status === 'accepted' ? 'friends' : 'team') : ($r ? 'former' : 'none'),
            'read_only' => !$current,
            'since' => $r ? $fmt($r->responded_at) : null,
            'ended_at' => ($r && $this->wasFriends($meId, $otherId)) ? $fmt($r->unfriended_at) : null,
            'ended_by_me' => ($r && $this->wasFriends($meId, $otherId)) ? (int) $r->unfriended_by === $meId : null,
        ];
    }

    /** a grey line in the chat ("Became friends", "Unfriended"). $actor is the person who did it. */
    public function system(int $actor, int $other, string $text): void
    {
        DB::table('friend_messages')->insert(['sender_id' => $actor, 'recipient_id' => $other, 'body' => $text, 'kind' => 'system', 'read_at' => now(), 'delivered_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** messages up to this id were cleared by $me and are hidden from them */
    private function cutoff(int $me, int $other): int
    {
        self::$hasClears ??= Schema::hasTable('friend_chat_clears');
        if (!self::$hasClears) return 0;
        return (int) DB::table('friend_chat_clears')->where('user_id', $me)->where('other_id', $other)->value('before_id');
    }

    /** the messages between two people, as $a (the viewer) may see them: a private recording (visible_to) is only for its owner */
    private function pair($q, int $a, int $b, bool $onlyVisible = true)
    {
        $q->where(fn ($y) => $y->where(fn ($x) => $x->where('sender_id', $a)->where('recipient_id', $b))->orWhere(fn ($x) => $x->where('sender_id', $b)->where('recipient_id', $a)));
        return $onlyVisible ? $q->where(fn ($v) => $v->whereNull('visible_to')->orWhere('visible_to', $a)) : $q;
    }

    public function previews(User $me, array $peerIds): array
    {
        if (!$peerIds) return [];
        $q = DB::table('friend_messages as m')
            ->where(fn ($w) => $w->where(fn ($x) => $x->where('m.sender_id', $me->id)->whereIn('m.recipient_id', $peerIds))
                ->orWhere(fn ($x) => $x->where('m.recipient_id', $me->id)->whereIn('m.sender_id', $peerIds)))
            ->where(fn ($w) => $w->whereNull('m.visible_to')->orWhere('m.visible_to', $me->id));
        $peer = 'CASE WHEN m.sender_id = '.(int) $me->id.' THEN m.recipient_id ELSE m.sender_id END';
        if (Schema::hasTable('friend_chat_clears')) {
            $q->leftJoin('friend_chat_clears as c', fn ($join) => $join->on('c.other_id', '=', DB::raw($peer))->where('c.user_id', $me->id))
                ->whereRaw('m.id > COALESCE(c.before_id, 0)');
        }
        $ids = $q->selectRaw('MAX(m.id) as id')->groupByRaw($peer)->pluck('id');
        $out = [];
        foreach (DB::table('friend_messages')->whereIn('id', $ids)->get() as $message) {
            $id = (int) ($message->sender_id == $me->id ? $message->recipient_id : $message->sender_id);
            $out[$id] = $this->preview($message);
        }
        return $out;
    }

    private function preview(object $m): string
    {
        if (($m->deleted_at ?? null) !== null) return 'Message deleted';
        return match ($m->kind) {
            'voice' => '🎤 Voice message',
            'file' => '📎 ' . ($m->file_name ?: 'File'),
            'call' => '📞 ' . $m->body,
            'system' => 'ℹ️ ' . $m->body,
            default => (string) Str::limit((string) $m->body, 80),
        };
    }

    private function shape(object $m, int $meId, ?object $ref = null, string $otherName = 'Friend'): array
    {
        $t = Carbon::parse($m->created_at);
        $gone = ($m->deleted_at ?? null) !== null;
        return [
            'id' => (int) $m->id,
            'mine' => (int) $m->sender_id === $meId,
            'kind' => $m->kind,
            'deleted' => $gone,
            'edited' => !$gone && ($m->edited_at ?? null) !== null,
            'body' => $gone ? null : $m->body,
            'file' => (!$gone && $m->file_path) ? ['name' => $m->file_name, 'mime' => ($fm = self::mimeFor($m->file_mime, $m->file_name)), 'type' => self::typeOf($fm), 'size' => (int) $m->file_size] : null,
            'duration' => (!$gone && ($m->duration ?? null)) ? (int) $m->duration : null,
            'reply' => $ref ? ['id' => (int) $ref->id, 'name' => (int) $ref->sender_id === $meId ? 'You' : $otherName, 'kind' => $ref->kind, 'preview' => $this->preview($ref)] : null,
            'time' => $t->format('H:i'),
            'date' => $t->format('M j'),
            'at' => $t->toIso8601String(),
            'read' => $this->tickState($m, $meId) === 'read',
            'status' => (int) $m->sender_id === $meId && !in_array($m->kind, ['call', 'system'], true) ? $this->tickState($m, $meId) : null, // sent | delivered | read (mine only)
            'delivered_at' => ((int) $m->sender_id === $meId && ($m->delivered_at ?? $m->read_at)) ? Carbon::parse($m->delivered_at ?? $m->read_at)->format('M j, H:i') : null,
            'read_at' => ((int) $m->sender_id === $meId && $m->read_at && $this->tickState($m, $meId) === 'read') ? Carbon::parse($m->read_at)->format('M j, H:i') : null,
            'forwarded' => !$gone && (bool) ($m->forwarded ?? 0),
            'call_id' => ($m->call_id ?? null) ? (int) $m->call_id : null,
            'reactions' => $gone ? [] : ($this->reactionMap[(int) $m->id] ?? []),
        ];
    }

    private array $reactionMap = [];

    /** sent (one grey tick) → delivered (two grey) → read (two blue; only when both people allow read receipts) */
    private function tickState(object $m, int $meId): string
    {
        if ((int) $m->sender_id !== $meId) return 'read';
        $other = (int) $m->recipient_id;
        if ($m->read_at && $this->receipts($meId) && $this->receipts($other)) return 'read';
        if ($m->read_at || ($m->delivered_at ?? null)) return 'delivered';
        return 'sent';
    }

    private function loadReactions($rows, int $meId, string $otherName): void
    {
        $this->reactionMap = [];
        if (!Schema::hasTable('friend_message_reactions')) return;
        $ids = $rows->pluck('id')->all();
        if (!$ids) return;
        $by = [];
        foreach (DB::table('friend_message_reactions')->whereIn('message_id', $ids)->orderBy('created_at')->get() as $r) {
            $e = &$by[(int) $r->message_id][$r->emoji];
            $e ??= ['emoji' => $r->emoji, 'count' => 0, 'mine' => false, 'users' => []];
            $e['count']++;
            $mine = (int) $r->user_id === $meId;
            if ($mine) $e['mine'] = true;
            $e['users'][] = $mine ? 'You' : $otherName;
            unset($e);
        }
        foreach ($by as $mid => $list) $this->reactionMap[$mid] = array_values($list);
    }

    private function shapeMany($rows, int $meId, string $otherName): array
    {
        $this->loadReactions($rows, $meId, $otherName);
        $ids = $rows->map(fn ($m) => $m->reply_to_id ?? null)->filter()->unique()->values()->all();
        $refs = $ids ? DB::table('friend_messages')->whereIn('id', $ids)->get()->keyBy('id') : collect();
        return $rows->map(fn ($m) => $this->shape($m, $meId, $refs->get($m->reply_to_id ?? 0), $otherName))->all();
    }

    /**
     * Newest 60 messages, or only those after $after. Also returns "changes": messages the person already has that were edited or
     * deleted since $since (a server time returned by the previous call), and "now" (the time to send back next time).
     * @return array{data:array,changes:array,now:string}
     */
    public function messages(User $me, int $otherId, ?int $after, ?string $since = null, bool $markRead = true, ?int $onlyMessage = null): array
    {
        $other = $this->viewableOrFail($me, $otherId);
        $cut = $this->cutoff($me->id, $otherId);
        $now = now();
        $q = DB::table('friend_messages')->where(fn ($w) => $this->pair($w, $me->id, $otherId))->where('id', '>', $cut);
        if ($onlyMessage !== null) $q->where('id', $onlyMessage);
        $rows = $after ? (clone $q)->where('id', '>', $after)->orderBy('id')->limit(200)->get() : (clone $q)->orderByDesc('id')->limit(60)->get()->reverse()->values();
        $changes = collect();
        if ($after && $since) {
            try {
                $t = Carbon::parse($since)->subSeconds(2); // small overlap: nothing is missed, duplicates are harmless
                $changes = (clone $q)->where('id', '<=', $after)->where(function ($w) use ($t) {
                    $w->where('edited_at', '>', $t)->orWhere('deleted_at', '>', $t)->orWhere('read_at', '>', $t)->orWhere('delivered_at', '>', $t)->orWhere('reacted_at', '>', $t);
                })->orderBy('id')->limit(100)->get();
            } catch (\Throwable $e) {}
        }
        if ($markRead && $rows->isNotEmpty()) $this->markRead($me, $otherId, (int) $rows->max('id'));
        return ['data' => $this->shapeMany($rows, $me->id, app(ContactNames::class)->fields($me, $other)['name']), 'changes' => $this->shapeMany($changes, $me->id, app(ContactNames::class)->fields($me, $other)['name']), 'now' => $now->toIso8601String(), 'relation' => $this->relation($me->id, $otherId)];
    }

    /** Acknowledge only messages the client has rendered; never later arrivals. */
    public function markRead(User $me, int $otherId, int $through): int
    {
        $this->viewableOrFail($me, $otherId);
        $cut = $this->cutoff($me->id, $otherId);
        $visible = DB::table('friend_messages')->where(fn ($q) => $this->pair($q, $me->id, $otherId))->where('id', '>', $cut);
        abort_unless((clone $visible)->where('id', $through)->exists(), 422, 'Message is not in this conversation.');
        $read = (clone $visible)->where('sender_id', $otherId)->where('recipient_id', $me->id)
            ->where('id', '<=', $through)->whereNull('read_at')->whereNull('deleted_at')
            ->update(['read_at' => now(), 'delivered_at' => now()]);
        if ($read) \App\Services\RealtimeUpdates::users([$me->id, $otherId], ['type' => 'chat_read']);
        return $read;
    }

    /** @return array{0:bool,1:string,2:?array} */
    public function send(User $me, int $otherId, ?string $body, ?UploadedFile $file, ?int $replyTo = null, ?int $duration = null, bool $voice = false): array
    {
        if (!FriendSettings::chatEnabled()) return [false, 'Chat is switched off.', null];
        if (!$this->areFriends($me->id, $otherId) && $this->wasFriends($me->id, $otherId)) return [false, 'You are no longer friends. You can read this chat, but not write in it.', null];
        $other = $this->friendOrFail($me, $otherId);
        $limiter = "friend-chat:{$me->id}";
        if (RateLimiter::tooManyAttempts($limiter, 120)) return [false, 'You are sending too fast. Wait a moment.', null];
        RateLimiter::hit($limiter, 60);

        $body = trim((string) $body);
        if ($body === '' && !$file) return [false, 'Write a message or attach a file.', null];
        if (mb_strlen($body) > 4000) return [false, 'The message is too long.', null];

        $row = ['sender_id' => $me->id, 'recipient_id' => $otherId, 'body' => $body !== '' ? $body : null, 'kind' => 'text', 'created_at' => now(), 'updated_at' => now()];

        // reply: only to a message of THIS chat that you can still see
        $ref = null;
        if ($replyTo) {
            $ref = DB::table('friend_messages')->where('id', $replyTo)->whereNull('deleted_at')->where(fn ($w) => $this->pair($w, $me->id, $otherId))->where('id', '>', $this->cutoff($me->id, $otherId))->first();
            if ($ref && Schema::hasColumn('friend_messages', 'reply_to_id')) $row['reply_to_id'] = $ref->id; else $ref = null;
        }

        if ($file) {
            if (!FriendSettings::filesEnabled()) return [false, 'Sending files is switched off.', null];
            if (!$file->isValid()) return [false, 'The file could not be uploaded.', null];
            $mb = FriendSettings::fileMaxMb();
            if ($file->getSize() > $mb * 1024 * 1024) return [false, "The file is larger than {$mb} MB.", null];
            $ext = strtolower($file->getClientOriginalExtension());
            $mime = strtolower(trim(explode(';', (string) $file->getClientMimeType())[0]));
            if ($voice) {
                if (!in_array($ext, self::VOICE_EXT, true) || !(str_starts_with($mime, 'audio/') || in_array($mime, ['video/webm', 'video/mp4', 'application/ogg'], true))) return [false, 'This voice format is not supported.', null];
                $row['kind'] = 'voice';
                $row['duration'] = min(900, max(1, (int) $duration));
            } else {
                if (in_array($ext, self::BLOCKED_EXT, true)) return [false, 'This type of file cannot be sent.', null];
                $row['kind'] = 'file';
            }
            $name = trim((string) Str::limit(preg_replace('/[^\w.\- ()]+/u', '_', $file->getClientOriginalName()), 120, ''));
            $folder = 'friend-files/' . min($me->id, $otherId) . '-' . max($me->id, $otherId);
            $path = $file->storeAs($folder, Str::uuid() . ($ext !== '' ? '.' . $ext : ''), 'local');
            $row += ['file_path' => $path, 'file_name' => $name !== '' ? $name : ($voice ? 'voice.' . $ext : 'file'), 'file_mime' => self::mimeFor($mime, $name), 'file_size' => $file->getSize()];
        }
        $id = DB::table('friend_messages')->insertGetId($row);
        $saved = DB::table('friend_messages')->find($id);
        FriendPush::message($saved);                                   // reaches the phone / browser even when the app is closed
        return [true, 'Sent.', $this->shape($saved, $me->id, $ref, app(ContactNames::class)->fields($me, $other)['name'])];
    }

    /** A message of this chat that is still visible to $me (not deleted, not cleared). */
    private function visible(User $me, int $otherId, int $messageId): ?object
    {
        return DB::table('friend_messages')->where('id', $messageId)->whereNull('deleted_at')->whereNotIn('kind', ['call', 'system'])->where(fn ($w) => $this->pair($w, $me->id, $otherId))->where('id', '>', $this->cutoff($me->id, $otherId))->first();
    }

    /** React with an emoji (the same emoji again removes it). One reaction per person per message. @return array{0:bool,1:string,2:?array} */
    public function react(User $me, int $otherId, int $messageId, ?string $emoji): array
    {
        $other = $this->friendOrFail($me, $otherId);
        $m = $this->visible($me, $otherId, $messageId);
        if (!$m) return [false, 'This message is no longer available.', null];
        $emoji = trim((string) $emoji);
        if ($emoji !== '' && (mb_strlen($emoji) > 12 || preg_match('/[\p{L}\p{N}]/u', $emoji))) return [false, 'Pick an emoji.', null];
        $cur = DB::table('friend_message_reactions')->where('message_id', $messageId)->where('user_id', $me->id)->value('emoji');
        if ($emoji === '' || $emoji === $cur) DB::table('friend_message_reactions')->where('message_id', $messageId)->where('user_id', $me->id)->delete();
        else DB::table('friend_message_reactions')->updateOrInsert(['message_id' => $messageId, 'user_id' => $me->id], ['emoji' => $emoji, 'created_at' => now()]);
        DB::table('friend_messages')->where('id', $messageId)->update(['reacted_at' => now()]);
        return [true, 'OK', $this->shapeMany(collect([DB::table('friend_messages')->find($messageId)]), $me->id, app(ContactNames::class)->fields($me, $other)['name'])[0]];
    }

    /** Forward a message (text, photo, video, file, voice) to up to 5 other friends / teammates. @return array{0:bool,1:string,2:int} */
    public function forward(User $me, int $fromUserId, int $messageId, array $toIds): array
    {
        if (!FriendSettings::chatEnabled()) return [false, 'Chat is switched off.', 0];
        $this->friendOrFail($me, $fromUserId);
        $m = $this->visible($me, $fromUserId, $messageId);
        if (!$m) return [false, 'This message is no longer available.', 0];
        $toIds = array_slice(array_values(array_unique(array_map('intval', $toIds))), 0, 5);
        if (!$toIds) return [false, 'Choose who to send it to.', 0];
        $limiter = "friend-chat:{$me->id}";
        $n = 0;
        foreach ($toIds as $to) {
            if ($to === $me->id || !$this->areFriends($me->id, $to) || RateLimiter::tooManyAttempts($limiter, 120)) continue;
            RateLimiter::hit($limiter, 60);
            $row = ['sender_id' => $me->id, 'recipient_id' => $to, 'body' => $m->body, 'kind' => $m->kind, 'duration' => $m->duration ?? null, 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn('friend_messages', 'forwarded')) $row['forwarded'] = 1;
            if ($m->file_path) {
                if (!FriendSettings::filesEnabled()) continue;
                $ext = pathinfo((string) $m->file_path, PATHINFO_EXTENSION);
                $new = 'friend-files/' . min($me->id, $to) . '-' . max($me->id, $to) . '/' . Str::uuid() . ($ext !== '' ? '.' . $ext : '');
                try { if (!Storage::disk('local')->copy($m->file_path, $new)) continue; } catch (\Throwable $e) { continue; }
                $row += ['file_path' => $new, 'file_name' => $m->file_name, 'file_mime' => $m->file_mime, 'file_size' => $m->file_size];
            }
            $fid = DB::table('friend_messages')->insertGetId($row);
            FriendPush::message(DB::table('friend_messages')->find($fid));
            $n++;
        }
        return $n > 0 ? [true, $n === 1 ? 'Forwarded.' : "Forwarded to $n people.", $n] : [false, 'Could not forward the message.', 0];
    }

    /** ids of everybody $me may chat with (accepted friends + teammates), one query each */
    private function allowedIds(User $me): array
    {
        $f = DB::table('friend_requests')->where('status', 'accepted')->where(fn ($q) => $q->where('requester_id', $me->id)->orWhere('addressee_id', $me->id))
            ->get(['requester_id', 'addressee_id'])->map(fn ($r) => (int) ((int) $r->requester_id === (int) $me->id ? $r->addressee_id : $r->requester_id))->all();
        $t = DB::table('workspace_members as x')->join('workspace_members as y', 'x.workspace_id', '=', 'y.workspace_id')->where('x.user_id', $me->id)->pluck('y.user_id')->map(fn ($i) => (int) $i)->all();
        return array_diff_key(array_flip(array_merge($f, $t)), $this->formerIds($me));
    }

    /** ids of people you were friends with and are not any more (id => true) */
    private function formerIds(User $me): array
    {
        return DB::table('friend_requests')->where('status', '!=', 'accepted')->where(fn ($q) => $q->where('status', 'unfriended')->orWhereNotNull('unfriended_at'))->where(fn ($q) => $q->where('requester_id', $me->id)->orWhere('addressee_id', $me->id))
            ->get(['requester_id', 'addressee_id'])->mapWithKeys(fn ($r) => [(int) ((int) $r->requester_id === (int) $me->id ? $r->addressee_id : $r->requester_id) => true])->all();
    }

    /**
     * The chat list: one row per person you talk to, newest first – last message, who wrote it, ticks, unread count, online.
     * Used by the app's Chats tab (together with the WhatsApp / Telegram / SMS conversations) and by the website.
     * @return array<int,array<string,mixed>>
     */
    public function recent(User $me, int $limit = 60): array
    {
        $rows = DB::table('friend_messages')->where(fn ($q) => $q->where('sender_id', $me->id)->orWhere('recipient_id', $me->id))
            ->where(fn ($v) => $v->whereNull('visible_to')->orWhere('visible_to', $me->id))
            // Relationship notices stay in history without moving the conversation.
            ->selectRaw("CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END AS partner, COALESCE(MAX(CASE WHEN kind != 'system' THEN id END), MIN(id)) AS mid", [$me->id])
            ->groupBy('partner')->orderByDesc('mid')->get();
        $preferences = app(ChatPreferences::class)->all($me->id);
        foreach ($preferences as $peer => $pref) {
            if (!$rows->contains('partner', $peer)) $rows->push((object) ['partner' => $peer, 'mid' => 0]);
        }
        if ($rows->isEmpty()) return [];
        $msgs = DB::table('friend_messages')->whereIn('id', $rows->pluck('mid')->all())->get()->keyBy('id');
        $users = User::whereIn('id', $rows->pluck('partner')->all())->where('status', 'active')->get()->keyBy('id');
        $allowed = $this->allowedIds($me);
        $former = $this->formerIds($me);
        $unread = $this->unreadCounts($me);
        $presence = app(PresenceService::class)->forViewer($me, $rows->pluck('partner')->map(fn ($i) => (int) $i)->all());
        $tz = $me->timezone ?: config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $out = [];
        foreach ($rows as $r) {
            $pid = (int) $r->partner; $m = $msgs->get($r->mid); $u = $users->get($pid);
            if (!$u || !(isset($allowed[$pid]) || isset($former[$pid]))) continue;
            $pref = $preferences[$pid] ?? null;
            if ($pref && $pref->hidden_through_id !== null && (!$m || (int) $m->id <= (int) $pref->hidden_through_id)) continue;
            $empty = !$m || (int) $m->id <= $this->cutoff($me->id, $pid);
            // Clearing content must not remove an existing conversation from the list.
            if (!$m) $m = (object) ['id' => 0, 'sender_id' => $me->id, 'file_path' => null, 'kind' => 'system', 'body' => '', 'created_at' => $pref->updated_at];
            $mine = (int) $m->sender_id === $me->id;
            $mime = $m->file_path ? self::realMime($m->file_name, $m->file_mime) : '';
            $text = ($m->deleted_at ?? null) !== null ? 'This message was deleted'
                : match (true) {
                    $m->kind === 'voice' => '🎤 Voice message',
                    $m->kind === 'call' => '📞 ' . $m->body . ($m->file_path ? ' · 🎙' : ''),
                    $m->kind === 'system' => 'ℹ️ ' . $m->body,
                    (bool) $m->file_path && str_starts_with($mime, 'image/') => '📷 ' . ($m->body ?: 'Photo'),
                    (bool) $m->file_path && str_starts_with($mime, 'video/') => '🎥 ' . ($m->body ?: 'Video'),
                    (bool) $m->file_path && str_starts_with($mime, 'audio/') => '🎵 Audio',
                    (bool) $m->file_path => '📎 ' . ($m->file_name ?: 'File'),
                    default => (string) Str::limit((string) $m->body, 90),
                };
            $t = Carbon::parse($m->created_at, 'UTC')->setTimezone($tz);
            $out[] = [
                'kind' => 'friend', 'id' => $pid, ...app(ContactNames::class)->fields($me, $u), 'username' => \App\Support\Username::plain($u->username), 'chat_url' => $u->chatUrl(), 'avatar_url' => $u->avatar_path ? asset('storage/' . $u->avatar_path) : null,
                'pinned' => (bool) ($pref->pinned ?? false), 'muted' => (bool) ($pref->muted ?? false), 'archived' => (bool) ($pref->archived ?? false),
                'preview' => $empty ? '' : $text, 'mine' => !$empty && $mine, 'status' => !$empty && $mine && !in_array($m->kind, ['call', 'system'], true) ? $this->tickState($m, $me->id) : null, 'message_kind' => $m->kind,
                'unread' => (int) ($unread[$pid] ?? 0), 'online' => !isset($former[$pid]) && (bool) ($presence[$pid]['online'] ?? false), 'former' => !isset($allowed[$pid]),
                'time' => $t->isSameDay($now) ? $t->format('H:i') : ($t->isSameDay($now->copy()->subDay()) ? 'Yesterday' : $t->format('M j')),
                'at' => $t->toIso8601String(),
            ];
        }
        usort($out, fn ($a, $b) => ($b['pinned'] <=> $a['pinned']) ?: strcmp($b['at'], $a['at']));
        return array_slice($out, 0, $limit);
    }

    /** people you can forward to (friends + teammates) */
    public function targets(User $me): array
    {
        $dir = app(PeopleService::class)->directory($me);
        $seen = []; $out = [];
        foreach (array_merge($dir['friends'] ?? [], $dir['team'] ?? []) as $p) {
            $id = (int) $p['id']; if (isset($seen[$id])) continue; $seen[$id] = 1;
            $out[] = ['id' => $id, 'name' => $p['name'], 'avatar_url' => $p['avatar_url'] ?? null];
        }
        usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    /** Edit the text of one of YOUR messages (text, or the caption of a file). @return array{0:bool,1:string,2:?array} */
    public function edit(User $me, int $otherId, int $messageId, string $body): array
    {
        $other = $this->friendOrFail($me, $otherId);
        $m = DB::table('friend_messages')->where('id', $messageId)->where('sender_id', $me->id)->where('recipient_id', $otherId)->whereNull('deleted_at')->first();
        if (!$m || !in_array($m->kind, ['text', 'file'], true)) return [false, 'This message cannot be edited.', null];
        $body = trim($body);
        if (mb_strlen($body) > 4000) return [false, 'The message is too long.', null];
        if ($body === '' && $m->kind === 'text') return [false, 'A message cannot be empty. Delete it instead.', null];
        if ($body === (string) $m->body) return [true, 'No change.', $this->shape($m, $me->id, null, app(ContactNames::class)->fields($me, $other)['name'])];
        DB::table('friend_messages')->where('id', $messageId)->update(['body' => $body !== '' ? $body : null, 'edited_at' => now(), 'updated_at' => now()]);
        return [true, 'Edited.', $this->shape(DB::table('friend_messages')->find($messageId), $me->id, null, app(ContactNames::class)->fields($me, $other)['name'])];
    }

    /** Delete one of YOUR messages for both of you: the text and any file / voice recording are removed, a "deleted" note stays. @return array{0:bool,1:string,2:?array} */
    public function delete(User $me, int $otherId, int $messageId): array
    {
        $other = $this->friendOrFail($me, $otherId);
        $m = DB::table('friend_messages')->where('id', $messageId)->where('sender_id', $me->id)->where('recipient_id', $otherId)->whereNull('deleted_at')->first();
        if (!$m || $m->kind === 'call') return [false, 'This message cannot be deleted.', null];
        if ($m->file_path) Storage::disk('local')->delete($m->file_path);
        DB::table('friend_messages')->where('id', $messageId)->update([
            'body' => null, 'file_path' => null, 'file_name' => null, 'file_mime' => null, 'file_size' => null, 'duration' => null,
            'deleted_at' => now(), 'updated_at' => now(), 'read_at' => $m->read_at ?? now(),
        ]);
        return [true, 'Message deleted.', $this->shape(DB::table('friend_messages')->find($messageId), $me->id, null, app(ContactNames::class)->fields($me, $other)['name'])];
    }

    /**
     * Clear the chat for YOU: every message, voice message and file disappears for you; the friend keeps their copy. Files are
     * deleted from the server once BOTH of you have cleared them. The friendship is never affected.
     * @return array{0:bool,1:string}
     */
    public function clear(User $me, int $otherId): array
    {
        $this->viewableOrFail($me, $otherId);
        if (Schema::hasTable('friend_chat_preferences')) DB::table('friend_chat_preferences')->insertOrIgnore([
            'user_id' => $me->id, 'other_id' => $otherId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $max = (int) DB::table('friend_messages')->where(fn ($w) => $this->pair($w, $me->id, $otherId))->max('id');
        if ($max > 0 && Schema::hasTable('friend_chat_clears')) {
            DB::table('friend_chat_clears')->updateOrInsert(['user_id' => $me->id, 'other_id' => $otherId], ['before_id' => $max]);
            DB::table('friend_messages')->where('sender_id', $otherId)->where('recipient_id', $me->id)->whereNull('read_at')->where('id', '<=', $max)->update(['read_at' => now()]);
            $this->purge($me->id, $otherId);
        }
        return [true, 'The chat was cleared.'];
    }

    /** delete (rows and files) what BOTH people have cleared */
    private function purge(int $a, int $b): void
    {
        $upTo = min($this->cutoff($a, $b), $this->cutoff($b, $a));
        if ($upTo <= 0) return;
        $rows = DB::table('friend_messages')->where(fn ($w) => $this->pair($w, $a, $b, false))->where('id', '<=', $upTo)->get(['id', 'file_path']);
        foreach ($rows as $r) if ($r->file_path) Storage::disk('local')->delete($r->file_path);
        DB::table('friend_messages')->where(fn ($w) => $this->pair($w, $a, $b, false))->where('id', '<=', $upTo)->delete();
    }

    /** @return array{0:string,1:string,2:string} [absolute path, file name, mime] – only for the two people in the chat */
    public function fileFor(User $me, int $messageId): array
    {
        $m = DB::table('friend_messages')->find($messageId);
        abort_unless($m && $m->file_path && in_array($me->id, [(int) $m->sender_id, (int) $m->recipient_id], true), 404);
        abort_unless(($m->visible_to ?? null) === null || (int) $m->visible_to === $me->id, 404); // somebody else's private recording
        $other = (int) $m->sender_id === $me->id ? (int) $m->recipient_id : (int) $m->sender_id;
        abort_if((int) $m->id <= $this->cutoff($me->id, $other), 404); // cleared by you
        $abs = Storage::disk('local')->path($m->file_path);
        abort_unless(is_file($abs), 404);
        return [$abs, $m->file_name ?: 'file', self::mimeFor($m->file_mime, $m->file_name)];
    }

    /**
     * What is new for the person, for the live alerts: the newest unread message from a friend (text, file, voice or a "Missed call"
     * entry) and how many unread messages there are.
     */
    public function liveSummary(User $me): array
    {
        $kinds = ['text', 'file', 'voice'];
        try { DB::table('friend_messages')->where('recipient_id', $me->id)->whereNull('delivered_at')->update(['delivered_at' => now()]); } catch (\Throwable $e) {} // the phone / browser has them: two ticks for the sender
        $unread = (int) DB::table('friend_messages')->where('recipient_id', $me->id)->whereNull('visible_to')->whereNull('read_at')->whereNull('deleted_at')->whereIn('kind', $kinds)->count();
        $muted = array_keys(array_filter(app(ChatPreferences::class)->all($me->id), fn ($p) => $p->muted));
        $m = DB::table('friend_messages')->whereNotIn('sender_id', $muted)->where('recipient_id', $me->id)->whereNull('visible_to')->whereNull('read_at')->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereIn('kind', $kinds)->orWhere(fn ($w) => $w->where('kind', 'call')->whereIn('body', ['Missed call', 'Missed video call'])))
            ->orderByDesc('id')->first();
        if (!$m) return ['unread' => $unread, 'latest' => null];
        $from = User::find($m->sender_id);
        $preview = $this->preview($m);
        if ($m->kind === 'file' && $m->body) $preview .= ' – ' . $m->body;
        if ($m->kind === 'text') $preview = (string) $m->body;
        return ['unread' => $unread, 'latest' => [
            'id' => (int) $m->id, 'from_id' => (int) $m->sender_id, 'chat_url' => $from?->chatUrl() ?? url('/people'), 'from_name' => $from?->name ?? 'Friend',
            'from_avatar' => $from?->avatar_path ? asset('storage/'.$from->avatar_path) : '',
            'kind' => $m->kind, 'preview' => Str::limit($preview, 140),
        ]];
    }

    /** sender id => number of unread messages for $me */
    public function unreadCounts(User $me): array
    {
        return DB::table('friend_messages')->where('recipient_id', $me->id)->whereNull('visible_to')->whereNull('read_at')->whereNull('deleted_at')->whereIn('kind', ['text', 'file', 'voice'])
            ->groupBy('sender_id')->selectRaw('sender_id, COUNT(*) as c')->pluck('c', 'sender_id')->map(fn ($c) => (int) $c)->all();
    }
}
