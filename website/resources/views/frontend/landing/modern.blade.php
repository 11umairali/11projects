@extends('frontend.layout')

@php
    $siteName = \App\Models\SystemSetting::get('site_name', config('app.name', 'MailTrixy'));

    $hero = array_merge([
        'badge' => __('AI customer communication platform'),
        'title_line1' => $siteName,
        'title_highlight' => __('AI-powered'),
        'title_line2' => __('automation'),
        'subtitle' => __('Manage conversations, campaigns, CRM, and workflows from one fast AI workspace.'),
        'cta_text' => __('Get Started Free'),
        'cta_url' => '/register',
        'cta2_text' => __('See Features'),
        'cta2_url' => '#features',
    ], is_array($hero ?? null) ? $hero : []);

    $featureItems = $features['items'] ?? [];
    if (! is_array($featureItems) || count($featureItems) === 0) {
        $featureItems = [
            ['title' => __('Unified Inbox'), 'desc' => __('Email, WhatsApp, SMS, Slack, and Telegram in one place.')],
            ['title' => __('AI-Powered Replies'), 'desc' => __('Context-aware reply drafts trained on your knowledge base.')],
            ['title' => __('Email Campaigns'), 'desc' => __('Campaign builder, A/B testing, and drip sequences.')],
            ['title' => __('CRM & Deals'), 'desc' => __('Track contacts, pipelines, and lead activity.')],
            ['title' => __('Workflow Automation'), 'desc' => __('No-code triggers, routing, and follow-up automation.')],
            ['title' => __('Analytics Dashboard'), 'desc' => __('Measure opens, clicks, replies, and conversion health.')],
        ];
    }

    $testimonialItems = $testimonials['items'] ?? [];
    if (! is_array($testimonialItems) || count($testimonialItems) === 0) {
        $testimonialItems = [
            ['quote' => __('MailTrixy cut our support response time and gave every agent a clear next action.'), 'name' => 'Marcus Rivera', 'role' => __('CEO'), 'company' => 'GrowthStack'],
            ['quote' => __('The campaign workflow view is fast, readable, and easy for the team to operate every day.'), 'name' => 'Emily Watson', 'role' => __('Marketing Director'), 'company' => 'Vertex Labs'],
            ['quote' => __('Clean inbox automation, practical AI replies, and the integrations we needed from day one.'), 'name' => 'Raj Patel', 'role' => __('Lead Engineer'), 'company' => 'CloudNine'],
        ];
    }

    $faqItems = $faq['items'] ?? [];
    if (! is_array($faqItems)) {
        $faqItems = [];
    }

    $cta = array_merge([
        'badge' => __('Ready to automate'),
        'title' => __('Ready to automate your workflow?'),
        'subtitle' => __('Join teams using AI-powered automation to reply faster and grow revenue.'),
        'cta_text' => __('Get Started Free'),
        'cta_url' => '/register',
    ], is_array($cta ?? null) ? $cta : []);

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

    $showcaseTabs = [
        'inbox' => ['label' => __('Inbox'), 'title' => __('Priority inbox'), 'desc' => __('Every channel, every owner, every AI suggestion in one view.')],
        'ai' => ['label' => __('AI'), 'title' => __('Reply assistant'), 'desc' => __('Knowledge-backed drafts with confidence, tone, and next action.')],
        'campaigns' => ['label' => __('Campaigns'), 'title' => __('Campaign studio'), 'desc' => __('A/B testing, drip steps, scheduling, and live performance.')],
        'crm' => ['label' => __('CRM'), 'title' => __('Pipeline control'), 'desc' => __('Contacts, deals, tasks, tags, and revenue reporting together.')],
    ];

    $capabilities = [
        ['group' => 'comm', 'title' => __('Unified Inbox'), 'desc' => __('All customer channels in one team queue.'), 'tag' => __('6 channels'), 'class' => 'bg-brand/10 text-brand border-brand/20'],
        ['group' => 'ai', 'title' => __('AI Smart Replies'), 'desc' => __('Drafts trained on your business context.'), 'tag' => __('AI'), 'class' => 'bg-success/10 text-success border-success/20'],
        ['group' => 'sales', 'title' => __('Contact CRM'), 'desc' => __('Profiles, tags, custom fields, and segments.'), 'tag' => __('CRM'), 'class' => 'bg-info/10 text-info border-info/20'],
        ['group' => 'sales', 'title' => __('Deals Pipeline'), 'desc' => __('Kanban stages, values, notes, and win tracking.'), 'tag' => __('Sales'), 'class' => 'bg-warning/10 text-warning border-warning/20'],
        ['group' => 'comm', 'title' => __('Email Campaigns'), 'desc' => __('Builder, scheduling, opens, clicks, and reports.'), 'tag' => __('Campaign'), 'class' => 'bg-brand/10 text-brand border-brand/20'],
        ['group' => 'ai', 'title' => __('Workflow Builder'), 'desc' => __('Triggers, branches, delays, and webhook actions.'), 'tag' => __('No code'), 'class' => 'bg-success/10 text-success border-success/20'],
        ['group' => 'ai', 'title' => __('Knowledge Base'), 'desc' => __('Docs, websites, files, and searchable AI context.'), 'tag' => __('RAG'), 'class' => 'bg-info/10 text-info border-info/20'],
        ['group' => 'sales', 'title' => __('Analytics'), 'desc' => __('Conversation, campaign, AI, and team metrics.'), 'tag' => __('Live'), 'class' => 'bg-warning/10 text-warning border-warning/20'],
        ['group' => 'admin', 'title' => __('Team Roles'), 'desc' => __('Owners, admins, agents, viewers, and invites.'), 'tag' => __('Access'), 'class' => 'bg-brand/10 text-brand border-brand/20'],
        ['group' => 'comm', 'title' => __('Live Chat Widget'), 'desc' => __('Embeddable website chat with real-time messages.'), 'tag' => __('Widget'), 'class' => 'bg-success/10 text-success border-success/20'],
        ['group' => 'admin', 'title' => __('Temp Mail'), 'desc' => __('Disposable addresses for testing and privacy.'), 'tag' => __('Privacy'), 'class' => 'bg-info/10 text-info border-info/20'],
        ['group' => 'admin', 'title' => __('Security Controls'), 'desc' => __('2FA, IP blocks, rate limits, and audit logs.'), 'tag' => __('Secure'), 'class' => 'bg-warning/10 text-warning border-warning/20'],
        ['group' => 'admin', 'title' => __('Payments'), 'desc' => __('Subscriptions, trials, coupons, and gateways.'), 'tag' => __('Billing'), 'class' => 'bg-brand/10 text-brand border-brand/20'],
        ['group' => 'admin', 'title' => __('API and Webhooks'), 'desc' => __('Token access, scopes, logs, and integrations.'), 'tag' => __('API'), 'class' => 'bg-success/10 text-success border-success/20'],
        ['group' => 'admin', 'title' => __('Multi Language'), 'desc' => __('Language switcher, RTL support, and currencies.'), 'tag' => __('i18n'), 'class' => 'bg-info/10 text-info border-info/20'],
        ['group' => 'comm', 'title' => __('Templates'), 'desc' => __('Reusable replies, signatures, and email layouts.'), 'tag' => __('Reusable'), 'class' => 'bg-warning/10 text-warning border-warning/20'],
    ];

    $detailSections = [
        [
            'badge' => __('Omnichannel'),
            'title' => __('Unified Inbox for Every Channel'),
            'desc' => __('Manage emails, WhatsApp, SMS, Telegram, Slack, and live chat from one beautiful inbox. AI drafts replies automatically so your team responds faster.'),
            'bullets' => [__('Multi-channel in one view'), __('AI-drafted replies'), __('Smart routing & assignment')],
            'tone' => 'bg-brand/10 text-brand border-brand/20',
            'rows' => [
                ['name' => 'Alex Kim', 'meta' => __('Email'), 'text' => __('Can we schedule a demo for our team?'), 'badge' => __('AI Draft Ready'), 'color' => 'bg-brand'],
                ['name' => 'Priya Patel', 'meta' => __('WhatsApp'), 'text' => __('Thanks! The integration is working perfectly.'), 'badge' => '', 'color' => 'bg-success'],
                ['name' => 'Tom Anderson', 'meta' => __('SMS'), 'text' => __('Please send the pricing PDF.'), 'badge' => __('AI Draft Ready'), 'color' => 'bg-warning'],
                ['name' => 'Emily Davis', 'meta' => __('Telegram'), 'text' => __('Hey, what are your support hours?'), 'badge' => '', 'color' => 'bg-info'],
            ],
        ],
        [
            'badge' => __('AI-Powered'),
            'title' => __('AI-Powered Smart Replies'),
            'desc' => __('Train AI with your knowledge base. Get context-aware reply suggestions with sentiment analysis and confidence scoring. Your AI gets smarter with every conversation.'),
            'bullets' => [__('Sentiment analysis & scoring'), __('Knowledge base training'), __('One-click send or edit')],
            'tone' => 'bg-success/10 text-success border-success/20',
            'rows' => [
                ['name' => __('Incoming question'), 'meta' => __('Positive'), 'text' => __("We're interested in Enterprise. Can you share API limits?"), 'badge' => __('94% confidence'), 'color' => 'bg-success'],
                ['name' => __('Matched source'), 'meta' => __('Knowledge Base'), 'text' => __('Enterprise API allows custom webhooks and high daily limits.'), 'badge' => __('Verified'), 'color' => 'bg-brand'],
                ['name' => __('Suggested reply'), 'meta' => __('Ready'), 'text' => __('Hi John, here are the Enterprise API and integration details...'), 'badge' => __('Send'), 'color' => 'bg-warning'],
            ],
        ],
        [
            'badge' => __('Campaigns'),
            'title' => __('Campaign Builder with A/B Testing'),
            'desc' => __('Create email campaigns with a clean builder. Run A/B tests, drip sequences, and track opens, clicks, replies, and conversions in real time.'),
            'bullets' => [__('Drag-and-drop editor'), __('A/B testing & drip sequences'), __('Real-time analytics dashboard')],
            'tone' => 'bg-warning/10 text-warning border-warning/20',
            'rows' => [
                ['name' => __('Variant A'), 'meta' => __('Open Rate'), 'text' => __('Winning subject line for launch campaign'), 'badge' => '68%', 'color' => 'bg-success'],
                ['name' => __('Variant B'), 'meta' => __('Open Rate'), 'text' => __('Alternative product-focused subject line'), 'badge' => '52%', 'color' => 'bg-brand'],
                ['name' => __('Drip step'), 'meta' => __('Day 3'), 'text' => __('Follow-up sequence continues for warm leads'), 'badge' => __('Active'), 'color' => 'bg-warning'],
            ],
        ],
        [
            'badge' => __('Automation'),
            'title' => __('Visual Workflow Automation'),
            'desc' => __('Build no-code automations for routing, tagging, replies, follow-ups, alerts, webhooks, and CRM actions without leaving the workspace.'),
            'bullets' => [__('Triggers, delays & conditions'), __('Auto-assign owners'), __('Webhooks and CRM actions')],
            'tone' => 'bg-info/10 text-info border-info/20',
            'rows' => [
                ['name' => __('Trigger'), 'meta' => __('New lead'), 'text' => __('When a new contact submits a demo request'), 'badge' => __('Start'), 'color' => 'bg-info'],
                ['name' => __('Condition'), 'meta' => __('Lead score'), 'text' => __('If score is higher than 80, route to sales'), 'badge' => __('If/Else'), 'color' => 'bg-warning'],
                ['name' => __('Action'), 'meta' => __('Follow-up'), 'text' => __('Send reply, assign owner, create deal'), 'badge' => __('Done'), 'color' => 'bg-success'],
            ],
        ],
        [
            'badge' => __('CRM & Sales'),
            'title' => __('Contact CRM and Deal Pipeline'),
            'desc' => __('Keep customer profiles, tags, segments, deal values, stages, notes, and follow-up work connected to every conversation.'),
            'bullets' => [__('Lead profiles and tags'), __('Visual deal stages'), __('Revenue and close tracking')],
            'tone' => 'bg-danger/10 text-danger border-danger/20',
            'rows' => [
                ['name' => __('Qualified'), 'meta' => __('12 deals'), 'text' => __('New opportunities ready for outreach'), 'badge' => '$34K', 'color' => 'bg-brand'],
                ['name' => __('Proposal'), 'meta' => __('8 deals'), 'text' => __('Pricing and contract discussions'), 'badge' => '$52K', 'color' => 'bg-warning'],
                ['name' => __('Won'), 'meta' => __('3 deals'), 'text' => __('Closed revenue this month'), 'badge' => '$18K', 'color' => 'bg-success'],
            ],
        ],
        [
            'badge' => __('Analytics'),
            'title' => __('Analytics, Reporting, and Team Control'),
            'desc' => __('Track conversation speed, campaign performance, AI confidence, agent workload, and revenue movement from one reporting layer.'),
            'bullets' => [__('Conversation analytics'), __('Campaign and AI reports'), __('Team productivity metrics')],
            'tone' => 'bg-secondary-500/10 text-secondary-600 border-secondary-500/20',
            'rows' => [
                ['name' => __('Conversations'), 'meta' => __('This week'), 'text' => __('Team handled more customer requests'), 'badge' => '+18%', 'color' => 'bg-success'],
                ['name' => __('Response time'), 'meta' => __('Median'), 'text' => __('First replies are moving faster'), 'badge' => '<2m', 'color' => 'bg-brand'],
                ['name' => __('AI accuracy'), 'meta' => __('7 days'), 'text' => __('Approved suggestions by agents'), 'badge' => '94%', 'color' => 'bg-warning'],
            ],
        ],
    ];

    $integrations = [
        ['name' => 'Gmail', 'abbr' => 'Gm', 'color' => '#EA4335', 'desc' => __('Email sync & send')],
        ['name' => 'Outlook', 'abbr' => 'Ou', 'color' => '#0078D4', 'desc' => __('Microsoft 365')],
        ['name' => 'WhatsApp', 'abbr' => 'Wa', 'color' => '#25D366', 'desc' => __('Business chat')],
        ['name' => 'Slack', 'abbr' => 'Sl', 'color' => '#4A154B', 'desc' => __('Team alerts')],
        ['name' => 'Telegram', 'abbr' => 'Tg', 'color' => '#26A5E4', 'desc' => __('Bot messages')],
        ['name' => 'Stripe', 'abbr' => 'St', 'color' => '#635BFF', 'desc' => __('Payments')],
        ['name' => 'HubSpot', 'abbr' => 'Hs', 'color' => '#FF7A59', 'desc' => __('CRM sync')],
        ['name' => 'Zapier', 'abbr' => 'Zp', 'color' => '#FF4A00', 'desc' => __('Automation')],
    ];
@endphp

@section('title', $siteName . ' - ' . __('AI-Powered Email Automation & CRM SaaS'))

@push('styles')
<style>
    .mh-reveal {
        opacity: 0;
        transform: translateY(26px);
        transition: opacity .75s cubic-bezier(.16,1,.3,1), transform .75s cubic-bezier(.16,1,.3,1);
    }
    .mh-reveal.is-visible {
        opacity: 1;
        transform: translateY(0);
    }
    .mh-grid-bg {
        background-image:
            linear-gradient(color-mix(in srgb, var(--color-border) 34%, transparent) 1px, transparent 1px),
            linear-gradient(90deg, color-mix(in srgb, var(--color-border) 34%, transparent) 1px, transparent 1px);
        background-size: 42px 42px;
    }
    .mh-panel {
        background: color-mix(in srgb, var(--color-surface-2) 92%, transparent);
        border: 1px solid color-mix(in srgb, var(--color-border) 78%, transparent);
        box-shadow: 0 18px 60px rgba(15, 23, 42, .08);
    }
    .dark .mh-panel,
    [data-theme="dark"] .mh-panel {
        box-shadow: 0 18px 60px rgba(0, 0, 0, .28);
    }
    .mh-bento {
        background:
            linear-gradient(135deg, color-mix(in srgb, var(--color-muted) 4%, transparent), transparent 34%),
            linear-gradient(315deg, color-mix(in srgb, var(--color-muted) 4%, transparent), transparent 38%),
            var(--color-surface-2);
    }
    .mh-marquee-left { animation: mh-marquee-left 34s linear infinite; }
    .mh-marquee-right { animation: mh-marquee-right 38s linear infinite; }
    .mh-marquee-left:hover,
    .mh-marquee-right:hover { animation-play-state: paused; }
    .mh-bar { transform-origin: left center; animation: mh-bar 4.2s ease-in-out infinite; }
    @keyframes mh-marquee-left {
        from { transform: translateX(0); }
        to { transform: translateX(-50%); }
    }
    @keyframes mh-marquee-right {
        from { transform: translateX(-50%); }
        to { transform: translateX(0); }
    }
    @keyframes mh-bar {
        0%, 100% { transform: scaleX(.64); }
        50% { transform: scaleX(1); }
    }
    @media (prefers-reduced-motion: reduce) {
        .mh-reveal {
            opacity: 1;
            transform: none;
            transition: none;
        }
        .mh-marquee-left,
        .mh-marquee-right,
        .mh-bar {
            animation: none;
        }
    }
</style>
@endpush

@section('content')
<div class="overflow-hidden text-ink" x-data="{ billingCycle: 'monthly', showcase: 'inbox', featureFilter: 'all' }">
    <section class="relative mh-grid-bg pt-28 sm:pt-36 lg:pt-44 pb-14 lg:pb-20 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4">
            <div class="mx-auto max-w-4xl text-center mh-reveal">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand animate-pulse"></span>
                    {{ $hero['badge'] }}
                </span>
                <h1 class="mt-6 text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-extrabold leading-[1.08] tracking-tight">
                    {{ $hero['title_line1'] }}<br class="hidden sm:block">
                    {{ __('with') }} <span class="gradient-text">{{ $hero['title_highlight'] }}</span> {{ $hero['title_line2'] }}
                </h1>
                <p class="mt-6 text-lg sm:text-xl text-muted max-w-2xl mx-auto leading-relaxed">
                    {{ $hero['subtitle'] }}
                </p>
                <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="{{ url($hero['cta_url']) }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 text-base font-semibold text-white bg-brand rounded-2xl shadow-lg shadow-soft hover:shadow-soft hover:brightness-110 transition-all">
                        {{ $hero['cta_text'] }}
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ url($hero['cta2_url']) }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 text-base font-semibold text-ink border border-border bg-surface-2/70 rounded-2xl hover:border-brand/60 hover:bg-surface-2 transition-all">
                        {{ $hero['cta2_text'] }}
                    </a>
                </div>
            </div>

            <div class="mt-14 mh-reveal" style="transition-delay: 120ms">
                <div class="mh-panel rounded-[2rem] overflow-hidden max-w-6xl mx-auto">
                    <div class="h-12 border-b border-border flex items-center justify-between px-4">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-danger/70"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-warning/70"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-success/70"></span>
                        </div>
                        <span class="hidden sm:inline-flex rounded-full bg-success/10 text-success px-3 py-1 text-xs font-bold">{{ __('AI workspace live') }}</span>
                    </div>
                    <div class="grid gap-0 lg:grid-cols-[260px_1fr_280px]">
                        <div class="border-b lg:border-b-0 lg:border-r border-border p-4 bg-surface/40">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-muted mb-4">{{ __('Channels') }}</p>
                            <div class="space-y-2">
                                @foreach(['Email', 'WhatsApp', 'Slack', 'SMS', 'Telegram'] as $index => $channel)
                                    <div class="flex items-center justify-between rounded-xl border border-border bg-surface-2 px-3 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full {{ ['bg-brand','bg-success','bg-warning','bg-info','bg-danger'][$index] }}"></span>
                                            <span class="text-sm font-semibold text-ink">{{ $channel }}</span>
                                        </div>
                                        <span class="text-xs font-bold text-muted">{{ [24,18,11,9,6][$index] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="p-4 sm:p-6">
                            <div class="grid gap-4 md:grid-cols-3">
                                @foreach([['Open rate','73%','bg-success'], ['Reply rate','31%','bg-brand'], ['AI confidence','94%','bg-warning']] as $metric)
                                    <div class="rounded-2xl border border-border bg-surface-2 p-4">
                                        <p class="text-xs font-semibold text-muted">{{ __($metric[0]) }}</p>
                                        <p class="mt-2 text-3xl font-extrabold text-ink">{{ $metric[1] }}</p>
                                        <div class="mt-3 h-2 rounded-full bg-border/60 overflow-hidden">
                                            <div class="{{ $metric[2] }} mh-bar h-full rounded-full" style="width: {{ $metric[1] }}"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4 rounded-2xl border border-brand/25 bg-brand/5 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-extrabold text-ink">{{ __('AI suggested reply') }}</p>
                                        <p class="mt-1 text-sm leading-6 text-muted">{{ __('Hi Alex, yes. MailTrixy can centralize every channel, draft replies from your knowledge base, trigger follow-ups, and report campaign performance from one workspace.') }}</p>
                                    </div>
                                    <span class="rounded-full bg-success/10 text-success px-3 py-1 text-xs font-bold">{{ __('Ready to send') }}</span>
                                </div>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                @foreach(array_slice($featureItems, 0, 4) as $index => $item)
                                    <div class="rounded-2xl border border-border bg-surface-2 p-4">
                                        <p class="text-sm font-bold text-ink">{{ $item['title'] ?? '' }}</p>
                                        <p class="mt-1 text-xs leading-5 text-muted">{{ $item['desc'] ?? '' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="border-t lg:border-t-0 lg:border-l border-border p-4 bg-surface/40">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-muted mb-4">{{ __('Pipeline') }}</p>
                            <div class="space-y-3">
                                @foreach([['Qualified','$34K','bg-brand'], ['Proposal','$52K','bg-warning'], ['Won','$18K','bg-success']] as $deal)
                                    <div class="rounded-2xl border border-border bg-surface-2 p-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-bold text-ink">{{ __($deal[0]) }}</span>
                                            <span class="text-sm font-extrabold text-ink">{{ $deal[1] }}</span>
                                        </div>
                                        <div class="mt-3 h-2 rounded-full bg-border/60 overflow-hidden">
                                            <div class="{{ $deal[2] }} mh-bar h-full rounded-full" style="width: 78%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="py-20 md:py-28">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center max-w-3xl mx-auto mb-12 mh-reveal">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ __('How it works') }}</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                    {{ __('From setup to') }} <span class="gradient-text italic">{{ __('full automation.') }}</span>
                </h2>
                <p class="mt-4 text-lg text-muted">{{ __('Connect channels, train AI, launch workflows, and track every result without switching tools.') }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-4">
                @foreach([
                    [__('Connect channels'), __('Bring email, chat, SMS, and social messages into one queue.')],
                    [__('Train AI'), __('Upload docs and templates so replies match your business.')],
                    [__('Automate work'), __('Route, tag, follow up, and create tasks without manual steps.')],
                    [__('Track growth'), __('Measure campaign, inbox, deal, and team performance.')],
                ] as $index => $step)
                    <div class="mh-reveal relative rounded-2xl border border-border bg-surface-2 p-5 shadow-soft" style="transition-delay: {{ $index * 80 }}ms">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand text-white text-sm font-extrabold">{{ $index + 1 }}</span>
                        <h3 class="mt-5 text-lg font-extrabold text-ink">{{ $step[0] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-muted">{{ $step[1] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="features" class="py-20 lg:py-28 border-y border-border/40 bg-surface-2/45">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center max-w-3xl mx-auto mb-12 mh-reveal">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ $features['badge'] ?? __('Features') }}</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                    {{ $features['title'] ?? __('Everything you need to master communication') }}
                </h2>
                <p class="mt-4 text-lg text-muted">{{ $features['subtitle'] ?? __('Unify inbox, AI replies, campaigns, contacts, workflows, and analytics in one workspace.') }}</p>
            </div>

            <div class="grid gap-5 lg:grid-cols-12">
                @foreach($featureItems as $index => $item)
                    <article class="mh-reveal mh-bento rounded-2xl border border-border p-5 shadow-soft {{ $index === 0 ? 'lg:col-span-7 lg:row-span-2' : ($index === 1 ? 'lg:col-span-5' : 'lg:col-span-4') }}" style="transition-delay: {{ ($index % 6) * 70 }}ms">
                        <div class="flex items-start justify-between gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ ['bg-brand/10 text-brand','bg-success/10 text-success','bg-warning/10 text-warning','bg-info/10 text-info','bg-danger/10 text-danger','bg-secondary-500/10 text-secondary-600'][$index % 6] }}">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="{{ ['M3 7h18M5 7v10a2 2 0 002 2h10a2 2 0 002-2V7', 'M12 3l2.4 5.6L20 11l-5.6 2.4L12 19l-2.4-5.6L4 11l5.6-2.4L12 3z', 'M4 19V5m0 14h16M8 16V9m5 7V7m5 9v-4', 'M4 6h16M4 12h16M4 18h10', 'M7 8h10M7 12h6m-9 8h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z', 'M4 12h4l3-7 4 14 3-7h2'][$index % 6] }}"/></svg>
                            </span>
                            <span class="text-xs font-extrabold text-muted/60">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <h3 class="mt-5 text-xl font-extrabold text-ink">{{ $item['title'] ?? '' }}</h3>
                        <p class="mt-3 text-sm leading-7 text-muted">{{ $item['desc'] ?? '' }}</p>
                        @if($index === 0)
                            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                                @foreach([__('Inbox'), __('AI reply'), __('Workflow')] as $chip)
                                    <span class="rounded-xl border border-border bg-surface-2 px-3 py-2 text-xs font-bold text-ink">{{ $chip }}</span>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-20 lg:py-28">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center max-w-3xl mx-auto mb-10 mh-reveal">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ __('Detailed Product Sections') }}</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                    {{ __('See exactly what each module does.') }}
                </h2>
                <p class="mt-4 text-lg text-muted">{{ __('Each section explains the workflow and shows the matching product surface your team will use every day.') }}</p>
            </div>

            <div class="space-y-16 lg:space-y-24">
                @foreach($detailSections as $index => $section)
                    <div class="mh-reveal grid gap-10 lg:grid-cols-2 lg:items-center" style="transition-delay: {{ ($index % 3) * 80 }}ms">
                        <div class="{{ $index % 2 ? 'lg:order-2' : '' }}">
                            <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold {{ $section['tone'] }} mb-4">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                {{ $section['badge'] }}
                            </div>
                            <h3 class="text-3xl lg:text-4xl font-extrabold tracking-tight text-ink">{{ $section['title'] }}</h3>
                            <p class="mt-4 text-base lg:text-lg text-muted leading-relaxed">{{ $section['desc'] }}</p>
                            <ul class="mt-6 space-y-3">
                                @foreach($section['bullets'] as $bullet)
                                    <li class="flex items-center gap-3 text-sm font-semibold text-ink">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-success/10 text-success">
                                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                        {{ $bullet }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="{{ $index % 2 ? 'lg:order-1' : '' }}">
                            <div class="mh-panel overflow-hidden rounded-2xl">
                                <div class="flex items-center justify-between border-b border-border bg-surface/45 px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-ink">{{ $section['title'] }}</span>
                                        <span class="rounded-full bg-brand/10 px-2 py-0.5 text-xs font-bold text-brand">{{ count($section['rows']) * 8 }}</span>
                                    </div>
                                    <div class="hidden gap-2 sm:flex">
                                        @foreach(['Email', 'WhatsApp', 'SMS'] as $tab)
                                            <span class="rounded-lg px-2 py-1 text-xs font-bold {{ $loop->first ? 'bg-brand/10 text-brand' : 'text-muted' }}">{{ $tab }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="divide-y divide-border/40">
                                    @foreach($section['rows'] as $rowIndex => $row)
                                        <div class="flex items-center gap-3 px-4 py-4">
                                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ ['bg-brand/15 text-brand', 'bg-success/15 text-success', 'bg-warning/15 text-warning', 'bg-info/15 text-info'][$rowIndex % 4] }} text-sm font-bold">
                                                {{ substr($row['name'], 0, 1) }}
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="truncate text-sm font-bold text-ink">{{ $row['name'] }}</span>
                                                    <span class="rounded-md bg-surface px-2 py-0.5 text-[10px] font-bold text-muted border border-border/60">{{ $row['meta'] }}</span>
                                                </div>
                                                <p class="mt-1 truncate text-sm text-muted">{{ $row['text'] }}</p>
                                            </div>
                                            @if($row['badge'])
                                                <span class="hidden shrink-0 rounded-full bg-brand/10 px-3 py-1 text-xs font-bold text-brand sm:inline-flex">{{ $row['badge'] }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-20 lg:py-28 border-y border-border/40 bg-surface-2/45">
        <div class="max-w-7xl mx-auto px-4">
            <div class="mh-reveal flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between mb-10">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ __('All Features') }}</span>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">{{ __('Every tool stays visible and organized.') }}</h2>
                    <p class="mt-4 text-lg text-muted">{{ __('Communication, AI, sales, and admin modules work together across one connected workspace.') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach([
                        ['key' => 'all', 'label' => __('All')],
                        ['key' => 'comm', 'label' => __('Communication')],
                        ['key' => 'ai', 'label' => __('AI & Automation')],
                        ['key' => 'sales', 'label' => __('CRM & Sales')],
                        ['key' => 'admin', 'label' => __('Admin')],
                    ] as $filter)
                        <button type="button" @click="featureFilter = '{{ $filter['key'] }}'"
                                class="rounded-xl border px-4 py-2 text-sm font-bold transition"
                                :class="featureFilter === '{{ $filter['key'] }}' ? 'bg-brand text-white border-brand shadow-lg shadow-brand/20' : 'border-border bg-surface-2 text-muted hover:text-ink hover:border-brand/40'">
                            {{ $filter['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($capabilities as $index => $capability)
                    <article x-show="featureFilter === 'all' || featureFilter === '{{ $capability['group'] }}'"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                             class="mh-reveal rounded-2xl border border-border bg-surface-2 p-5 shadow-soft hover:-translate-y-1 hover:border-brand/40"
                             style="transition-delay: {{ ($index % 8) * 45 }}ms">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <span class="rounded-full border px-2.5 py-1 text-xs font-extrabold {{ $capability['class'] }}">{{ $capability['tag'] }}</span>
                            <span class="text-xs font-extrabold text-muted/60">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <h3 class="text-base font-extrabold text-ink">{{ $capability['title'] }}</h3>
                        <p class="mt-3 text-sm leading-7 text-muted">{{ $capability['desc'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="integrations" class="py-20 lg:py-28 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 text-center mh-reveal">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ __('Integrations') }}</span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                {{ __('Connects with tools') }} <span class="gradient-text">{{ __('you already use') }}</span>
            </h2>
            <p class="mt-4 text-lg text-muted max-w-xl mx-auto">{{ __('Seamless integrations with the platforms your team relies on every day.') }}</p>
        </div>
        <div class="mt-14 relative">
            <div class="absolute left-0 top-0 bottom-0 w-24 bg-gradient-to-r from-surface to-transparent z-10 pointer-events-none"></div>
            <div class="absolute right-0 top-0 bottom-0 w-24 bg-gradient-to-l from-surface to-transparent z-10 pointer-events-none"></div>
            <div class="flex w-max gap-5 mh-marquee-left">
                @foreach(array_merge($integrations, $integrations) as $integration)
                    <div class="group flex w-64 shrink-0 items-center gap-4 rounded-2xl border border-border/50 bg-surface-2 px-6 py-4 transition-all duration-300 hover:border-brand/30 hover:shadow-lg">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-110" style="background: {{ $integration['color'] }}12;">
                            <span class="text-lg font-bold" style="color: {{ $integration['color'] }};">{{ $integration['abbr'] }}</span>
                        </div>
                        <div class="min-w-0 text-left">
                            <p class="text-sm font-semibold text-ink">{{ $integration['name'] }}</p>
                            <p class="text-xs text-muted">{{ $integration['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="mt-5 relative">
            <div class="absolute left-0 top-0 bottom-0 w-24 bg-gradient-to-r from-surface to-transparent z-10 pointer-events-none"></div>
            <div class="absolute right-0 top-0 bottom-0 w-24 bg-gradient-to-l from-surface to-transparent z-10 pointer-events-none"></div>
            <div class="flex w-max gap-5 mh-marquee-right">
                @foreach(array_merge(array_reverse($integrations), array_reverse($integrations)) as $integration)
                    <div class="group flex w-64 shrink-0 items-center gap-4 rounded-2xl border border-border/50 bg-surface-2 px-6 py-4 transition-all duration-300 hover:border-brand/30 hover:shadow-lg">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-110" style="background: {{ $integration['color'] }}12;">
                            <span class="text-lg font-bold" style="color: {{ $integration['color'] }};">{{ $integration['abbr'] }}</span>
                        </div>
                        <div class="min-w-0 text-left">
                            <p class="text-sm font-semibold text-ink">{{ $integration['name'] }}</p>
                            <p class="text-xs text-muted">{{ $integration['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="testimonials" class="py-20 lg:py-28 border-y border-border/40 bg-surface-2/45">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center max-w-2xl mx-auto mb-12 mh-reveal">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ __('Testimonials') }}</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                    {{ $testimonials['title'] ?? __('Loved by teams worldwide') }}
                </h2>
                <p class="mt-4 text-lg text-muted">{{ $testimonials['subtitle'] ?? __('See what customers say about MailTrixy.') }}</p>
            </div>
            <div class="grid gap-5 lg:grid-cols-3">
                @foreach($testimonialItems as $index => $item)
                    <blockquote class="mh-reveal rounded-2xl border border-border bg-surface-2 p-6 shadow-soft" style="transition-delay: {{ $index * 90 }}ms">
                        <div class="mb-4 flex gap-0.5 text-amber-400">
                            @for($i = 0; $i < 5; $i++)
                                <svg viewBox="0 0 20 20" class="h-4 w-4" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            @endfor
                        </div>
                        <p class="text-sm leading-7 text-ink/80 italic">"{{ $item['quote'] ?? $item['body'] ?? '' }}"</p>
                        <footer class="mt-6 flex items-center gap-3 border-t border-border/40 pt-5">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand/10 text-sm font-bold text-brand">{{ substr($item['name'] ?? 'C', 0, 1) }}</span>
                            <div>
                                <div class="text-sm font-semibold text-ink">{{ $item['name'] ?? __('Customer') }}</div>
                                <div class="text-xs text-muted">{{ trim(($item['role'] ?? '') . (($item['company'] ?? '') ? ' at ' . $item['company'] : '')) }}</div>
                            </div>
                        </footer>
                    </blockquote>
                @endforeach
            </div>
        </div>
    </section>

    @if(!\App\Support\BillingPolicy::isFree())
<section id="pricing" class="py-20 lg:py-28 relative">
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-slate-400/5 to-transparent -z-10" aria-hidden="true"></div>
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center max-w-2xl mx-auto mb-12 lg:mb-16 mh-reveal">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ __('Pricing') }}</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                    {{ __('Simple,') }} <span class="gradient-text">{{ __('transparent') }}</span> {{ __('pricing') }}
                </h2>
                <p class="mt-4 text-lg text-muted">{{ __("Start free, upgrade when you're ready. No hidden fees, cancel anytime.") }}</p>

                <div class="mt-8 inline-flex items-center gap-3 bg-surface-2 border border-border/50 rounded-2xl p-1.5">
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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-6">
                @foreach($plans as $index => $plan)
                    <div class="mh-reveal relative rounded-2xl border bg-surface-2/80 p-6 flex flex-col {{ $plan['popular'] ? 'pricing-popular border-brand' : 'border-border/50' }}" style="transition-delay: {{ $index * 80 }}ms">
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
                                <span class="text-4xl font-extrabold text-ink" x-text="billingCycle === 'monthly' ? '${{ $plan['monthly'] }}' : '${{ $plan['yearly'] }}'">${{ $plan['monthly'] }}</span>
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
                                    <svg class="w-4 h-4 text-success shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m4.5 12.75 6 6 9-13.5"/></svg>
                                    <span class="text-ink/80">{{ $feature }}</span>
                                </div>
                            @endforeach
                            @foreach($plan['disabled'] as $feature)
                                <div class="flex items-center gap-2.5 text-sm">
                                    <svg class="w-4 h-4 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18 18 6M6 6l12 12"/></svg>
                                    <span class="text-muted">{{ $feature }}</span>
                                </div>
                            @endforeach
                        </div>

                        <a href="{{ url($plan['name'] === __('Enterprise') ? '/contact' : '/register') }}"
                           class="block text-center px-5 py-3 rounded-xl text-sm font-semibold transition-all mt-auto {{ $plan['popular'] ? 'bg-brand text-white shadow-lg shadow-brand/20 hover:shadow-soft hover:brightness-110' : 'border border-border text-ink hover:border-brand/40 hover:bg-brand/5' }}">
                            {{ $plan['cta'] }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

    @if(count($faqItems))
        <section id="faq" class="py-20 lg:py-28 border-y border-border/40 bg-surface-2/45">
            <div class="max-w-4xl mx-auto px-4">
                <div class="text-center mb-10 mh-reveal">
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand/10 text-brand border border-brand/20 mb-4">{{ __('FAQ') }}</span>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">{{ $faq['title'] ?? __('Frequently Asked Questions') }}</h2>
                    @if(!empty($faq['subtitle']))
                        <p class="mt-4 text-lg text-muted">{{ $faq['subtitle'] }}</p>
                    @endif
                </div>
                <div class="space-y-3">
                    @foreach($faqItems as $index => $item)
                        <details class="mh-reveal group rounded-2xl border border-border bg-surface-2 p-5 shadow-soft" style="transition-delay: {{ $index * 60 }}ms">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-bold text-ink">
                                {{ $item['question'] ?? '' }}
                                <span class="rounded-lg border border-border p-1 text-muted transition group-open:rotate-45">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                                </span>
                            </summary>
                            <p class="mt-4 text-sm leading-7 text-muted">{{ $item['answer'] ?? '' }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="py-20 lg:py-28">
        <div class="max-w-7xl mx-auto px-4">
            <div class="mh-reveal rounded-3xl p-10 sm:p-14 lg:p-20 text-center relative overflow-hidden bg-surface-2 border border-border/50">
                <div class="relative z-10">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-brand/10 border border-brand/20 mb-6">
                        <span class="relative flex h-2 w-2"><span class="animate-ping absolute inset-0 rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-400"></span></span>
                        <span class="text-sm font-medium text-muted">{{ $cta['badge'] ?? '' }}</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight max-w-3xl mx-auto">
                        {{ $cta['title'] }}
                    </h2>
                    <p class="mt-5 text-lg text-muted max-w-2xl mx-auto leading-relaxed">
                        {{ $cta['subtitle'] }}
                    </p>
                    <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a href="{{ url($cta['cta_url'] ?? '/register') }}" class="group w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 text-base font-bold rounded-2xl bg-brand text-white shadow-lg shadow-brand/20 hover:shadow-soft hover:brightness-110 transition-all">
                            {{ $cta['cta_text'] ?? __('Get Started Free') }}
                            <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        </a>
                        @if(!\App\Support\BillingPolicy::isFree())<a href="#pricing" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 text-base font-semibold text-ink border border-border rounded-2xl hover:border-brand/40 hover:bg-brand/5 transition-all">
                            {{ __('View Pricing') }}
                        </a>@endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var items = document.querySelectorAll('.mh-reveal');
    if (!('IntersectionObserver' in window)) {
        items.forEach(function (item) { item.classList.add('is-visible'); });
        return;
    }
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });
    items.forEach(function (item) { observer.observe(item); });
});
</script>
@endpush
