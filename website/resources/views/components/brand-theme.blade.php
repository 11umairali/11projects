@php
    $colors = \App\Support\UiTheme::variables(
        \App\Models\SystemSetting::get('primary_color'),
        \App\Models\SystemSetting::get('secondary_color'),
        \App\Models\SystemSetting::get('accent_color'),
    );
@endphp
<style id="brand-theme">
    :root {
        @foreach($colors as $token => $value)
        {{ $token }}: {{ $value }};
        @endforeach
    }
</style>
