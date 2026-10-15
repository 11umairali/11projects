{{--
    Integration section partial for the Integrations tab.

    @param string $id          - Unique identifier (google, stripe, etc.)
    @param string $title       - Display title
    @param string $enabled_key - SystemSetting key for the enabled toggle
    @param array  $fields      - Array of field definitions [name, label, type, placeholder]
    @param string $help        - Help text (HTML allowed)
--}}
@php
    use App\Models\SystemSetting;
    $isEnabled = SystemSetting::enabled($enabled_key ?? '', false);
@endphp

<div x-data="{ open: {{ $isEnabled ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-bold text-ink uppercase tracking-wider">{{ $title }}</h3>
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="hidden" name="{{ $enabled_key }}" value="false">
            <input type="checkbox" name="{{ $enabled_key }}" value="true" class="sr-only peer" x-model="open" {{ $isEnabled ? 'checked' : '' }}>
            <div class="w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:ring-2 peer-focus:ring-brand/20 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all after:shadow-sm peer-checked:bg-brand"></div>
        </label>
    </div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
        <div class="grid grid-cols-1 sm:grid-cols-{{ min(count($fields), 3) }} gap-4">
            @foreach($fields as $field)
            <div>
                <label class="settings-label">{{ $field['label'] }}</label>
                @if(($field['type'] ?? 'text') === 'password')
                    @php $hasValue = !empty(SystemSetting::get($field['name'])); @endphp
                    <input type="password"
                           name="{{ $field['name'] }}"
                           value=""
                           placeholder="{{ $hasValue ? '••••••••••••' : ($field['placeholder'] ?? '') }}"
                           class="settings-input font-mono text-xs">
                @else
                    <input type="{{ $field['type'] ?? 'text' }}"
                           name="{{ $field['name'] }}"
                           value="{{ SystemSetting::get($field['name']) }}"
                           placeholder="{{ $field['placeholder'] ?? '' }}"
                           class="settings-input font-mono text-xs">
                @endif
            </div>
            @endforeach
        </div>
        @if(!empty($help))
        <p class="text-xs text-muted mt-2">{!! $help !!}</p>
        @endif
    </div>
</div>
