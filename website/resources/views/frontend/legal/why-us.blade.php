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
                <h1 class="text-4xl md:text-6xl font-bold tracking-tight">Why <span class="text-brand italic">Choose Us</span></h1>
                @if(!empty($c['subtitle']))
                <p class="text-xl text-muted leading-relaxed max-w-2xl mx-auto">{{ $c['subtitle'] }}</p>
                @endif
            </div>

            @if(!empty($c['reasons']) && is_array($c['reasons']))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                @foreach($c['reasons'] as $i => $reason)
                @if(!empty($reason['title']))
                <div x-data="{ shown: false }" x-intersect="shown = true"
                     style="transition-delay: {{ $i * 100 }}ms"
                     class="p-8 rounded-[2rem] bg-surface-2 border border-border shadow-sm hover:shadow-xl transition-all group transform"
                     :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'">
                    <div class="h-10 w-10 rounded-xl bg-brand/10 text-brand flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <span class="text-lg font-bold">{{ $i + 1 }}</span>
                    </div>
                    <h3 class="text-xl font-bold mb-3">{{ $reason['title'] }}</h3>
                    <p class="text-muted leading-relaxed text-sm">{{ $reason['desc'] ?? '' }}</p>
                </div>
                @endif
                @endforeach
            </div>
            @endif
        </div>
    </div>
</main>
@endsection
