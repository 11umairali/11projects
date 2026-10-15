<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
    <meta name="dahi-realtime" content="{{ json_encode([
        'user' => auth()->id(), 'workspace' => auth()->user()->active_workspace_id,
        'key' => config('broadcasting.default') === 'pusher' ? config('broadcasting.connections.pusher.key') : null,
        'cluster' => config('broadcasting.connections.pusher.options.cluster', 'mt1'),
        'host' => config('services.realtime.ws_host'), 'port' => config('services.realtime.ws_port', 443),
        'tls' => config('services.realtime.ws_scheme', 'https') === 'https', 'auth' => url('/broadcasting/auth'),
    ]) }}">
    @endauth
    @php
        // Polling in meeting.js remains available during an incomplete asset deployment.
        // A missing optional websocket bundle must not make the call room return HTTP 500.
        $realtimeAssets = '';
        try {
            $realtimeAssets = app(\Illuminate\Foundation\Vite::class)('resources/js/realtime.js');
        } catch (\Illuminate\Foundation\ViteException $error) {
            \Illuminate\Support\Facades\Log::warning('Meeting realtime assets unavailable; rebuild website assets with npm ci && npm run build.', ['exception' => get_class($error)]);
        }
    @endphp
    {!! $realtimeAssets !!}
    <title>{{ $meeting['title'] }} · {{ config('app.name') }}</title>
    <style>
        :root { --bg:#0f1115; --panel:#181b22; --line:#2a2f3a; --ink:#f2f4f8; --muted:#9aa3b2; --brand:#4F46E5; --red:#dc2626; --green:#16a34a; }
        * { box-sizing: border-box; } html, body { height:100%; margin:0; } body { background:var(--bg); color:var(--ink); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; overflow:hidden; }
        .hidden { display:none !important; } #mt-left{font-size:.8rem;opacity:.85;margin-right:.7rem} #mt-left.warn{color:#fbbf24} #mt-left.over{color:#f87171} .dotp{display:inline-block;width:.55rem;height:.55rem;border-radius:50%;background:#6b7280;margin-right:.4rem} .dotp.on{background:#22c55e} button { font:inherit; color:inherit; cursor:pointer; } a { color:#a5b4fc; }
        #mt { height:100%; position:relative; }
        .mt-screen { height:100%; display:flex; align-items:center; justify-content:center; padding:1.2rem; overflow:auto; }
        .card { background:var(--panel); border:1px solid var(--line); border-radius:1.2rem; padding:1.4rem; width:100%; max-width:56rem; display:grid; grid-template-columns: 1.3fr 1fr; gap:1.6rem; }
        @media (max-width: 760px) { .card { grid-template-columns:1fr; } }
        .pv { position:relative; background:#000; border-radius:1rem; aspect-ratio:16/10; overflow:hidden; display:flex; align-items:center; justify-content:center; }
        .pv video { width:100%; height:100%; object-fit:cover; transform:scaleX(-1); } .pv .off { position:absolute; color:var(--muted); font-size:.9rem; }
        .pv .tg { position:absolute; bottom:.7rem; left:0; right:0; display:flex; justify-content:center; gap:.7rem; }
        .rb { width:2.9rem; height:2.9rem; border-radius:50%; border:1px solid var(--line); background:#2a2f3a; display:inline-flex; align-items:center; justify-content:center; } .rb.off { background:var(--red); border-color:var(--red); }
        h1 { font-size:1.35rem; margin:.1rem 0 .2rem; } .sub { color:var(--muted); font-size:.9rem; margin-bottom:1rem; }
        input[type=text] { width:100%; padding:.8rem 1rem; border-radius:.8rem; border:1px solid var(--line); background:#0f1115; color:var(--ink); font:inherit; margin:.3rem 0 .8rem; }
        .primary { width:100%; padding:.85rem 1rem; border:0; border-radius:.8rem; background:var(--brand); color:#fff; font-weight:600; } .primary:disabled { opacity:.6; }
        .msg { color:var(--muted); font-size:.85rem; min-height:1.3rem; margin-top:.6rem; }
        .center { text-align:center; max-width:28rem; } .center .primary, .center .ghost { margin-top:.8rem; }
        .ghost { display:inline-block; padding:.8rem 1.2rem; border-radius:.8rem; border:1px solid var(--line); background:transparent; text-decoration:none; color:var(--ink); }
        .spin { width:2.2rem; height:2.2rem; border-radius:50%; border:3px solid var(--line); border-top-color:var(--brand); animation:sp 1s linear infinite; margin:0 auto 1rem; } @keyframes sp { to { transform:rotate(360deg); } }

        /* room */
        #mt-room { height:100%; display:flex; flex-direction:column; }
        #mt-top { height:3.2rem; display:flex; align-items:center; gap:.8rem; padding:0 1rem; border-bottom:1px solid var(--line); background:var(--panel); }
        #mt-top .t { font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; } #mt-top .i { color:var(--muted); font-size:.85rem; margin-left:auto; display:flex; gap:.9rem; align-items:center; }
        .lnk { background:none; border:0; color:#a5b4fc; padding:0; font-size:.85rem; }
        #mt-main { flex:1; min-height:0; display:flex; }
        #mt-stage { flex:1; min-width:0; padding:.6rem; position:relative; }
        #mt-stage.grid { display:grid; grid-template-columns:repeat(var(--cols,1), 1fr); grid-auto-rows:1fr; gap:.6rem; }
        #mt-stage.spot { display:flex; flex-wrap:wrap; gap:.6rem; align-content:flex-start; } #mt-stage.spot .tile { flex:0 0 11rem; height:7rem; } #mt-stage.spot .tile.presenter { flex:1 1 100%; height:calc(100% - 7.8rem); order:-1; }
        .tile { position:relative; background:#1b1f27; border-radius:.9rem; overflow:hidden; min-height:6rem; border:2px solid transparent; transition:border-color .15s; } .tile.speaking { border-color:#22c55e; }
        .tile video { width:100%; height:100%; object-fit:cover; background:#000; } .tile.mine video { transform:scaleX(-1); } .tile.presenter video { object-fit:contain; transform:none; }
        .tile.novideo video { visibility:hidden; } .tile-av { position:absolute; inset:0; margin:auto; width:5rem; height:5rem; border-radius:50%; background:#33405c center/cover; display:none; align-items:center; justify-content:center; font-size:2rem; font-weight:600; } .tile.novideo .tile-av { display:flex; }
        .tile-name { position:absolute; left:.5rem; bottom:.4rem; background:rgba(0,0,0,.55); padding:.15rem .5rem; border-radius:.5rem; font-size:.78rem; max-width:80%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .tile-badges { position:absolute; top:.4rem; right:.4rem; display:flex; gap:.3rem; } .b { background:rgba(0,0,0,.6); border-radius:.5rem; padding:.1rem .4rem; font-size:.75rem; display:inline-flex; align-items:center; } .b svg { width:14px; height:14px; } .b-mic { color:#f87171; } .b-share { background:var(--brand); }
        #mt-alone { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; color:var(--muted); pointer-events:none; padding:2rem; } #mt-alone code { color:#fff; background:#1b1f27; padding:.3rem .6rem; border-radius:.5rem; margin-top:.5rem; word-break:break-all; pointer-events:auto; }
        #mt-side { width:21rem; max-width:100%; background:var(--panel); border-left:1px solid var(--line); display:flex; flex-direction:column; }
        @media (max-width: 760px) { #mt-side { position:fixed; inset:3.2rem 0 5.2rem 0; width:auto; z-index:20; } }
        #mt-side .tabs { display:flex; border-bottom:1px solid var(--line); } #mt-side .tabs button { flex:1; background:none; border:0; padding:.8rem; border-bottom:2px solid transparent; color:var(--muted); } #mt-side .tabs button.on { color:var(--ink); border-color:var(--brand); } #mt-side .x { padding:.8rem 1rem; background:none; border:0; color:var(--muted); }
        #mt-people, #mt-msgs { flex:1; overflow-y:auto; padding:.6rem; } #mt-peoplepane, #mt-chatpane { flex:1; min-height:0; display:flex; flex-direction:column; }
        .sec { display:flex; justify-content:space-between; color:var(--muted); font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; padding:.6rem .3rem .3rem; }
        .row { display:flex; align-items:center; gap:.4rem; padding:.45rem .3rem; border-radius:.6rem; flex-wrap:wrap; } .row .nm { flex:1; min-width:6rem; font-size:.9rem; } .row .ic { color:var(--muted); display:inline-flex; } .row .ic svg { width:16px; height:16px; } .row .ic.off { color:#f87171; }
        .sm { background:#2a2f3a; border:0; border-radius:.5rem; padding:.25rem .55rem; font-size:.78rem; } .sm.go { background:var(--green); } .sm.bad { background:#7f1d1d; }
        .msg { margin:.35rem 0; } .msg.mine { text-align:right; } .mh { font-size:.72rem; color:var(--muted); } .mb { display:inline-block; background:#2a2f3a; padding:.4rem .7rem; border-radius:.8rem; max-width:90%; word-break:break-word; text-align:left; } .msg.mine .mb { background:var(--brand); }
        #mt-chatform { display:flex; gap:.4rem; padding:.6rem; border-top:1px solid var(--line); } #mt-chatform input { flex:1; margin:0; } #mt-chatform button { background:var(--brand); border:0; border-radius:.7rem; padding:0 1rem; }
        #mt-ctl { height:5.2rem; background:var(--panel); border-top:1px solid var(--line); display:flex; align-items:center; justify-content:center; gap:.5rem; padding:0 .6rem; padding-bottom:env(safe-area-inset-bottom); overflow-x:auto; }
        #mt-ctl button { position:relative; display:flex; flex-direction:column; align-items:center; gap:.2rem; background:none; border:0; padding:.5rem .7rem; border-radius:.8rem; font-size:.72rem; color:var(--ink); min-width:4.2rem; white-space:nowrap; }
        #mt-ctl button:hover { background:#232834; } #mt-ctl button.off svg { color:#f87171; } #mt-ctl button.on { background:#2b2f6b; } #mt-ctl #mt-b-leave { background:var(--red); } #mt-ctl #mt-b-leave:hover { background:#b91c1c; }
        #mt-ctl .bw { position:relative; display:flex; }
        .dot { position:absolute; top:.2rem; right:.5rem; background:var(--red); border-radius:1rem; min-width:1.1rem; height:1.1rem; font-size:.65rem; display:flex; align-items:center; justify-content:center; padding:0 .25rem; }
        #mt-toasts { position:fixed; top:4rem; left:50%; transform:translateX(-50%); display:flex; flex-direction:column; gap:.4rem; z-index:50; pointer-events:none; } .mt-toast { background:#000c; border:1px solid var(--line); padding:.5rem .9rem; border-radius:.8rem; font-size:.85rem; }
        .mt-menu { position:fixed; z-index:60; background:var(--panel); border:1px solid var(--line); border-radius:.9rem; padding:.3rem; min-width:15rem; box-shadow:0 10px 30px #0008; } .mt-menu button { display:block; width:100%; text-align:left; background:none; border:0; padding:.7rem .9rem; border-radius:.6rem; } .mt-menu button:hover { background:#232834; } .mt-menu .bad { color:#f87171; }
        .mt-remote-audio { position:fixed; width:1px; height:1px; opacity:0; pointer-events:none; }
        #mt-ctl{height:auto;min-height:5.2rem;flex-shrink:0;flex-wrap:wrap;overflow:visible;padding:10px;gap:4px} #mt-ctl button{min-width:3.5rem;padding:8px;font-size:11px} #mt-top{flex-shrink:0}
        #mt-alone{inset:5rem 1rem auto;z-index:2;padding:12px;border-radius:12px;background:#111827b3;width:fit-content;max-width:calc(100% - 2rem);margin:auto;font-size:13px}
        .mt-browser{position:fixed;inset:54px 0 0;width:100%;height:calc(100% - 54px);border:0;background:var(--bg);z-index:80}
        #mt-return-bar{position:fixed;inset:0 0 auto;height:54px;z-index:90;background:#24463a;display:flex;align-items:center;gap:12px;padding:8px 16px;font-size:13px} #mt-return-label{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap} #mt-return-bar button{border:1px solid #ffffff50;background:transparent;padding:8px 12px;border-radius:8px;white-space:nowrap} #mt-return-end{color:#fecaca}
        @media(max-width:760px){#mt-ctl{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:4px}#mt-ctl button{width:100%;min-width:0;padding:8px 2px}#mt-ctl .bw{min-width:0}#mt-top{height:auto;min-height:52px;flex-wrap:wrap;padding:8px}#mt-top .i{gap:8px;flex-wrap:wrap}#mt-side{bottom:160px}#mt-return-label{font-size:11px}}
    </style>
</head>
<body>
<div id="mt"
     data-code="{{ $meeting['code'] }}" data-pretty="{{ $meeting['pretty'] }}" data-title="{{ $meeting['title'] }}" data-url="{{ $meeting['url'] }}" data-kind="{{ $meeting['kind'] }}"
     data-user="{{ json_encode($user) }}" data-ended="{{ $ended ? 1 : 0 }}" data-guests="{{ $allowGuests ? 1 : 0 }}" data-signin="{{ url('/login') }}">

    {{-- 1. before joining --}}
    <section id="mt-pre" class="mt-screen">
        <div class="card">
            <div class="pv">
                <video id="mt-preview" autoplay muted playsinline></video>
                <div id="mt-prev-off" class="off">Camera is off</div>
                <div class="tg"><button id="mt-pre-mic" class="rb" type="button" aria-label="Microphone"></button><button id="mt-pre-cam" class="rb" type="button" aria-label="Camera"></button></div>
            </div>
            <div>
                <h1 id="mt-pre-title">{{ $meeting['title'] }}</h1>
                <div class="sub">{{ $meeting['kind'] === 'call' ? (str_starts_with((string) $meeting['title'], 'Audio') ? __('Audio call') : __('Video call')) : 'Meeting' }} · <span id="mt-pre-code"></span>@if($meeting['host_name']) · {{ __('Host: :n', ['n' => $meeting['host_name']]) }}@endif</div>
                <div id="mt-name-wrap"><label class="sub" for="mt-name">{{ __('Your name') }}</label><input id="mt-name" type="text" maxlength="60" placeholder="{{ __('Type your name') }}" autocomplete="name"></div>
                <div id="mt-as" class="sub"></div>
                <button id="mt-join" class="primary" type="button">{{ __('Join now') }}</button>
                <div id="mt-signin" class="msg hidden"><a href="{{ url('/login') }}">{{ __('Sign in') }}</a> {{ __('to join with your account.') }}</div>
                <div id="mt-pre-msg" class="msg"></div><button id="mt-pre-end" class="ghost" type="button">{{ __('Cancel / go back') }}</button>
            </div>
        </div>
    </section>

    {{-- 2. waiting room --}}
    <section id="mt-wait" class="mt-screen hidden"><div class="center"><div class="spin"></div><h1 id="mt-wait-title"></h1><div class="sub">{{ __('Waiting for the host to let you in…') }}</div><button id="mt-wait-end" class="ghost" type="button">{{ __('Leave meeting') }}</button></div></section>

    {{-- 3. finished --}}
    <section id="mt-end" class="mt-screen hidden"><div class="center"><h1 id="mt-end-msg"></h1><button id="mt-rejoin" class="primary hidden" type="button">{{ __('Rejoin') }}</button><a class="ghost" href="{{ $user ? url('/meetings') : url('/') }}">{{ __('Back') }}</a></div></section>

    {{-- 4. the room --}}
    <section id="mt-room" class="hidden">
        <header id="mt-top">
            <div class="t" id="mt-title"></div><span id="mt-lock" class="hidden" title="{{ __('Locked') }}">🔒</span><button id="mt-enable-audio" class="lnk hidden" type="button">{{ __('Enable call audio') }}</button>
            <div class="i"><span id="mt-count"></span><span id="mt-left"></span><span id="mt-clock">0:00</span><button id="mt-add" class="lnk hidden" type="button">{{ __('Add people') }}</button><button id="mt-share" class="lnk" type="button">{{ __('Share') }}</button><button id="mt-copy" class="lnk" type="button">{{ __('Copy link') }}</button></div>
        </header>
        <div id="mt-main">
            <div id="mt-stage" class="grid"></div>
            <div id="mt-alone"><div id="mt-alone-text"></div><code id="mt-alone-link"></code></div>
            <aside id="mt-side" class="hidden">
                <div class="tabs"><button id="mt-tab-people" class="on" type="button">{{ __('People') }}</button><button id="mt-tab-chat" type="button">{{ __('Chat') }}</button><button id="mt-side-x" class="x" type="button" aria-label="{{ __('Close') }}">&times;</button></div>
                <div id="mt-peoplepane"><div id="mt-people"></div></div>
                <div id="mt-chatpane" class="hidden"><div id="mt-msgs"></div><form id="mt-chatform"><input type="text" id="mt-chatin" maxlength="1000" placeholder="{{ __('Message everyone…') }}" autocomplete="off"><button type="submit">{{ __('Send') }}</button></form></div>
            </aside>
        </div>
        <footer id="mt-ctl">
            <button id="mt-b-mic" type="button"></button><button id="mt-b-cam" type="button"></button><button id="mt-b-share" type="button"></button><button id="mt-b-hand" type="button"></button>
            <span class="bw"><button id="mt-b-chat" type="button"></button><span id="mt-chat-dot" class="dot hidden"></span></span>
            <span class="bw"><button id="mt-b-people" type="button"></button><span id="mt-people-dot" class="dot hidden"></span></span>
            <button id="mt-b-speaker" type="button"></button><button id="mt-b-minimize" type="button"></button><button id="mt-b-more" type="button" class="hidden"></button><button id="mt-b-leave" type="button"></button>
        </footer>
    </section>
    <div id="mt-toasts"></div>
    <div id="mt-return-bar" class="hidden"><span id="mt-return-label"></span><button id="mt-return" type="button">{{ __('Return to call') }}</button><button id="mt-return-end" type="button">{{ __('End / leave') }}</button></div>
</div>
<script src="{{ asset('js/call-recorder.js') }}?v=20261007"></script>
<script src="{{ asset('js/call-tones.js') }}?v=20261014"></script>
<script src="{{ asset('js/meeting.js') }}?v=20261014-media"></script>
</body>
</html>
