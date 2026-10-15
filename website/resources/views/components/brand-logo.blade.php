@props(['name' => config('app.name', 'Dahimail')])
<span {{ $attributes->class(['inline-flex items-center gap-3']) }}>
    <img src="{{ asset('images/dahimail-logo.png') }}" alt="{{ $name }}" width="40" height="40" class="h-10 w-10 shrink-0 rounded-lg">
    <span class="text-xl font-semibold leading-none">{{ $name }}</span>
</span>
