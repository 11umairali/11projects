@props([
    'name',
    'label' => null,
    'required' => false,
    'minlength' => null,
    'maxlength' => null,
    'placeholder' => '',
    'value' => '',
    'help' => null,
    'error' => null,
    'disabled' => false,
    'rows' => 3,
    'autoResize' => true,
    'wireModel' => null,
])

@php
    $inputId = $name . '-' . uniqid();
@endphp

<div
    x-data="formTextarea({
        name: '{{ $name }}',
        required: {{ $required ? 'true' : 'false' }},
        minlength: {{ $minlength ?? 'null' }},
        maxlength: {{ $maxlength ?? 'null' }},
        autoResize: {{ $autoResize ? 'true' : 'false' }},
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

    {{-- Textarea --}}
    <div class="relative">
        <textarea
            id="{{ $inputId }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}"
            @if($required) required aria-required="true" @endif
            @if($minlength) minlength="{{ $minlength }}" @endif
            @if($maxlength) maxlength="{{ $maxlength }}" @endif
            @if($disabled) disabled @endif
            @if($wireModel) wire:model="{{ $wireModel }}" @endif
            x-ref="textarea"
            x-on:blur="validate()"
            x-on:input="onInput($event)"
            :aria-invalid="state === 'invalid' ? 'true' : undefined"
            :aria-describedby="(help || errorMessage) ? '{{ $inputId }}-desc' : undefined"
            {{ $attributes->class(['textarea w-full']) }}
            :class="{
                '!border-success/50 focus:!ring-success/30': state === 'valid',
                '!border-danger/50 focus:!ring-danger/30': state === 'invalid',
            }"
            @if($autoResize) style="overflow: hidden; resize: none;" @endif
        >{{ $value }}</textarea>

        {{-- Validation state icon --}}
        <div class="pointer-events-none absolute top-3 right-3" x-cloak>
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

    {{-- Footer: character counter --}}
    @if($maxlength)
        <div class="mt-1 flex justify-end">
            <span
                class="text-xs tabular-nums"
                :class="charCount > {{ $maxlength }} ? 'text-danger font-medium' : 'text-muted'"
                x-text="charCount + ' / {{ $maxlength }}'"
                aria-live="polite"
            ></span>
        </div>
    @endif

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
        if (Alpine.data.__form_textarea_registered) return;
        Alpine.data.__form_textarea_registered = true;

        Alpine.data('formTextarea', (config) => ({
            state: 'neutral',
            errorMessage: '',
            charCount: 0,

            init() {
                const el = this.$refs.textarea;
                if (!el) return;

                this.charCount = el.value.length;

                if (config.serverError) {
                    this.state = 'invalid';
                    this.errorMessage = config.serverError;
                }

                if (config.autoResize) {
                    this.$nextTick(() => this.resize());
                }
            },

            onInput(e) {
                this.charCount = e.target.value.length;

                if (config.autoResize) {
                    this.resize();
                }

                if (this.state === 'invalid') {
                    this.validate();
                }
            },

            resize() {
                const el = this.$refs.textarea;
                if (!el) return;
                el.style.height = 'auto';
                el.style.height = el.scrollHeight + 'px';
            },

            validate() {
                const el = this.$refs.textarea;
                if (!el) return;
                const value = el.value.trim();

                if (!value && !config.required) {
                    this.state = 'neutral';
                    this.errorMessage = '';
                    return;
                }

                if (config.required && !value) {
                    this.state = 'invalid';
                    this.errorMessage = 'This field is required';
                    return;
                }

                if (config.minlength && value.length < config.minlength) {
                    this.state = 'invalid';
                    this.errorMessage = `Must be at least ${config.minlength} characters`;
                    return;
                }

                if (config.maxlength && value.length > config.maxlength) {
                    this.state = 'invalid';
                    this.errorMessage = `Must be no more than ${config.maxlength} characters`;
                    return;
                }

                this.state = 'valid';
                this.errorMessage = '';
            },
        }));
    });
</script>
