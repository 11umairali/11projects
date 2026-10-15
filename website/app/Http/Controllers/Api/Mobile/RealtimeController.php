<?php
namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function config(Request $request)
    {
        $key = config('broadcasting.connections.pusher.key');
        return response()->json([
            'enabled' => config('broadcasting.default') === 'pusher' && !empty($key),
            'key' => $key,
            'host' => config('services.realtime.ws_host') ?: 'ws-'.config('broadcasting.connections.pusher.options.cluster', 'mt1').'.pusher.com',
            'port' => (int) config('services.realtime.ws_port', 443),
            'tls' => config('services.realtime.ws_scheme', 'https') === 'https',
            'channel' => 'private-user.'.$request->user()->id,
        ])->header('Cache-Control', 'no-store');
    }

    public function auth(Request $request)
    {
        $data = $request->validate(['socket_id' => ['required', 'string', 'max:100', 'regex:/^\d+\.\d+$/'], 'channel_name' => ['required', 'string', 'max:100']]);
        abort_unless($data['channel_name'] === 'private-user.'.$request->user()->id, 403);
        $key = config('broadcasting.connections.pusher.key');
        $secret = config('broadcasting.connections.pusher.secret');
        abort_unless(config('broadcasting.default') === 'pusher' && $key && $secret, 503);
        return response()->json(['auth' => $key.':'.hash_hmac('sha256', $data['socket_id'].':'.$data['channel_name'], $secret)])->header('Cache-Control', 'no-store');
    }
}
