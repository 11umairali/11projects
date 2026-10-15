<?php

namespace App\Http\Controllers\Friends;

use App\Http\Controllers\Controller;
use App\Services\Friends\FriendFinder;
use App\Services\Friends\FriendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Find people (username, full name, full e-mail, phone) and send a friend request – website + app. */
class FriendSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $r = app(FriendFinder::class)->search($request->user(), (string) $request->query('q', ''));
        return response()->json(['data' => $r['results'], 'hint' => $r['hint']]);
    }

    public function request(Request $request): JsonResponse
    {
        $d = $request->validate(['user_id' => 'required|integer']);
        [$ok, $msg] = app(FriendService::class)->send($request->user(), (int) $d['user_id']);
        return response()->json(['message' => $msg], $ok ? 200 : 422);
    }
}
