<?php

namespace App\Http\Controllers\Friends;

use App\Http\Controllers\Controller;
use App\Services\Friends\FriendChatService;
use App\Support\FriendSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Friend chat endpoints – used by BOTH the website (session login) and the mobile app (token). */
class FriendChatController extends Controller
{
    public function messages(Request $request, int $userId): JsonResponse
    {
        if (!FriendSettings::chatEnabled()) return response()->json(['message' => 'Chat is switched off.', 'data' => []], 403);
        $validated = $request->validate(['message_id' => 'nullable|integer|min:1']);
        $after = $request->query('after') !== null ? (int) $request->query('after') : null;
        $r = app(FriendChatService::class)->messages($request->user(), $userId, $after ?: null, $request->query('since'), !$request->is('api/*') && $request->boolean('mark_read', true), isset($validated['message_id']) ? (int) $validated['message_id'] : null);
        try { app(\App\Services\Friends\PresenceService::class)->touch($request->user()->id); } catch (\Throwable $e) {}
        $pr = app(\App\Services\Friends\PresenceService::class)->forViewer($request->user(), [$userId])[$userId] ?? ['online' => false, 'label' => null];
        return response()->json(['data' => $r['data'], 'changes' => $r['changes'], 'now' => $r['now'], 'presence' => $pr, 'relation' => $r['relation'] ?? null]);
    }

    public function read(Request $request, int $userId): JsonResponse
    {
        $data = $request->validate(['through_message_id' => 'required|integer|min:1']);
        app(FriendChatService::class)->markRead($request->user(), $userId, (int) $data['through_message_id']);
        return response()->json(['message' => 'Read receipt saved.', 'unread' => array_sum(app(FriendChatService::class)->unreadCounts($request->user()))]);
    }

    public function send(Request $request, int $userId): JsonResponse
    {
        $request->validate(['body' => 'nullable|string|max:4000', 'file' => 'nullable|file', 'reply_to' => 'nullable|integer', 'duration' => 'nullable|integer|min:0|max:3600', 'voice' => 'nullable']);
        [$ok, $msg, $data] = app(FriendChatService::class)->send(
            $request->user(), $userId, $request->input('body'), $request->file('file'),
            $request->filled('reply_to') ? (int) $request->input('reply_to') : null,
            $request->filled('duration') ? (int) $request->input('duration') : null,
            filter_var($request->input('voice'), FILTER_VALIDATE_BOOLEAN)
        );
        return response()->json(['message' => $msg, 'data' => $data], $ok ? 200 : 422);
    }

    /** Edit one of your own messages. */
    public function edit(Request $request, int $userId, int $messageId): JsonResponse
    {
        $d = $request->validate(['body' => 'present|nullable|string|max:4000']);
        [$ok, $msg, $data] = app(FriendChatService::class)->edit($request->user(), $userId, $messageId, (string) ($d['body'] ?? ''));
        return response()->json(['message' => $msg, 'data' => $data], $ok ? 200 : 422);
    }

    /** Delete one of your own messages (for both of you; a "deleted" note stays). */
    public function delete(Request $request, int $userId, int $messageId): JsonResponse
    {
        [$ok, $msg, $data] = app(FriendChatService::class)->delete($request->user(), $userId, $messageId);
        return response()->json(['message' => $msg, 'data' => $data], $ok ? 200 : 422);
    }

    /** Clear the chat for YOU (the friend keeps their copy). The friendship stays. */
    public function clear(Request $request, int $userId): JsonResponse
    {
        [$ok, $msg] = app(FriendChatService::class)->clear($request->user(), $userId);
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }

    public function file(Request $request, int $messageId)
    {
        [$abs, $name, $mime] = app(FriendChatService::class)->fileFor($request->user(), $messageId);
        // photos, videos, audio, PDF and plain text may be shown INSIDE the chat / browser (?inline=1); everything else is a download
        $safe = (bool) preg_match('~^(image/(jpeg|png|gif|webp)|video/(mp4|webm|quicktime|3gpp)|audio/[\w.+-]+|application/pdf|text/plain)$~i', $mime);
        $h = ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600'];
        if ($request->boolean('inline') && $safe) {
            return response()->file($abs, $h + ['Content-Disposition' => 'inline; filename="' . addslashes(preg_replace('/[^\w.\- ]/', '_', $name)) . '"']);
        }
        return response()->download($abs, $name, $h);
    }

    public function react(Request $request, int $userId, int $messageId): JsonResponse
    {
        $request->validate(['emoji' => 'nullable|string|max:40']);
        [$ok, $msg, $data] = app(FriendChatService::class)->react($request->user(), $userId, $messageId, $request->input('emoji'));
        return response()->json(['message' => $msg, 'data' => $data], $ok ? 200 : 422);
    }

    public function forward(Request $request, int $userId, int $messageId): JsonResponse
    {
        $d = $request->validate(['to' => 'required|array|min:1|max:5', 'to.*' => 'integer']);
        [$ok, $msg, $n] = app(FriendChatService::class)->forward($request->user(), $userId, $messageId, $d['to']);
        return response()->json(['message' => $msg, 'data' => ['sent' => $n]], $ok ? 200 : 422);
    }

    public function targets(Request $request): JsonResponse
    {
        return response()->json(['data' => app(FriendChatService::class)->targets($request->user())]);
    }

    /** the chat list (one row per person) – app Chats tab + website */
    public function organize(Request $request): JsonResponse
    {
        $d = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|integer|min:1', 'action' => 'required|in:pin,unpin,mute,unmute,archive,unarchive,delete,clear']);
        app(\App\Services\Friends\ChatPreferences::class)->apply($request->user(), $d['ids'], $d['action']);
        return response()->json(['message' => 'Chats updated.']);
    }

    public function chats(Request $request): JsonResponse
    {
        if (!FriendSettings::chatEnabled()) return response()->json(['data' => [], 'features' => FriendSettings::features()]);
        try { app(\App\Services\Friends\PresenceService::class)->touch($request->user()->id); } catch (\Throwable $e) {}
        return response()->json(['data' => app(FriendChatService::class)->recent($request->user()), 'features' => FriendSettings::features()]);
    }
}
