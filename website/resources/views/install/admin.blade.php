@extends('install.layout')
@section('content')
<h2 class="text-xl font-bold text-gray-900 mb-1">{{ __('Create Admin Account') }}</h2>
<p class="text-sm text-gray-500 mb-6">{{ __('This will be the super admin account for managing') }} {{ config('app.name') }}.</p>

<form method="POST" action="{{ route('install.admin.save') }}">
    @csrf
    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Full Name') }}</label>
                <input type="text" name="name" value="{{ session('admin_data.name', '') }}" required maxlength="40" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="John Doe">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Email Address') }}</label>
                <input type="email" name="email" value="{{ session('admin_data.email', '') }}" required maxlength="255" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="admin@example.com">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Password') }}</label>
                <input type="password" name="password" required minlength="8" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="{{ __('Min. 8 characters') }}">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Confirm Password') }}</label>
                <input type="password" name="password_confirmation" required minlength="8" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="{{ __('Repeat password') }}">
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
    @endif

    <div class="mt-8 flex items-center justify-between">
        <a href="{{ route('install.application') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back') }}</a>
        <button type="submit" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700">{{ __('Create Admin & Install') }}</button>
    </div>
</form>
@endsection
