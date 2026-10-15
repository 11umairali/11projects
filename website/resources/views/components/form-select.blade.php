@props([
    'name',
    'label' => null,
    'required' => false,
    'options' => [],
    'placeholder' => 'Select an option',
    'value' => '',
    'help' => null,
    'error' => null,
    'disabled' => false,
    'wireModel' => null,
])

@php
    $inputId = $name . '-' . uniqid();
@endphp

<div
    x-data="formSelect({
        name: '{{ $name }}',
        required: {{ $required ? 'true' : 'false' }},
        serverError: {{ $error ? \"'\" . addslashes($error) . \"'\" : 'null' }},
    })"
    class="w-full"
>
    {{-- Label --}}
    @if($label)
        <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-ink">
            {{ $label }}
            @if($required)
                <span class="text-danger ml-0.5" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{-- Select --}}
    <div class="relative">
        <select
            id="{{ $inputId }}"
            name="{{ $name }}"
            @if($required) required aria-required="true" @endif
            @if($disabled) disabled @endif
            @if($wireModel) wire:model="{{ $wireModel }}" @endif
            x-ref="select"
            x-on:change="validate()"
            x-on:blur="validate()"
            :aria-invalid="state === 'invalid' ? 'true' : undefined"
            :aria-describedby="(help || errorMessage) ? '{{ $inputId }}-desc' : undefined"
            {{ $attributes->class(['select w-full']) }}
            :class="{
                '!border-success/50 focus:!ring-success/30': state === 'valid',
                '!border-danger/50 focus:!ring-danger/30': state === 'invalid',
            }"
        >
            @if($placeholder)
                <option value="" disabled {{ !$value ? 'selected' : '' }}>{{ $placeholder }}</option>
            @endif

            @foreach($options as $optionValue => $optionLabel)
                @if(is_array($optionLabel))
                    {{-- Option group --}}
                    <optgroup label="{{ $optionValue }}">
                        @foreach($optionLabel as $groupValue => $groupLabel)
                            <option value="{{ $groupValue }}" {{ (string)$value === (string)$groupValue ? 'selected' : '' }}>
                                {{ $groupLabel }}
                            </option>
                        @endforeach
                    </optgroup>
                @else
                    <option value="{{ $optionValue }}" {{ (string)$value === (string)$optionValue ? 'selected' : '' }}>
                        {{ $optionLabel }}
                    </option>
                @endif
            @endforeach

            {{ $slot }}
        </select>

        {{-- Validation state icon (positioned before the chevron) --}}
        <div class="pointer-events-none absolute inset-y-0 right-8 flex items-center" x-cloak>
            <template x-if="state === 'valid'">
                <svg class="h-4 w-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </template>
            <template x-if="state === 'invalid'">
                <svg class="h-4 w-4 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </template>
        </div>
    </div>

    {{-- Description / error area --}}
    <div id="{{ $inputId }}-desc">
        @error($name)
            <p class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>
        @enderror

        <p
            x-show="state === 'invalid' && errorMessage"
            x-text="errorMessage"
            x-cloak
            class="mt-1.5 text-xs text-danger"
            role="alert"
        ></p>

        @if($help)
            <p class="mt-1.5 text-xs text-muted">{{ $help }}</p>
        @endif
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        if (Alpine.data.__form_select_registered) return;
        Alpine.data.__form_select_registered = true;

        Alpine.data('formSelect', (config) => ({
            state: 'neutral',
            errorMessage: '',

            init() {
                if (config.serverError) {
                    this.state = 'invalid';
                    this.errorMessage = config.serverError;
                }
            },

            validate() {
                const el = this.$refs.select;
                if (!el) return;
                const value = el.value;

                if (!value && !config.required) {
                    this.state = 'neutral';
                    this.errorMessage = '';
                    return;
                }

                if (config.required && !value) {
                    this.state = 'invalid';
                    this.errorMessage = 'Please select an option';
                    return;
                }

                this.state = 'valid';
                this.errorMessage = '';
            },
        }));
    });
</script>
