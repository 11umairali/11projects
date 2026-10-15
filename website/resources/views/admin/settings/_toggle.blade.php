{{--
    Toggle switch partial for settings pages.

    @param string $name       - Form input name
    @param string $label      - Toggle title
    @param string $description - Short description
    @param string $value      - Current value ('true'/'false')
    @param string $color      - Tailwind color name (green, blue, indigo, violet, red)
    @param string $icon       - SVG path data
--}}
@php
    $isOn = filter_var($value ?? false, FILTER_VALIDATE_BOOLEAN);

    $colorMap = [
        'green'  => ['bg' => 'bg-green-100 dark:bg-green-900/30',  'text' => 'text-green-600 dark:text-green-400',  'border' => 'border-green-200 dark:border-green-800', 'from' => 'from-green-50 dark:from-green-950/30', 'to' => 'to-emerald-50 dark:to-emerald-950/30', 'checked' => 'peer-checked:bg-green-500'],
        'blue'   => ['bg' => 'bg-blue-100 dark:bg-blue-900/30',    'text' => 'text-blue-600 dark:text-blue-400',    'border' => 'border-blue-200 dark:border-blue-800',   'from' => 'from-blue-50 dark:from-blue-950/30',  'to' => 'to-indigo-50 dark:to-indigo-950/30',  'checked' => 'peer-checked:bg-blue-500'],
        'indigo' => ['bg' => 'bg-indigo-100 dark:bg-indigo-900/30','text' => 'text-indigo-600 dark:text-indigo-400','border' => 'border-indigo-200 dark:border-indigo-800','from' => 'from-indigo-50 dark:from-indigo-950/30','to' => 'to-purple-50 dark:to-purple-950/30','checked' => 'peer-checked:bg-indigo-500'],
        'violet' => ['bg' => 'bg-violet-100 dark:bg-violet-900/30','text' => 'text-violet-600 dark:text-violet-400','border' => 'border-violet-200 dark:border-violet-800','from' => 'from-violet-50 dark:from-violet-950/30','to' => 'to-purple-50 dark:to-purple-950/30','checked' => 'peer-checked:bg-violet-500'],
        'red'    => ['bg' => 'bg-red-100 dark:bg-red-900/30',      'text' => 'text-red-600 dark:text-red-400',      'border' => 'border-red-200 dark:border-red-800',     'from' => 'from-red-50 dark:from-red-950/30',    'to' => 'to-orange-50 dark:to-orange-950/30',  'checked' => 'peer-checked:bg-red-500'],
    ];
    $c = $colorMap[$color ?? 'green'] ?? $colorMap['green'];
@endphp

<div class="flex items-center justify-between p-4 bg-gradient-to-r {{ $c['from'] }} {{ $c['to'] }} border {{ $c['border'] }} rounded-xl">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 {{ $c['bg'] }} rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 {{ $c['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
        </div>
        <div>
            <h3 class="text-sm font-bold text-ink">{{ $label }}</h3>
            <p class="text-xs text-muted">{{ $description }}</p>
        </div>
    </div>
    <label class="relative inline-flex items-center cursor-pointer">
        <input type="hidden" name="{{ $name }}" value="false">
        <input type="checkbox" name="{{ $name }}" value="true" class="sr-only peer" {{ $isOn ? 'checked' : '' }}>
        <div class="w-12 h-7 bg-gray-200 dark:bg-gray-700 peer-focus:ring-2 peer-focus:ring-brand/20 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all after:shadow-sm {{ $c['checked'] }}"></div>
    </label>
</div>
