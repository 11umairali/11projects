@php($__phoneMode = \App\Support\PhoneSettings::registrationMode())
@if($__phoneMode !== 'off')
    {{-- Phone number: pick the country, then type only the number (no example format shown) --}}
    <div>
        <label for="phone_national" class="block text-sm font-medium text-muted mb-1">
            {{ __('Phone number') }} @if($__phoneMode !== 'required')<span class="text-xs">({{ __('optional') }})</span>@endif
        </label>
        <div class="flex gap-2">
            <select name="phone_country" aria-label="{{ __('Country code') }}" class="input w-40 flex-shrink-0"
                    x-data x-init="if (!$el.value) { const r = ((navigator.language || '').split('-')[1] || '').toUpperCase(); if (r && [...$el.options].some(o => o.value === r)) $el.value = r; }">
                <option value="">{{ __('Country code') }}</option>
                @foreach(config('phone_countries', []) as $__c)
                    <option value="{{ $__c['iso'] }}" @selected(old('phone_country') === $__c['iso'])>{{ $__c['flag'] }} {{ $__c['name'] }} ({{ $__c['dial'] }})</option>
                @endforeach
            </select>
            <input id="phone_national" type="tel" name="phone_national" value="{{ old('phone_national') }}" inputmode="tel" autocomplete="tel-national"
                   placeholder="{{ __('Phone number') }}" @if($__phoneMode === 'required') required @endif
                   class="input flex-1 min-w-0 @error('phone_national') !border-danger @enderror">
        </div>
        @error('phone_national') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        <p class="mt-1 text-xs text-muted">{{ __('Choose your country, then type your number without the country code.') }}</p>
    </div>
@endif
