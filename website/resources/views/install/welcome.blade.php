@extends('install.layout')
@section('title', __('Welcome'))
@section('step-name', __('Welcome'))

@section('content')
<div class="space-y-5">
    <div class="space-y-1">
        <h1 class="text-2xl font-bold tracking-tight">
            {{ __('Welcome to') }} <span class="text-primary italic">{{ config('app.name') }}. s h a r e d    o  n     - c o d e l i s t . c c -</span>
        </h1>
        <p class="text-gray-500 text-xs font-medium">{{ __("Your AI-powered email automation & CRM platform. Let's get you set up.") }}</p>
    </div>

    {{-- Feature highlights --}}
    <div class="grid grid-cols-2 gap-2.5">
        @php
            $cards = [
                ['title' => __('Unified Inbox'), 'desc' => __('Gmail, Outlook, IMAP & 5 channels'), 'color' => 'text-blue-600 bg-blue-500/10', 'icon' => 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75'],
                ['title' => __('AI-Powered Replies'), 'desc' => __('OpenAI, Claude, Gemini, Mistral'), 'color' => 'text-purple-600 bg-purple-500/10', 'icon' => 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z'],
                ['title' => __('CRM & Pipeline'), 'desc' => __('Contacts, deals & workflows'), 'color' => 'text-emerald-600 bg-emerald-500/10', 'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z'],
                ['title' => __('Email Campaigns'), 'desc' => __('A/B testing, drips & templates'), 'color' => 'text-indigo-600 bg-indigo-500/10', 'icon' => 'M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5'],
                ['title' => __('40+ Payment Gateways'), 'desc' => __('Stripe, PayPal, Razorpay & more'), 'color' => 'text-green-600 bg-green-500/10', 'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
                ['title' => __('Multi-Currency'), 'desc' => __('100+ currencies supported'), 'color' => 'text-cyan-600 bg-cyan-500/10', 'icon' => 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['title' => __('Multi-Language'), 'desc' => __('RTL support & translations'), 'color' => 'text-rose-600 bg-rose-500/10', 'icon' => 'M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802'],
                ['title' => __('Security & GDPR'), 'desc' => __('2FA, encryption, audit logs'), 'color' => 'text-amber-600 bg-amber-500/10', 'icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z'],
                ['title' => __('Live Chat Widget'), 'desc' => __('Embeddable with AI auto-reply'), 'color' => 'text-violet-600 bg-violet-500/10', 'icon' => 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z'],
                ['title' => __('Email Templates'), 'desc' => __('Drag-drop builder & gallery'), 'color' => 'text-teal-600 bg-teal-500/10', 'icon' => 'M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42'],
            ];
        @endphp
        @foreach($cards as $card)
        <div class="flex items-start gap-3 rounded-xl border border-[#2d3039] bg-[#15171e] p-3.5">
            <div class="w-8 h-8 rounded-lg {{ $card['color'] }} flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/></svg>
            </div>
            <div>
                <h3 class="text-xs font-semibold text-gray-900">{{ $card['title'] }}</h3>
                <p class="text-[10px] text-gray-500 mt-0.5">{{ $card['desc'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Stats bar --}}
    <div class="flex items-center justify-between px-4 py-3 bg-[#15171e] rounded-xl border border-[#2d3039]">
        <div class="text-center">
            <p class="text-lg font-bold text-gray-900">20+</p>
            <p class="text-[9px] font-semibold uppercase tracking-widest text-gray-400">{{ __('Modules') }}</p>
        </div>
        <div class="w-px h-8 bg-[#2d3039]"></div>
        <div class="text-center">
            <p class="text-lg font-bold text-gray-900">4</p>
            <p class="text-[9px] font-semibold uppercase tracking-widest text-gray-400">{{ __('AI Providers') }}</p>
        </div>
        <div class="w-px h-8 bg-[#2d3039]"></div>
        <div class="text-center">
            <p class="text-lg font-bold text-gray-900">5</p>
            <p class="text-[9px] font-semibold uppercase tracking-widest text-gray-400">{{ __('Channels') }}</p>
        </div>
        <div class="w-px h-8 bg-[#2d3039]"></div>
        <div class="text-center">
            <p class="text-lg font-bold text-gray-900">40+</p>
            <p class="text-[9px] font-semibold uppercase tracking-widest text-gray-400">{{ __('Gateways') }}</p>
        </div>
        <div class="w-px h-8 bg-[#2d3039]"></div>
        <div class="text-center">
            <p class="text-lg font-bold text-gray-900">100+</p>
            <p class="text-[9px] font-semibold uppercase tracking-widest text-gray-400">{{ __('Currencies') }}</p>
        </div>
    </div>

    <a href="{{ route('install.requirements') }}" class="flex items-center justify-center w-full h-11 rounded-xl bg-primary hover:bg-primary/90 text-sm font-bold shadow-lg shadow-primary/20 transition-all text-white">
        {{ __("Let's Get Started") }}
    </a>
</div>
@endsection
