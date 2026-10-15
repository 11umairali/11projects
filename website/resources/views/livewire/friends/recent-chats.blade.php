<div wire:poll.5s.visible="$refresh">
    @if(count($recentChats))
        <div class="max-w-5xl mx-auto mb-4">
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-4 py-3 border-b border-border flex items-center justify-between">
                    <div class="font-medium text-ink">{{ __('Chats') }}@if(($unreadAll = collect($recentChats)->sum('unread')) > 0)<span class="ml-2 text-xs rounded-full bg-brand text-white px-2 py-0.5">{{ $unreadAll }}</span>@endif</div>
                    <a href="{{ url('/inbox') }}" class="text-xs text-brand hover:underline">{{ __('Customer chats & mail') }} &rarr;</a>
                </div>
                <div class="divide-y divide-border max-h-96 overflow-y-auto">
                    @foreach($recentChats as $c)
                        <a wire:key="recent-chat-{{ $c['id'] }}" href="{{ $c['chat_url'] }}" class="flex items-center gap-3 px-4 py-3 hover:bg-black/5">
                            <div class="relative flex-shrink-0">
                                <div class="w-11 h-11 rounded-full profile-initial flex items-center justify-center font-semibold overflow-hidden">@if($c['avatar_url'])<img src="{{ $c['avatar_url'] }}" alt="" class="w-full h-full object-cover">@else{{ strtoupper(mb_substr($c['name'], 0, 1)) }}@endif</div>
                                @if($c['online'])<span class="absolute right-0 bottom-0 w-3 h-3 rounded-full bg-green-500 border-2 border-surface-2"></span>@endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2"><span class="font-medium text-ink truncate {{ $c['unread'] ? 'font-bold' : '' }}">{{ $c['name'] }}</span><span class="text-xs {{ $c['unread'] ? 'text-brand font-semibold' : 'text-muted' }}">{{ $c['time'] }}</span></div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm text-muted truncate">@if($c['mine'] && !in_array($c['message_kind'], ['call', 'system']))<span class="{{ ($c['status'] ?? '') === 'read' ? 'text-sky-500' : '' }}">{{ ($c['status'] ?? 'sent') === 'sent' ? '✓' : '✓✓' }}</span> @endif{{ $c['preview'] }}</span>
                                    @if($c['unread'])<span class="text-xs rounded-full bg-brand text-white px-2 py-0.5 flex-shrink-0">{{ $c['unread'] }}</span>@endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @elseif($enabled)
        <p class="max-w-5xl mx-auto mb-4 text-sm text-muted">{{ __('Your conversations will appear here.') }}</p>
    @endif
</div>
