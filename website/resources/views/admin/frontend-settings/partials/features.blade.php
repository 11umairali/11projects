<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Header') }}</h2>
    </div>
    <div class="p-6 space-y-4">
        <div>
            <label class="settings-label">{{ __('Badge Text') }}</label>
            <input type="text" name="content[badge]" value="{{ old('content.badge', $content['badge'] ?? '') }}" class="settings-input" placeholder="{{ __('Features') }}">
        </div>
        <div>
            <label class="settings-label">{{ __('Section Title') }}</label>
            <input type="text" name="content[title]" value="{{ old('content.title', $content['title'] ?? '') }}" class="settings-input">
        </div>
        <div>
            <label class="settings-label">{{ __('Section Subtitle') }}</label>
            <input type="text" name="content[subtitle]" value="{{ old('content.subtitle', $content['subtitle'] ?? '') }}" class="settings-input">
        </div>
    </div>
</div>

<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Feature Cards') }}</h2>
        <p class="text-xs text-muted mt-0.5">{{ __('Edit up to 6 feature cards shown on the landing page.') }}</p>
    </div>
    <div class="p-6 space-y-5">
        @for($i = 0; $i < 6; $i++)
        <div class="rounded-xl border border-border/50 p-4 space-y-3">
            <div>
                <label class="settings-label">{{ __('Feature') }} {{ $i + 1 }} {{ __('Title') }}</label>
                <input type="text" name="content[items][{{ $i }}][title]" value="{{ old("content.items.{$i}.title", $content['items'][$i]['title'] ?? '') }}" class="settings-input">
            </div>
            <div>
                <label class="settings-label">{{ __('Feature') }} {{ $i + 1 }} {{ __('Description') }}</label>
                <textarea name="content[items][{{ $i }}][desc]" rows="2" class="settings-input resize-none">{{ old("content.items.{$i}.desc", $content['items'][$i]['desc'] ?? '') }}</textarea>
            </div>
        </div>
        @endfor
    </div>
</div>
