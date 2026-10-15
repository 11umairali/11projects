@extends('install.layout')
@section('content')
<h2 class="text-xl font-bold text-gray-900 mb-1">{{ __('Database Configuration') }}</h2>
<p class="text-sm text-gray-500 mb-6">{{ __('Enter your MySQL database credentials.') }}</p>

<form method="POST" action="{{ route('install.database.save') }}" id="db-form">
    @csrf
    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Host') }}</label>
                <input type="text" name="host" value="{{ session('db.host', '127.0.0.1') }}" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Port') }}</label>
                <input type="number" name="port" value="{{ session('db.port', '3306') }}" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Database Name') }}</label>
            <input type="text" name="database" value="{{ session('db.database', '') }}" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="mailtrixy">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Username') }}</label>
                <input type="text" name="username" value="{{ session('db.username', 'root') }}" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Password') }}</label>
                <input type="password" name="password" value="{{ session('db.password', '') }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
        </div>
    </div>

    <div id="test-result" class="mt-4 hidden p-3 rounded-xl text-sm"></div>

    <div class="mt-6 flex items-center justify-between">
        <a href="{{ route('install.requirements') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back') }}</a>
        <div class="flex gap-3">
            <button type="button" onclick="testConnection()" class="px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50" id="test-btn">{{ __('Test Connection') }}</button>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700">{{ __('Save & Continue') }}</button>
        </div>
    </div>
</form>

@push('scripts')
<script>
function testConnection() {
    const btn = document.getElementById('test-btn');
    const result = document.getElementById('test-result');
    const form = document.getElementById('db-form');
    const data = new FormData(form);

    btn.textContent = '{{ __("Testing...") }}';
    btn.disabled = true;
    result.classList.add('hidden');

    fetch('{{ route("install.database.test") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
        body: data
    })
    .then(r => r.json())
    .then(d => {
        result.classList.remove('hidden');
        if (d.success) {
            result.className = 'mt-4 p-3 rounded-xl text-sm bg-green-50 text-green-700 border border-green-200';
            result.textContent = '{{ __("Connection successful!") }}';
        } else {
            result.className = 'mt-4 p-3 rounded-xl text-sm bg-red-50 text-red-700 border border-red-200';
            result.textContent = '{{ __("Connection failed:") }} ' + (d.message || '{{ __("Unknown error") }}');
        }
    })
    .catch(() => {
        result.classList.remove('hidden');
        result.className = 'mt-4 p-3 rounded-xl text-sm bg-red-50 text-red-700 border border-red-200';
        result.textContent = '{{ __("Connection test failed.") }}';
    })
    .finally(() => { btn.textContent = '{{ __("Test Connection") }}'; btn.disabled = false; });
}
</script>
@endpush
@endsection
