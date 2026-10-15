{{-- Contact Page Settings --}}
<div class="panel overflow-hidden">
    <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
        <h2 class="text-base font-bold text-ink">{{ __('Contact Page Content') }}</h2>
    </div>
    <div class="p-6 space-y-4">
        <div>
            <label class="settings-label">{{ __('Subtitle') }}</label>
            <input type="text" name="content[subtitle]" value="{{ old('content.subtitle', $content['subtitle'] ?? '') }}" class="settings-input" placeholder="{{ __("Have a question? We'd love to hear from you.") }}">
        </div>
        <div>
            <label class="settings-label">{{ __('Support Email Label') }}</label>
            <input type="text" name="content[support_email_label]" value="{{ old('content.support_email_label', $content['support_email_label'] ?? '') }}" class="settings-input" placeholder="{{ __('Email Support') }}">
            <p class="settings-hint">{{ __('The actual email address is pulled from System Settings > General > Support Email.') }}</p>
        </div>
        <div>
            <label class="settings-label">{{ __('Response Time') }}</label>
            <input type="text" name="content[response_time]" value="{{ old('content.response_time', $content['response_time'] ?? '') }}" class="settings-input" placeholder="{{ __('24-48 hours') }}">
        </div>
        <div>
            <label class="settings-label">{{ __('Support Channels Description') }}</label>
            <textarea name="content[support_channels]" rows="3" class="settings-input resize-none">{{ old('content.support_channels', $content['support_channels'] ?? '') }}</textarea>
        </div>
    </div>
</div>
