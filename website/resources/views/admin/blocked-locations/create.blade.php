<x-layouts.admin :title="__('Block Location')" :subtitle="__('Add a new geographic location restriction.')">
    <div class="space-y-6">

        <form method="POST" action="{{ route('admin.blocked-locations.store') }}" class="space-y-6">
            @csrf

            {{-- Location Information --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Location Details') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Specify the geographic area to block. Country is required; state and city are optional for more targeted blocking.') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Country Code --}}
                        <div>
                            <label for="country_code" class="block text-sm font-medium text-ink mb-1.5">{{ __('Country Code') }} <span class="text-danger">*</span></label>
                            <input type="text" name="country_code" id="country_code" value="{{ old('country_code') }}" required
                                   placeholder="{{ __('e.g. US, CN, RU') }}"
                                   maxlength="2"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink font-mono uppercase @error('country_code') !border-danger !ring-danger/20 @enderror">
                            <p class="text-xs text-muted mt-1">{{ __('ISO 3166-1 alpha-2 code (2 letters).') }}</p>
                            @error('country_code') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Country Name --}}
                        <div>
                            <label for="country_name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Country Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="country_name" id="country_name" value="{{ old('country_name') }}" required
                                   placeholder="{{ __('e.g. United States, China, Russia') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('country_name') !border-danger !ring-danger/20 @enderror">
                            @error('country_name') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- State --}}
                        <div>
                            <label for="state" class="block text-sm font-medium text-ink mb-1.5">{{ __('State / Province') }}</label>
                            <input type="text" name="state" id="state" value="{{ old('state') }}"
                                   placeholder="{{ __('e.g. California (optional)') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('state') !border-danger !ring-danger/20 @enderror">
                            <p class="text-xs text-muted mt-1">{{ __('Leave blank to block the entire country.') }}</p>
                            @error('state') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- City --}}
                        <div>
                            <label for="city" class="block text-sm font-medium text-ink mb-1.5">{{ __('City') }}</label>
                            <input type="text" name="city" id="city" value="{{ old('city') }}"
                                   placeholder="{{ __('e.g. Los Angeles (optional)') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('city') !border-danger !ring-danger/20 @enderror">
                            <p class="text-xs text-muted mt-1">{{ __('Leave blank to block the entire state or country.') }}</p>
                            @error('city') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Block Settings --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Block Settings') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Provide a reason and set the initial status for this block rule.') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    {{-- Reason --}}
                    <div>
                        <label for="reason" class="block text-sm font-medium text-ink mb-1.5">{{ __('Reason') }}</label>
                        <textarea name="reason" id="reason" rows="3"
                                  placeholder="{{ __('e.g. High fraud activity, compliance requirements, abuse origin') }}"
                                  class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink resize-none @error('reason') !border-danger !ring-danger/20 @enderror">{{ old('reason') }}</textarea>
                        @error('reason') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Active --}}
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}
                                   class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            <span class="text-sm font-medium text-ink">{{ __('Active') }}</span>
                        </label>
                        <span class="text-xs text-muted">{{ __('Uncheck to create the rule as inactive (disabled).') }}</span>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between">
                <a href="{{ route('admin.blocked-locations.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-muted border border-border rounded-xl hover:bg-surface transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    {{ __('Back to Blocked Locations') }}
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Block Location') }}
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
