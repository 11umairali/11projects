<div class="w-[210px] flex flex-col border-r border-[#2d3039] bg-[#15171e]/50 select-none pb-2 shrink-0"
     wire:poll.30s.visible>

    {{-- Account Switcher (details/summary = no Alpine needed) --}}
    <details class="relative px-4 mt-4 mb-2 group">
        <summary class="py-2 flex items-center gap-2.5 cursor-pointer list-none rounded-lg hover:bg-white/5 transition px-2 -mx-2 [&::-webkit-details-marker]:hidden">
            @php $activeAccount = $activeAccountId ? $emailAccounts->firstWhere('id', $activeAccountId) : null; $displayAccount = $activeAccount ?? $emailAccounts->first(); @endphp
            <div class="w-[26px] h-[26px] rounded-full overflow-hidden relative border border-white/10 shrink-0">
                <svg class="w-full h-full" viewBox="0 0 100 100" fill="none">
                    <circle cx="50" cy="50" r="50" fill="url(#sideGrad)"/>
                    <path d="M50 20C40 20 30 28 30 40C30 50 40 55 50 65C60 55 70 50 70 40C70 28 60 20 50 20Z" fill="white" opacity="0.8"/>
                    <defs><linearGradient id="sideGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#f43f5e;stop-opacity:1" />
                        <stop offset="50%" style="stop-color:#a855f7;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#3b82f6;stop-opacity:1" />
                    </linearGradient></defs>
                </svg>
            </div>
            <div class="flex-1 overflow-hidden flex items-center justify-between">
                <span class="font-semibold text-[13px] text-gray-200 truncate pr-2">{{ $activeAccountId ? ($displayAccount->email ?? auth()->user()->email) : __('All Accounts') }}</span>
                <svg class="w-3 h-3 text-gray-500 group-hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg>
            </div>
        </summary>
        {{-- Dropdown --}}
        <div class="absolute top-full left-4 w-48 mt-1 bg-[#1a1d27] border border-white/10 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] z-50 py-1.5 font-sans">
            @foreach($emailAccounts as $acct)
            <button wire:click="setAccount({{ $acct->id }})"
                    onclick="this.closest('details').removeAttribute('open')"
                    class="w-full text-left px-3 py-2 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5 {{ $activeAccountId === $acct->id ? 'bg-white/5' : '' }}">
                <div class="w-[18px] h-[18px] rounded-full flex items-center justify-center font-bold text-[9px] shrink-0 border
                    @if($acct->provider === 'gmail') bg-red-500/20 text-red-400 border-red-500/20
                    @elseif($acct->provider === 'outlook') bg-blue-500/20 text-blue-400 border-blue-500/20
                    @else bg-purple-500/20 text-purple-400 border-purple-500/20
                    @endif">
                    @if($acct->provider === 'gmail') G
                    @elseif($acct->provider === 'outlook') O
                    @else {{ strtoupper(substr($acct->email, 0, 1)) }}
                    @endif
                </div>
                <span class="truncate">{{ $acct->email }}</span>
            </button>
            @endforeach
            @if($activeAccountId)
            <div class="h-px bg-white/5 my-1.5 w-full"></div>
            <button wire:click="setAccount(null)"
                    onclick="this.closest('details').removeAttribute('open')"
                    class="w-full text-left px-3 py-2 text-[12px] text-gray-400 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                <svg class="w-4 h-4 opacity-70 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                {{ __('All Accounts') }}
            </button>
            @endif
        </div>
    </details>

    {{-- Compose Button --}}
    <div class="px-3 mb-3">
        <button @click="$dispatch('start-compose')"
                class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-[#3b82f6] text-white text-[13px] font-semibold rounded-[7px] hover:bg-[#2563eb] transition-colors shadow-sm">
            <x-icon name="plus" class="w-4 h-4 stroke-[2.5]" />
            {{ __('Compose') }}
        </button>
    </div>

    {{-- Nav Links --}}
    <div class="flex-1 overflow-y-auto hide-scroll px-3 space-y-0.5">

        {{-- Inbox --}}
        <button wire:click="setChannel('all')" wire:click.prevent="setFolder('inbox')"
                class="w-full px-3 py-1.5 flex items-center gap-3 rounded-[7px] font-medium text-[13px] tracking-wide transition
                {{ $activeFolder === 'inbox' && $activeChannel === 'all' ? 'bg-[#3b82f6] text-white shadow' : 'hover:bg-white/5 text-gray-400 hover:text-gray-200' }}">
            <x-icon name="inbox" class="w-[15px] h-[15px] stroke-[2.5]" />
            {{ __('Inbox') }}
            @if(($channelCounts['all'] ?? 0) > 0)
            <span class="ml-auto text-[11px] font-semibold {{ $activeFolder === 'inbox' && $activeChannel === 'all' ? 'text-white/80' : 'text-gray-500' }}">{{ $channelCounts['all'] }}</span>
            @endif
        </button>

        {{-- Sent --}}
        <button wire:click="setFolder('sent')"
                class="w-full px-3 py-1.5 flex items-center gap-3 rounded-[7px] font-medium text-[13px] tracking-wide transition
                {{ $activeFolder === 'sent' ? 'bg-[#3b82f6] text-white shadow' : 'hover:bg-white/5 text-gray-400 hover:text-gray-200' }}">
            <x-icon name="send" class="w-[15px] h-[15px] stroke-[2.5]" />
            {{ __('Sent') }}
        </button>

        {{-- Starred --}}
        <button wire:click="setFolder('starred')"
                class="w-full px-3 py-1.5 flex items-center gap-3 rounded-[7px] font-medium text-[13px] tracking-wide transition
                {{ $activeFolder === 'starred' ? 'bg-[#3b82f6] text-white shadow' : 'hover:bg-white/5 text-gray-400 hover:text-gray-200' }}">
            <x-icon name="star" class="w-[15px] h-[15px] stroke-[2.5]" />
            {{ __('Starred') }}
        </button>

        {{-- Snoozed --}}
        <button wire:click="setFolder('snoozed')"
                class="w-full px-3 py-1.5 flex items-center gap-3 rounded-[7px] font-medium text-[13px] tracking-wide transition
                {{ $activeFolder === 'snoozed' ? 'bg-[#3b82f6] text-white shadow' : 'hover:bg-white/5 text-gray-400 hover:text-gray-200' }}">
            <x-icon name="clock" class="w-[15px] h-[15px] stroke-[2.5]" />
            {{ __('Snoozed') }}
        </button>

        <div class="h-2"></div>

        {{-- Channels (with chevron, click to filter by channel) --}}
        @php
            $channelItems = [
                ['key' => 'email', 'label' => __('Email'), 'icon' => 'mail'],
                ['key' => 'whatsapp', 'label' => __('WhatsApp'), 'icon' => 'message-circle'],
                ['key' => 'sms', 'label' => __('SMS'), 'icon' => 'message-circle'],
                ['key' => 'telegram', 'label' => __('Telegram'), 'icon' => 'send'],
                ['key' => 'slack', 'label' => __('Slack'), 'icon' => 'hash'],
                ['key' => 'chat', 'label' => __('Live Chat'), 'icon' => 'message-square'],
            ];
        @endphp
        @foreach($channelItems as $ch)
        <button wire:click="setChannel('{{ $ch['key'] }}')"
                class="w-full px-3 py-1.5 flex items-center gap-3 rounded-[7px] font-medium text-[13px] tracking-wide transition
                {{ $activeChannel === $ch['key'] ? 'bg-[#3b82f6] text-white shadow' : 'hover:bg-white/5 text-gray-400 hover:text-gray-200' }}">
            <x-icon name="{{ $ch['icon'] }}" class="w-[15px] h-[15px] stroke-[2.5]" />
            {{ $ch['label'] }}
            @if(($channelCounts[$ch['key']] ?? 0) > 0)
            <span class="ml-auto text-[11px] font-semibold {{ $activeChannel === $ch['key'] ? 'text-white/80' : 'text-gray-500' }}">{{ $channelCounts[$ch['key']] }}</span>
            @endif
        </button>
        @endforeach

    </div>

    {{-- Bottom Actions --}}
    <div class="px-3 space-y-0.5 mt-auto pt-4">
        <button wire:click="setFolder('archive')"
                class="w-full px-3 py-1.5 flex items-center gap-3 rounded-[7px] font-medium text-[13px] tracking-wide transition
                {{ $activeFolder === 'archive' ? 'bg-[#3b82f6] text-white shadow' : 'hover:bg-white/5 text-gray-400 hover:text-gray-200' }}">
            <x-icon name="archive" class="w-[15px] h-[15px] stroke-[2.5]" />
            {{ __('Archive') }}
        </button>
        <button wire:click="setFolder('trash')"
                class="w-full px-3 py-1.5 flex items-center gap-3 rounded-[7px] font-medium text-[13px] tracking-wide transition
                {{ $activeFolder === 'trash' ? 'bg-[#3b82f6] text-white shadow' : 'hover:bg-white/5 text-gray-400 hover:text-gray-200' }}">
            <x-icon name="trash" class="w-[15px] h-[15px] stroke-[2.5]" />
            {{ __('Bin') }}
        </button>

        <div class="h-px bg-white/5 my-2"></div>

        <a href="{{ route('inbox.canned-responses') }}" wire:navigate
           class="w-full px-3 py-1.5 flex items-center gap-3 rounded-[7px] font-medium text-[13px] tracking-wide transition hover:bg-white/5 text-gray-400 hover:text-gray-200">
            <x-icon name="zap" class="w-[15px] h-[15px] stroke-[2.5]" />
            {{ __('Quick Replies') }}
        </a>
        <a href="{{ url('/settings') }}" wire:navigate
           class="w-full px-3 py-1.5 flex items-center gap-3 rounded-[7px] font-medium text-[13px] tracking-wide transition hover:bg-white/5 text-gray-400 hover:text-gray-200">
            <x-icon name="settings" class="w-[15px] h-[15px] stroke-[2.5]" />
            {{ __('Settings') }}
        </a>
        <a href="{{ url('/dashboard') }}" wire:navigate
           class="w-full px-3 py-1.5 flex items-center gap-3 rounded-[7px] font-medium text-[13px] tracking-wide transition hover:bg-white/5 text-gray-500 hover:text-gray-200">
            <svg class="w-[15px] h-[15px] stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            {{ __('Exit Inbox') }}
        </a>
    </div>
</div>
