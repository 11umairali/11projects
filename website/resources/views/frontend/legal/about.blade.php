@extends('frontend.layout')

@section('title', ($content['title']) . ' — ' . config('app.name'))

@php $c = $content ?? []; @endphp

@section('content')
<main class="flex-1 pt-32 pb-20">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
            <div x-data="{ shown: false }" x-init="setTimeout(() => shown = true, 0)"
                 class="space-y-6 mb-16 text-center transition-all duration-700 transform"
                 :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'">
                <h1 class="text-4xl md:text-6xl font-bold tracking-tight">{{ $c['title'] }} <span class="text-brand italic">Us</span></h1>
                @if(!empty($c['subtitle']))
                <p class="text-xl text-muted leading-relaxed max-w-2xl mx-auto">{{ $c['subtitle'] }}</p>
                @endif
            </div>

            @if(!empty($c['story']))
            <div class="prose prose-lg max-w-none mb-16">
                <p class="text-muted leading-relaxed text-base">{{ $c['story'] }}</p>
            </div>
            @endif

            @if(!empty($c['stats']) && is_array($c['stats']))
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-16">
                @foreach($c['stats'] as $stat)
                @if(!empty($stat['value']))
                <div class="text-center p-6 rounded-2xl bg-surface-2 border border-border/50">
                    <div class="text-3xl font-extrabold text-brand">{{ $stat['value'] }}</div>
                    <div class="text-sm text-muted mt-1">{{ $stat['label'] ?? '' }}</div>
                </div>
                @endif
                @endforeach
            </div>
            @endif

            @if(!empty($c['mission']))
            <div class="p-8 md:p-12 rounded-[2.5rem] bg-surface-2/30 border border-border/50 text-center">
                <h2 class="text-xl font-bold text-ink mb-4">{{ __("Our Mission") }}</h2>
                <p class="text-muted leading-relaxed">{{ $c['mission'] }}</p>
            </div>
            @endif
        </div>
    </div>
</main>
@endsection
