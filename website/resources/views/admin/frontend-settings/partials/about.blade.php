<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('About Page Content') }}</h2>
    </div>
    <div class="p-6 space-y-4">
        <div>
            <label class="settings-label">{{ __('Page Title') }}</label>
            <input type="text" name="content[title]" value="{{ old('content.title', $content['title'] ?? '') }}" class="settings-input" placeholder="About Us">
        </div>
        <div>
            <label class="settings-label">{{ __('Subtitle') }}</label>
            <textarea name="content[subtitle]" rows="2" class="settings-input resize-none">{{ old('content.subtitle', $content['subtitle'] ?? '') }}</textarea>
        </div>
        <div>
            <label class="settings-label">{{ __('Story / Description') }}</label>
            <textarea name="content[story]" rows="5" class="settings-input resize-none">{{ old('content.story', $content['story'] ?? '') }}</textarea>
        </div>
        <div>
            <label class="settings-label">{{ __('Mission Statement') }}</label>
            <textarea name="content[mission]" rows="3" class="settings-input resize-none">{{ old('content.mission', $content['mission'] ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Stats') }}</h2>
        <p class="text-xs text-muted mt-0.5">{{ __('Key numbers displayed on the about page.') }}</p>
    </div>
    <div class="p-6 space-y-4">
        @for($i = 0; $i < 4; $i++)
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="settings-label">{{ __('Stat') }} {{ $i + 1 }} {{ __('Value') }}</label>
                <input type="text" name="content[stats][{{ $i }}][value]" value="{{ old("content.stats.{$i}.value", $content['stats'][$i]['value'] ?? '') }}" class="settings-input" placeholder="10K+">
            </div>
            <div>
                <label class="settings-label">{{ __('Stat') }} {{ $i + 1 }} {{ __('Label') }}</label>
                <input type="text" name="content[stats][{{ $i }}][label]" value="{{ old("content.stats.{$i}.label", $content['stats'][$i]['label'] ?? '') }}" class="settings-input" placeholder="Active Users">
            </div>
        </div>
        @endfor
    </div>
</div>
