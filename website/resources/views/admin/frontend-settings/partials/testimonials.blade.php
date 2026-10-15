<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Header') }}</h2>
    </div>
    <div class="p-6">
        <label class="settings-label">{{ __('Section Title') }}</label>
        <input type="text" name="content[title]" value="{{ old('content.title', $content['title'] ?? '') }}" class="settings-input">
    </div>
</div>

<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Testimonial Cards') }}</h2>
        <p class="text-xs text-muted mt-0.5">{{ __('Edit up to 6 customer testimonials.') }}</p>
    </div>
    <div class="p-6 space-y-5">
        @for($i = 0; $i < 6; $i++)
        <div class="rounded-xl border border-border/50 p-4 space-y-3">
            <div>
                <label class="settings-label">{{ __('Quote') }}</label>
                <textarea name="content[items][{{ $i }}][quote]" rows="2" class="settings-input resize-none">{{ old("content.items.{$i}.quote", $content['items'][$i]['quote'] ?? '') }}</textarea>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                <div>
                    <label class="settings-label">{{ __('Name') }}</label>
                    <input type="text" name="content[items][{{ $i }}][name]" value="{{ old("content.items.{$i}.name", $content['items'][$i]['name'] ?? '') }}" class="settings-input">
                </div>
                <div>
                    <label class="settings-label">{{ __('Title / Role') }}</label>
                    <input type="text" name="content[items][{{ $i }}][title]" value="{{ old("content.items.{$i}.title", $content['items'][$i]['title'] ?? '') }}" class="settings-input">
                </div>
                <div>
                    <label class="settings-label">{{ __('Company') }}</label>
                    <input type="text" name="content[items][{{ $i }}][company]" value="{{ old("content.items.{$i}.company", $content['items'][$i]['company'] ?? '') }}" class="settings-input">
                </div>
            </div>
        </div>
        @endfor
    </div>
</div>
