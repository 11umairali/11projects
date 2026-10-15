<a wire:key="chat-person-{{ $tab }}-{{ $p['id'] }}" href="{{ $p['chat_url'] }}" class="messenger-person {{ $selectedId === (int) $p['id'] ? 'is-selected' : '' }}" @if($selectedId === (int) $p['id']) aria-current="page" @endif>
    <div class="messenger-avatar">
        @if($p['avatar_url'])<img src="{{ $p['avatar_url'] }}" alt="">@else{{ strtoupper(mb_substr($p['name'], 0, 1)) }}@endif
        @if(!empty($p['online']))<span class="messenger-online" title="{{ __('Online') }}"></span>@endif
    </div>
    <div class="min-w-0 flex-1">
        <div class="font-semibold truncate">{{ $p['name'] }}</div>
        <div class="text-sm text-muted truncate">{{ $p['preview'] ?? $p['status_label'] ?? $p['username'] ?? __('Start a conversation') }}</div>
        @if(!empty($p['call_status']))<div class="text-xs text-brand">{{ $p['call_status'] === 'ringing' ? __('Ringing…') : __('Call in progress') }}</div>@endif
    </div>
    @if(($p['unread'] ?? 0) > 0)<span class="rounded-full bg-brand text-white text-xs px-2 py-1" aria-label="{{ __('Unread messages') }}">{{ $p['unread'] }}</span>@endif
</a>
