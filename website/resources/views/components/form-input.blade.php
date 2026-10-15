@props([
    'name',
    'label' => null,
    'type' => 'text',
    'required' => false,
    'minlength' => null,
    'maxlength' => null,
    'pattern' => null,
    'placeholder' => '',
    'value' => '',
    'help' => null,
    'error' => null,
    'disabled' => false,
    'autocomplete' => null,
    'wireModel' => null,
])

@php
    $inputId = $name . '-' . uniqid();
    $isPassword = $type === 'password';
@endphp

<div
    x-data="formInput({
        name: '{{ $name }}',
        type: '{{ $type }}',
        required: {{ $required ? 'true' : 'false' }},
        minlength: {{ $minlength ?? 'null' }},
        maxlength: {{ $maxlength ?? 'null' }},
        pattern: {{ $pattern ? \"'\" . addslashes($pattern) . \"'\" : 'null' }},
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

    {{-- Input wrapper --}}
    <div class="relative">
        <input
            id="{{ $inputId }}"
            name="{{ $name }}"
            :type="inputType"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            @if($required) required aria-required="true" @endif
            @if($minlength) minlength="{{ $minlength }}" @endif
            @if($maxlength) maxlength="{{ $maxlength }}" @endif
            @if($pattern) pattern="{{ $pattern }}" @endif
            @if($disabled) disabled @endif
            @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if($wireModel) wire:model="{{ $wireModel }}" @endif
            x-ref="input"
            x-on:blur="validate()"
            x-on:input="onInput($event)"
            :aria-invalid="state === 'invalid' ? 'true' : undefined"
            :aria-describedby="(help || errorMessage) ? '{{ $inputId }}-desc' : undefined"
            {{ $attributes->class([
                'input w-full',
                'pr-10' => !$isPassword && !$maxlength,
                'pr-20' => $isPassword,
                'pr-16' => $maxlength && !$isPassword,
            ]) }}
            :class="{
                '!border-success/50 focus:!ring-success/30': state === 'valid',
                '!border-danger/50 focus:!ring-danger/30': state === 'invalid',
            }"
        >

        {{-- Validation state icon --}}
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3" x-cloak>
            {{-- Valid check --}}
            <template x-if="state === 'valid'">
                <svg class="h-4 w-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </template>
            {{-- Invalid X --}}
            <template x-if="state === 'invalid'">
                <svg class="h-4 w-4 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </template>
        </div>

        {{-- Password visibility toggle --}}
        @if($isPassword)
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted hover:text-ink transition-colors"
                x-on:click="togglePasswordVisibility()"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
            >
                <template x-if="!showPassword">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </template>
                <template x-if="showPassword">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </template>
            </button>
        @endif
    </div>

    {{-- Password strength meter --}}
    @if($isPassword)
        <div x-show="passwordStrength.score > 0" x-cloak class="mt-2">
            <div class="flex gap-1">
                <template x-for="i in 4" :key="i">
                    <div
                        class="h-1 flex-1 rounded-full transition-colors duration-200"
                        :class="{
                            'bg-border': i > passwordStrength.score,
                            'bg-danger': i <= passwordStrength.score && passwordStrength.score === 1,
                            'bg-warning': i <= passwordStrength.score && passwordStrength.score === 2,
                            'bg-success/70': i <= passwordStrength.score && passwordStrength.score === 3,
                            'bg-success': i <= passwordStrength.score && passwordStrength.score === 4,
                        }"
                    ></div>
                </template>
            </div>
            <p
                class="mt-1 text-xs font-medium"
                :class="{
                    'text-danger': passwordStrength.score === 1,
                    'text-warning': passwordStrength.score === 2,
                    'text-success/70': passwordStrength.score === 3,
                    'text-success': passwordStrength.score === 4,
                }"
                x-text="passwordStrength.label"
                aria-live="polite"
            ></p>
        </div>
    @endif

    {{-- Character counter --}}
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
        {{-- Server error from @error --}}
        @error($name)
            <p class="mt-1.5 text-xs text-danger" role="alert">{{ $message }}</p>
        @enderror

        {{-- Client-side validation error --}}
        <p
            x-show="state === 'invalid' && errorMessage"
            x-text="errorMessage"
            x-cloak
            class="mt-1.5 text-xs text-danger"
            role="alert"
        ></p>

        {{-- Helper text --}}
        @if($help)
            <p class="mt-1.5 text-xs text-muted">{{ $help }}</p>
        @endif
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        if (Alpine.data.__form_input_registered) return;
        Alpine.data.__form_input_registered = true;

        Alpine.data('formInput', (config) => ({
            state: 'neutral',
            errorMessage: '',
            charCount: 0,
            showPassword: false,
            inputType: config.type,
            passwordStrength: { score: 0, label: '' },

            init() {
                const val = this.$refs.input?.value || '';
                this.charCount = val.length;

                if (config.serverError) {
                    this.state = 'invalid';
                    this.errorMessage = config.serverError;
                }
            },

            onInput(e) {
                this.charCount = e.target.value.length;

                if (config.type === 'password') {
                    this.calculatePasswordStrength(e.target.value);
                }

                // If already validated (invalid), re-validate on input for immediate feedback
                if (this.state === 'invalid') {
                    this.validate();
                }
            },

            validate() {
                const input = this.$refs.input;
                if (!input) return;
                const value = input.value.trim();

                // Empty non-required field = neutral
                if (!value && !config.required) {
                    this.state = 'neutral';
                    this.errorMessage = '';
                    return;
                }

                // Required check
                if (config.required && !value) {
                    this.state = 'invalid';
                    this.errorMessage = 'This field is required';
                    return;
                }

                // Minlength
                if (config.minlength && value.length < config.minlength) {
                    this.state = 'invalid';
                    this.errorMessage = `Must be at least ${config.minlength} characters`;
                    return;
                }

                // Maxlength
                if (config.maxlength && value.length > config.maxlength) {
                    this.state = 'invalid';
                    this.errorMessage = `Must be no more than ${config.maxlength} characters`;
                    return;
                }

                // Pattern
                if (config.pattern) {
                    const regex = new RegExp(config.pattern);
                    if (!regex.test(value)) {
                        this.state = 'invalid';
                        this.errorMessage = 'Please match the requested format';
                        return;
                    }
                }

                // Type-specific validation
                if (config.type === 'email') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        this.state = 'invalid';
                        this.errorMessage = 'Please enter a valid email address';
                        return;
                    }
                }

                if (config.type === 'url') {
                    try {
                        new URL(value);
                    } catch {
                        this.state = 'invalid';
                        this.errorMessage = 'Please enter a valid URL';
                        return;
                    }
                }

                if (config.type === 'tel') {
                    const telRegex = /^[\d\s\-+().]{7,}$/;
                    if (!telRegex.test(value)) {
                        this.state = 'invalid';
                        this.errorMessage = 'Please enter a valid phone number';
                        return;
                    }
                }

                this.state = 'valid';
                this.errorMessage = '';
            },

            togglePasswordVisibility() {
                this.showPassword = !this.showPassword;
                this.inputType = this.showPassword ? 'text' : 'password';
            },

            calculatePasswordStrength(password) {
                if (!password) {
                    this.passwordStrength = { score: 0, label: '' };
                    return;
                }

                let score = 0;
                if (password.length >= 8) score++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
                if (/\d/.test(password)) score++;
                if (/[^a-zA-Z0-9]/.test(password)) score++;

                const labels = ['', 'Weak', 'Fair', 'Strong', 'Very strong'];
                this.passwordStrength = { score, label: labels[score] };
            },
        }));
    });
</script>
