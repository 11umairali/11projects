{{--
    Breadcrumb Component
    --------------------
    Responsive breadcrumb navigation with proper ARIA semantics.

    Usage:
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Contacts', 'url' => '/contacts'],
            ['label' => 'John Doe'],
        ]" />

    Props:
        items - Array of ['label' => string, 'url' => string (optional)]
               The last item is treated as the current page (rendered bold, no link).

    Accessibility:
        - <nav> with aria-label="Breadcrumb"
        - <ol> with schema.org BreadcrumbList structured data
        - aria-current="page" on the last item
        - Chevron separators are aria-hidden

    Responsive:
        - On mobile (< 640px), middle items collapse to "..." when there are 4+ items
        - First, second-to-last, and last items always visible
--}}

@props([
    'items' => [],
])

@if(count($items) > 0)
<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol class="breadcrumb flex-wrap" itemscope itemtype="https://schema.org/BreadcrumbList">
        @foreach($items as $i => $item)
            @php
                $isLast = $loop->last;
                $isFirst = $loop->first;
                $total = count($items);
                // On mobile, collapse middle items when 4+ items exist
                // Show: first, second-to-last, last. Hide everything between.
                $isCollapsible = $total >= 4 && !$isFirst && !$isLast && $i !== ($total - 2);
                $position = $i + 1;
            @endphp

            {{-- Collapsed ellipsis (shown once before the second-to-last item on mobile) --}}
            @if($total >= 4 && $i === 1)
                <li class="flex items-center gap-2 sm:hidden" aria-hidden="true">
                    <span class="text-muted/60 text-xs">...</span>
                    <svg class="w-3.5 h-3.5 text-muted/50 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </li>
            @endif

            <li
                class="flex items-center gap-2 {{ $isCollapsible ? 'hidden sm:flex' : '' }}"
                itemprop="itemListElement"
                itemscope
                itemtype="https://schema.org/ListItem"
            >
                @if(!$isFirst)
                    <svg class="w-3.5 h-3.5 text-muted/50 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                @endif

                @if($isLast)
                    <span
                        class="breadcrumb-current truncate max-w-[200px]"
                        aria-current="page"
                        itemprop="name"
                    >{{ $item['label'] }}</span>
                @else
                    <a
                        href="{{ $item['url'] ?? '#' }}"
                        wire:navigate
                        class="truncate max-w-[200px]"
                        itemprop="item"
                    >
                        <span itemprop="name">{{ $item['label'] }}</span>
                    </a>
                @endif

                <meta itemprop="position" content="{{ $position }}">
            </li>
        @endforeach
    </ol>
</nav>
@endif
