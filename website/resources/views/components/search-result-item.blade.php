@props([
    'type' => 'default',
    'title' => '',
    'subtitle' => '',
    'url' => '#',
    'timestamp' => null,
    'meta' => null,
    'highlighted' => false,
])

{{--
    Search Result Item
    ──────────────────
    A single result row for the global search results page.

    Usage:
    <x-search-result-item
        type="contact"
        title="Jane Smith"
        subtitle="jane@acme.com"
        meta="Acme Corp"
        url="/contacts/42"
        timestamp="2 hours ago"
    />
--}}
@php
    $typeConfig = match($type) {
        'contact'      => ['bg' => 'bg-info/10',    'text' => 'text-info',    'label' => 'Contact'],
        'conversation' => ['bg' => 'bg-success/10', 'text' => 'text-success', 'label' => 'Conversation'],
        'message'      => ['bg' => 'bg-brand/10',   'text' => 'text-brand',   'label' => 'Message'],
        'campaign'     => ['bg' => 'bg-warning/10', 'text' => 'text-warning', 'label' => 'Campaign'],
        'deal'         => ['bg' => 'bg-accent/10',  'text' => 'text-accent',  'label' => 'Deal'],
        'document'     => ['bg' => 'bg-muted/10',   'text' => 'text-muted',   'label' => 'KB Article'],
        'workflow'     => ['bg' => 'bg-brand/10',   'text' => 'text-brand',   'label' => 'Workflow'],
        default        => ['bg' => 'bg-muted/10',   'text' => 'text-muted',   'label' => ucfirst($type)],
    };
@endphp

<a
    href="{{ $url }}"
    wire:navigate
    class="group flex items-start gap-4 rounded-xl border border-border bg-surface-2/80 p-4 transition-all duration-150 hover:border-brand/30 hover:bg-brand/[0.03] hover:shadow-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 {{ $highlighted ? 'border-brand/20 bg-brand/[0.02]' : '' }}"
    role="listitem"
>
    {{-- Type icon --}}
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $typeConfig['bg'] }} {{ $typeConfig['text'] }} transition-colors group-hover:ring-2 group-hover:ring-brand/10">
        @switch($type)
            @case('contact')
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                @break
            @case('conversation')
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                @break
            @case('message')
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                @break
            @case('campaign')
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                @break
            @case('deal')
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @break
            @case('document')
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                @break
            @case('workflow')
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                @break
            @default
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        @endswitch
    </div>

    {{-- Content --}}
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            <h4 class="text-sm font-semibold text-ink truncate group-hover:text-brand transition-colors">{{ $title }}</h4>
            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ $typeConfig['bg'] }} {{ $typeConfig['text'] }}">
                {{ $typeConfig['label'] }}
            </span>
        </div>
        @if($subtitle)
        <p class="mt-0.5 text-xs text-muted truncate">{{ $subtitle }}</p>
        @endif
        <div class="mt-1.5 flex items-center gap-3 text-xs text-muted">
            @if($meta)
            <span>{{ $meta }}</span>
            @endif
            @if($timestamp)
            <span class="flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $timestamp }}
            </span>
            @endif
        </div>
    </div>

    {{-- Arrow --}}
    <div class="shrink-0 self-center text-muted opacity-0 transition-opacity group-hover:opacity-100">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </div>
</a>
