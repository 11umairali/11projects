<x-layouts.admin :title="__('Phone Verification')" :subtitle="__('Control phone numbers at sign-up and how they are verified.')">
    <div class="space-y-6 max-w-3xl">
        @if(session('success'))
            <div class="rounded-xl border border-green-300 bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-red-300 bg-red-50 text-red-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- What is happening right now --}}
        <div class="panel p-5">
            <div class="text-sm font-semibold text-ink mb-1">{{ __('Current behaviour') }}</div>
            @if(!$verification)
                <p class="text-sm text-muted">{{ __('Verification is OFF: people can register and add a phone number WITHOUT any SMS or WhatsApp code. Numbers saved this way are marked unverified and are not used for friend discovery (otherwise anyone could type another person\'s number to see who has it saved).') }}</p>
            @elseif(empty($channels))
                <p class="text-sm text-amber-700">{{ __('Verification is switched ON but no sending method is ready, so it behaves as OFF. Enable SMS and/or WhatsApp below and make sure its credentials are saved in Settings → Integrations.') }}</p>
            @else
                <p class="text-sm text-muted">{{ __('Verification is ON: people confirm their number with a code sent by :c. Only verified numbers can use friend discovery.', ['c' => implode(' / ', array_map(fn ($c) => $c === 'sms' ? 'SMS' : 'WhatsApp', $channels))]) }}</p>
            @endif
            @if($testMode)<p class="text-xs text-amber-700 mt-2">{{ __('Test mode is active (FRIENDS_SMS_DRIVER=log): codes are written to the log, not sent.') }}</p>@endif
        </div>

        <form method="POST" action="{{ route('admin.phone-verification.save') }}" class="space-y-6">
            @csrf

            <div class="panel p-6 space-y-3">
                <h3 class="text-base font-semibold text-ink">{{ __('Phone number at sign-up') }}</h3>
                <p class="text-sm text-muted">{{ __('Applies to the website and the mobile app.') }}</p>
                @foreach(['off' => [__('Do not ask'), __('No phone field on the sign-up form.')], 'optional' => [__('Optional'), __('Shown, but people can leave it empty.')], 'required' => [__('Required'), __('People must enter a phone number to register.')]] as $val => [$label, $hint])
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="phone_registration" value="{{ $val }}" class="mt-1" @checked($registration === $val)>
                        <span><span class="text-sm font-medium text-ink">{{ $label }}</span><span class="block text-xs text-muted">{{ $hint }}</span></span>
                    </label>
                @endforeach
            </div>

            <div class="panel p-6 space-y-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="phone_verification_enabled" value="1" class="mt-1" @checked($verification)>
                    <span><span class="text-base font-semibold text-ink">{{ __('Verify phone numbers with a code') }}</span>
                        <span class="block text-sm text-muted">{{ __('Switch OFF to let people add a number without any verification.') }}</span></span>
                </label>

                <div class="border-t border-border pt-4 space-y-4">
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="phone_verify_sms" value="1" @checked($sms)>
                            <span class="text-sm font-medium text-ink">{{ __('Send the code by SMS') }}</span>
                            <span class="text-xs rounded-full px-2 py-0.5 {{ $smsReady ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $smsReady ? __('Twilio is set up') : __('Twilio not set up') }}</span>
                        </label>
                        @if(!$smsReady)<p class="text-xs text-muted mt-1 ml-7">{{ __('Add the Twilio SID, auth token and phone number under Settings → Integrations.') }}</p>@endif
                    </div>

                    <div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="phone_verify_whatsapp" value="1" @checked($whatsapp)>
                            <span class="text-sm font-medium text-ink">{{ __('Send the code by WhatsApp') }}</span>
                            <span class="text-xs rounded-full px-2 py-0.5 {{ $whatsappReady ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $whatsappReady ? __('WhatsApp is set up') : __('WhatsApp not set up') }}</span>
                        </label>
                        @if(!$whatsappReady)<p class="text-xs text-muted mt-1 ml-7">{{ __('Add the WhatsApp phone number ID and access token under Settings → Integrations.') }}</p>@endif
                        <div class="ml-7 mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs text-muted mb-1">{{ __('Approved template name') }}</label>
                                <input type="text" name="whatsapp_otp_template" value="{{ old('whatsapp_otp_template', $template) }}" class="input w-full" placeholder="{{ __('e.g. verification_code') }}">
                            </div>
                            <div>
                                <label class="block text-xs text-muted mb-1">{{ __('Template language code') }}</label>
                                <input type="text" name="whatsapp_otp_language" value="{{ old('whatsapp_otp_language', $language) }}" class="input w-full" placeholder="en_US">
                            </div>
                            <label class="sm:col-span-2 flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="whatsapp_otp_button" value="1" @checked($button)> {{ __('The template has a “copy code” button (authentication template)') }}</label>
                        </div>
                        <p class="text-xs text-muted mt-2 ml-7">{{ __('WhatsApp only lets a business start a conversation with an APPROVED template. Without a template name a plain message is tried, which usually fails for new numbers.') }}</p>
                    </div>
                </div>
            </div>

            <div class="panel p-6 space-y-2 border-amber-300">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="phone_discovery_unverified" value="1" class="mt-1" @checked($unverifiedDiscovery)>
                    <span><span class="text-base font-semibold text-ink">{{ __('Allow friend discovery with UNVERIFIED numbers') }}</span>
                        <span class="block text-sm text-muted">{{ __('Use this if you do not send verification codes. People can then be found by the phone number they typed, and friends see a "number not verified" note.') }}</span>
                        <span class="block text-xs text-amber-700 mt-1">{{ __('Not recommended: without a code nobody can prove a number is theirs, so someone could claim another person\'s number and appear as that person to everyone who saved it. Leave this OFF if you can use SMS or WhatsApp verification.') }}</span></span>
                </label>
            </div>

            <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        </form>
    </div>
</x-layouts.admin>
