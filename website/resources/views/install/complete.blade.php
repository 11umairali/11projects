@extends('install.layout')
@section('title', __('Complete'))
@section('step-name', __('Complete'))

@section('content')
<div class="space-y-6 text-center">
    <div class="w-20 h-20 bg-emerald-500/10 rounded-full flex items-center justify-center mx-auto">
        <svg class="w-10 h-10 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </div>

    <div class="space-y-2">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('Installation') }} <span class="text-primary italic">{{ __('Complete!') }}</span></h1>
        <p class="text-gray-500 text-sm">{{ config('app.name') }} {{ __('has been successfully installed and is ready to use.') }}</p>
    </div>

    @if(session('install_complete'))
    <div class="bg-[#15171e] rounded-xl border border-[#2d3039] p-5 text-left max-w-sm mx-auto space-y-2">
        <label class="text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ __('Admin Credentials') }}</label>
        <div class="flex justify-between text-sm"><span class="text-gray-500">{{ __('Email') }}</span><span class="font-semibold text-gray-900">{{ session('install_complete.email') }}</span></div>
        <div class="flex justify-between text-sm"><span class="text-gray-500">{{ __('URL') }}</span><span class="font-semibold text-gray-900">{{ session('install_complete.url') }}</span></div>
    </div>
    @endif

    <div class="flex items-center justify-center gap-3">
        <a href="{{ url('/login') }}" class="h-11 px-6 rounded-xl bg-primary hover:bg-primary/90 text-sm font-bold shadow-lg shadow-primary/20 transition-all text-white flex items-center justify-center">{{ __('Open Admin Panel') }}</a>
        <a href="{{ url('/') }}" class="h-11 px-6 rounded-xl bg-gray-100 border border-gray-200 hover:bg-[#1a1d27] text-sm font-bold transition-all text-gray-700 flex items-center justify-center">{{ __('Visit Homepage') }}</a>
    </div>
</div>
@endsection
