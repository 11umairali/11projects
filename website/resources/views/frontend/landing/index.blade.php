@extends('frontend.layout')

@section('title', config('app.name') . ' – ' . __('AI-Powered Email Automation & CRM SaaS'))

@section('content')
<div x-data="{ billingCycle: 'monthly' }">
    {{-- Ambient Background --}}
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-[10%] -left-[10%] w-[50%] h-[50%] rounded-full bg-slate-400/5 blur-[120px] blob-animation"></div>
        <div class="absolute top-[20%] -right-[10%] w-[40%] h-[40%] rounded-full bg-slate-400/5 blur-[120px] blob-animation" style="animation-delay: 3s;"></div>
        <div class="absolute bottom-[10%] left-[30%] w-[30%] h-[30%] rounded-full bg-slate-400/5 blur-[100px] blob-animation" style="animation-delay: 6s;"></div>
    </div>

{{-- ═══════════════════════════════════════════════════════════════
         2. HERO SECTION
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="relative pt-28 sm:pt-36 lg:pt-44 pb-16 lg:pb-24 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4">
            {{-- Badge --}}
            <div class="flex justify-center mb-6 animate-fade-up"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand animate-pulse"></span>
                    {{ $hero['badge'] }}
                </span>
            </div>

            {{-- Headline --}}
            <h1 class="text-center text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-extrabold leading-[1.1] tracking-tight animate-fade-up delay-100">
                {{ $hero['title_line1'] }}<br class="hidden sm:block">
                {{ __('with') }} <span class="gradient-text">{{ $hero['title_highlight'] }}</span> {{ $hero['title_line2'] }}
            </h1>

            {{-- Subtitle --}}
            <p class="mt-6 text-center text-lg sm:text-xl text-muted max-w-2xl mx-auto leading-relaxed animate-fade-up delay-200">
                {{ $hero['subtitle'] }}
            </p>

            {{-- CTA Buttons --}}
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4 animate-fade-up delay-300">
                <a href="{{ url($hero['cta_url']) }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 text-base font-semibold text-white bg-brand rounded-2xl shadow-lg shadow-soft hover:shadow-soft hover:brightness-110 transition-all">
                    {{ $hero['cta_text'] }}
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </a>
                <a href="{{ url($hero['cta2_url']) }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 text-base font-semibold text-ink border border-border rounded-2xl hover:border-brand/60 hover:bg-surface-2/60 transition-all">
                    {{ $hero['cta2_text'] }}
                </a>
            </div>

        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         3. HOW IT WORKS — Proper full section (4 steps)
    ═══════════════════════════════════════════════════════════════ --}}
    <section id="how-it-works" class="py-20 md:py-32 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4">
            {{-- Section Header --}}
            <div class="text-center mb-16 md:mb-24 space-y-4">
                <h2 x-data="{ shown: false }" x-intersect="shown = true"
                    class="text-3xl sm:text-4xl lg:text-5xl xl:text-6xl font-extrabold tracking-tight transition-all duration-700 transform"
                    :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'">
                    {{ __('From setup to') }} <br class="hidden sm:block">
                    <span class="gradient-text italic">{{ __('full automation.') }}</span>
                </h2>
                <p class="text-lg text-muted max-w-2xl mx-auto">{{ __('Get started in minutes. Connect your channels, let AI learn your business, and watch it scale.') }}</p>
            </div>

            <div class="relative">
                {{-- Connecting Line (Desktop) --}}
                <div class="hidden md:block absolute top-[20%] left-[10%] right-[10%] h-px bg-gradient-to-r from-transparent via-brand/30 to-transparent -z-10"></div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
                    @foreach([
                        ['title' => __('Connect Channels'), 'desc' => __('Link Gmail, Outlook, WhatsApp, Telegram, Slack & SMS in one click. Secure OAuth — no passwords stored.'), 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.813a4.5 4.5 0 00-6.364-6.364L4.5 8.25a4.5 4.5 0 006.364 6.364l4.5-4.5"/></svg>'],
                        ['title' => __('Train Your AI'), 'desc' => __('Upload knowledge base docs, websites & files. AI learns your tone and responds with confidence scoring.'), 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 01-1.275 1.275L3 12l5.813 1.912a2 2 0 011.275 1.275L12 21l1.912-5.813a2 2 0 011.275-1.275L21 12l-5.813-1.912a2 2 0 01-1.275-1.275L12 3Z"/></svg>'],
                        ['title' => __('Build Workflows'), 'desc' => __('Visual no-code builder. Auto-tag contacts, send follow-ups, trigger campaigns based on any event.'), 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6z"/></svg>'],
                        ['title' => __('Scale & Grow'), 'desc' => __('Launch campaigns, track analytics, close deals. Your business runs on autopilot while revenue grows.'), 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>'],
                    ] as $index => $step)
                    <div x-data="{ shown: false }" x-intersect="shown = true"
                         style="transition-delay: {{ $index * 100 }}ms"
                         class="flex flex-col items-center text-center group transition-all duration-700 transform"
                         :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'">
                        <div class="h-28 w-28 rounded-[2rem] bg-surface-2 border border-brand/20 flex items-center justify-center mb-8 relative shadow-2xl group-hover:scale-110 group-hover:border-brand/50 transition-all duration-500">
                            {!! $step['icon'] !!}
                            <div class="absolute -top-3 -right-3 h-10 w-10 rounded-2xl bg-brand text-white flex items-center justify-center font-black text-lg border-4 border-surface shadow-lg">
                                {{ $index + 1 }}
                            </div>
                        </div>
                        <h3 class="text-xl font-bold text-ink mb-3 group-hover:text-brand transition-colors">{{ $step['title'] }}</h3>
                        <p class="text-sm text-muted leading-relaxed">{{ $step['desc'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         4. FEATURES SECTION
    ═══════════════════════════════════════════════════════════════ --}}
    <section id="features" class="py-20 lg:py-28">
        <div class="max-w-7xl mx-auto px-4">
            {{-- Section Header --}}
            <div class="text-center max-w-2xl mx-auto mb-16 lg:mb-20"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ __('Features') }}</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                    {{ __('Everything you need to') }}<br class="hidden sm:block"> <span class="gradient-text">{{ __('master communication') }}</span>
                </h2>
                <p class="mt-4 text-lg text-muted">{{ __('Powerful tools that work together seamlessly to automate, optimize, and scale your outreach.') }}</p>
            </div>

            {{-- Feature 1: Unified Inbox --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center mb-20 lg:mb-28"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z"/></svg>
                        {{ __('Omnichannel') }}
                    </div>
                    <h3 class="text-2xl lg:text-3xl font-bold text-ink tracking-tight">{{ __('Unified Inbox for Every Channel') }}</h3>
                    <p class="mt-4 text-base lg:text-lg text-muted leading-relaxed">{{ __('Manage emails, WhatsApp, SMS, Telegram, Slack, and live chat from one beautiful inbox. AI drafts replies automatically so your team responds faster.') }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach([__('Multi-channel in one view'), __('AI-drafted replies'), __('Smart routing & assignment')] as $item)
                        <li class="flex items-center gap-3 text-sm text-ink font-medium">
                            <div class="w-5 h-5 rounded-full bg-success/10 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                <div class="feature-visual rounded-2xl bg-surface-2 border border-border/60 shadow-soft overflow-hidden">
                    {{-- Inbox Mockup --}}
                    <div class="px-4 py-3 border-b border-border/40 flex items-center justify-between bg-surface/40">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-ink">{{ __('All Channels') }}</span>
                            <span class="text-xs bg-brand/10 text-brand px-2 py-0.5 rounded-full">24</span>
                        </div>
                        <div class="flex gap-1">
                            @foreach(['Email', 'WhatsApp', 'SMS'] as $ch)
                            <span class="text-xs px-2 py-1 rounded-lg {{ $loop->first ? 'bg-brand/10 text-brand' : 'text-muted' }} font-medium">{{ $ch }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="divide-y divide-border/30">
                        @foreach([
                            ['name' => 'Alex Kim', 'msg' => __('Can we schedule a demo for our team?'), 'channel' => __('Email'), 'color' => 'bg-brand', 'badge' => __('AI Draft Ready')],
                            ['name' => 'Priya Patel', 'msg' => __('Thanks! The integration is working perfectly.'), 'channel' => __('WhatsApp'), 'color' => 'bg-success', 'badge' => ''],
                            ['name' => 'Tom Anderson', 'msg' => __('Please send the pricing PDF.'), 'channel' => __('SMS'), 'color' => 'bg-warning', 'badge' => __('AI Draft Ready')],
                            ['name' => 'Emily Davis', 'msg' => __('Hey, what are your support hours?'), 'channel' => __('Telegram'), 'color' => 'bg-info', 'badge' => ''],
                        ] as $msg)
                        <div class="px-4 py-3 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full {{ $msg['color'] }}/15 flex items-center justify-center text-xs font-bold {{ str_replace('bg-', 'text-', $msg['color']) }} shrink-0">
                                {{ substr($msg['name'], 0, 1) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-ink truncate">{{ $msg['name'] }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-surface text-muted font-medium border border-border/40">{{ $msg['channel'] }}</span>
                                </div>
                                <p class="text-xs text-muted truncate">{{ $msg['msg'] }}</p>
                            </div>
                            @if($msg['badge'])
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-brand/10 text-brand font-medium whitespace-nowrap shrink-0 hidden sm:inline">{{ $msg['badge'] }}</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Feature 2: AI Smart Replies (reversed) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center mb-20 lg:mb-28"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <div class="order-2 lg:order-1 feature-visual rounded-2xl bg-surface-2 border border-border/60 shadow-soft overflow-hidden">
                    {{-- AI Reply Mockup --}}
                    <div class="px-4 py-3 border-b border-border/40 bg-surface/40">
                        <span class="text-sm font-semibold text-ink">{{ __('AI Reply Assistant') }}</span>
                    </div>
                    <div class="p-4 space-y-4">
                        {{-- Incoming message --}}
                        <div class="flex gap-3">
                            <div class="w-8 h-8 rounded-full bg-brand/15 flex items-center justify-center text-xs font-bold text-brand shrink-0">J</div>
                            <div class="bg-surface rounded-2xl rounded-tl-md px-4 py-3 max-w-[85%]">
                                <p class="text-sm text-ink">{{ __("Hi, we're interested in the Enterprise plan. Can you share details about API limits and custom integrations?") }}</p>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <span class="text-[10px] text-muted">John Markovic</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-info/10 text-info font-medium">{{ __('Positive') }}</span>
                                </div>
                            </div>
                        </div>
                        {{-- AI Suggestion --}}
                        <div class="ml-11 border border-brand/30 rounded-2xl bg-brand/5 p-4 relative">
                            <div class="flex items-center gap-2 mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
                                <span class="text-xs font-semibold text-brand">{{ __('AI Suggested Reply') }}</span>
                                <span class="ml-auto text-[10px] px-2 py-0.5 rounded-full bg-success/10 text-success font-semibold">{{ __('94% confidence') }}</span>
                            </div>
                            <p class="text-sm text-ink leading-relaxed">{{ __('Hi John, thank you for your interest in our Enterprise plan! Our API allows up to 100K requests/day with custom webhooks, dedicated IP pools, and...') }}</p>
                            <div class="mt-3 flex gap-2">
                                <span class="text-xs px-3 py-1.5 rounded-lg bg-brand text-white font-medium cursor-pointer">{{ __('Send Reply') }}</span>
                                <span class="text-xs px-3 py-1.5 rounded-lg border border-border text-muted font-medium cursor-pointer">{{ __('Edit') }}</span>
                                <span class="text-xs px-3 py-1.5 rounded-lg border border-border text-muted font-medium cursor-pointer">{{ __('Regenerate') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-secondary-900/40 text-secondary-400 border border-secondary-800 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
                        {{ __('AI-Powered') }}
                    </div>
                    <h3 class="text-2xl lg:text-3xl font-bold text-ink tracking-tight">{{ __('AI-Powered Smart Replies') }}</h3>
                    <p class="mt-4 text-base lg:text-lg text-muted leading-relaxed">{{ __('Train AI with your knowledge base. Get context-aware reply suggestions with sentiment analysis and confidence scoring. Your AI gets smarter with every conversation.') }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach([__('Sentiment analysis & scoring'), __('Knowledge base training'), __('One-click send or edit')] as $item)
                        <li class="flex items-center gap-3 text-sm text-ink">
                            <div class="w-5 h-5 rounded-full bg-success/10 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Feature 3: Campaign Builder --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center mb-20 lg:mb-28"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-success/10 text-success border border-success/20 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                        {{ __('Campaigns') }}
                    </div>
                    <h3 class="text-2xl lg:text-3xl font-bold text-ink tracking-tight">{{ __('Campaign Builder with A/B Testing') }}</h3>
                    <p class="mt-4 text-base lg:text-lg text-muted leading-relaxed">{{ __('Create stunning email campaigns with drag-and-drop builder. Run A/B tests, drip sequences, and track opens, clicks, and conversions in real time.') }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach([__('Drag-and-drop editor'), __('A/B testing & drip sequences'), __('Real-time analytics dashboard')] as $item)
                        <li class="flex items-center gap-3 text-sm text-ink">
                            <div class="w-5 h-5 rounded-full bg-success/10 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                <div class="feature-visual rounded-2xl bg-surface-2 border border-border/60 shadow-soft overflow-hidden">
                    {{-- Campaign Analytics Mockup --}}
                    <div class="px-4 py-3 border-b border-border/40 bg-surface/40 flex items-center justify-between">
                        <span class="text-sm font-semibold text-ink">{{ __('Campaign: Spring Launch') }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-success/10 text-success font-medium">{{ __('Active') }}</span>
                    </div>
                    <div class="p-4 space-y-4">
                        {{-- Mini chart bars --}}
                        <div class="grid grid-cols-3 gap-3">
                            <div class="text-center p-3 rounded-xl bg-surface border border-border/40">
                                <div class="text-lg font-bold text-ink">68.4%</div>
                                <div class="text-[10px] text-muted font-medium mt-0.5">{{ __('Open Rate') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-xl bg-surface border border-border/40">
                                <div class="text-lg font-bold text-ink">24.1%</div>
                                <div class="text-[10px] text-muted font-medium mt-0.5">{{ __('Click Rate') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-xl bg-surface border border-border/40">
                                <div class="text-lg font-bold text-ink">3.8%</div>
                                <div class="text-[10px] text-muted font-medium mt-0.5">{{ __('Conversion') }}</div>
                            </div>
                        </div>
                        {{-- A/B Test Visual --}}
                        <div class="rounded-xl bg-surface border border-border/40 p-3">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-semibold text-ink">{{ __('A/B Test Results') }}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-brand/10 text-brand font-medium">{{ __('Winner: Variant A') }}</span>
                            </div>
                            <div class="space-y-2">
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="text-muted">{{ __('Variant A') }}</span>
                                        <span class="font-semibold text-brand">68.4%</span>
                                    </div>
                                    <div class="h-2 bg-surface-2 rounded-full overflow-hidden border border-border/20">
                                        <div class="h-full w-[68%] bg-brand rounded-full"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="text-muted">{{ __('Variant B') }}</span>
                                        <span class="font-semibold text-muted">52.1%</span>
                                    </div>
                                    <div class="h-2 bg-surface-2 rounded-full overflow-hidden border border-border/20">
                                        <div class="h-full w-[52%] bg-muted/40 rounded-full"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Send count --}}
                        <div class="flex items-center justify-between px-3 py-2 rounded-xl bg-surface border border-border/40">
                            <span class="text-xs text-muted">{{ __('Total Sent') }}</span>
                            <span class="text-sm font-bold text-ink">12,481</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Feature 4: Workflow Automation (reversed) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <div class="order-2 lg:order-1 feature-visual rounded-2xl bg-surface-2 border border-border/60 shadow-soft overflow-hidden">
                    {{-- Workflow Mockup --}}
                    <div class="px-4 py-3 border-b border-border/40 bg-surface/40 flex items-center justify-between">
                        <span class="text-sm font-semibold text-ink">{{ __('Welcome Sequence') }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-success/10 text-success font-medium">{{ __('Active') }}</span>
                    </div>
                    <div class="p-6">
                        {{-- Workflow nodes --}}
                        <div class="space-y-3">
                            {{-- Trigger --}}
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-brand/10 border border-brand/20 flex items-center justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/></svg>
                                </div>
                                <div class="flex-1 rounded-xl bg-surface border border-border/40 px-4 py-2.5">
                                    <div class="text-xs font-semibold text-ink">{{ __('Trigger: New Signup') }}</div>
                                    <div class="text-[10px] text-muted">{{ __('When a contact registers') }}</div>
                                </div>
                            </div>
                            {{-- Connector --}}
                            <div class="ml-5 w-px h-4 bg-border/60"></div>
                            {{-- Wait --}}
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-warning/10 border border-warning/20 flex items-center justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                </div>
                                <div class="flex-1 rounded-xl bg-surface border border-border/40 px-4 py-2.5">
                                    <div class="text-xs font-semibold text-ink">{{ __('Wait: 5 minutes') }}</div>
                                    <div class="text-[10px] text-muted">{{ __('Delay before next action') }}</div>
                                </div>
                            </div>
                            {{-- Connector --}}
                            <div class="ml-5 w-px h-4 bg-border/60"></div>
                            {{-- Send email --}}
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-success/10 border border-success/20 flex items-center justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                                </div>
                                <div class="flex-1 rounded-xl bg-surface border border-border/40 px-4 py-2.5">
                                    <div class="text-xs font-semibold text-ink">{{ __('Send: Welcome Email') }}</div>
                                    <div class="text-[10px] text-muted">{{ __('Personalized onboarding message') }}</div>
                                </div>
                            </div>
                            {{-- Connector --}}
                            <div class="ml-5 w-px h-4 bg-border/60"></div>
                            {{-- Condition --}}
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-secondary-900/40 border border-secondary-800 flex items-center justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-secondary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"/></svg>
                                </div>
                                <div class="flex-1 rounded-xl bg-surface border border-border/40 px-4 py-2.5">
                                    <div class="text-xs font-semibold text-ink">{{ __('Condition: Opened Email?') }}</div>
                                    <div class="text-[10px] text-muted">{{ __('Branch: Yes / No') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-warning/10 text-warning border border-warning/20 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/></svg>
                        {{ __('Automation') }}
                    </div>
                    <h3 class="text-2xl lg:text-3xl font-bold text-ink tracking-tight">{{ __('Visual Workflow Automation') }}</h3>
                    <p class="mt-4 text-base lg:text-lg text-muted leading-relaxed">{{ __('Build powerful automations without code. Auto-tag contacts, send follow-ups, and trigger actions based on any event. Set it once, let it run forever.') }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach([__('No-code visual builder'), __('Event-based triggers'), __('Conditional branching & delays')] as $item)
                        <li class="flex items-center gap-3 text-sm text-ink">
                            <div class="w-5 h-5 rounded-full bg-success/10 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Feature 5: Contact & Lead CRM --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center mt-20 lg:mt-28"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-info/10 text-info border border-info/20 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                        {{ __('CRM') }}
                    </div>
                    <h3 class="text-2xl lg:text-3xl font-bold text-ink tracking-tight">{{ __('Contact & Lead Management') }}</h3>
                    <p class="mt-4 text-base lg:text-lg text-muted leading-relaxed">{{ __('Full CRM with lead scoring, custom fields, dynamic segments, and tags. Import thousands of contacts from CSV/Excel and track every interaction.') }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach([__('Lead scoring & custom fields'), __('Dynamic segments & smart tags'), __('CSV/Excel import & export'), __('Contact activity timeline')] as $item)
                        <li class="flex items-center gap-3 text-sm text-ink">
                            <div class="w-5 h-5 rounded-full bg-success/10 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                <div class="feature-visual rounded-2xl bg-surface-2 border border-border/60 shadow-soft overflow-hidden">
                    <div class="px-4 py-3 border-b border-border/40 bg-surface/40 flex items-center justify-between">
                        <span class="text-sm font-semibold text-ink">{{ __('Contacts') }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-info/10 text-info font-medium">{{ __('8,431 total') }}</span>
                    </div>
                    <div class="divide-y divide-border/30">
                        @foreach([
                            ['name' => 'Sarah Chen', 'email' => 'sarah@techflow.io', 'score' => '92', 'tag' => __('Hot Lead')],
                            ['name' => 'Marcus Rivera', 'email' => 'marcus@growthstack.co', 'score' => '78', 'tag' => __('Enterprise')],
                            ['name' => 'Emily Watson', 'email' => 'emily@vertexlabs.com', 'score' => '65', 'tag' => __('Nurture')],
                        ] as $contact)
                        <div class="px-4 py-3 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-brand/15 flex items-center justify-center text-xs font-bold text-brand shrink-0">{{ substr($contact['name'], 0, 1) }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-ink truncate">{{ $contact['name'] }}</div>
                                <div class="text-xs text-muted truncate">{{ $contact['email'] }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-xs font-bold text-brand">{{ $contact['score'] }}%</div>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-brand/10 text-brand font-medium">{{ $contact['tag'] }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Feature 6: Deal & Pipeline (reversed) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center mt-20 lg:mt-28"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <div class="order-2 lg:order-1 feature-visual rounded-2xl bg-surface-2 border border-border/60 shadow-soft overflow-hidden">
                    <div class="px-4 py-3 border-b border-border/40 bg-surface/40 flex items-center justify-between">
                        <span class="text-sm font-semibold text-ink">{{ __('Sales Pipeline') }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-success/10 text-success font-medium">{{ __('$124K total') }}</span>
                    </div>
                    <div class="p-4">
                        <div class="flex gap-3 overflow-x-auto pb-2">
                            @foreach([
                                ['stage' => __('Qualified'), 'deals' => '12', 'value' => '$34K', 'color' => 'brand'],
                                ['stage' => __('Proposal'), 'deals' => '8', 'value' => '$52K', 'color' => 'warning'],
                                ['stage' => __('Negotiation'), 'deals' => '5', 'value' => '$28K', 'color' => 'info'],
                                ['stage' => __('Won'), 'deals' => '3', 'value' => '$10K', 'color' => 'success'],
                            ] as $stage)
                            <div class="flex-1 min-w-[80px] rounded-xl bg-surface border border-border/40 p-3 text-center">
                                <div class="text-[10px] text-muted font-medium mb-1">{{ $stage['stage'] }}</div>
                                <div class="text-sm font-bold text-{{ $stage['color'] }}">{{ $stage['value'] }}</div>
                                <div class="text-[10px] text-muted">{{ $stage['deals'] }} {{ __('deals') }}</div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-success/10 text-success border border-success/20 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5"/></svg>
                        {{ __('Sales') }}
                    </div>
                    <h3 class="text-2xl lg:text-3xl font-bold text-ink tracking-tight">{{ __('Deal & Pipeline Management') }}</h3>
                    <p class="mt-4 text-base lg:text-lg text-muted leading-relaxed">{{ __('Visual kanban pipelines with customizable stages. Track deal values, win probability, expected close dates, and lost reasons.') }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach([__('Drag-and-drop kanban board'), __('Win probability per stage'), __('Revenue tracking & forecasting'), __('Custom deal fields & notes')] as $item)
                        <li class="flex items-center gap-3 text-sm text-ink">
                            <div class="w-5 h-5 rounded-full bg-success/10 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Feature 7: Knowledge Base & RAG --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center mt-20 lg:mt-28"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                        {{ __('Knowledge') }}
                    </div>
                    <h3 class="text-2xl lg:text-3xl font-bold text-ink tracking-tight">{{ __('Knowledge Base & RAG') }}</h3>
                    <p class="mt-4 text-base lg:text-lg text-muted leading-relaxed">{{ __('Train your AI on documents, websites, and files. Vector search with Pinecone ensures accurate, context-aware responses every time.') }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach([__('Upload docs, PDFs & websites'), __('Auto-chunking & vector embeddings'), __('Pinecone or MySQL fulltext search'), __('Priority documents & usage tracking')] as $item)
                        <li class="flex items-center gap-3 text-sm text-ink">
                            <div class="w-5 h-5 rounded-full bg-success/10 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                <div class="feature-visual rounded-2xl bg-surface-2 border border-border/60 shadow-soft overflow-hidden">
                    <div class="px-4 py-3 border-b border-border/40 bg-surface/40">
                        <span class="text-sm font-semibold text-ink">{{ __('Knowledge Base') }}</span>
                    </div>
                    <div class="p-4 space-y-3">
                        @foreach([
                            ['name' => __('Product Documentation'), 'type' => __('Website'), 'chunks' => '142', 'status' => __('Indexed')],
                            ['name' => __('FAQ & Support Guide'), 'type' => __('PDF'), 'chunks' => '89', 'status' => __('Indexed')],
                            ['name' => __('Pricing & Plans'), 'type' => __('Text'), 'chunks' => '24', 'status' => __('Priority')],
                        ] as $doc)
                        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-surface border border-border/40">
                            <div class="w-8 h-8 rounded-lg bg-brand/10 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-ink truncate">{{ $doc['name'] }}</div>
                                <div class="text-[10px] text-muted">{{ $doc['type'] }} · {{ $doc['chunks'] }} {{ __('chunks') }}</div>
                            </div>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-success/10 text-success font-medium shrink-0">{{ $doc['status'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Feature 8: Analytics (reversed) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center mt-20 lg:mt-28"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <div class="order-2 lg:order-1 feature-visual rounded-2xl bg-surface-2 border border-border/60 shadow-soft overflow-hidden">
                    <div class="px-4 py-3 border-b border-border/40 bg-surface/40">
                        <span class="text-sm font-semibold text-ink">{{ __('Dashboard Overview') }}</span>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            @foreach([
                                ['label' => __('Conversations'), 'value' => '2,841', 'change' => '+18%'],
                                ['label' => __('Response Time'), 'value' => __('< 2min'), 'change' => '-42%'],
                                ['label' => __('AI Accuracy'), 'value' => '94.2%', 'change' => '+5%'],
                                ['label' => __('Team Load'), 'value' => __('Balanced'), 'change' => __('Optimal')],
                            ] as $metric)
                            <div class="rounded-xl bg-surface border border-border/40 p-3">
                                <div class="text-[10px] text-muted font-medium">{{ $metric['label'] }}</div>
                                <div class="text-sm font-bold text-ink mt-0.5">{{ $metric['value'] }}</div>
                                <div class="text-[10px] text-success font-medium">{{ $metric['change'] }}</div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-warning/10 text-warning border border-warning/20 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                        {{ __('Analytics') }}
                    </div>
                    <h3 class="text-2xl lg:text-3xl font-bold text-ink tracking-tight">{{ __('Real-Time Analytics & Reports') }}</h3>
                    <p class="mt-4 text-base lg:text-lg text-muted leading-relaxed">{{ __('Comprehensive dashboards for conversations, campaigns, AI performance, and team productivity. Make data-driven decisions instantly.') }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach([__('Conversation & campaign analytics'), __('AI performance & cost tracking'), __('Team productivity metrics'), __('Period filtering & trend comparison')] as $item)
                        <li class="flex items-center gap-3 text-sm text-ink">
                            <div class="w-5 h-5 rounded-full bg-success/10 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Feature 9: Team & Workspaces --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center mt-20 lg:mt-28"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-info/10 text-info border border-info/20 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477"/></svg>
                        {{ __('Team') }}
                    </div>
                    <h3 class="text-2xl lg:text-3xl font-bold text-ink tracking-tight">{{ __('Multi-Workspace & Team Management') }}</h3>
                    <p class="mt-4 text-base lg:text-lg text-muted leading-relaxed">{{ __('Create multiple workspaces with role-based access. Invite team members as owners, admins, agents, or viewers. Smart assignment routing.') }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach([__('Owner, admin, agent & viewer roles'), __('Token-based team invitations'), __('Smart conversation assignment'), __('Per-workspace data isolation')] as $item)
                        <li class="flex items-center gap-3 text-sm text-ink">
                            <div class="w-5 h-5 rounded-full bg-success/10 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                <div class="feature-visual rounded-2xl bg-surface-2 border border-border/60 shadow-soft overflow-hidden">
                    <div class="px-4 py-3 border-b border-border/40 bg-surface/40">
                        <span class="text-sm font-semibold text-ink">{{ __('Team Members') }}</span>
                    </div>
                    <div class="divide-y divide-border/30">
                        @foreach([
                            ['name' => __('You'), 'role' => __('Owner'), 'color' => 'brand', 'status' => 'Online'],
                            ['name' => 'Alex Kim', 'role' => __('Admin'), 'color' => 'info', 'status' => 'Online'],
                            ['name' => 'Priya Patel', 'role' => __('Agent'), 'color' => 'success', 'status' => 'Away'],
                            ['name' => 'Tom Anderson', 'role' => __('Viewer'), 'color' => 'muted', 'status' => 'Offline'],
                        ] as $member)
                        <div class="px-4 py-3 flex items-center gap-3">
                            <div class="relative">
                                <div class="w-9 h-9 rounded-full bg-{{ $member['color'] }}/15 flex items-center justify-center text-xs font-bold text-{{ $member['color'] }} shrink-0">{{ substr($member['name'], 0, 1) }}</div>
                                <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-surface-2 {{ $member['status'] === 'Online' ? 'bg-success' : ($member['status'] === 'Away' ? 'bg-warning' : 'bg-muted/40') }}"></div>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-semibold text-ink">{{ $member['name'] }}</div>
                                <div class="text-[10px] text-muted">{{ __($member['status']) }}</div>
                            </div>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-{{ $member['color'] }}/10 text-{{ $member['color'] }} font-medium">{{ $member['role'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Feature 10: Security & Payments (reversed) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center mt-20 lg:mt-28"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <div class="order-2 lg:order-1 feature-visual rounded-2xl bg-surface-2 border border-border/60 shadow-soft overflow-hidden">
                    <div class="px-4 py-3 border-b border-border/40 bg-surface/40">
                        <span class="text-sm font-semibold text-ink">{{ __('Security & Billing') }}</span>
                    </div>
                    <div class="p-4 space-y-3">
                        @foreach([
                            ['label' => __('Two-Factor Auth'), 'status' => __('Enabled'), 'icon' => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z', 'color' => 'success'],
                            ['label' => __('DDoS Protection'), 'status' => __('Active'), 'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z', 'color' => 'success'],
                            ['label' => __('GDPR Compliance'), 'status' => __('Compliant'), 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'success'],
                            ['label' => __('Payment Gateways'), 'status' => __('30+ Active'), 'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z', 'color' => 'brand'],
                        ] as $sec)
                        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-surface border border-border/40">
                            <div class="w-8 h-8 rounded-lg bg-{{ $sec['color'] }}/10 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-{{ $sec['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sec['icon'] }}"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs font-semibold text-ink">{{ $sec['label'] }}</div>
                            </div>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-{{ $sec['color'] }}/10 text-{{ $sec['color'] }} font-medium">{{ $sec['status'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-danger/10 text-danger border border-danger/20 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751"/></svg>
                        {{ __('Security') }}
                    </div>
                    <h3 class="text-2xl lg:text-3xl font-bold text-ink tracking-tight">{{ __('Enterprise Security & 30+ Payment Gateways') }}</h3>
                    <p class="mt-4 text-base lg:text-lg text-muted leading-relaxed">{{ __('Bank-grade security with 2FA, IP blocking, rate limiting, DDoS protection, and GDPR compliance. Accept payments via Stripe, PayPal, Razorpay, and 27+ more.') }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach([__('2FA, IP blocking & honeypot protection'), __('Encrypted secrets & audit logging'), __('GDPR data export & account deletion'), __('Stripe, PayPal, Razorpay + 27 more')] as $item)
                        <li class="flex items-center gap-3 text-sm text-ink">
                            <div class="w-5 h-5 rounded-full bg-success/10 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         4.5 ALL FEATURES — Comprehensive Feature Showcase
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="py-20 lg:py-28 border-t border-border/30">
        <div class="max-w-7xl mx-auto px-4"
             x-data="{ active: 0 }" x-intersect:enter.once="$el.classList.add('animate-fade-up')">
            <div class="text-center max-w-2xl mx-auto mb-12 lg:mb-16">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand animate-pulse"></span>
                    {{ __('20+ Built-in Features') }}
                </span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                    {{ __('One platform,') }}<br class="hidden sm:block"> <span class="gradient-text">{{ __('every tool you need') }}</span>
                </h2>
                <p class="mt-4 text-lg text-muted">{{ __('AI automation, CRM, multi-channel inbox, campaigns, and 30+ payment gateways — all in one self-hosted platform.') }}</p>
            </div>

            {{-- Category Tabs --}}
            <div class="flex flex-wrap justify-center gap-2 mb-10">
                @foreach([__('All'), __('Communication'), __('CRM & Sales'), __('AI & Automation'), __('Admin & Security')] as $i => $tab)
                <button @click="active = {{ $i }}" class="px-4 py-2 rounded-xl text-sm font-semibold transition-all"
                        :class="active === {{ $i }} ? 'bg-brand text-white shadow-lg shadow-brand/20' : 'text-muted hover:text-ink bg-surface-2 border border-border/50 hover:border-brand/30'">{{ $tab }}</button>
                @endforeach
            </div>

            {{-- Features Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach([
                    ['cat' => [0,1], 'title' => __('Unified Inbox'), 'desc' => __('6 channels — Email, WhatsApp, SMS, Telegram, Slack & Live Chat in one view.'), 'badge' => __('6 Channels'), 'badgeColor' => 'brand'],
                    ['cat' => [0,3], 'title' => __('AI Smart Replies'), 'desc' => __('GPT-4o, Claude, Gemini & Mistral. Sentiment analysis, confidence scoring, one-click send.'), 'badge' => __('4 AI Models'), 'badgeColor' => 'secondary-500'],
                    ['cat' => [0,2], 'title' => __('Contact & Lead CRM'), 'desc' => __('Lead scoring, custom fields, tags, dynamic segments, and full import/export.'), 'badge' => __('CRM'), 'badgeColor' => 'info'],
                    ['cat' => [0,2], 'title' => __('Deal & Pipeline'), 'desc' => __('Visual kanban board with stages, win probability, close dates & revenue tracking.'), 'badge' => __('Kanban'), 'badgeColor' => 'success'],
                    ['cat' => [0,1], 'title' => __('Campaign Builder'), 'desc' => __('A/B testing, drip sequences, scheduling, open & click tracking with analytics.'), 'badge' => __('A/B Test'), 'badgeColor' => 'warning'],
                    ['cat' => [0,3], 'title' => __('Workflow Automation'), 'desc' => __('No-code visual builder with triggers, conditions, delays, branching & webhooks.'), 'badge' => __('No-Code'), 'badgeColor' => 'danger'],
                    ['cat' => [0,3], 'title' => __('Knowledge Base & RAG'), 'desc' => __('Train AI on docs, websites & files. Vector search with Pinecone for accurate replies.'), 'badge' => __('RAG'), 'badgeColor' => 'brand'],
                    ['cat' => [0,2], 'title' => __('Analytics & Reports'), 'desc' => __('Real-time dashboards for conversations, campaigns, AI performance & team metrics.'), 'badge' => __('Real-time'), 'badgeColor' => 'success'],
                    ['cat' => [0,4], 'title' => __('Team & Workspaces'), 'desc' => __('Multi-workspace. Owner, admin, agent & viewer roles. Invite system & assignment.'), 'badge' => __('Multi-Tenant'), 'badgeColor' => 'info'],
                    ['cat' => [0,1], 'title' => __('Live Chat Widget'), 'desc' => __('Embeddable widget for your website with real-time Pusher-powered messaging.'), 'badge' => __('Embed'), 'badgeColor' => 'secondary-500'],
                    ['cat' => [0,1], 'title' => __('Disposable Email'), 'desc' => __('Temporary email addresses on custom domains for testing, signups & privacy.'), 'badge' => __('Privacy'), 'badgeColor' => 'warning'],
                    ['cat' => [0,4], 'title' => __('Enterprise Security'), 'desc' => __('2FA, IP blocking, rate limiting, DDoS protection, honeypot & audit logs.'), 'badge' => __('2FA'), 'badgeColor' => 'danger'],
                    ['cat' => [0,4], 'title' => __('30+ Payment Gateways'), 'desc' => __('Stripe, PayPal, Razorpay, Paddle, Mollie & more. Subscriptions, trials & coupons.'), 'badge' => '30+', 'badgeColor' => 'success'],
                    ['cat' => [0,4], 'title' => __('REST API & Webhooks'), 'desc' => __('Full API with Sanctum auth, scoped abilities, webhook logs & Zapier.'), 'badge' => __('API v1'), 'badgeColor' => 'brand'],
                    ['cat' => [0,4], 'title' => __('Multi-Language'), 'desc' => __('Full i18n with language switcher, RTL layout support & multi-currency billing.'), 'badge' => __('i18n'), 'badgeColor' => 'info'],
                    ['cat' => [0,1], 'title' => __('Email Templates'), 'desc' => __('Reusable HTML & text templates, signatures, canned responses & auto-reply rules.'), 'badge' => __('Templates'), 'badgeColor' => 'warning'],
                    ['cat' => [0,4], 'title' => __('GDPR & Data Privacy'), 'desc' => __('Data export, account deletion, consent management & retention policies.'), 'badge' => __('GDPR'), 'badgeColor' => 'secondary-500'],
                    ['cat' => [0,4], 'title' => __('Admin Control Panel'), 'desc' => __('User management, CMS pages, plan management, audit logs & system settings.'), 'badge' => __('Admin'), 'badgeColor' => 'danger'],
                    ['cat' => [0,3], 'title' => __('Drip Sequences'), 'desc' => __('Multi-step automated email sequences with delays, conditions & enrollment tracking.'), 'badge' => __('Drip'), 'badgeColor' => 'brand'],
                    ['cat' => [0,2], 'title' => __('Import & Export'), 'desc' => __('CSV & Excel import/export for contacts. Full GDPR-compliant workspace data export.'), 'badge' => __('CSV/XLSX'), 'badgeColor' => 'info'],
                ] as $i => $f)
                <div x-show="active === 0 @foreach($f['cat'] as $c)|| active === {{ $c }} @endforeach"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     class="group relative rounded-2xl bg-surface-2/80 border border-border/50 p-5 hover:border-brand/30 hover:shadow-xl hover:shadow-brand/5 transition-all duration-300 cursor-default">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-{{ $f['badgeColor'] }}/10 text-{{ $f['badgeColor'] }}">{{ $f['badge'] }}</span>
                    </div>
                    <h3 class="text-sm font-bold text-ink mb-1.5 group-hover:text-brand transition-colors">{{ $f['title'] }}</h3>
                    <p class="text-xs text-muted leading-relaxed">{{ $f['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         5. INTEGRATIONS SECTION
    ═══════════════════════════════════════════════════════════════ --}}
    <section id="integrations" class="py-20 lg:py-28 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-brand/5 to-transparent -z-10" aria-hidden="true"></div>
        <div class="max-w-7xl mx-auto px-4 text-center"
             x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ __('Integrations') }}</span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                {{ __('Connects with tools') }} <span class="gradient-text">{{ __('you already use') }}</span>
            </h2>
            <p class="mt-4 text-lg text-muted max-w-xl mx-auto">{{ __('Seamless integrations with the platforms your team relies on every day.') }}</p>
        </div>

        {{-- Infinite scrolling marquee - Row 1 (left to right) --}}
        <div class="mt-14 relative">
            {{-- Fade edges --}}
            <div class="absolute left-0 top-0 bottom-0 w-24 bg-gradient-to-r from-surface to-transparent z-10 pointer-events-none"></div>
            <div class="absolute right-0 top-0 bottom-0 w-24 bg-gradient-to-l from-surface to-transparent z-10 pointer-events-none"></div>

            <div class="flex gap-5 animate-[marqueeLeft_30s_linear_infinite]" style="width: max-content;">
                @for($r = 0; $r < 2; $r++)
                @foreach([
                    ['name' => 'Gmail', 'abbr' => 'Gm', 'color' => '#EA4335', 'desc' => __('Email sync & send')],
                    ['name' => 'Outlook', 'abbr' => 'Ou', 'color' => '#0078D4', 'desc' => __('Microsoft 365')],
                    ['name' => 'Salesforce', 'abbr' => 'Sf', 'color' => '#00A1E0', 'desc' => __('CRM sync')],
                    ['name' => 'HubSpot', 'abbr' => 'Hs', 'color' => '#FF7A59', 'desc' => __('Marketing hub')],
                    ['name' => 'Slack', 'abbr' => 'Sl', 'color' => '#4A154B', 'desc' => __('Team messaging')],
                ] as $int)
                <div class="group flex items-center gap-4 px-6 py-4 rounded-2xl bg-surface-2 border border-border/50 hover:border-brand/30 hover:shadow-lg transition-all duration-300 cursor-pointer shrink-0 w-64">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-3" style="background: {{ $int['color'] }}12;">
                        <span class="text-lg font-bold" style="color: {{ $int['color'] }};">{{ $int['abbr'] }}</span>
                    </div>
                    <div class="text-left min-w-0">
                        <p class="text-sm font-semibold text-ink">{{ $int['name'] }}</p>
                        <p class="text-xs text-muted">{{ $int['desc'] }}</p>
                    </div>
                    <div class="ml-auto shrink-0">
                        <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                    </div>
                </div>
                @endforeach
                @endfor
            </div>
        </div>

        {{-- Infinite scrolling marquee - Row 2 (right to left) --}}
        <div class="mt-5 relative">
            <div class="absolute left-0 top-0 bottom-0 w-24 bg-gradient-to-r from-surface to-transparent z-10 pointer-events-none"></div>
            <div class="absolute right-0 top-0 bottom-0 w-24 bg-gradient-to-l from-surface to-transparent z-10 pointer-events-none"></div>

            <div class="flex gap-5 animate-[marqueeRight_35s_linear_infinite]" style="width: max-content;">
                @for($r = 0; $r < 2; $r++)
                @foreach([
                    ['name' => 'WhatsApp', 'abbr' => 'Wa', 'color' => '#25D366', 'desc' => __('Business chat')],
                    ['name' => 'Telegram', 'abbr' => 'Tg', 'color' => '#26A5E4', 'desc' => __('Bot integration')],
                    ['name' => 'Zapier', 'abbr' => 'Zp', 'color' => '#FF4A00', 'desc' => __('5,000+ apps')],
                    ['name' => 'Google Calendar', 'abbr' => 'Ca', 'color' => '#4285F4', 'desc' => __('Meeting sync')],
                    ['name' => 'Stripe', 'abbr' => 'St', 'color' => '#635BFF', 'desc' => __('Payments')],
                ] as $int)
                <div class="group flex items-center gap-4 px-6 py-4 rounded-2xl bg-surface-2 border border-border/50 hover:border-brand/30 hover:shadow-lg transition-all duration-300 cursor-pointer shrink-0 w-64">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3" style="background: {{ $int['color'] }}12;">
                        <span class="text-lg font-bold" style="color: {{ $int['color'] }};">{{ $int['abbr'] }}</span>
                    </div>
                    <div class="text-left min-w-0">
                        <p class="text-sm font-semibold text-ink">{{ $int['name'] }}</p>
                        <p class="text-xs text-muted">{{ $int['desc'] }}</p>
                    </div>
                    <div class="ml-auto shrink-0">
                        <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                    </div>
                </div>
                @endforeach
                @endfor
            </div>
        </div>

        {{-- Center badge --}}
        <div class="text-center mt-10" x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
            <a href="{{ url('/register') }}" class="inline-flex items-center gap-3 px-6 py-3 rounded-2xl bg-surface-2 border border-border/50 shadow-soft hover:shadow-lg hover:border-brand/30 transition-all duration-300 group">
                <div class="flex -space-x-2">
                    @foreach(['#EA4335','#0078D4','#25D366','#FF4A00','#635BFF'] as $c)
                    <div class="w-7 h-7 rounded-full border-2 border-surface flex items-center justify-center text-[9px] font-bold text-white shadow-sm" style="background-color:{{ $c }}">+</div>
                    @endforeach
                </div>
                <span class="text-sm font-semibold text-ink">{{ __('10+ integrations and growing') }}</span>
                <svg class="w-4 h-4 text-muted group-hover:text-brand group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         6. TESTIMONIALS — Infinite CSS Marquee (same as integrations)
    ═══════════════════════════════════════════════════════════════ --}}
    @php $__dbTestimonials = \App\Models\Testimonial::where('is_active', true)->latest()->get(); @endphp
    @if($__dbTestimonials->count())
    <section id="testimonials" class="py-20 lg:py-28 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center max-w-2xl mx-auto mb-12 lg:mb-16"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ __('Testimonials') }}</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                    {{ __('Loved by teams') }} <span class="gradient-text">{{ __('worldwide') }}</span>
                </h2>
                <p class="mt-4 text-lg text-muted">{{ __('See what our customers have to say about') }} {{ config('app.name') }}.</p>
            </div>
        </div>

        @php $__colors = ['bg-brand', 'bg-success', 'bg-secondary-500', 'bg-info', 'bg-warning', 'bg-danger']; @endphp

        {{-- Row 1: Scrolls left --}}
        <div class="relative">
            <div class="absolute left-0 top-0 bottom-0 w-24 bg-gradient-to-r from-surface to-transparent z-10 pointer-events-none"></div>
            <div class="absolute right-0 top-0 bottom-0 w-24 bg-gradient-to-l from-surface to-transparent z-10 pointer-events-none"></div>

            <div class="flex gap-6 animate-[marqueeLeft_40s_linear_infinite] hover:[animation-play-state:paused]" style="width: max-content;">
                @for($r = 0; $r < 3; $r++)
                @foreach($__dbTestimonials as $ti => $t)
                <div class="shrink-0 w-[340px] sm:w-[380px]">
                    <div class="h-full rounded-2xl bg-surface-2 border border-border/50 p-6 hover:border-brand/20 hover:shadow-lg transition-all duration-300">
                        <div class="flex gap-0.5 mb-4">
                            @for($s = 0; $s < ($t->rating ?? 5); $s++)
                            <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            @endfor
                        </div>
                        <p class="text-sm text-ink/75 leading-relaxed mb-5 italic line-clamp-4">"{{ $t->review }}"</p>
                        <div class="flex items-center gap-3 pt-4 border-t border-border/30">
                            @if($t->client_image)
                            <img src="{{ asset('storage/' . $t->client_image) }}" class="w-10 h-10 rounded-full object-cover" alt="{{ $t->client_name }}">
                            @else
                            <div class="w-10 h-10 rounded-full {{ $__colors[$ti % count($__colors)] }}/15 flex items-center justify-center text-sm font-bold {{ str_replace('bg-', 'text-', $__colors[$ti % count($__colors)]) }}">
                                {{ substr($t->client_name, 0, 1) }}
                            </div>
                            @endif
                            <div>
                                <div class="text-sm font-semibold text-ink">{{ $t->client_name }}</div>
                                @if($t->client_position)
                                <div class="text-xs text-muted">{{ $t->client_position }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
                @endfor
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         7. PRICING SECTION
    ═══════════════════════════════════════════════════════════════ --}}
    @if(!\App\Support\BillingPolicy::isFree())
<section id="pricing" class="py-20 lg:py-28 relative">
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-brand/5 to-transparent -z-10" aria-hidden="true"></div>
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center max-w-2xl mx-auto mb-12 lg:mb-16"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ __('Pricing') }}</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                    {{ __('Simple,') }} <span class="gradient-text">{{ __('transparent') }}</span> {{ __('pricing') }}
                </h2>
                <p class="mt-4 text-lg text-muted">{{ __("Start free, upgrade when you're ready. No hidden fees, cancel anytime.") }}</p>

                {{-- Billing Toggle --}}
                <div class="mt-8 inline-flex items-center gap-3 bg-surface-2 border border-border/50 rounded-2xl p-1.5"
                     x-data>
                    <button @click="billingCycle = 'monthly'"
                            :class="billingCycle === 'monthly' ? 'bg-brand text-white shadow-lg shadow-brand/20' : 'text-muted hover:text-ink'"
                            class="px-5 py-2 rounded-xl text-sm font-semibold transition-all">
                        {{ __('Monthly') }}
                    </button>
                    <button @click="billingCycle = 'yearly'"
                            :class="billingCycle === 'yearly' ? 'bg-brand text-white shadow-lg shadow-brand/20' : 'text-muted hover:text-ink'"
                            class="px-5 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2">
                        {{ __('Yearly') }}
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-success/20 text-success font-bold" :class="billingCycle === 'yearly' ? 'bg-white/20 text-white' : ''">{{ __('Save 20%') }}</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-6"
                 x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">

                @php
                $plans = [
                    [
                        'name' => __('Free'),
                        'monthly' => 0,
                        'yearly' => 0,
                        'desc' => __('For individuals getting started'),
                        'popular' => false,
                        'cta' => __('Start Free'),
                        'features' => [__('500 emails/month'), __('1 user'), __('1 channel (Email)'), __('Basic templates'), __('Community support')],
                        'disabled' => [__('AI replies'), __('Workflow automation'), __('A/B testing'), __('API access')],
                    ],
                    [
                        'name' => __('Starter'),
                        'monthly' => 29,
                        'yearly' => 23,
                        'desc' => __('For small teams scaling up'),
                        'popular' => false,
                        'cta' => __('Get Started'),
                        'features' => [__('5,000 emails/month'), __('3 users'), __('3 channels'), __('AI reply suggestions'), __('Basic workflows'), __('Email support')],
                        'disabled' => [__('A/B testing'), __('Custom integrations'), __('Dedicated IP')],
                    ],
                    [
                        'name' => __('Pro'),
                        'monthly' => 99,
                        'yearly' => 79,
                        'desc' => __('For growing businesses'),
                        'popular' => true,
                        'cta' => __('Get Started'),
                        'features' => [__('50,000 emails/month'), __('10 users'), __('All channels'), __('AI smart replies & training'), __('Advanced workflows'), __('A/B testing'), __('Campaign analytics'), __('Priority support')],
                        'disabled' => [__('Custom integrations'), __('Dedicated IP')],
                    ],
                    [
                        'name' => __('Enterprise'),
                        'monthly' => 199,
                        'yearly' => 159,
                        'desc' => __('For large organizations'),
                        'popular' => false,
                        'cta' => __('Contact Sales'),
                        'features' => [__('Unlimited emails'), __('Unlimited users'), __('All channels'), __('Custom AI training'), __('Advanced workflows'), __('A/B testing'), __('Custom integrations'), __('Dedicated IP pool'), __('API access (100K/day)'), __('Dedicated account manager')],
                        'disabled' => [],
                    ],
                ];
                @endphp

                @foreach($plans as $plan)
                <div class="relative rounded-2xl border bg-surface-2/80 p-6 flex flex-col {{ $plan['popular'] ? 'pricing-popular border-brand' : 'border-border/50' }}">
                    @if($plan['popular'])
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="px-4 py-1 rounded-full bg-brand text-white text-xs font-bold shadow-lg shadow-brand/30">{{ __('Most Popular') }}</span>
                    </div>
                    @endif

                    <div class="mb-5">
                        <h3 class="text-lg font-bold text-ink">{{ $plan['name'] }}</h3>
                        <p class="text-sm text-muted mt-1">{{ $plan['desc'] }}</p>
                    </div>

                    <div class="mb-6">
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-ink" x-text="billingCycle === 'monthly' ? '${{ $plan['monthly'] }}' : '${{ $plan['yearly'] }}'">
                                ${{ $plan['monthly'] }}
                            </span>
                            @if($plan['monthly'] > 0)
                            <span class="text-sm text-muted">{{ __('/mo') }}</span>
                            @endif
                        </div>
                        @if($plan['monthly'] > 0)
                        <p class="text-xs text-muted mt-1" x-show="billingCycle === 'yearly'">
                            {{ __('Billed annually') }} ($<span x-text="{{ $plan['yearly'] }} * 12">{{ $plan['yearly'] * 12 }}</span>{{ __('/year') }})
                        </p>
                        @endif
                    </div>

                    <div class="space-y-2.5 flex-1 mb-6">
                        @foreach($plan['features'] as $feature)
                        <div class="flex items-center gap-2.5 text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-success shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            <span class="text-ink/80">{{ $feature }}</span>
                        </div>
                        @endforeach
                        @foreach($plan['disabled'] as $feature)
                        <div class="flex items-center gap-2.5 text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            <span class="text-muted">{{ $feature }}</span>
                        </div>
                        @endforeach
                    </div>

                    <a href="{{ url('/register') }}"
                       class="block text-center px-5 py-3 rounded-xl text-sm font-semibold transition-all mt-auto {{ $plan['popular'] ? 'bg-brand text-white shadow-lg shadow-brand/20 hover:shadow-soft hover:brightness-110' : 'border border-border text-ink hover:border-brand/40 hover:bg-brand/5' }}">
                        {{ $plan['cta'] }}
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

    {{-- ═══════════════════════════════════════════════════════════════
         8. CTA SECTION — Dark, subtle, matches UI
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="py-20 lg:py-28">
        <div class="max-w-7xl mx-auto px-4" x-data x-intersect:enter.once="$el.classList.add('animate-fade-up')">
            <div class="rounded-3xl p-10 sm:p-14 lg:p-20 text-center relative overflow-hidden bg-surface-2 border border-border/50">
                {{-- Subtle dot grid --}}
                <div class="absolute inset-0 opacity-[0.03]" aria-hidden="true">
                    <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="cta-dots" width="24" height="24" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="currentColor"/></pattern></defs><rect width="100%" height="100%" fill="url(#cta-dots)"/></svg>
                </div>
                {{-- Subtle brand glow --}}
                <div class="absolute top-0 right-1/4 w-64 h-64 bg-brand/5 rounded-full blur-[100px]" aria-hidden="true"></div>
                <div class="absolute bottom-0 left-1/4 w-48 h-48 bg-secondary-500/5 rounded-full blur-[80px]" aria-hidden="true"></div>

                <div class="relative z-10">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-brand/10 border border-brand/20 mb-6">
                        <span class="relative flex h-2 w-2"><span class="animate-ping absolute inset-0 rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-400"></span></span>
                        <span class="text-sm font-medium text-muted">{{ $cta['badge'] ?? '' }}</span>
                    </div>

                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-ink tracking-tight leading-tight">
                        {{ $cta['title'] ?? __('Ready to automate your workflow?') }}
                    </h2>
                    <p class="mt-5 text-base sm:text-lg text-muted max-w-xl mx-auto leading-relaxed">
                        {{ $cta['subtitle'] ?? __('Join thousands of businesses using AI-powered automation.') }}
                    </p>

                    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                        <a href="{{ url($cta['cta_url'] ?? '/register') }}" class="group w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 text-base font-bold rounded-2xl bg-brand text-white shadow-lg shadow-brand/20 hover:shadow-soft hover:brightness-110 transition-all">
                            {{ $cta['cta_text'] ?? __('Get Started Free') }}
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                        @if(!\App\Support\BillingPolicy::isFree())<a href="#pricing" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 text-base font-semibold text-ink border border-border rounded-2xl hover:border-brand/40 hover:bg-brand/5 transition-all">
                            {{ __('View Pricing') }}
                        </a>@endif
                    </div>

                    <div class="mt-10 flex flex-wrap items-center justify-center gap-6 sm:gap-8">
                        @foreach([[__('SOC2 Compliant'),'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],[__('256-bit Encryption'),'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],[__('GDPR Ready'),'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],[__('99.9% Uptime'),'M13 10V3L4 14h7v7l9-11h-7z']] as $trust)
                        <div class="flex items-center gap-1.5 text-muted">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $trust[1] }}"/></svg>
                            <span class="text-xs font-medium">{{ $trust[0] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

@push('styles')
<style>
/* ── Scroll Reveal Animations ── */
.reveal-up { opacity: 0; transform: translateY(40px); transition: opacity 0.8s cubic-bezier(0.16,1,0.3,1), transform 0.8s cubic-bezier(0.16,1,0.3,1); }
.reveal-up.revealed { opacity: 1; transform: translateY(0); }

.reveal-left { opacity: 0; transform: translateX(-50px); transition: opacity 0.8s cubic-bezier(0.16,1,0.3,1), transform 0.8s cubic-bezier(0.16,1,0.3,1); }
.reveal-left.revealed { opacity: 1; transform: translateX(0); }

.reveal-right { opacity: 0; transform: translateX(50px); transition: opacity 0.8s cubic-bezier(0.16,1,0.3,1), transform 0.8s cubic-bezier(0.16,1,0.3,1); }
.reveal-right.revealed { opacity: 1; transform: translateX(0); }

.reveal-scale { opacity: 0; transform: scale(0.92); transition: opacity 0.8s cubic-bezier(0.16,1,0.3,1), transform 0.8s cubic-bezier(0.16,1,0.3,1); }
.reveal-scale.revealed { opacity: 1; transform: scale(1); }

/* Stagger children */
.reveal-stagger > * { opacity: 0; transform: translateY(24px); transition: opacity 0.6s cubic-bezier(0.16,1,0.3,1), transform 0.6s cubic-bezier(0.16,1,0.3,1); }
.reveal-stagger.revealed > *:nth-child(1) { transition-delay: 0ms; }
.reveal-stagger.revealed > *:nth-child(2) { transition-delay: 80ms; }
.reveal-stagger.revealed > *:nth-child(3) { transition-delay: 160ms; }
.reveal-stagger.revealed > *:nth-child(4) { transition-delay: 240ms; }
.reveal-stagger.revealed > *:nth-child(5) { transition-delay: 320ms; }
.reveal-stagger.revealed > *:nth-child(6) { transition-delay: 400ms; }
.reveal-stagger.revealed > * { opacity: 1; transform: translateY(0); }

@media (prefers-reduced-motion: reduce) {
    .reveal-up, .reveal-left, .reveal-right, .reveal-scale, .reveal-stagger > * {
        opacity: 1 !important; transform: none !important; transition: none !important;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Global scroll reveal for entire landing page ──
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) {
            if (e.isIntersecting) {
                e.target.classList.add('revealed');
                observer.unobserve(e.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });

    // Auto-tag sections, headings, cards, grids
    var sections = document.querySelectorAll('#features, #integrations, #testimonials, #pricing, #faq');
    sections.forEach(function(s) {
        // Section headers — fade up
        var headers = s.querySelectorAll('h2, h3');
        headers.forEach(function(h) {
            if (!h.closest('.reveal-up, .reveal-left, .reveal-right')) {
                h.classList.add('reveal-up');
                observer.observe(h);
            }
        });
        // Section intro text — fade up
        var intros = s.querySelectorAll(':scope > div > div > p');
        intros.forEach(function(p) {
            p.classList.add('reveal-up');
            p.style.transitionDelay = '100ms';
            observer.observe(p);
        });
    });

    // Feature deep-dive grids: alternate left/right
    var featureGrids = document.querySelectorAll('#features .grid.grid-cols-1.lg\\:grid-cols-2');
    featureGrids.forEach(function(grid, i) {
        var cols = grid.children;
        if (cols.length >= 2) {
            // text side
            var textSide = grid.querySelector('div:not(.feature-visual):not(.order-2):not([class*=order-2])');
            var mockupSide = grid.querySelector('.feature-visual, div.order-2, div[class*=order-2]');
            if (i % 2 === 0) {
                if (textSide) { textSide.classList.add('reveal-left'); observer.observe(textSide); }
                if (mockupSide) { mockupSide.classList.add('reveal-right'); observer.observe(mockupSide); }
            } else {
                if (textSide) { textSide.classList.add('reveal-right'); observer.observe(textSide); }
                if (mockupSide) { mockupSide.classList.add('reveal-left'); observer.observe(mockupSide); }
            }
        }
    });

    // How it works steps — already handled by Alpine x-intersect

    // All Features grid cards — stagger
    var allFeatGrid = document.querySelector('.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-3.xl\\:grid-cols-4');
    if (allFeatGrid) {
        allFeatGrid.classList.add('reveal-stagger');
        observer.observe(allFeatGrid);
    }

    // Integration marquee rows — scale reveal
    var marqueeRows = document.querySelectorAll('#integrations .flex.gap-5');
    marqueeRows.forEach(function(r) { r.classList.add('reveal-scale'); observer.observe(r); });

    // Pricing cards — stagger
    var pricingGrid = document.querySelector('#pricing .grid');
    if (pricingGrid) { pricingGrid.classList.add('reveal-stagger'); observer.observe(pricingGrid); }

    // CTA section — scale
    var ctaBox = document.querySelector('section:last-of-type .rounded-3xl');
    if (ctaBox) { ctaBox.classList.add('reveal-scale'); observer.observe(ctaBox); }

    // Any remaining [data-animate] from original code
    document.querySelectorAll('[data-animate]').forEach(function(el) {
        if (!el.classList.contains('reveal-up')) {
            el.classList.add('reveal-up');
            observer.observe(el);
        }
    });

    // ── Testimonial auto-scroll (same marquee as integrations, CSS-driven) ──
    // No JS needed — handled by CSS animation marqueeLeft
});
</script>
@endpush
@endsection
