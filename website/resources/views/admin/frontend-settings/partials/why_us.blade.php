<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Why Us Page Content') }}</h2>
    </div>
    <div class="p-6 space-y-4">
        <div>
            <label class="settings-label">{{ __('Page Title') }}</label>
            <input type="text" name="content[title]" value="{{ old('content.title', $content['title'] ?? '') }}" class="settings-input" placeholder="Why Choose Us">
        </div>
        <div>
            <label class="settings-label">{{ __('Subtitle') }}</label>
            <textarea name="content[subtitle]" rows="2" class="settings-input resize-none">{{ old('content.subtitle', $content['subtitle'] ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Reasons') }}</h2>
        <p class="text-xs text-muted mt-0.5">{{ __('Up to 6 reasons displayed as cards.') }}</p>
    </div>
    <div class="p-6 space-y-5">
        @for($i = 0; $i < 6; $i++)
        <div class="rounded-xl border border-border/50 p-4 space-y-3">
            <div>
                <label class="settings-label">{{ __('Reason') }} {{ $i + 1 }} {{ __('Title') }}</label>
                <input type="text" name="content[reasons][{{ $i }}][title]" value="{{ old("content.reasons.{$i}.title", $content['reasons'][$i]['title'] ?? '') }}" class="settings-input">
            </div>
            <div>
                <label class="settings-label">{{ __('Reason') }} {{ $i + 1 }} {{ __('Description') }}</label>
                <textarea name="content[reasons][{{ $i }}][desc]" rows="2" class="settings-input resize-none">{{ old("content.reasons.{$i}.desc", $content['reasons'][$i]['desc'] ?? '') }}</textarea>
            </div>
        </div>
        @endfor
    </div>
</div>
