<x-layouts.app :title="__('Call history')">
    @php($calls = app(\App\Services\Friends\FriendCallService::class)->history(auth()->user()))
    @php($calls = request('user_id') ? array_values(array_filter($calls, fn ($call) => $call['peer']['id'] === (int) request('user_id'))) : $calls)
    <div class="max-w-3xl mx-auto space-y-4">
        <a href="{{ url('/people') }}" class="text-sm text-muted hover:text-ink">&larr; {{ __('Friends') }}</a>
        <div class="flex items-center justify-between"><h1 class="text-xl font-semibold text-ink">{{ __('Call history') }}</h1>
            @if(count($calls) && !request("user_id"))<button type="button" class="text-sm text-red-600 hover:underline" onclick="if (confirm(@js(__('Clear your call history?')))) fetch('/friends/api/calls/history', { method: 'DELETE', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } }).then(function () { location.reload(); })">{{ __('Clear') }}</button>@endif</div>
        <div class="bg-surface-2 rounded-2xl border border-border divide-y divide-border">
            @forelse($calls as $c)
                <div class="flex items-center gap-3 p-4">
                    <div class="w-10 h-10 rounded-full profile-initial flex items-center justify-center font-semibold overflow-hidden flex-shrink-0">
                        @if($c['peer']['avatar_url'])<img src="{{ $c['peer']['avatar_url'] }}" alt="" class="w-full h-full object-cover">@else{{ strtoupper(mb_substr($c['peer']['name'], 0, 1)) }}@endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-ink truncate {{ $c['outcome'] === 'missed' && !$c['outgoing'] ? 'text-red-600' : '' }}">{{ $c['peer']['name'] }}</div>
                        <div class="text-xs text-muted">
                            {{ $c['outgoing'] ? '↗ ' . __('Outgoing') : '↙ ' . __('Incoming') }} · {{ $c['video'] ? __('Video') : __('Audio') }} ·
                            {{ $c['outcome'] === 'answered' ? ($c['duration_text'] ?: __('Answered')) : ($c['outcome'] === 'declined' ? __('Declined') : ($c['outgoing'] ? __('No answer') : __('Missed'))) }} · {{ $c['when'] }}
                        </div>
                    </div>
                    @if(!empty($c['recording_message_id']))<a href="{{ $c['peer']['chat_url'] }}" class="text-xs text-brand hover:underline" title="{{ __('Open the chat to play the recording') }}">&#127897; {{ __('Recording') }}</a>@endif
                    <button type="button" class="btn-secondary" onclick="window.FriendCall && window.FriendCall.start({{ $c['peer']['id'] }}, @js($c['peer']['name']))">{{ __('Call') }}</button>
                    <button type="button" class="btn-secondary" onclick="window.FriendCall && window.FriendCall.startVideo({{ $c['peer']['id'] }}, @js($c['peer']['name']))">{{ __('Video') }}</button>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-muted">{{ __('No calls yet.') }}</div>
            @endforelse
        </div>
    </div>
    <script src="{{ asset('js/call-tones.js') }}?v=20261014" defer></script>
    <script src="{{ asset('js/friend-call.js') }}?v=20261014-media" defer></script>
</x-layouts.app>
