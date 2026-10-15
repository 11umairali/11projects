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
        <h2 class="text-base font-bold text-ink">{{ __('FAQ Items') }}</h2>
        <p class="text-xs text-muted mt-0.5">{{ __('Edit the 5 FAQ accordion items.') }}</p>
    </div>
    <div class="p-6 space-y-5">
        @for($i = 0; $i < 5; $i++)
        <div class="rounded-xl border border-border/50 p-4 space-y-3">
            <div>
                <label class="settings-label">{{ __('Question') }} {{ $i + 1 }}</label>
                <input type="text" name="content[items][{{ $i }}][question]" value="{{ old("content.items.{$i}.question", $content['items'][$i]['question'] ?? '') }}" class="settings-input">
            </div>
            <div>
                <label class="settings-label">{{ __('Answer') }} {{ $i + 1 }}</label>
                <textarea name="content[items][{{ $i }}][answer]" rows="3" class="settings-input resize-none">{{ old("content.items.{$i}.answer", $content['items'][$i]['answer'] ?? '') }}</textarea>
            </div>
        </div>
        @endfor
    </div>
</div>
