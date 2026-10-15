{{-- Header --}}
<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Header') }}</h2>
    </div>
    <div class="p-6 space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div>
                <label class="settings-label">{{ __('Last Updated Date') }}</label>
                <input type="text" name="content[last_updated]" value="{{ old('content.last_updated', $content['last_updated'] ?? '') }}" class="settings-input" placeholder="{{ __('January 1, 2026') }}">
            </div>
        </div>
        <div>
            <label class="settings-label">{{ __('Subtitle') }}</label>
            <textarea name="content[subtitle]" rows="2" class="settings-input resize-none">{{ old('content.subtitle', $content['subtitle'] ?? '') }}</textarea>
        </div>
    </div>
</div>

{{-- Privacy Sections --}}
<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Privacy Sections') }}</h2>
        <p class="text-xs text-muted mt-0.5">{{ __('Each section appears as a card with title and content.') }}</p>
    </div>
    <div class="p-6 space-y-5">
        @for($i = 0; $i < 8; $i++)
        <div class="rounded-xl border border-border/50 p-4 space-y-3">
            <div>
                <label class="settings-label">{{ __('Section') }} {{ $i + 1 }} {{ __('Title') }}</label>
                <input type="text" name="content[sections][{{ $i }}][title]" value="{{ old("content.sections.{$i}.title", $content['sections'][$i]['title'] ?? '') }}" class="settings-input">
            </div>
            <div>
                <label class="settings-label">{{ __('Section') }} {{ $i + 1 }} {{ __('Content') }}</label>
                <textarea name="content[sections][{{ $i }}][content]" rows="3" class="settings-input resize-none">{{ old("content.sections.{$i}.content", $content['sections'][$i]['content'] ?? '') }}</textarea>
            </div>
        </div>
        @endfor
    </div>
</div>

{{-- Bottom Text --}}
<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Bottom Section') }}</h2>
    </div>
    <div class="p-6">
        <label class="settings-label">{{ __('Bottom Text') }}</label>
        <textarea name="content[bottom_text]" rows="3" class="settings-input resize-none">{{ old('content.bottom_text', $content['bottom_text'] ?? '') }}</textarea>
    </div>
</div>
