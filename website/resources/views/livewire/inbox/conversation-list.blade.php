<div class="w-[400px] flex flex-col border-r border-[#2d3039] bg-[#0c0d12]/80 shrink-0"
     wire:poll.10s.visible="pollRefresh"
     x-data="{ sortDrop: false, searchOpen: false, tagDrop: false, teamDrop: false }"
     x-init="searchOpen = {{ $search ? 'true' : 'false' }};"
     >

    {{-- Top Actions Row --}}
    <div class="h-12 flex items-center px-4 justify-between border-b border-[#2d3039]/60 shrink-0 pt-3 relative">
        <div class="flex items-center gap-0.5 text-gray-400">

            {{-- Folder / Channel Label --}}
            <div class="relative z-50">
                <button @click="sortDrop = !sortDrop" @click.away="sortDrop = false"
                        class="py-1 px-1.5 rounded-lg flex items-center gap-1.5 hover:bg-white/5 hover:text-gray-200 transition text-[13px] font-semibold tracking-wide ml-0.5 whitespace-nowrap shrink-0"
                        title="Switch Channel">
                    <x-icon name="inbox" class="w-[15px] h-[15px] stroke-[2.5] text-blue-400" />
                    <span>{{ ucfirst($folder) }}</span>
                    <x-icon name="chevron-down" class="w-3 h-3 text-gray-500" />
                </button>

                {{-- Dropdown --}}
                <div x-show="sortDrop"
                     x-transition.opacity.duration.200ms
                     style="display: none;"
                     class="absolute top-full left-0 mt-1.5 w-32 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] py-1.5 font-sans">
                    <button @click="sortDrop = false" wire:click="$set('sortBy', 'newest')"
                            class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5 {{ $sortBy === 'newest' ? 'bg-white/5' : '' }}">
                        <svg class="w-3.5 h-3.5 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg> {{ __('Newest') }}
                    </button>
                    <button @click="sortDrop = false" wire:click="$set('sortBy', 'oldest')"
                            class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5 {{ $sortBy === 'oldest' ? 'bg-white/5' : '' }}">
                        <svg class="w-3.5 h-3.5 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg> {{ __('Oldest') }}
                    </button>
                    <button @click="sortDrop = false" wire:click="$set('sortBy', 'priority')"
                            class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5 {{ $sortBy === 'priority' ? 'bg-white/5' : '' }}">
                        <x-icon name="flag" class="w-3.5 h-3.5 text-yellow-500 shrink-0" /> {{ __('Priority') }}
                    </button>
                    <button @click="sortDrop = false" wire:click="$set('sortBy', 'unread')"
                            class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5 {{ $sortBy === 'unread' ? 'bg-white/5' : '' }}">
                        <svg class="w-3.5 h-3.5 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> {{ __('Unread') }}
                    </button>
                </div>
            </div>

            {{-- Tags filter dropdown --}}
            <div class="relative z-40">
                <button @click="tagDrop = !tagDrop; sortDrop = false" @click.away="tagDrop = false"
                        class="py-1 px-1.5 rounded-lg flex items-center gap-1 hover:bg-white/5 hover:text-gray-200 transition text-[12px] font-medium whitespace-nowrap shrink-0
                        {{ $filterTag ? 'text-[#3b82f6]' : 'text-gray-500' }}">
                    <x-icon name="tag" class="w-3.5 h-3.5" />
                    <span>{{ $filterTag ?? __('Tags') }}</span>
                    <x-icon name="chevron-down" class="w-2.5 h-2.5 text-gray-600" />
                </button>
                <div x-show="tagDrop"
                     x-transition.opacity.duration.200ms
                     style="display: none;"
                     class="absolute top-full left-0 mt-1.5 w-44 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] py-1.5 font-sans max-h-64 overflow-y-auto">
                    @if($filterTag)
                    <button @click="tagDrop = false" wire:click="$set('filterTag', '')"
                            class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-[#3b82f6] hover:bg-white/5 transition flex items-center gap-2">
                        <x-icon name="x" class="w-3 h-3" /> {{ __('Clear filter') }}
                    </button>
                    <div class="h-px bg-white/5 my-1"></div>
                    @endif
                    @foreach($this->getTagsForFilter() as $tag)
                    <button @click="tagDrop = false" wire:click="$set('filterTag', '{{ $tag->name }}')"
                            class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5
                            {{ $filterTag === $tag->name ? 'bg-white/5' : '' }}">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $tag->color ?? '#6B7280' }}"></span>
                        {{ $tag->name }}
                        @if($tag->conversations_count > 0)
                        <span class="ml-auto text-[10px] text-gray-500">{{ $tag->conversations_count }}</span>
                        @endif
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Team filter dropdown --}}
            <div class="relative z-30">
                <button @click="teamDrop = !teamDrop; sortDrop = false; tagDrop = false" @click.away="teamDrop = false"
                        class="py-1 px-1.5 rounded-lg flex items-center gap-1 hover:bg-white/5 hover:text-gray-200 transition text-[12px] font-medium whitespace-nowrap shrink-0
                        {{ $filterAssignee ? 'text-[#3b82f6]' : 'text-gray-500' }}">
                    <x-icon name="users" class="w-3.5 h-3.5" />
                    <span>{{ __('Team') }}</span>
                    <x-icon name="chevron-down" class="w-2.5 h-2.5 text-gray-600" />
                </button>
                <div x-show="teamDrop"
                     x-transition.opacity.duration.200ms
                     style="display: none;"
                     class="absolute top-full left-0 mt-1.5 w-48 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] py-1.5 font-sans max-h-64 overflow-y-auto">
                    @if($filterAssignee)
                    <button @click="teamDrop = false" wire:click="$set('filterAssignee', null)"
                            class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-[#3b82f6] hover:bg-white/5 transition flex items-center gap-2">
                        <x-icon name="x" class="w-3 h-3" /> {{ __('Clear filter') }}
                    </button>
                    <div class="h-px bg-white/5 my-1"></div>
                    @endif
                    @foreach($this->getTeamForFilter() as $member)
                    <button @click="teamDrop = false" wire:click="$set('filterAssignee', {{ $member->id }})"
                            class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5
                            {{ $filterAssignee === $member->id ? 'bg-white/5' : '' }}">
                        <span class="w-5 h-5 rounded-full bg-gray-700 flex items-center justify-center text-[8px] font-bold text-gray-300 shrink-0">{{ $member->initials }}</span>
                        {{ $member->name }}
                    </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 text-blue-400/80">
            {{-- Refresh / Resync --}}
            <button wire:click="$dispatch('trigger-sync')"
                    wire:loading.attr="disabled"
                    class="p-1 rounded hover:text-blue-300 transition disabled:opacity-50"
                    title="{{ __('Refresh & Sync') }}">
                <x-icon name="refresh-cw" class="w-[16px] h-[16px] stroke-[2.5]" wire:loading.class="animate-spin" wire:target="$dispatch('trigger-sync')" />
            </button>
            {{-- Search toggle --}}
            <div>
                <button @click="searchOpen = !searchOpen" class="p-1 rounded hover:text-blue-300 transition">
                    <x-icon name="search" class="w-[18px] h-[18px] stroke-[2.5]" />
                </button>
                {{-- Search input (overlays on top bar) --}}
                <div x-show="searchOpen" x-transition x-cloak
                     class="absolute inset-x-0 top-0 h-12 bg-[#0c0d12] z-[100] flex items-center px-4 gap-2 border-b border-[#2d3039]/60"
                     style="display:none">
                    <x-icon name="search" class="w-4 h-4 text-gray-500 shrink-0" />
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="{{ __('Search conversations...') }}"
                           class="flex-1 bg-transparent text-[13px] text-gray-300 placeholder-gray-600 focus:outline-none"
                           x-ref="searchInput"
                           @keydown.escape="searchOpen = false">
                    <button @click="searchOpen = false; $wire.set('search', '')" class="p-1 text-gray-500 hover:text-gray-300 transition">
                        <x-icon name="x" class="w-4 h-4" />
                    </button>
                </div>
            </div>

            {{-- Compose + Select --}}
            <div class="flex items-center gap-1 bg-white/5 rounded-md px-1 py-0.5 ml-1 text-gray-400">
                <button @click="$dispatch('start-compose')" class="p-1 rounded hover:bg-white/10 transition" title="{{ __('Compose') }}">
                    <x-icon name="pencil" class="w-4 h-4 stroke-[2]" />
                </button>
                <button wire:click="toggleSelectAll" class="p-1 rounded hover:bg-white/10 transition" title="{{ $selectAll ? __('Deselect all') : __('Select all') }}">
                    @if($selectAll)
                    <x-icon name="check" class="w-[18px] h-[18px] stroke-[2] text-[#3b82f6]" />
                    @else
                    <svg class="w-[18px] h-[18px] stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 12H3m13 0h5M3 6h18M3 18h18"/></svg>
                    @endif
                </button>
            </div>
        </div>
    </div>

    {{-- Bulk actions bar --}}
    @if(!empty($selectedIds))
    <div class="px-4 py-2 bg-[#3b82f6]/5 border-b border-[#2d3039] flex items-center gap-2 flex-wrap">
        <span class="text-[12px] font-medium text-gray-200">{{ count($selectedIds) }} {{ __('selected') }}</span>
        <span class="text-gray-600 text-[12px]">|</span>
        <button type="button" wire:click="toggleSelectAll" class="text-[12px] text-[#3b82f6] font-medium hover:text-blue-300 transition-colors">
            {{ $selectAll ? __('Deselect all') : __('Select all') }}
        </button>
        <div class="flex items-center gap-1 ml-auto">
            <button type="button" wire:click="bulkMarkAsRead" title="{{ __('Mark as read') }}"
                    class="p-1.5 rounded text-gray-500 hover:bg-white/5 hover:text-gray-300 transition-colors">
                <x-icon name="check-check" class="w-3.5 h-3.5" />
            </button>
            <button type="button" wire:click="bulkStar" title="{{ __('Star') }}"
                    class="p-1.5 rounded text-gray-500 hover:bg-white/5 hover:text-gray-300 transition-colors">
                <x-icon name="star" class="w-3.5 h-3.5" />
            </button>
            <button type="button" wire:click="bulkArchive" title="{{ __('Archive') }}"
                    class="p-1.5 rounded text-gray-500 hover:bg-white/5 hover:text-gray-300 transition-colors">
                <x-icon name="archive" class="w-3.5 h-3.5" />
            </button>
            <button type="button" wire:click="bulkDelete" wire:confirm="Delete selected conversations? This action cannot be undone." title="{{ __('Delete') }}"
                    class="p-1.5 rounded text-gray-500 hover:bg-white/5 hover:text-red-400 transition-colors">
                <x-icon name="trash" class="w-3.5 h-3.5" />
            </button>
        </div>
    </div>
    @endif

    {{-- Conversation Cards --}}
    <div class="flex-1 overflow-y-auto p-1 space-y-1"
         x-data
         @scroll.debounce.200ms="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 100) { $wire.loadMore() }">

        @php
            // Group conversations by month
            $grouped = collect($loadedConversations)->groupBy(function ($conv) {
                $date = $conv['time'] ?? '';
                // Try to parse relative time to a month label
                // Since we only have relative time strings, group by first letter or use "Recent"
                return 'Recent';
            });

            // Better grouping: use actual timestamps if available, otherwise group all as "Conversations"
            $monthGroups = [];
            foreach ($loadedConversations as $conv) {
                $monthGroups[''][] = $conv;
            }
        @endphp

        @forelse($loadedConversations as $conv)
            @php
                $isActive = $selectedConversationId === $conv['id'];
                $isSelected = in_array($conv['id'], $selectedIds);
            @endphp

            <div wire:key="conv-{{ $conv['id'] }}"
                 wire:click="selectConversation({{ $conv['id'] }})"
                 class="px-3 py-2 rounded-lg transition cursor-pointer mb-0.5
                 {{ $isActive
                     ? 'bg-[#3b82f6] text-white shadow-lg'
                     : 'bg-white/[0.03] border border-white/[0.03] hover:border-white/10' }}">

                <div class="flex gap-3">
                    {{-- Avatar (if contact has initials) --}}
                    <div class="relative flex-shrink-0">
                        {{-- Checkbox overlay --}}
                        <button type="button" wire:click.stop="toggleSelect({{ $conv['id'] }})"
                                class="absolute -left-0.5 -top-0.5 w-5 h-5 rounded border flex items-center justify-center transition-all z-10
                                       {{ $isSelected
                                           ? 'bg-[#3b82f6] border-blue-500 text-white opacity-100'
                                           : 'border-[#2d3039] bg-[#1a1d27] opacity-0 group-hover:opacity-100 hover:opacity-100' }}"
                                aria-label="{{ $isSelected ? 'Deselect' : 'Select' }}">
                            @if($isSelected)
                            <x-icon name="check" class="w-3 h-3" />
                            @endif
                        </button>

                        @php
                            $avatarColors = ['bg-pink-700 text-white', 'bg-blue-700 text-white', 'bg-emerald-700 text-white', 'bg-amber-700 text-white', 'bg-violet-700 text-white', 'bg-rose-700 text-white', 'bg-cyan-700 text-white', 'bg-indigo-700 text-white'];
                            $colorIndex = $conv['id'] % count($avatarColors);
                        @endphp
                        <div class="w-9 h-9 rounded-full border border-white/10 shrink-0 flex items-center justify-center text-lg font-bold
                             {{ $isActive ? 'bg-white/20 text-white border-white/20' : $avatarColors[$colorIndex] }}
                             {{ $isSelected ? 'opacity-0' : '' }}">
                            {{ $conv['contact_initials'] }}
                        </div>
                    </div>

                    <div class="overflow-hidden flex-1 min-w-0">
                        {{-- Title --}}
                        <h4 class="font-bold text-[13px] truncate font-sans {{ $isActive ? 'text-white' : 'text-gray-200' }}">
                            @if($conv['is_starred'] ?? false)
                            <x-icon name="pin" class="w-3 h-3 inline -rotate-45 mr-0.5 {{ $isActive ? 'text-white/70' : 'text-gray-500' }}" />
                            @endif
                            {{ $conv['contact_name'] }}
                        </h4>

                        {{-- Subject --}}
                        @if($conv['subject'] && $conv['subject'] !== '(no subject)')
                        <p class="text-[11px] mt-0.5 leading-snug truncate {{ $isActive ? 'text-white/90 font-medium' : ($conv['is_unread'] ? 'text-gray-200 font-medium' : 'text-gray-400') }}">
                            {{ $conv['subject'] }}
                        </p>
                        @endif

                        {{-- Preview --}}
                        <p class="text-xs mt-0.5 leading-snug truncate {{ $isActive ? 'text-white/80' : 'text-gray-400' }}">
                            {{ $conv['preview'] }}
                        </p>
                    </div>
                </div>

                {{-- Footer: timestamp, counts, tags --}}
                <div class="mt-1 flex items-center justify-between">
                    <div class="flex items-center gap-2 {{ $isActive ? 'text-white/70' : 'text-gray-500/80' }}">
                        <span class="text-[11px] font-semibold tracking-wide">{{ $conv['time'] }}</span>

                        {{-- Unread dot --}}
                        @if($conv['is_unread'] && !$isActive)
                        <span class="w-2 h-2 rounded-full bg-[#3b82f6]"></span>
                        @endif

                        {{-- Message count --}}
                        @if(($conv['messages_count'] ?? 0) > 1)
                        <div class="flex items-center gap-1.5 {{ $isActive ? 'text-white/70' : 'text-gray-500' }}">
                            <x-icon name="message-circle" class="w-[13px] h-[13px] stroke-[2.5]" />
                            <span class="text-[11px] font-medium">{{ $conv['messages_count'] }}</span>
                        </div>
                        @endif

                        {{-- Channel icon --}}
                        @if(($conv['channel'] ?? 'email') !== 'email')
                        @switch($conv['channel'])
                            @case('whatsapp')
                                <x-icon name="message-circle" class="w-3 h-3 {{ $isActive ? 'text-white/60' : 'text-green-400/60' }}" />
                                @break
                            @case('sms')
                                <x-icon name="phone" class="w-3 h-3 {{ $isActive ? 'text-white/60' : 'text-blue-400/60' }}" />
                                @break
                            @case('telegram')
                                <x-icon name="send" class="w-3 h-3 {{ $isActive ? 'text-white/60' : 'text-sky-400/60' }}" />
                                @break
                            @case('slack')
                                <x-icon name="hash" class="w-3 h-3 {{ $isActive ? 'text-white/60' : 'text-purple-400/60' }}" />
                                @break
                            @case('chat')
                                <x-icon name="message-square" class="w-3 h-3 {{ $isActive ? 'text-white/60' : 'text-orange-400/60' }}" />
                                @break
                        @endswitch
                        @endif

                        {{-- AI badge --}}
                        @if($conv['is_ai_handled'] ?? false)
                        <x-icon name="zap" class="w-3 h-3 {{ $isActive ? 'text-white/60' : 'text-cyan-400/60' }}" />
                        @endif

                        {{-- Priority indicator --}}
                        @if(in_array($conv['priority'] ?? 'normal', ['high', 'urgent']))
                        <x-icon name="alert-triangle" class="w-3 h-3 {{ $isActive ? 'text-white/60' : 'text-red-400/60' }}" />
                        @endif
                    </div>

                    {{-- Tags --}}
                    <div class="flex items-center gap-1">
                        @foreach(array_slice($conv['tags'] ?? [], 0, 3) as $tag)
                        <span class="text-[10px] font-medium {{ $isActive ? 'text-white/90' : 'text-gray-500' }}">
                            #{{ $tag['name'] }}
                        </span>
                        @endforeach

                        {{-- Assigned --}}
                        @if($conv['assigned_to_initials'] ?? false)
                        <span class="w-5 h-5 rounded-full bg-white/10 flex items-center justify-center text-[8px] font-bold {{ $isActive ? 'text-white/80' : 'text-gray-400' }}"
                              title="{{ $conv['assigned_to_name'] ?? '' }}">
                            {{ $conv['assigned_to_initials'] }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            {{-- Empty states --}}
            @if($search || $filterTag)
                <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-white/[0.03] border border-white/[0.05] flex items-center justify-center mb-4">
                        <x-icon name="search" class="w-7 h-7 text-gray-600" />
                    </div>
                    <h3 class="text-sm font-semibold text-gray-200 mb-1">{{ __('No conversations match') }}</h3>
                    <p class="text-[13px] text-gray-500 mb-4 max-w-[240px]">
                        {{ __('Try adjusting your search query or filters to find what you are looking for.') }}
                    </p>
                    <button type="button" wire:click="$set('search', ''); $set('filterTag', '')" class="text-[13px] font-medium text-[#3b82f6] hover:text-blue-300 transition-colors">
                        {{ __('Clear all filters') }}
                    </button>
                </div>
            @elseif(in_array($channel, ['whatsapp', 'sms', 'telegram', 'slack', 'chat']))
                <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-white/[0.03] border border-white/[0.05] flex items-center justify-center mb-4">
                        @switch($channel)
                            @case('whatsapp') <x-icon name="message-circle" class="w-7 h-7 text-gray-600" /> @break
                            @case('sms') <x-icon name="phone" class="w-7 h-7 text-gray-600" /> @break
                            @case('telegram') <x-icon name="send" class="w-7 h-7 text-gray-600" /> @break
                            @case('slack') <x-icon name="hash" class="w-7 h-7 text-gray-600" /> @break
                            @case('chat') <x-icon name="message-square" class="w-7 h-7 text-gray-600" /> @break
                        @endswitch
                    </div>
                    <h3 class="text-sm font-semibold text-gray-200 mb-1">{{ __('No conversations yet') }}</h3>
                    <p class="text-[13px] text-gray-500 mb-4 max-w-[240px]">
                        @switch($channel)
                            @case('whatsapp') {{ __('Connect WhatsApp to start receiving messages.') }} @break
                            @case('sms') {{ __('Connect SMS (Twilio) to receive text messages.') }} @break
                            @case('telegram') {{ __('Connect Telegram to manage bot conversations.') }} @break
                            @case('slack') {{ __('Connect Slack to manage workspace messages.') }} @break
                            @case('chat') {{ __('Set up Live Chat widget for your website.') }} @break
                        @endswitch
                    </p>
                    <a href="{{ route('settings.channels') }}" class="inline-flex items-center px-3 py-1.5 bg-[#3b82f6] text-white text-sm font-medium rounded-lg hover:bg-[#2563eb] transition-colors">
                        @switch($channel)
                            @case('whatsapp') {{ __('Connect WhatsApp') }} @break
                            @case('sms') {{ __('Connect Twilio') }} @break
                            @case('telegram') {{ __('Connect Telegram') }} @break
                            @case('slack') {{ __('Connect Slack') }} @break
                            @case('chat') {{ __('Set Up Live Chat') }} @break
                        @endswitch
                    </a>
                </div>
            @elseif($this->isSyncing)
                <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#3b82f6]/10 text-[#3b82f6] mb-4">
                        <svg viewBox="0 0 24 24" class="h-8 w-8 animate-spin" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-gray-200 mb-1">{{ __('Syncing your inbox...') }}</h3>
                    <p class="text-sm text-gray-500 max-w-xs mb-2">{{ __('We\'re importing your emails. This may take a few minutes for large mailboxes.') }}</p>
                    <p class="text-xs text-gray-600">{{ __('This page refreshes automatically.') }}</p>
                </div>
            @elseif(!$this->hasEmailAccounts)
                <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#3b82f6]/10 text-[#3b82f6] mb-4">
                        <x-icon name="mail" class="w-8 h-8" />
                    </div>
                    <h3 class="text-base font-semibold text-gray-200 mb-1">{{ __('Connect your email') }}</h3>
                    <p class="text-sm text-gray-500 max-w-xs mb-4">{{ __('Link your Gmail, Outlook, or IMAP account to start managing all your conversations in one place.') }}</p>
                    <a href="{{ route('settings.email') }}" wire:navigate class="inline-flex items-center px-3 py-1.5 bg-[#3b82f6] text-white text-sm font-medium rounded-lg hover:bg-[#2563eb] transition-colors">
                        {{ __('Connect Email Account') }}
                    </a>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/[0.03] border border-white/[0.05] text-gray-600 mb-4">
                        <x-icon name="inbox" class="w-8 h-8" />
                    </div>
                    <h3 class="text-base font-semibold text-gray-200 mb-1">{{ __('No conversations yet') }}</h3>
                    <p class="text-sm text-gray-500 max-w-xs">{{ __('Conversations will appear here when you receive messages in this channel.') }}</p>
                </div>
            @endif
        @endforelse

        {{-- Load more --}}
        @if($hasMorePages ?? false)
        <div class="py-4 flex justify-center">
            <button type="button" wire:click="loadMore" class="text-sm text-[#3b82f6] hover:text-blue-300 font-medium transition-colors">
                <span wire:loading.remove wire:target="loadMore">{{ __('Load more conversations') }}</span>
                <span wire:loading wire:target="loadMore" class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ __('Loading...') }}
                </span>
            </button>
        </div>
        @endif
    </div>

    {{-- Footer count --}}
    @if(count($loadedConversations) > 0)
    <div class="px-4 py-2 border-t border-[#2d3039]">
        <span class="text-[11px] text-gray-600">
            {{ count($loadedConversations) }} {{ count($loadedConversations) !== 1 ? __('conversations') : __('conversation') }}
        </span>
    </div>
    @endif

    {{-- Inbox sync loop — plain <script> inside the Livewire root so it
         survives re-renders. Guarded so it only runs once per page load.
         Logs to console at every step so server-side issues are diagnosable
         via F12 > Console. --}}
    <script>
    (function () {
        console.log('[InboxSync] script parsed, endpoint=' + @json(url('/inbox/api/sync')));

        if (window.__inboxSyncRunning) {
            console.log('[InboxSync] already running, skip');
            return;
        }
        window.__inboxSyncRunning = true;

        var endpoint = @json(url('/inbox/api/sync'));
        var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        if (!csrf) {
            console.warn('[InboxSync] no CSRF meta tag — POST will fail');
        }

        var consecutiveEmpty = 0;
        var callCount = 0;

        function fetchBatch() {
            callCount++;
            console.log('[InboxSync] call #' + callCount + ' → POST ' + endpoint);

            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function (r) {
                console.log('[InboxSync] response status=' + r.status);
                return r.ok ? r.json() : null;
            })
            .then(function (d) {
                if (!d) {
                    console.warn('[InboxSync] no JSON body, retry in 5s');
                    setTimeout(fetchBatch, 5000);
                    return;
                }

                console.log('[InboxSync] synced=' + d.synced + ' has_more=' + d.has_more);

                if (d.synced > 0) {
                    consecutiveEmpty = 0;
                    try {
                        if (window.Livewire && Livewire.dispatch) {
                            Livewire.dispatch('refresh-conversations');
                        }
                    } catch (e) {}
                } else {
                    consecutiveEmpty++;
                }

                if (d.has_more) {
                    setTimeout(fetchBatch, 800);
                } else if (consecutiveEmpty < 30) {
                    setTimeout(fetchBatch, 30000);
                } else {
                    console.log('[InboxSync] idle, stopping — cron will continue in background');
                }
            })
            .catch(function (err) {
                console.error('[InboxSync] fetch error', err);
                setTimeout(fetchBatch, 5000);
            });
        }

        // Kick off. Use setTimeout(0) to let the rest of the page settle first.
        setTimeout(fetchBatch, 100);
    })();
    </script>
</div>
