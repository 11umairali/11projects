@extends('frontend.layout')

@section('title', 'Privacy Policy — ' . config('app.name'))

@php
    $c = $content ?? [];
    $sections = $c['sections'] ?? [];
@endphp

@section('content')
<main class="flex-1 py-32">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">

            {{-- Header --}}
            <div x-data="{ shown: false }" x-init="setTimeout(() => shown = true, 0)"
                 class="space-y-6 mb-16 text-center md:text-left transition-all duration-700 transform"
                 :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'">
                <h1 class="text-4xl md:text-6xl font-bold tracking-tight">Privacy <span class="text-brand italic">Policy</span></h1>
                <p class="text-xl text-muted leading-relaxed max-w-2xl">{{ $c['subtitle'] ?? 'Your privacy is important to us. This policy explains how we collect, use, and protect your data.' }}</p>
                <div class="flex flex-wrap gap-3 justify-center md:justify-start">
                    <div class="flex items-center gap-2 px-3 py-1 bg-surface-2/50 border border-border/50 rounded-full text-[10px] font-bold uppercase tracking-widest text-muted">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Last Updated: {{ $c['last_updated'] ?? '' }}
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1 bg-success/10 border border-success/20 rounded-full text-[10px] font-bold uppercase tracking-widest text-success">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
                        GDPR Compliant
                    </div>
                </div>
            </div>

            {{-- Sections as card grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($sections as $index => $section)
                @if(!empty($section['title']))
                <div x-data="{ shown: false }" x-intersect="shown = true"
                     style="transition-delay: {{ $index * 100 }}ms"
                     class="p-6 md:p-8 rounded-2xl bg-surface-2 border border-border/50 shadow-sm hover:shadow-xl hover:border-brand/20 transition-all group duration-500 transform"
                     :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'">
                    <div class="h-10 w-10 rounded-xl bg-brand/10 border border-brand/20 flex items-center justify-center text-brand mb-5 group-hover:scale-110 transition-transform">
                        <span class="text-sm font-bold">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <h3 class="text-lg font-bold text-ink mb-3">{{ $section['title'] }}</h3>
                    <p class="text-sm text-muted leading-relaxed">{{ $section['content'] ?? '' }}</p>
                </div>
                @endif
                @endforeach
            </div>

            {{-- Bottom text --}}
            @if(!empty($c['bottom_text']))
            <div x-data="{ shown: false }" x-intersect="shown = true"
                 class="mt-16 p-8 md:p-12 rounded-2xl bg-surface-2/50 border border-border/50 text-sm leading-relaxed text-muted transition-all duration-700 transform"
                 :class="shown ? 'opacity-100' : 'opacity-0'">
                <p>{{ $c['bottom_text'] }}</p>
            </div>
            @endif

            {{-- Bottom CTA --}}
            <div class="mt-8 p-8 rounded-2xl bg-surface-2/50 border border-border/50 text-center">
                <p class="text-sm text-muted">
                    {{ __('Questions about your data?') }} <a href="{{ route('contact') }}" class="text-brand font-bold hover:underline">{{ __('Contact our privacy team') }}</a>
                </p>
            </div>
        </div>
    </div>
</main>
@endsection
