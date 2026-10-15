<?php

namespace App\Http\Controllers\Friends;

use App\Http\Controllers\Controller;
use App\Services\Friends\PeopleService;
use App\Services\Friends\PhoneVerifier;
use App\Support\FriendSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** People directory and profiles for the mobile app (the website renders the same data on its own pages). */
class PeopleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $u = $request->user();
        return response()->json(['data' => app(PeopleService::class)->directory($u) + ['phone' => app(PhoneVerifier::class)->status($u), 'features' => FriendSettings::features()]]);
    }

    public function show(Request $request, int $userId): JsonResponse
    {
        return response()->json(['data' => app(PeopleService::class)->profile($request->user(), $userId) + ['features' => FriendSettings::features()]]);
    }
}
