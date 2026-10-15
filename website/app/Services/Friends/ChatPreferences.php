<?php

namespace App\Services\Friends;

use App\Models\User;
use App\Services\RealtimeUpdates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Private chat organization shared by all of a user's devices. */
class ChatPreferences
{
    public function all(int $userId): array
    {
        return Schema::hasTable('friend_chat_preferences')
            ? DB::table('friend_chat_preferences')->where('user_id', $userId)->get()->keyBy('other_id')->all() : [];
    }

    public function muted(int $userId, int $otherId): bool
    {
        return (bool) ($this->all($userId)[$otherId]->muted ?? false);
    }

    public function apply(User $user, array $ids, string $action): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids || count($ids) > 100 || !in_array($action, ['pin', 'unpin', 'mute', 'unmute', 'archive', 'unarchive', 'delete', 'clear'], true)) {
            throw ValidationException::withMessages(['action' => 'Choose chats and a valid action.']);
        }
        $chat = app(FriendChatService::class);
        // Authorize the complete selection before changing any row.
        foreach ($ids as $id) $chat->viewableOrFail($user, $id);
        DB::transaction(function () use ($user, $ids, $action, $chat) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($action === 'pin') {
                $pinned = DB::table('friend_chat_preferences')->where('user_id', $user->id)->where('pinned', true)->pluck('other_id')->all();
                if (count(array_unique(array_merge($pinned, $ids))) > 5) {
                    throw ValidationException::withMessages(['ids' => 'You can pin up to 5 chats.']);
                }
            }
            foreach ($ids as $id) {
                $values = ['updated_at' => now()];
                if ($action === 'clear') {
                    $chat->clear($user, $id);
                    $values['hidden_through_id'] = null;
                } elseif ($action === 'delete') {
                    $values += ['hidden_through_id' => (int) DB::table('friend_messages')->where(fn ($q) => $q
                        ->where(fn ($w) => $w->where('sender_id', $user->id)->where('recipient_id', $id))
                        ->orWhere(fn ($w) => $w->where('sender_id', $id)->where('recipient_id', $user->id)))->max('id'), 'pinned' => false];
                } else {
                    $column = match ($action) { 'pin', 'unpin' => 'pinned', 'mute', 'unmute' => 'muted', default => 'archived' };
                    $values[$column] = !str_starts_with($action, 'un');
                    $values['hidden_through_id'] = null;
                }
                DB::table('friend_chat_preferences')->insertOrIgnore(['user_id' => $user->id, 'other_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('friend_chat_preferences')->where('user_id', $user->id)->where('other_id', $id)->update($values);
            }
            RealtimeUpdates::users([$user->id], ['type' => 'chat_preferences']);
        });
    }
}
