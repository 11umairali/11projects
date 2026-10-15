@extends('install.layout')
@section('content')
<h2 class="text-xl font-bold text-gray-900 mb-1">{{ __('Installing') }} {{ config('app.name') }}</h2>
<p class="text-gray-500 text-sm mb-6">{{ __('Please wait while we set up your application. Do not close this page.') }}</p>

<div id="steps" class="space-y-2 mb-6">
    <div class="step flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-gray-50" data-step="write_env">
        <div class="icon w-7 h-7 rounded-full flex items-center justify-center bg-gray-200 text-gray-500 text-xs font-bold flex-shrink-0">1</div>
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-700">{{ __('Writing environment file') }}</p>
            <p class="status text-xs text-gray-400">{{ __('Waiting...') }}</p>
        </div>
    </div>
    <div class="step flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-gray-50" data-step="migrations">
        <div class="icon w-7 h-7 rounded-full flex items-center justify-center bg-gray-200 text-gray-500 text-xs font-bold flex-shrink-0">2</div>
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-700">{{ __('Running database migrations') }}</p>
            <p class="status text-xs text-gray-400">{{ __('Waiting...') }}</p>
        </div>
    </div>
    <div class="step flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-gray-50" data-step="seed_data">
        <div class="icon w-7 h-7 rounded-full flex items-center justify-center bg-gray-200 text-gray-500 text-xs font-bold flex-shrink-0">3</div>
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-700">{{ __('Seeding initial data') }}</p>
            <p class="status text-xs text-gray-400">{{ __('Waiting...') }}</p>
        </div>
    </div>
    <div class="step flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-gray-50" data-step="create_admin">
        <div class="icon w-7 h-7 rounded-full flex items-center justify-center bg-gray-200 text-gray-500 text-xs font-bold flex-shrink-0">4</div>
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-700">{{ __('Creating admin account') }}</p>
            <p class="status text-xs text-gray-400">{{ __('Waiting...') }}</p>
        </div>
    </div>
    <div class="step flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-gray-50" data-step="permissions">
        <div class="icon w-7 h-7 rounded-full flex items-center justify-center bg-gray-200 text-gray-500 text-xs font-bold flex-shrink-0">5</div>
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-700">{{ __('Setting file permissions') }}</p>
            <p class="status text-xs text-gray-400">{{ __('Waiting...') }}</p>
        </div>
    </div>
    <div class="step flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-gray-50" data-step="finalize">
        <div class="icon w-7 h-7 rounded-full flex items-center justify-center bg-gray-200 text-gray-500 text-xs font-bold flex-shrink-0">6</div>
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-700">{{ __('Finalizing installation') }}</p>
            <p class="status text-xs text-gray-400">{{ __('Waiting...') }}</p>
        </div>
    </div>
</div>

<div id="errorBox" class="hidden mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"></div>

<div id="progressBar" class="w-full bg-gray-200 rounded-full h-2 mb-4">
    <div id="progressFill" class="bg-indigo-600 h-2 rounded-full transition-all duration-500" style="width:0%"></div>
</div>
<p id="progressText" class="text-xs text-gray-400 text-center">{{ __('Starting installation...') }}</p>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const steps = ['write_env', 'migrations', 'seed_data', 'create_admin', 'permissions', 'finalize'];
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    let currentIndex = 0;

    function runStep(index) {
        if (index >= steps.length) {
            document.getElementById('progressText').textContent = '{{ __("Installation complete! Redirecting...") }}';
            document.getElementById('progressFill').style.width = '100%';
            setTimeout(() => window.location.href = '{{ route("install.complete") }}', 1000);
            return;
        }

        const stepName = steps[index];
        const el = document.querySelector(`[data-step="${stepName}"]`);
        const icon = el.querySelector('.icon');
        const status = el.querySelector('.status');

        // Mark running
        el.classList.remove('bg-gray-50', 'border-gray-200');
        el.classList.add('bg-indigo-50', 'border-indigo-200');
        icon.classList.remove('bg-gray-200', 'text-gray-500');
        icon.classList.add('bg-indigo-500', 'text-white');
        status.textContent = '{{ __("Processing...") }}';
        status.classList.remove('text-gray-400');
        status.classList.add('text-indigo-500');

        const pct = Math.round(((index) / steps.length) * 100);
        document.getElementById('progressFill').style.width = pct + '%';
        document.getElementById('progressText').textContent = '{{ __("Step") }} ' + (index + 1) + ' {{ __("of") }} ' + steps.length + '...';

        fetch('{{ route("install.execute") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ step: stepName }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Mark done
                el.classList.remove('bg-indigo-50', 'border-indigo-200');
                el.classList.add('bg-green-50', 'border-green-200');
                icon.classList.remove('bg-indigo-500');
                icon.classList.add('bg-green-500');
                icon.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
                status.textContent = data.message;
                status.classList.remove('text-indigo-500');
                status.classList.add('text-green-600');

                runStep(index + 1);
            } else {
                // Mark fail
                el.classList.remove('bg-indigo-50', 'border-indigo-200');
                el.classList.add('bg-red-50', 'border-red-200');
                icon.classList.remove('bg-indigo-500');
                icon.classList.add('bg-red-500');
                icon.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
                status.textContent = data.message;
                status.classList.remove('text-indigo-500');
                status.classList.add('text-red-600');

                document.getElementById('errorBox').classList.remove('hidden');
                document.getElementById('errorBox').textContent = '{{ __("Installation failed at step") }} ' + (index + 1) + ': ' + data.message;
                document.getElementById('progressText').textContent = '{{ __("Installation failed.") }}';
            }
        })
        .catch(err => {
            el.classList.remove('bg-indigo-50', 'border-indigo-200');
            el.classList.add('bg-red-50', 'border-red-200');
            icon.classList.remove('bg-indigo-500');
            icon.classList.add('bg-red-500');
            status.textContent = '{{ __("Network error") }}';
            status.classList.remove('text-indigo-500');
            status.classList.add('text-red-600');

            document.getElementById('errorBox').classList.remove('hidden');
            document.getElementById('errorBox').textContent = '{{ __("Network error. Please check your connection and try again.") }}';
            document.getElementById('progressText').textContent = '{{ __("Installation failed.") }}';
        });
    }

    // Start
    runStep(0);
});
</script>
@endpush
