<x-layouts.app :title="__('Chat')">
    <style>
        .fc-wrap { display:flex; flex-direction:column; height: 100%; min-height: 0; }
        .fc-list { flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:.4rem; }
        .fc-row { display:flex; align-items:flex-end; gap:.35rem; } .fc-row.mine { justify-content:flex-end; }
        .fc-bubble { max-width:78%; padding:.5rem .8rem; border-radius:1rem; background:var(--color-chat-received); color:var(--color-ink); border:1px solid var(--color-border); word-wrap:break-word; overflow-wrap:anywhere; white-space:pre-wrap; font-size:.92rem; }
        .fc-row.mine .fc-bubble { background:var(--color-chat-sent); color:var(--color-ink); }
        .fc-meta { font-size:.68rem; opacity:.7; margin-top:.15rem; text-align:right; }
        .fc-file { display:flex; align-items:center; gap:.5rem; text-decoration:none; color:inherit; font-weight:600; }
        .fc-file small { display:block; font-weight:400; opacity:.75; }
        .fc-sys audio, .fc-sys video { display:block; margin:.35rem auto 0; max-width:100%; } .fc-sys video { max-height:14rem; border-radius:.6rem; }
        .fc-sys { align-self:center; font-size:.75rem; opacity:.7; padding:.15rem .7rem; border-radius:999px; background:rgba(127,127,127,.14); }
        .fc-day { align-self:center; font-size:.7rem; opacity:.6; margin:.4rem 0 .1rem; }
        .fc-quote { border-left:3px solid var(--color-muted); background:var(--color-control); border-radius:.5rem; padding:.25rem .5rem; margin-bottom:.35rem; font-size:.8rem; }
        .fc-row:not(.mine) .fc-quote { border-left-color:var(--color-muted); background:var(--color-control); }
        .fc-quote b { display:block; font-size:.72rem; } .fc-quote span { opacity:.85; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:16rem; }
        .fc-more { opacity:0; border:0; background:transparent; cursor:pointer; font-size:1.1rem; line-height:1; padding:.2rem .3rem; color:inherit; }
        .fc-row:hover .fc-more, .fc-more:focus { opacity:.75; } @media (hover:none) { .fc-more { opacity:.5; } }
        .fc-actions { position:fixed; z-index:60; min-width:10rem; background:var(--color-surface-2,#fff); color:inherit; border:1px solid rgba(127,127,127,.35); border-radius:.75rem; box-shadow:0 10px 30px rgba(0,0,0,.25); padding:.25rem; }
        .fc-actions button { display:block; width:100%; text-align:left; border:0; background:transparent; color:inherit; padding:.5rem .8rem; border-radius:.5rem; font-size:.9rem; cursor:pointer; }
        .fc-actions button:hover { background:rgba(127,127,127,.15); } .fc-actions button.bad { color:#dc2626; }
        .fc-gone { background:transparent !important; border:1px dashed rgba(127,127,127,.5); color:inherit !important; opacity:.75; }
        .fc-media { display:block; max-width:100%; width:16rem; max-height:20rem; object-fit:cover; border-radius:.75rem; }
        .fc-bubble:has(img.fc-media), .fc-bubble:has(video.fc-media) { padding:.3rem; }
        .fc-tick { display:inline-flex; vertical-align:middle; margin-left:.25rem; opacity:.75; } .fc-tick.read { color:var(--color-link); opacity:1; } .fc-row.mine .fc-tick.read { color:var(--color-link); }
        .fc-fwd { font-size:.72rem; font-style:italic; opacity:.75; margin-bottom:.15rem; }
        .fc-reacts { display:flex; flex-wrap:wrap; gap:.2rem; margin-top:.3rem; justify-content:flex-end; }
        .fc-react { border:1px solid rgba(127,127,127,.4); background:rgba(127,127,127,.18); color:inherit; border-radius:999px; padding:0 .4rem; font-size:.78rem; line-height:1.4rem; cursor:pointer; }
        .fc-react.mine { border-color:var(--color-link); background:var(--color-control); }
        .fc-quick { display:flex; gap:.1rem; padding:.2rem .3rem .35rem; border-bottom:1px solid rgba(127,127,127,.25); margin-bottom:.2rem; }
        .fc-actions .fc-quick button { width:auto; padding:.2rem .35rem; font-size:1.3rem; line-height:1.5rem; border-radius:999px; }
        .fc-emoji { position:fixed; z-index:70; width:19rem; max-height:15rem; overflow-y:auto; display:flex; flex-wrap:wrap; gap:.1rem; padding:.4rem; background:var(--color-surface-2,#fff); border:1px solid rgba(127,127,127,.35); border-radius:.75rem; box-shadow:0 10px 30px rgba(0,0,0,.25); }
        .fc-emoji button { border:0; background:transparent; font-size:1.4rem; width:2.2rem; height:2.2rem; border-radius:.5rem; cursor:pointer; } .fc-emoji button:hover { background:rgba(127,127,127,.2); }
        .fc-modal { position:fixed; inset:0; z-index:80; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; padding:1rem; }
        .fc-card { background:var(--color-surface-2,#fff); color:inherit; border-radius:1rem; width:100%; max-width:24rem; max-height:80vh; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:.6rem; }
        .fc-card-h { display:flex; justify-content:space-between; align-items:center; } .fc-card-h button { border:0; background:transparent; font-size:1.5rem; line-height:1; cursor:pointer; color:inherit; }
        .fc-sub { font-size:.8rem; opacity:.7; } .fc-pick { display:flex; flex-direction:column; max-height:16rem; overflow-y:auto; } .fc-prow { display:flex; gap:.6rem; align-items:center; padding:.45rem .2rem; cursor:pointer; border-bottom:1px solid rgba(127,127,127,.15); }
        .fc-irow { display:flex; justify-content:space-between; font-size:.88rem; padding:.3rem 0; border-bottom:1px solid rgba(127,127,127,.15); }
        .fc-light { background:rgba(0,0,0,.92); flex-direction:column; } .fc-light img { max-width:96vw; max-height:84vh; object-fit:contain; border-radius:.5rem; }
        .fc-lightbar { position:absolute; top:.8rem; right:1rem; display:flex; gap:1rem; align-items:center; } .fc-lightbar a, .fc-lightbar button { color:#fff; font-size:1rem; background:transparent; border:0; cursor:pointer; text-decoration:none; } .fc-lightbar button { font-size:2rem; line-height:1; }
        .fc-deleted { font-style:italic; font-size:.88rem; }
        .fc-bubble audio { max-width:100%; height:2.2rem; } .fc-dur { font-size:.72rem; opacity:.75; }
        .fc-rec-dot { width:.7rem; height:.7rem; border-radius:50%; background:#dc2626; animation: fcpulse 1s infinite; } @keyframes fcpulse { 50% { opacity:.25; } }
    </style>

    <x-friends.workspace :active="true" :selected-id="$friend->id">
    <div class="messenger-thread" data-friend-chat data-user-id="{{ $friend->id }}" data-name="{{ ($profile['name'] ?? $friend->name) }}" data-features='@json(\App\Support\FriendSettings::features())' data-relation='@json($relation ?? null)'>
        <div class="bg-surface-2 fc-wrap" style="overflow:hidden">
            <div class="messenger-chat-header flex items-center gap-3 px-4 py-3 border-b border-border">
                <a href="{{ url('/people') }}" class="text-muted hover:text-ink" aria-label="{{ __('Back to Friends') }}">&larr;</a>
                <a href="{{ $friend->profileUrl() }}" class="flex items-center gap-3 min-w-0 flex-1" title="{{ __('Open profile') }}">
                    <div class="w-10 h-10 rounded-full bg-surface text-muted flex items-center justify-center font-semibold overflow-hidden flex-shrink-0">
                        @if($friend->avatar_path)<img src="{{ asset('storage/' . $friend->avatar_path) }}" alt="" class="w-full h-full object-cover">@else{{ strtoupper(mb_substr(($profile['name'] ?? $friend->name), 0, 1)) }}@endif
                    </div>
                    <div class="min-w-0"><div class="font-semibold text-ink truncate">{{ ($profile['name'] ?? $friend->name) }}</div><div id="fc-status" class="text-xs text-muted truncate">{{ $profile['status_label'] ?? __('Status unavailable') }}</div></div>
                </a>
                @if(\App\Support\FriendSettings::callsEnabled())
                    <button type="button" id="fc-call" class="messenger-icon" title="{{ __('Audio call') }}" aria-label="{{ __('Audio call') }}"><x-action-icon name="phone" /></button>
                    <button type="button" id="fc-video" class="messenger-icon" title="{{ __('Video call') }}" aria-label="{{ __('Video call') }}"><x-action-icon name="video" /></button>
                @endif
                @if(app(\App\Services\Meetings\MeetingService::class)->enabled())
                    <button type="button" id="fc-meeting" class="messenger-icon" title="{{ __('Start meeting') }}" aria-label="{{ __('Start meeting') }}"><x-action-icon name="calendar" /></button>
                @endif
                <div class="relative">
                    <button type="button" id="fc-menu-btn" class="messenger-icon" aria-label="{{ __('More') }}" aria-haspopup="true"><x-action-icon name="more" /></button>
                    <div id="fc-menu" class="hidden absolute right-0 mt-1 w-64 bg-surface-2 border border-border rounded-xl shadow-lg z-20 py-1 text-sm">
                        <button type="button" class="block w-full text-left px-4 py-2 hover:bg-black/5" @click="profileOpen = true" :aria-expanded="profileOpen.toString()">{{ __('Contact details') }}</button>
                        <a href="{{ url('/people/calls') }}" class="block px-4 py-2 hover:bg-black/5">{{ __('Call history') }}</a>
                        <button type="button" id="fc-clear-me" class="block w-full text-left px-4 py-2 hover:bg-black/5">{{ __('Clear chat') }}</button>
                    </div>
                </div>
            </div>

            <div id="fc-list" class="fc-list" aria-live="polite"></div>

            {{-- shown instead of the message box when you are no longer friends (chat is kept, read-only) --}}
            <div id="fc-readonly" class="hidden px-4 py-3 border-t border-border text-sm text-muted flex flex-wrap items-center gap-3">
                <span class="flex-1 min-w-0">{{ __('You are no longer friends, so you can read this chat but not write or call. Your history is kept.') }}</span>
                <a href="{{ $friend->profileUrl() }}" class="btn-secondary">{{ __('View profile') }}</a>
            </div>

            <div id="fc-replybar" class="hidden px-4 py-2 border-t border-border text-sm flex items-center gap-3">
                <div class="min-w-0 flex-1" style="border-left:3px solid #4F46E5;padding-left:.6rem"><div id="fc-reply-name" class="text-xs font-semibold"></div><div id="fc-reply-text" class="text-xs text-muted truncate"></div></div>
                <button type="button" id="fc-reply-x" class="text-muted hover:text-ink" aria-label="{{ __('Cancel reply') }}">&times;</button>
            </div>

            <div id="fc-recbar" class="hidden flex items-center gap-3 p-3 border-t border-border">
                <span class="fc-rec-dot"></span><span id="fc-rec-time" class="text-sm font-medium w-12">0:00</span><span class="text-sm text-muted flex-1">{{ __('Recording…') }}</span>
                <button type="button" id="fc-rec-cancel" class="btn-secondary">{{ __('Cancel') }}</button>
                <button type="button" id="fc-rec-send" class="btn-primary">{{ __('Send') }}</button>
            </div>

            <form id="fc-form" class="flex items-end gap-2 p-3 border-t border-border">
                @if(\App\Support\FriendSettings::filesEnabled())
                    <label class="cursor-pointer text-muted hover:text-ink p-2" title="{{ __('Attach a file (max :n MB)', ['n' => \App\Support\FriendSettings::fileMaxMb()]) }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        <input type="file" id="fc-file" class="hidden">
                    </label>
                @endif
                <button type="button" id="fc-emoji-btn" class="p-2 text-muted hover:text-ink" title="{{ __('Emoji') }}" aria-label="{{ __('Emoji') }}"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M8.5 14.5a4.5 4.5 0 0 0 7 0M9 9.5h.01M15 9.5h.01"/></svg></button>
                <div class="flex-1 min-w-0">
                    <div id="fc-chosen" class="text-xs text-muted mb-1 hidden"></div>
                    <textarea id="fc-input" rows="1" placeholder="{{ __('Write a message…') }}" class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm resize-none" maxlength="4000"></textarea>
                </div>
                @if(\App\Support\FriendSettings::filesEnabled())
                                        <button type="button" id="fc-mic" class="p-2 text-muted hover:text-ink" title="{{ __('Record a voice message') }}" aria-label="{{ __('Record a voice message') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path stroke-linecap="round" d="M5 11a7 7 0 0 0 14 0M12 18v4"/></svg>
                    </button>
                @endif
                <button type="submit" class="btn-primary">{{ __('Send') }}</button>
            </form>
        </div>
    </div>

    <x-slot:profile>
        <div class="messenger-profile-card">
            <div class="messenger-avatar large">@if($friend->avatar_path)<img src="{{ asset('storage/' . $friend->avatar_path) }}" alt="">@else{{ strtoupper(mb_substr(($profile['name'] ?? $friend->name), 0, 1)) }}@endif</div>
            <h2 class="text-xl font-semibold">{{ ($profile['name'] ?? $friend->name) }}</h2>
            <p id="fc-profile-status" class="text-sm text-muted">{{ $profile['status_label'] ?? __('Status unavailable') }}</p>
            <dl class="messenger-metadata">
                @foreach([__('Username') => $profile['username'] ?? null, __('Email') => $profile['email'] ?? null, __('Contact Name') => $profile['contact_name'] ?? null, __('Profile Name') => $profile['profile_name'] ?? null, __('Member since') => $profile['member_since'] ?? null, __('Friends since') => $profile['friend_since'] ?? null] as $label => $value)
                    @if($value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif
                @endforeach
                <div><dt>{{ __('Connection') }}</dt><dd>{{ !empty($profile['is_friend']) ? __('Friend') : (!empty($profile['is_team']) ? __('Teammate') : __('Former friend')) }}</dd></div>
            </dl>
            <a class="btn-secondary" href="{{ $friend->profileUrl() }}">{{ __('View full profile') }}</a>
        </div>
    </x-slot:profile>
    </x-friends.workspace>

    <script src="{{ asset('js/call-tones.js') }}?v=20261014" defer></script>
    <script src="{{ asset('js/friend-call.js') }}?v=20261014-media" defer></script>
    <script src="{{ asset('js/friend-chat.js') }}?v=20261013-lifecycle" defer></script>
</x-layouts.app>
