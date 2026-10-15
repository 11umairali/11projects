@extends('install.layout')
@section('title', __('Requirements'))
@section('step-name', __('Requirements'))

@section('content')
<div class="space-y-5">
    <div class="space-y-1">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('Server') }} <span class="text-primary italic">{{ __('Requirements.') }}</span></h1>
        <p class="text-gray-500 text-xs font-medium">{{ __('Checking your server meets the minimum requirements.') }}</p>
    </div>

    @php
        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $extensions = [];
        foreach(['bcmath','ctype','curl','dom','fileinfo','gd','json','mbstring','openssl','pdo','pdo_mysql','tokenizer','xml','zip','imap'] as $ext) {
            $extensions[$ext] = extension_loaded($ext);
        }
        $directories = [];
        foreach(['storage','storage/app','storage/framework','storage/logs','bootstrap/cache'] as $dir) {
            $directories[$dir] = is_writable(base_path($dir));
        }
        $allPassed = $phpOk && !in_array(false, $extensions) && !in_array(false, $directories);
    @endphp

    @if($phpOk)
    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-600 flex items-center gap-2">
        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        PHP {{ PHP_VERSION }}
    </div>
    @else
    <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-600 flex items-center gap-2">
        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        PHP {{ PHP_VERSION }} — {{ __('Requires 8.2+') }}
    </div>
    @endif

    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ __('PHP Extensions') }}</label>
            @php $extPassed = count(array_filter($extensions)); $extTotal = count($extensions); @endphp
            <span class="text-[10px] font-bold {{ $extPassed === $extTotal ? 'text-emerald-600' : 'text-red-600' }}">{{ $extPassed }}/{{ $extTotal }}</span>
        </div>
        <div class="grid grid-cols-3 gap-1">
            @foreach($extensions as $name => $loaded)
            <div class="flex items-center justify-between rounded-lg border border-[#2d3039] bg-[#15171e] px-2.5 py-1.5">
                <span class="text-xs font-medium text-gray-900">{{ $name }}</span>
                @if($loaded)
                <svg class="h-3.5 w-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                @else
                <svg class="h-3.5 w-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ __('Directories') }}</label>
            @php $dirPassed = count(array_filter($directories)); $dirTotal = count($directories); @endphp
            <span class="text-[10px] font-bold {{ $dirPassed === $dirTotal ? 'text-emerald-600' : 'text-red-600' }}">{{ $dirPassed }}/{{ $dirTotal }}</span>
        </div>
        <div class="space-y-1">
            @foreach($directories as $path => $writable)
            <div class="flex items-center justify-between rounded-lg border border-[#2d3039] bg-[#15171e] px-2.5 py-1.5">
                <span class="text-xs font-medium text-gray-900 truncate mr-2">{{ $path }}/</span>
                @if($writable)
                <svg class="h-3.5 w-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                @else
                <svg class="h-3.5 w-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    @if($allPassed)
    <div class="flex gap-3">
        <a href="{{ route('install.welcome') }}" class="h-11 px-5 rounded-xl bg-gray-100 border border-gray-200 hover:bg-[#1a1d27] text-sm font-bold transition-all text-gray-700 flex items-center justify-center gap-1.5">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('Back') }}
        </a>
        <form method="POST" action="{{ route('install.requirements.check') }}" class="flex-1">
            @csrf
            <button type="submit" class="w-full h-11 rounded-xl bg-primary hover:bg-primary/90 text-sm font-bold shadow-lg shadow-primary/20 transition-all text-white">{{ __('Continue') }}</button>
        </form>
    </div>
    @else
    <div class="space-y-2">
        <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-xs font-medium text-red-600">
            {{ __('Please fix the above requirements before continuing.') }}
        </div>
        <div class="flex gap-3">
            <a href="{{ route('install.welcome') }}" class="h-11 px-5 rounded-xl bg-gray-100 border border-gray-200 hover:bg-[#1a1d27] text-sm font-bold transition-all text-gray-700 flex items-center justify-center gap-1.5">{{ __('Back') }}</a>
            <button type="button" disabled class="flex-1 h-11 rounded-xl bg-gray-200 text-sm font-bold text-gray-400 cursor-not-allowed opacity-60">{{ __('Continue') }}</button>
        </div>
    </div>
    @endif
</div>
@endsection
