<?php

namespace App\Http\Controllers\Friends;

use App\Http\Controllers\Controller;
use App\Services\Friends\FriendCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Audio-call endpoints – used by BOTH the website and the mobile app, so they can call each other. */
class FriendCallController extends Controller
{
    private function svc(): FriendCallService { return app(FriendCallService::class); }

    /** a failure while starting a call is logged; admins also see the real reason on screen (everybody else gets a plain message) */
    private function failed(Request $request, \Throwable $e): JsonResponse
    {
        \Illuminate\Support\Facades\Log::error('call start failed: ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine()]);
        $admin = (bool) ($request->user()->is_admin ?? false);
        $status = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 500;
        if ($status >= 400 && $status < 500) return response()->json(['message' => $e->getMessage() ?: 'This person is not available.', 'data' => null], $status);
        return response()->json(['message' => $admin ? 'Call error (admin detail): ' . $e->getMessage() . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']' : 'Could not start the call. Please try again.', 'data' => null], 500);
    }

    public function start(Request $request, int $userId): JsonResponse
    {
        try {
            [$ok, $msg, $data] = $this->svc()->start($request->user(), $userId);
        } catch (\Throwable $e) { return $this->failed($request, $e); }
        return response()->json(['message' => $msg, 'data' => $data], $ok ? 200 : 422);
    }

    /** Video call: a private 2-person meeting; the friend's phone / browser rings and joins the same room. */
    public function startVideo(Request $request, int $userId): JsonResponse
    {
        try {
            [$ok, $msg, $data] = app(FriendCallService::class)->start($request->user(), $userId, true, filter_var($request->input('audio_only', $request->query('audio')), FILTER_VALIDATE_BOOLEAN));
        } catch (\Throwable $e) { return $this->failed($request, $e); }
        return response()->json(['message' => $msg, 'data' => $data], $ok ? 200 : 422);
    }

    public function clearHistory(Request $request): JsonResponse
    {
        app(FriendCallService::class)->clearHistory($request->user());
        return response()->json(['message' => 'Call history cleared.']);
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json(['data' => app(FriendCallService::class)->history($request->user())]);
    }

    public function poll(Request $request): JsonResponse
    {
        try { app(\App\Services\Friends\PresenceService::class)->touch($request->user()->id); } catch (\Throwable $e) {} // "I am online" heartbeat
        return response()->json(['data' => $this->svc()->poll($request->user())]);
    }

    public function signal(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['type' => 'required|string', 'payload' => 'required|string']);
        [$ok, $msg] = $this->svc()->signal($request->user(), $id, $d['type'], $d['payload']);
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }

    public function signals(Request $request, int $id): JsonResponse
    {
        $r = $this->svc()->signals($request->user(), $id, (int) $request->query('after', 0));
        return $r === null ? response()->json(['message' => 'Not found.'], 404) : response()->json(['data' => $r]);
    }

    public function answer(Request $request, int $id): JsonResponse
    {
        [$ok, $msg] = $this->svc()->answer($request->user(), $id);
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }

    public function decline(Request $request, int $id): JsonResponse
    {
        [$ok, $msg] = $this->svc()->decline($request->user(), $id);
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }

    public function end(Request $request, int $id): JsonResponse
    {
        [$ok, $msg] = $this->svc()->end($request->user(), $id);
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }
}
