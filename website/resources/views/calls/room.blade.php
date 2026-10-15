<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="dahi-realtime" content="{{ json_encode([
        'user' => auth()->id(), 'workspace' => auth()->user()->active_workspace_id,
        'key' => config('broadcasting.default') === 'pusher' ? config('broadcasting.connections.pusher.key') : null,
        'cluster' => config('broadcasting.connections.pusher.options.cluster', 'mt1'),
        'host' => config('services.realtime.ws_host'), 'port' => config('services.realtime.ws_port', 443),
        'tls' => config('services.realtime.ws_scheme', 'https') === 'https', 'auth' => url('/broadcasting/auth'),
    ]) }}">
    @php
        // Polling stays available when the optional realtime bundle is missing: the call screen must always load.
        $realtimeAssets = '';
        try { $realtimeAssets = app(\Illuminate\Foundation\Vite::class)('resources/js/realtime.js'); }
        catch (\Illuminate\Foundation\ViteException $error) { \Illuminate\Support\Facades\Log::warning('Call realtime assets unavailable; polling stays on.', ['exception' => get_class($error)]); }
    @endphp
    {!! $realtimeAssets !!}
    <title>{{ $audioOnly ? __('Audio call') : __('Video call') }} · {{ $peer['name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { background: #0b141a; color: #fff; font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; overflow: hidden; -webkit-tap-highlight-color: transparent; }
        button { font: inherit; color: inherit; cursor: pointer; border: 0; }
        .hidden { display: none !important; }
        #cl { position: fixed; inset: 0; background: radial-gradient(120% 90% at 50% 0%, #1f3a4a 0%, #0b141a 70%); overflow: hidden; }

        /* remote video (video calls) */
        #cl-stage { position: absolute; inset: 0; display: grid; gap: 2px; grid-template-columns: 1fr; grid-auto-rows: 1fr; background: #000; }
        #cl-stage.g2 { grid-template-columns: 1fr 1fr; } #cl-stage.g3 { grid-template-columns: 1fr 1fr; }
        #cl.audio #cl-stage, #cl.nostage #cl-stage { visibility: hidden; }          /* still plays the sound */
        .tile { position: relative; overflow: hidden; background: #111b21; min-height: 0; }
        .tile video { width: 100%; height: 100%; object-fit: cover; background: #000; }
        .tile.novideo video { visibility: hidden; }
        .tile .tav { position: absolute; inset: 0; margin: auto; width: 6rem; height: 6rem; border-radius: 50%; background: #33405c center/cover; display: none; align-items: center; justify-content: center; font-size: 2.4rem; font-weight: 600; }
        .tile.novideo .tav { display: flex; }
        .tile .tn { position: absolute; left: .6rem; bottom: 7.5rem; background: rgba(0,0,0,.45); padding: .15rem .55rem; border-radius: .6rem; font-size: .8rem; }

        /* centre: big avatar for audio calls, ringing, or when the other camera is off */
        #cl-center { position: absolute; left: 0; right: 0; top: 0; bottom: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 1rem 1rem 7rem; pointer-events: none; }
        #cl-av { width: 9.5rem; height: 9.5rem; border-radius: 50%; background: #33405c center/cover; display: flex; align-items: center; justify-content: center; font-size: 3.8rem; font-weight: 600; box-shadow: 0 0 0 0 rgba(255,255,255,.25); }
        #cl.ringing #cl-av { animation: pulse 1.8s ease-out infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(255,255,255,.28); } 100% { box-shadow: 0 0 0 2.4rem rgba(255,255,255,0); } }
        #cl-name { font-size: 1.7rem; font-weight: 600; margin-top: 1.2rem; max-width: 90vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #cl-status { font-size: 1rem; opacity: .8; margin-top: .35rem; min-height: 1.4rem; }
        #cl-center.compact { display: none; }

        /* small header when the video is on screen */
        #cl-head { position: absolute; top: 0; left: 0; right: 0; padding: calc(env(safe-area-inset-top, 0px) + .9rem) 1rem 1.4rem; text-align: center; background: linear-gradient(180deg, rgba(0,0,0,.55), transparent); pointer-events: none; display: none; }
        #cl-head .n { font-weight: 600; font-size: 1.05rem; } #cl-head .s { font-size: .85rem; opacity: .85; margin-top: .1rem; }
        #cl.video-on #cl-head { display: block; }

        /* my own picture */
        #cl-self { position: absolute; right: .9rem; top: calc(env(safe-area-inset-top, 0px) + 4.2rem); width: 7.2rem; aspect-ratio: 3/4; border-radius: .9rem; overflow: hidden; background: #000; box-shadow: 0 6px 20px rgba(0,0,0,.45); border: 1px solid rgba(255,255,255,.18); z-index: 3; }
        #cl-self video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
        @media (min-width: 760px) { #cl-self { width: 10rem; } }

        /* controls */
        #cl-bar { position: absolute; left: 0; right: 0; bottom: 0; display: flex; justify-content: center; align-items: center; gap: 1.1rem; padding: 1rem 1rem calc(env(safe-area-inset-bottom, 0px) + 1.6rem); z-index: 4; }
        .cb { width: 3.7rem; height: 3.7rem; border-radius: 50%; background: rgba(255,255,255,.16); display: inline-flex; align-items: center; justify-content: center; backdrop-filter: blur(6px); transition: background .15s, transform .1s; }
        .cb:hover { background: rgba(255,255,255,.26); } .cb:active { transform: scale(.94); }
        .cb.off { background: #fff; color: #111b21; }
        .cb.end { background: #e5383b; width: 4.2rem; height: 4.2rem; } .cb.end:hover { background: #c92a2d; }
        .cb svg { width: 26px; height: 26px; }
        #cl-note { position: absolute; left: 50%; transform: translateX(-50%); bottom: 8.6rem; background: rgba(0,0,0,.65); padding: .5rem .9rem; border-radius: 1rem; font-size: .85rem; z-index: 5; }
        #cl-end-msg { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; flex-direction: column; background: rgba(11,20,26,.88); z-index: 6; text-align: center; padding: 1.5rem; }
        #cl.ended #cl-end-msg { display: flex; } #cl-end-msg h1 { font-size: 1.4rem; margin: 0 0 .4rem; } #cl-end-msg p { opacity: .8; margin: 0 0 1.1rem; }
        #cl-back { background: #25d366; color: #05210f; font-weight: 600; padding: .75rem 1.6rem; border-radius: 999px; }
    </style>
</head>
<body>
<div id="cl" class="{{ $audioOnly ? 'audio' : '' }}"
     data-code="{{ $code }}" data-call-id="{{ $callId }}" data-audio="{{ $audioOnly ? 1 : 0 }}" data-role="{{ $role }}" data-return="{{ $return }}"
     data-me="{{ json_encode($me) }}" data-peer="{{ json_encode($peer) }}">

    <div id="cl-stage"></div>

    <div id="cl-head"><div class="n" id="cl-head-n"></div><div class="s" id="cl-head-s"></div></div>

    <div id="cl-center">
        <div id="cl-av"></div>
        <div id="cl-name"></div>
        <div id="cl-status">{{ __('Connecting…') }}</div>
    </div>

    <div id="cl-self" class="hidden"><video id="cl-self-v" autoplay muted playsinline></video></div>

    <div id="cl-note" class="hidden">{{ __('Tap anywhere to turn the sound on') }}</div>

    <div id="cl-bar">
        <button id="cl-mic" class="cb" type="button" aria-label="{{ __('Mute') }}"></button>
        <button id="cl-cam" class="cb {{ $audioOnly ? 'hidden' : '' }}" type="button" aria-label="{{ __('Camera') }}"></button>
        <button id="cl-end" class="cb end" type="button" aria-label="{{ __('End call') }}"></button>
    </div>

    <div id="cl-end-msg"><h1 id="cl-end-title"></h1><p id="cl-end-sub"></p><button id="cl-back" type="button">{{ __('Back') }}</button></div>
</div>
<script src="{{ asset('js/call-tones.js') }}?v=20261014"></script>
<script src="{{ asset('js/call-room.js') }}?v=20261014-media"></script>
</body>
</html>