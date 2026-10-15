<div wire:poll.5s.visible class="chat-list">
    <header class="messenger-sidebar-header">
        <div class="flex items-center gap-2">
            @if($newChat)<button type="button" wire:click="closePicker" class="messenger-icon" aria-label="{{ __('Back to chats') }}"><x-icon name="arrow-left" class="w-5 h-5" /></button>@endif
            <h1 class="text-xl font-semibold">{{ $newChat ? __('New chat') : __('Chats') }}</h1>
        </div>
        @unless($newChat)
            <button type="button" wire:click="startChat" class="messenger-icon" title="{{ __('New chat') }}" aria-label="{{ __('New chat') }}"><x-action-icon name="chat" /></button>
        @endunless
    </header>
    <div class="px-3 py-2"><input type="search" wire:model.live.debounce.250ms="search" class="input w-full" placeholder="{{ $newChat ? __('Search friends') : __('Search conversations') }}" aria-label="{{ $newChat ? __('Search friends') : __('Search conversations') }}"></div>
    @if($newChat)
        <p class="px-4 py-2 text-xs text-muted">{{ __('Friends · A–Z') }}</p>
        @php $previousLetter = null; @endphp
        @forelse($contacts as $person)
            @php $letter = mb_strtoupper(mb_substr($person['name'], 0, 1)); @endphp
            @if($letter !== $previousLetter)<h2 class="chat-picker-letter">{{ $letter }}</h2>@endif
            @php $previousLetter = $letter; @endphp
            <a wire:key="new-chat-{{ $person['id'] }}" class="chat-picker-person" href="{{ $person['chat_url'] }}">
                <span class="messenger-avatar">@if($person['avatar_url'])<img src="{{ $person['avatar_url'] }}" alt="">@else{{ $letter }}@endif</span>
                <span class="min-w-0"><strong class="block truncate text-sm">{{ $person['name'] }}</strong><small class="text-muted">{{ __('Start conversation') }}</small></span>
            </a>
        @empty
            <p class="p-6 text-sm text-muted text-center">{{ $search ? __('No friends match your search.') : __('Your friends will appear here. Add friends from the Friends page.') }}</p>
        @endforelse
    @else
    @if($notice)<p role="status" class="px-3 py-2 text-xs text-muted">{{ $notice }}</p>@endif
    @error('ids')<p role="alert" class="px-3 py-2 text-red-600">{{ $message }}</p>@enderror
    @if(count($selected))
        <div class="chat-selection" aria-label="{{ __('Selected chats') }}">
            <button wire:click="$set('selected', [])" class="messenger-icon" title="{{ __('Cancel selection') }}"><x-icon name="x" class="w-5 h-5" /></button>
            <strong>{{ count($selected) }}</strong>
            <button wire:click="organize('{{ $allPinned ? 'unpin' : 'pin' }}')" class="messenger-icon" title="{{ $allPinned ? __('Unpin') : __('Pin (maximum 5)') }}"><x-icon name="pin" class="w-5 h-5" /></button>
            <button wire:click="organize('{{ $allMuted ? 'unmute' : 'mute' }}')" class="messenger-icon" title="{{ $allMuted ? __('Unmute notifications') : __('Mute notifications') }}"><x-icon name="bell-off" class="w-5 h-5" /></button>
            <button wire:click="organize('{{ $archived ? 'unarchive' : 'archive' }}')" class="messenger-icon" title="{{ $archived ? __('Unarchive') : __('Archive') }}"><x-icon name="archive" class="w-5 h-5" /></button>
            <button wire:click="organize('delete')" wire:confirm="{{ __('Remove selected chats from your list? A new message will bring them back. Your friendships stay.') }}" class="messenger-icon" title="{{ __('Delete chats') }}"><x-icon name="trash" class="w-5 h-5" /></button>
            <details class="relative"><summary class="messenger-icon" aria-label="{{ __('More actions') }}"><x-icon name="more-horizontal" class="w-5 h-5" /></summary>
                <div class="chat-selection-menu">
                    @if(count($selected) === 1)
                        <a href="{{ url('/people/'.$selected[0]) }}">{{ __('View profile') }}</a>
                        <a href="{{ url('/people/calls').'?user_id='.$selected[0] }}">{{ __('Call history') }}</a>
                    @endif
                    <button wire:click="organize('clear')" wire:confirm="{{ __('Clear messages for you? The chats stay in your list and other people keep their copies.') }}">{{ count($selected) === 1 ? __('Clear chat') : __('Clear chats') }}</button>
                </div>
            </details>
        </div>
    @else
        <button class="chat-archive-row" wire:click="folder({{ $archived ? 'false' : 'true' }})">
            <x-icon :name="$archived ? 'arrow-left' : 'archive'" class="w-5 h-5" />
            <span>{{ $archived ? __('Back to chats') : __('Archived') }}</span>
            @if(!$archived && $archiveCount)<span>{{ $archiveCount }}</span>@endif
        </button>
    @endif
    @forelse($chats as $p)
        <div wire:key="chat-{{ $p['id'] }}" class="chat-list-row {{ in_array($p['id'], $selected) ? 'is-selected' : '' }}"
            x-data="{ timer: null, held: false }" @pointerdown="held=false; timer=setTimeout(() => { held=true; $wire.toggle({{ $p['id'] }}); }, 500)"
            @pointerup="clearTimeout(timer)" @pointercancel="clearTimeout(timer)" @pointerleave="clearTimeout(timer)" @contextmenu.prevent="clearTimeout(timer); if(!held){ held=true; $wire.toggle({{ $p['id'] }}); }">
            <input type="checkbox" aria-label="{{ __('Select chat with :name', ['name' => $p['name']]) }}" @pointerdown.stop @click.stop="$wire.toggle({{ $p['id'] }})" @checked(in_array($p['id'], $selected))>
            <a href="{{ $p['chat_url'] }}" @click="if(held){$event.preventDefault();held=false;} else if({{ count($selected) }}){$event.preventDefault();$wire.toggle({{ $p['id'] }});}" class="chat-list-link">
                <span class="messenger-avatar">@if($p['avatar_url'])<img src="{{ $p['avatar_url'] }}" alt="">@else{{ mb_strtoupper(mb_substr($p['name'], 0, 1)) }}@endif</span>
                <span class="min-w-0 flex-1"><span class="flex items-center justify-between gap-2"><strong class="truncate text-sm">{{ $p['name'] }}</strong><small class="text-muted shrink-0">{{ $p['time'] }}</small></span>
                    <span class="flex items-center gap-1 text-xs text-muted">
                        @if($p['mine'] && $p['status'])<span class="{{ $p['status'] === 'read' ? 'text-blue-600' : '' }}">{{ $p['status'] === 'sent' ? '✓' : '✓✓' }}</span>@endif
                        <span class="truncate flex-1">{{ $p['preview'] ?: __('No messages') }}</span>
                        @if($p['pinned'])<x-icon name="pin" class="w-3.5 h-3.5 shrink-0" />@endif
                        @if($p['muted'])<x-icon name="bell-off" class="w-3.5 h-3.5 shrink-0" />@endif
                        @if($p['unread'])<span class="rounded-full bg-blue-600 text-white px-1.5">{{ $p['unread'] }}</span>@endif
                    </span>
                </span>
            </a>
        </div>
    @empty
        <p class="p-6 text-sm text-muted text-center">{{ $archived ? __('No archived chats.') : __('No conversations yet. Find a friend to start chatting.') }}</p>
    @endforelse
    @endif
</div>
