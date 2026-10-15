<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Hero Section') }}</h2>
    </div>
    <div class="p-6 space-y-4">
        <div>
            <label class="settings-label">{{ __('Badge Text') }}</label>
            <input type="text" name="content[badge]" value="{{ old('content.badge', $content['badge'] ?? '') }}" class="settings-input" placeholder="{{ __('Now with GPT-4o & Claude 4 Support') }}">
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div>
                <label class="settings-label">{{ __('Title Line 1') }}</label>
                <input type="text" name="content[title_line1]" value="{{ old('content.title_line1', $content['title_line1'] ?? '') }}" class="settings-input" placeholder="{{ __('Achieve flawless email delivery') }}">
            </div>
            <div>
                <label class="settings-label">{{ __('Highlighted Word') }}</label>
                <input type="text" name="content[title_highlight]" value="{{ old('content.title_highlight', $content['title_highlight'] ?? '') }}" class="settings-input" placeholder="{{ __('AI-powered') }}">
                <p class="settings-hint">{{ __('This text gets the gradient color effect.') }}</p>
            </div>
            <div>
                <label class="settings-label">{{ __('Title Line 2') }}</label>
                <input type="text" name="content[title_line2]" value="{{ old('content.title_line2', $content['title_line2'] ?? '') }}" class="settings-input" placeholder="{{ __('automation.') }}">
            </div>
        </div>
        <div>
            <label class="settings-label">{{ __('Subtitle') }}</label>
            <textarea name="content[subtitle]" rows="3" class="settings-input resize-none">{{ old('content.subtitle', $content['subtitle'] ?? '') }}</textarea>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div>
                <label class="settings-label">{{ __('Primary Button Text') }}</label>
                <input type="text" name="content[cta_text]" value="{{ old('content.cta_text', $content['cta_text'] ?? '') }}" class="settings-input" placeholder="{{ __('Get Started Free') }}">
            </div>
            <div>
                <label class="settings-label">{{ __('Primary Button URL') }}</label>
                <input type="text" name="content[cta_url]" value="{{ old('content.cta_url', $content['cta_url'] ?? '') }}" class="settings-input" placeholder="/register">
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div>
                <label class="settings-label">{{ __('Secondary Button Text') }}</label>
                <input type="text" name="content[cta2_text]" value="{{ old('content.cta2_text', $content['cta2_text'] ?? '') }}" class="settings-input" placeholder="{{ __('See Features') }}">
            </div>
            <div>
                <label class="settings-label">{{ __('Secondary Button URL') }}</label>
                <input type="text" name="content[cta2_url]" value="{{ old('content.cta2_url', $content['cta2_url'] ?? '') }}" class="settings-input" placeholder="#features">
            </div>
        </div>
    </div>
</div>
