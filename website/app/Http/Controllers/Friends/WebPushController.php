<?php

namespace App\Http\Controllers\Friends;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Registers THIS browser for push notifications (Firebase web token) – website only; phones use me/devices. */
class WebPushController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $d = $request->validate(['token' => 'required|string|max:500']);
        $uid = $request->user()->id;
        $exists = DB::table('device_tokens')->where('token', $d['token'])->exists();
        DB::table('device_tokens')->updateOrInsert(['token' => $d['token']], ['user_id' => $uid, 'platform' => 'web', 'voip_token' => null, 'updated_at' => now()] + ($exists ? [] : ['created_at' => now()]));
        // keep the 8 newest browsers of a person, forget the rest (old profiles, reinstalls)
        $old = DB::table('device_tokens')->where('user_id', $uid)->where('platform', 'web')->orderByDesc('updated_at')->skip(8)->take(100)->pluck('id');
        if ($old->isNotEmpty()) DB::table('device_tokens')->whereIn('id', $old)->delete();
        return response()->json(['message' => 'Notifications are on for this browser.']);
    }

    public function unregister(Request $request): JsonResponse
    {
        $d = $request->validate(['token' => 'required|string|max:500']);
        DB::table('device_tokens')->where('user_id', $request->user()->id)->where('token', $d['token'])->delete();
        return response()->json(['message' => 'Notifications are off for this browser.']);
    }
}
