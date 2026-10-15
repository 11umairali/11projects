<div class="space-y-6">
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-lg font-semibold text-ink">{{ __('Phone number & discovery') }}</h2>
        <p class="text-sm text-muted mt-1">{{ __('Add your phone number. If verification is available you can also let people who already have your number saved find you here, like in other chat apps. Nothing is shared until you verify your number and switch discovery on.') }}</p>
    </div>

    @if($message)
        <div class="rounded-xl border px-4 py-3 text-sm {{ $ok ? 'border-green-300 bg-green-50 text-green-800' : 'border-red-300 bg-red-50 text-red-800' }}">{{ $message }}</div>
    @endif

    @if(!($status['verification_enabled'] ?? false))
        <div class="rounded-xl border border-border bg-surface-2 px-4 py-3 text-sm text-muted">
            {{ ($status['unverified_discovery'] ?? false) ? __('Number verification is switched off, so your number is saved without a code. Friends can still find you if you turn discovery on; they will see that your number is not verified.') : __('Number verification is switched off, so your number is saved without a code. Friend discovery needs a verified number, so it is not available right now.') }}
        </div>
    @endif

    <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-4">
        @if(($status['has_number'] ?? false) && !($status['verified'] ?? false) && !($status['pending'] ?? false) && ($status['unverified_discovery'] ?? false))
            <div class="flex items-center justify-between gap-3">
                <div><div class="text-xs text-muted">{{ __('Saved number') }}</div><div class="font-medium text-ink">{{ $status['number'] }} <span class="text-xs text-muted">· {{ __('not verified') }}</span></div></div>
                <button type="button" wire:click="removeNumber" wire:confirm="{{ __('Remove your phone number?') }}" class="text-sm text-muted hover:text-red-600">{{ __('Remove') }}</button>
            </div>
            <div class="border-t border-border pt-4 flex items-start justify-between gap-4">
                <div>
                    <div class="font-medium text-ink">{{ __('Let people who have my number find me') }}</div>
                    <div class="text-sm text-muted">{{ __('Friends will see that your number is not verified. They see your name and photo; your email stays private until you accept.') }}</div>
                </div>
                @if($status['discoverable'])
                    <button type="button" wire:click="setDiscoverable(false)" class="btn-secondary flex-shrink-0">{{ __('Turn off') }}</button>
                @else
                    <button type="button" wire:click="setDiscoverable(true)" class="btn-primary flex-shrink-0">{{ __('Turn on') }}</button>
                @endif
            </div>
        @elseif(($status['has_number'] ?? false) && !($status['verified'] ?? false) && !($status['pending'] ?? false) && !($status['verification_enabled'] ?? false))
            <div class="flex items-center justify-between gap-3">
                <div><div class="text-xs text-muted">{{ __('Saved number') }}</div><div class="font-medium text-ink">{{ $status['number'] }} <span class="text-xs text-muted">· {{ __('not verified') }}</span></div></div>
                <button type="button" wire:click="removeNumber" wire:confirm="{{ __('Remove your phone number?') }}" class="text-sm text-muted hover:text-red-600">{{ __('Remove') }}</button>
            </div>
        @endif

        @if($status['verified'] ?? false)
            <div class="flex items-center justify-between gap-3">
                <div><div class="text-xs text-muted">{{ __('Verified number') }}</div><div class="font-medium text-ink">{{ $status['number'] }}</div></div>
                <button type="button" wire:click="removeNumber" wire:confirm="{{ __('Remove your phone number?') }}" class="text-sm text-muted hover:text-red-600">{{ __('Remove') }}</button>
            </div>
            <div class="border-t border-border pt-4 flex items-start justify-between gap-4">
                <div>
                    <div class="font-medium text-ink">{{ __('Let people who have my number find me') }}</div>
                    <div class="text-sm text-muted">{{ __('They see your name and photo and can send you a request. Your email stays private until you accept.') }}</div>
                </div>
                @if($status['discoverable'])
                    <button type="button" wire:click="setDiscoverable(false)" class="btn-secondary flex-shrink-0">{{ __('Turn off') }}</button>
                @else
                    <button type="button" wire:click="setDiscoverable(true)" class="btn-primary flex-shrink-0">{{ __('Turn on') }}</button>
                @endif
            </div>
        @else
            <div>
                <label class="block text-sm font-medium text-ink mb-1">{{ ($status['has_number'] ?? false) ? __('Change phone number') : __('Your phone number') }}</label>
                <div class="flex flex-wrap gap-2">
                    <select wire:model="country" aria-label="{{ __('Country code') }}" class="rounded-xl border border-border bg-surface px-3 py-2 text-sm w-48">
                        <option value="">{{ __('Country code') }}</option>
                        @foreach(config('phone_countries', []) as $c)
                            <option value="{{ $c['iso'] }}">{{ $c['flag'] }} {{ $c['name'] }} ({{ $c['dial'] }})</option>
                        @endforeach
                    </select>
                    <input type="tel" inputmode="tel" wire:model="national" placeholder="{{ __('Phone number') }}" class="flex-1 min-w-[10rem] rounded-xl border border-border bg-surface px-3 py-2 text-sm">
                </div>
                <p class="text-xs text-muted mt-1">{{ __('Choose your country, then type your number without the country code.') }}</p>
            </div>

            @if(count($status['channels'] ?? []) > 1)
                <div>
                    <div class="text-sm font-medium text-ink mb-1">{{ __('Send my code by') }}</div>
                    <div class="flex gap-4 text-sm">
                        @foreach($status['channels'] as $ch)
                            <label class="inline-flex items-center gap-2"><input type="radio" wire:model="channel" value="{{ $ch }}"> {{ $ch === 'sms' ? 'SMS' : 'WhatsApp' }}</label>
                        @endforeach
                    </div>
                </div>
            @endif

            <button type="button" wire:click="saveNumber" class="btn-primary">
                {{ ($status['verification_enabled'] ?? false) ? __('Send code') : __('Save number') }}
            </button>

            @if(($status['pending'] ?? false) && ($status['verification_enabled'] ?? false))
                <div class="border-t border-border pt-4">
                    <label class="block text-sm font-medium text-ink mb-1">{{ __('Verification code') }}</label>
                    <div class="flex gap-2">
                        <input type="text" inputmode="numeric" maxlength="6" wire:model="code" class="w-40 rounded-xl border border-border bg-surface px-3 py-2 text-sm tracking-widest">
                        <button type="button" wire:click="verify" class="btn-primary">{{ __('Verify') }}</button>
                    </div>
                </div>
            @endif
        @endif
    </div>

    <p class="text-xs text-muted">{{ __('How discovery works: if someone saved your number in their contacts, they are told you are here and can send a request, but only if your number is verified and you turned discovery on. You can turn it off or remove your number at any time.') }}</p>
</div>
