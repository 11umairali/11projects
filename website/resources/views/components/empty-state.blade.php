@props([
    'type' => 'default',
    'title' => 'Nothing here yet',
    'description' => '',
    'actionUrl' => null,
    'actionLabel' => null,
    'compact' => false,
])

<div class="{{ $compact ? 'py-8' : 'py-16' }} flex flex-col items-center justify-center text-center px-6">
    {{-- SVG Illustration --}}
    <div class="{{ $compact ? 'w-28 h-28 mb-4' : 'w-40 h-40 mb-6' }}">
        @switch($type)
            @case('inbox')
            @case('email')
                {{-- Inbox/Email illustration --}}
                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                    <circle cx="100" cy="100" r="90" fill="color-mix(in srgb, var(--color-brand) 6%, transparent)"/>
                    <circle cx="100" cy="100" r="65" fill="color-mix(in srgb, var(--color-brand) 10%, transparent)"/>
                    <rect x="50" y="65" width="100" height="70" rx="12" fill="var(--color-surface-2)" stroke="color-mix(in srgb, var(--color-brand) 30%, transparent)" stroke-width="2"/>
                    <path d="M50 77L100 107L150 77" stroke="var(--color-brand)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <circle cx="145" cy="62" r="16" fill="var(--color-brand)" opacity="0.9"/>
                    <path d="M139 62h12M145 56v12" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                    <rect x="75" y="112" width="30" height="4" rx="2" fill="color-mix(in srgb, var(--color-muted) 30%, transparent)"/>
                    <rect x="75" y="120" width="50" height="4" rx="2" fill="color-mix(in srgb, var(--color-muted) 20%, transparent)"/>
                </svg>
                @break

            @case('contacts')
            @case('users')
                {{-- Contacts/People illustration --}}
                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                    <circle cx="100" cy="100" r="90" fill="color-mix(in srgb, var(--color-success) 6%, transparent)"/>
                    <circle cx="100" cy="100" r="65" fill="color-mix(in srgb, var(--color-success) 10%, transparent)"/>
                    <circle cx="100" cy="78" r="20" fill="var(--color-surface-2)" stroke="var(--color-success)" stroke-width="2.5"/>
                    <path d="M68 130c0-17.673 14.327-32 32-32s32 14.327 32 32" stroke="var(--color-success)" stroke-width="2.5" fill="var(--color-surface-2)" stroke-linecap="round"/>
                    <circle cx="148" cy="85" r="14" fill="var(--color-surface-2)" stroke="color-mix(in srgb, var(--color-success) 50%, transparent)" stroke-width="2"/>
                    <path d="M128 120c0-11.046 8.954-20 20-20s20 8.954 20 20" stroke="color-mix(in srgb, var(--color-success) 50%, transparent)" stroke-width="2" fill="none" stroke-linecap="round"/>
                    <circle cx="52" cy="85" r="14" fill="var(--color-surface-2)" stroke="color-mix(in srgb, var(--color-success) 50%, transparent)" stroke-width="2"/>
                    <path d="M32 120c0-11.046 8.954-20 20-20s20 8.954 20 20" stroke="color-mix(in srgb, var(--color-success) 50%, transparent)" stroke-width="2" fill="none" stroke-linecap="round"/>
                    <circle cx="155" cy="70" r="12" fill="var(--color-success)" opacity="0.9"/>
                    <path d="M150 70h10M155 65v10" stroke="white" stroke-width="2" stroke-linecap="round"/>
                </svg>
                @break

            @case('campaigns')
            @case('send')
                {{-- Campaigns/Send illustration --}}
                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                    <circle cx="100" cy="100" r="90" fill="color-mix(in srgb, var(--color-accent) 6%, transparent)"/>
                    <circle cx="100" cy="100" r="65" fill="color-mix(in srgb, var(--color-accent) 10%, transparent)"/>
                    <path d="M60 100L140 60L120 140L95 108L60 100Z" fill="var(--color-surface-2)" stroke="var(--color-accent)" stroke-width="2.5" stroke-linejoin="round"/>
                    <path d="M95 108L140 60" stroke="var(--color-accent)" stroke-width="2" stroke-linecap="round" opacity="0.5"/>
                    <circle cx="75" cy="55" r="8" fill="color-mix(in srgb, var(--color-accent) 20%, transparent)"/>
                    <circle cx="150" cy="95" r="6" fill="color-mix(in srgb, var(--color-accent) 25%, transparent)"/>
                    <circle cx="80" cy="140" r="5" fill="color-mix(in srgb, var(--color-accent) 15%, transparent)"/>
                    <path d="M60 60l5 5M55 75l6 2M150 130l-5-3M145 145l-4-5" stroke="color-mix(in srgb, var(--color-accent) 30%, transparent)" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                @break

            @case('deals')
            @case('pipeline')
                {{-- Deals/Pipeline illustration --}}
                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                    <circle cx="100" cy="100" r="90" fill="color-mix(in srgb, var(--color-warning) 6%, transparent)"/>
                    <circle cx="100" cy="100" r="65" fill="color-mix(in srgb, var(--color-warning) 10%, transparent)"/>
                    <rect x="45" y="80" width="30" height="50" rx="6" fill="var(--color-surface-2)" stroke="var(--color-warning)" stroke-width="2"/>
                    <rect x="85" y="65" width="30" height="65" rx="6" fill="var(--color-surface-2)" stroke="var(--color-warning)" stroke-width="2"/>
                    <rect x="125" y="50" width="30" height="80" rx="6" fill="var(--color-surface-2)" stroke="var(--color-warning)" stroke-width="2"/>
                    <rect x="52" y="90" width="16" height="4" rx="2" fill="color-mix(in srgb, var(--color-warning) 40%, transparent)"/>
                    <rect x="92" y="75" width="16" height="4" rx="2" fill="color-mix(in srgb, var(--color-warning) 40%, transparent)"/>
                    <rect x="132" y="60" width="16" height="4" rx="2" fill="color-mix(in srgb, var(--color-warning) 40%, transparent)"/>
                    <path d="M60 135L100 135L140 135" stroke="color-mix(in srgb, var(--color-muted) 25%, transparent)" stroke-width="1.5" stroke-dasharray="4 4"/>
                    <path d="M55 75l10-15l10 8l15-20l10 12l15-18" stroke="var(--color-warning)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
                @break

            @case('workflows')
            @case('automation')
                {{-- Workflows/Automation illustration --}}
                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                    <circle cx="100" cy="100" r="90" fill="color-mix(in srgb, var(--color-brand) 6%, transparent)"/>
                    <circle cx="100" cy="100" r="65" fill="color-mix(in srgb, var(--color-brand) 10%, transparent)"/>
                    <rect x="75" y="55" width="50" height="30" rx="8" fill="var(--color-surface-2)" stroke="var(--color-brand)" stroke-width="2"/>
                    <rect x="45" y="115" width="45" height="30" rx="8" fill="var(--color-surface-2)" stroke="var(--color-brand)" stroke-width="2" opacity="0.7"/>
                    <rect x="110" y="115" width="45" height="30" rx="8" fill="var(--color-surface-2)" stroke="var(--color-brand)" stroke-width="2" opacity="0.7"/>
                    <path d="M100 85V95M100 95L67 115M100 95L133 115" stroke="var(--color-brand)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="100" cy="70" r="6" fill="var(--color-brand)" opacity="0.2"/>
                    <circle cx="100" cy="70" r="3" fill="var(--color-brand)"/>
                    <circle cx="67" cy="130" r="3" fill="var(--color-brand)" opacity="0.6"/>
                    <circle cx="133" cy="130" r="3" fill="var(--color-brand)" opacity="0.6"/>
                    <path d="M88 67h6M97 63v6" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                @break

            @case('analytics')
            @case('chart')
                {{-- Analytics/Chart illustration --}}
                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                    <circle cx="100" cy="100" r="90" fill="color-mix(in srgb, var(--color-info) 6%, transparent)"/>
                    <circle cx="100" cy="100" r="65" fill="color-mix(in srgb, var(--color-info) 10%, transparent)"/>
                    <rect x="50" y="55" width="100" height="80" rx="10" fill="var(--color-surface-2)" stroke="color-mix(in srgb, var(--color-info) 30%, transparent)" stroke-width="2"/>
                    <path d="M65 120L85 95L105 105L125 75L135 85" stroke="var(--color-info)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M65 120L85 95L105 105L125 75L135 85V120H65Z" fill="color-mix(in srgb, var(--color-info) 10%, transparent)"/>
                    <circle cx="85" cy="95" r="3" fill="var(--color-info)"/>
                    <circle cx="105" cy="105" r="3" fill="var(--color-info)"/>
                    <circle cx="125" cy="75" r="3" fill="var(--color-info)"/>
                    <rect x="65" y="67" width="25" height="4" rx="2" fill="color-mix(in srgb, var(--color-muted) 30%, transparent)"/>
                    <rect x="65" y="74" width="40" height="3" rx="1.5" fill="color-mix(in srgb, var(--color-muted) 20%, transparent)"/>
                </svg>
                @break

            @case('search')
                {{-- Search/No results illustration --}}
                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                    <circle cx="100" cy="100" r="90" fill="color-mix(in srgb, var(--color-muted) 6%, transparent)"/>
                    <circle cx="100" cy="100" r="65" fill="color-mix(in srgb, var(--color-muted) 8%, transparent)"/>
                    <circle cx="90" cy="90" r="30" fill="var(--color-surface-2)" stroke="var(--color-muted)" stroke-width="3"/>
                    <line x1="112" y1="112" x2="140" y2="140" stroke="var(--color-muted)" stroke-width="4" stroke-linecap="round"/>
                    <path d="M80 85h20M90 75v20" stroke="color-mix(in srgb, var(--color-muted) 30%, transparent)" stroke-width="2" stroke-linecap="round" opacity="0.5"/>
                </svg>
                @break

            @case('document')
            @case('knowledge')
                {{-- Document/Knowledge base illustration --}}
                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                    <circle cx="100" cy="100" r="90" fill="color-mix(in srgb, var(--color-brand) 6%, transparent)"/>
                    <circle cx="100" cy="100" r="65" fill="color-mix(in srgb, var(--color-brand) 10%, transparent)"/>
                    <rect x="65" y="50" width="55" height="70" rx="6" fill="var(--color-surface-2)" stroke="var(--color-brand)" stroke-width="2"/>
                    <rect x="80" y="60" width="55" height="70" rx="6" fill="var(--color-surface-2)" stroke="color-mix(in srgb, var(--color-brand) 50%, transparent)" stroke-width="2"/>
                    <rect x="90" y="78" width="30" height="3" rx="1.5" fill="color-mix(in srgb, var(--color-brand) 30%, transparent)"/>
                    <rect x="90" y="86" width="35" height="3" rx="1.5" fill="color-mix(in srgb, var(--color-muted) 25%, transparent)"/>
                    <rect x="90" y="94" width="25" height="3" rx="1.5" fill="color-mix(in srgb, var(--color-muted) 20%, transparent)"/>
                    <rect x="90" y="102" width="30" height="3" rx="1.5" fill="color-mix(in srgb, var(--color-muted) 15%, transparent)"/>
                    <circle cx="140" cy="55" r="14" fill="var(--color-brand)" opacity="0.9"/>
                    <path d="M134 55h12M140 49v12" stroke="white" stroke-width="2" stroke-linecap="round"/>
                </svg>
                @break

            @default
                {{-- Default/Generic illustration --}}
                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                    <circle cx="100" cy="100" r="90" fill="color-mix(in srgb, var(--color-brand) 6%, transparent)"/>
                    <circle cx="100" cy="100" r="65" fill="color-mix(in srgb, var(--color-brand) 10%, transparent)"/>
                    <rect x="60" y="65" width="80" height="60" rx="10" fill="var(--color-surface-2)" stroke="color-mix(in srgb, var(--color-brand) 30%, transparent)" stroke-width="2"/>
                    <rect x="75" y="80" width="35" height="4" rx="2" fill="color-mix(in srgb, var(--color-muted) 30%, transparent)"/>
                    <rect x="75" y="90" width="50" height="4" rx="2" fill="color-mix(in srgb, var(--color-muted) 20%, transparent)"/>
                    <rect x="75" y="100" width="25" height="4" rx="2" fill="color-mix(in srgb, var(--color-muted) 15%, transparent)"/>
                    <circle cx="145" cy="62" r="14" fill="var(--color-brand)" opacity="0.9"/>
                    <path d="M139 62h12M145 56v12" stroke="white" stroke-width="2" stroke-linecap="round"/>
                </svg>
                @break
        @endswitch
    </div>

    {{-- Title --}}
    <h3 class="{{ $compact ? 'text-base' : 'text-lg' }} font-semibold text-ink">{{ $title }}</h3>

    {{-- Description --}}
    @if($description)
    <p class="text-sm text-muted mt-1.5 max-w-sm">{{ $description }}</p>
    @endif

    {{-- CTA Button --}}
    @if($actionUrl && $actionLabel)
    <a href="{{ $actionUrl }}" wire:navigate class="btn-primary mt-5 gap-2">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        {{ $actionLabel }}
    </a>
    @endif

    {{-- Optional slot for custom content --}}
    @if(isset($slot) && !$slot->isEmpty())
    <div class="mt-4">
        {{ $slot }}
    </div>
    @endif
</div>
