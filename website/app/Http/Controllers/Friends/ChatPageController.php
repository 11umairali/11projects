<?php

namespace App\Http\Controllers\Friends;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Friends\FriendChatService;

class ChatPageController extends Controller
{
    private function page(User $user, FriendChatService $chat)
    {
        $friend = $chat->viewableOrFail(auth()->user(), $user->id);
        return view('friends.chat', ['friend' => $friend, 'relation' => $chat->relation(auth()->id(), $friend->id), 'profile' => app(\App\Services\Friends\PeopleService::class)->profile(auth()->user(), $friend->id)]);
    }

    public function username(string $username, FriendChatService $chat)
    {
        $user = \App\Support\Username::find($username);
        $chat->viewableOrFail(auth()->user(), $user->id);
        if (url()->current() !== $user->chatUrl()) return redirect($user->chatUrl());
        return $this->page($user, $chat);
    }

    public function uuid(string $uuid, FriendChatService $chat)
    {
        $user = User::where('uuid', $uuid)->firstOrFail();
        $chat->viewableOrFail(auth()->user(), $user->id);
        return $user->username ? redirect($user->chatUrl()) : $this->page($user, $chat);
    }

    public function legacy(int $userId, FriendChatService $chat)
    {
        $friend = $chat->viewableOrFail(auth()->user(), $userId);
        return redirect($friend->chatUrl());
    }
}
