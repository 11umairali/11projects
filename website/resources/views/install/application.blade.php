@extends('install.layout')
@section('content')
<h2 class="text-xl font-bold text-gray-900 mb-1">{{ __('Application Settings') }}</h2>
<p class="text-sm text-gray-500 mb-6">{{ __('Configure your') }} {{ config('app.name') }} {{ __('instance.') }}</p>

<form method="POST" action="{{ route('install.application.save') }}">
    @csrf
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Application Name') }}</label>
            <input type="text" name="name" value="{{ session('app.name', config('app.name', 'MailTrixy')) }}" required maxlength="100" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="MailTrixy">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Application URL') }}</label>
            <input type="url" name="url" value="{{ session('app.url', request()->root()) }}" required maxlength="255" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="https://yourdomain.com">
            <p class="text-xs text-gray-400 mt-1">{{ __('The full URL where') }} {{ config('app.name') }} {{ __('will be accessible. No trailing slash.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Timezone') }}</label>
            <select name="timezone" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                @foreach(timezone_identifiers_list() as $tz)
                <option value="{{ $tz }}" {{ session('app.timezone', 'UTC') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-8 flex items-center justify-between">
        <a href="{{ route('install.database') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back') }}</a>
        <button type="submit" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700">{{ __('Save & Continue') }}</button>
    </div>
</form>
@endsection
