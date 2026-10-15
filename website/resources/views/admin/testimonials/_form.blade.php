<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Testimonial Details') }}</h2>
    </div>
    <div class="p-6 space-y-5">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div>
                <label class="settings-label">{{ __('Client Name') }} <span class="text-danger">*</span></label>
                <input type="text" name="client_name" value="{{ old('client_name', $testimonial->client_name ?? '') }}" class="settings-input" required placeholder="Sarah Chen">
            </div>
            <div>
                <label class="settings-label">{{ __('Position / Title') }}</label>
                <input type="text" name="client_position" value="{{ old('client_position', $testimonial->client_position ?? '') }}" class="settings-input" placeholder="Head of Customer Success, TechFlow">
            </div>
        </div>

        <div>
            <label class="settings-label">{{ __('Review') }} <span class="text-danger">*</span></label>
            <textarea name="review" rows="4" class="settings-input resize-none" required placeholder="What did the client say?">{{ old('review', $testimonial->review ?? '') }}</textarea>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div>
                <label class="settings-label">{{ __('Rating') }}</label>
                <select name="rating" class="settings-select">
                    @for($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" {{ old('rating', $testimonial->rating ?? 5) == $i ? 'selected' : '' }}>{{ $i }} {{ str_repeat('★', $i) }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="settings-label">{{ __('Client Photo') }}</label>
                <input type="file" name="client_image" accept="image/*" class="settings-input text-xs">
                @if(isset($testimonial) && $testimonial->client_image)
                <div class="mt-2 flex items-center gap-2">
                    <img src="{{ asset('storage/' . $testimonial->client_image) }}" class="w-10 h-10 rounded-full object-cover">
                    <span class="text-xs text-muted">{{ __('Current photo') }}</span>
                </div>
                @endif
            </div>
            <div>
                <label class="settings-label">{{ __('Status') }}</label>
                <label class="flex items-center gap-3 mt-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $testimonial->is_active ?? true) ? 'checked' : '' }} class="rounded border-border text-brand focus:ring-brand">
                    <span class="text-sm text-ink">{{ __('Active (visible on website)') }}</span>
                </label>
            </div>
        </div>
    </div>
</div>
