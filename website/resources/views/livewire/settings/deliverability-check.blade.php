<div class="space-y-6">
    {{-- Flash messages --}}
    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
         class="p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('error') }}
    </div>
    @endif

    {{-- Header & Domain Input --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <div class="flex items-start gap-4 mb-5">
            <div class="w-12 h-12 bg-brand/10 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-ink">{{ __('Email Deliverability Check') }}</h2>
                <p class="text-sm text-muted mt-0.5">{{ __('Check if your domains SPF, DKIM, and DMARC records are properly configured. Correct setup prevents your emails from landing in spam.') }}</p>
            </div>
        </div>

        <form wire:submit="checkDeliverability" class="flex items-start gap-3">
            <div class="flex-1">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    </div>
                    <input type="text"
                           wire:model="domain"
                           placeholder="example.com"
                           class="w-full pl-10 pr-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           autocomplete="off"
                           spellcheck="false">
                </div>
                @error('domain')
                <p class="text-xs text-red-500 mt-1.5">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit"
                    class="btn-primary px-5 py-2.5 text-sm font-semibold flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                    wire:target="checkDeliverability">
                <span wire:loading.remove wire:target="checkDeliverability">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <span wire:loading wire:target="checkDeliverability">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </span>
                <span wire:loading.remove wire:target="checkDeliverability">{{ __('Check Domain') }}</span>
                <span wire:loading wire:target="checkDeliverability">{{ __('Checking...') }}</span>
            </button>
        </form>
        <p class="text-xs text-muted mt-2">{{ __('DNS lookups may take a few seconds. Results are cached for 1 hour. Up to 5 checks per hour.') }}</p>
    </div>

    {{-- Loading overlay --}}
    <div wire:loading wire:target="checkDeliverability" class="bg-surface-2 rounded-2xl border border-border p-12 text-center">
        <svg class="w-10 h-10 animate-spin text-primary-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <p class="text-sm font-medium text-ink/80">{{ __('Running DNS checks for') }} {{ $domain }}...</p>
        <p class="text-xs text-muted mt-1">{{ __('Checking SPF, DKIM, DMARC, and MX records') }}</p>
    </div>

    @if($results)
    {{-- Overall Score --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6" wire:loading.remove wire:target="checkDeliverability">
        <div class="flex items-center gap-6">
            {{-- Score circle --}}
            <div class="relative flex-shrink-0">
                @php
                    $score = $results['overall_score'] ?? 0;
                    $circumference = 2 * pi() * 54;
                    $dashOffset = $circumference - ($score / 100) * $circumference;
                    $scoreColor = $score >= 80 ? '#22c55e' : ($score >= 50 ? '#eab308' : '#ef4444');
                    $scoreBg = $score >= 80 ? '#dcfce7' : ($score >= 50 ? '#fef9c3' : '#fef2f2');
                @endphp
                <svg class="w-32 h-32 -rotate-90" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="54" fill="none" stroke="#f3f4f6" stroke-width="8"/>
                    <circle cx="60" cy="60" r="54" fill="none"
                            stroke="{{ $scoreColor }}"
                            stroke-width="8"
                            stroke-linecap="round"
                            stroke-dasharray="{{ $circumference }}"
                            stroke-dashoffset="{{ $dashOffset }}"
                            style="transition: stroke-dashoffset 1s ease-in-out"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-3xl font-bold" style="color: {{ $scoreColor }}">{{ $score }}</span>
                    <span class="text-xs text-muted -mt-0.5">/ 100</span>
                </div>
            </div>

            {{-- Score summary --}}
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-ink">
                    @if($score >= 90)
                        {{ __('Excellent Deliverability') }}
                    @elseif($score >= 70)
                        {{ __('Good Deliverability') }}
                    @elseif($score >= 50)
                        {{ __('Fair Deliverability') }}
                    @elseif($score >= 25)
                        {{ __('Poor Deliverability') }}
                    @else
                        {{ __('Critical Issues Found') }}
                    @endif
                </h3>
                <p class="text-sm text-muted mt-1">
                    @if($score >= 90)
                        {{ __('Your domain') }} {{ $results['domain'] }} {{ __('has properly configured email authentication. Emails from this domain should be delivered reliably to most recipients.') }}
                    @elseif($score >= 70)
                        {{ __('Your domain') }} {{ $results['domain'] }} {{ __('has good email authentication but there is room for improvement. Review the recommendations below.') }}
                    @elseif($score >= 50)
                        {{ __('Your domain') }} {{ $results['domain'] }} {{ __('is partially configured. Some emails may land in spam. Address the issues below to improve deliverability.') }}
                    @else
                        {{ __('Your domain') }} {{ $results['domain'] }} {{ __('has significant email authentication issues. Many emails will be rejected or marked as spam. Immediate action is recommended.') }}
                    @endif
                </p>

                {{-- Quick status badges --}}
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach(['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC', 'mx' => 'MX'] as $key => $label)
                        @php $status = $results[$key]['status'] ?? 'fail'; @endphp
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium
                            {{ $status === 'pass' ? 'bg-success/10 text-success' : ($status === 'warning' ? 'bg-warning/10 text-warning' : 'bg-danger/10 text-danger') }}">
                            @if($status === 'pass')
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            @elseif($status === 'warning')
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            @else
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                            {{ $label }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Detailed Results Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" wire:loading.remove wire:target="checkDeliverability">

        {{-- SPF Card --}}
        @php $spf = $results['spf'] ?? []; $spfStatus = $spf['status'] ?? 'fail'; @endphp
        <div class="bg-surface-2 rounded-2xl border border-border p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center
                    {{ $spfStatus === 'pass' ? 'bg-success/10' : ($spfStatus === 'warning' ? 'bg-warning/10' : 'bg-danger/10') }}">
                    @if($spfStatus === 'pass')
                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @elseif($spfStatus === 'warning')
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    @else
                        <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    @endif
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-ink">{{ __('SPF Record') }}</h4>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full
                        {{ $spfStatus === 'pass' ? 'bg-success/15 text-success' : ($spfStatus === 'warning' ? 'bg-warning/15 text-warning' : 'bg-danger/15 text-danger') }}">
                        {{ ucfirst($spfStatus) }}
                    </span>
                </div>
            </div>

            <p class="text-sm text-muted  mb-3">{{ $spf['details'] ?? __('No data available.') }}</p>

            @if(!empty($spf['record']))
            <div class="relative" x-data="{ copied: false }">
                <div class="bg-surface rounded-lg p-3 pr-10 font-mono text-xs text-ink/80 break-all border border-border">
                    {{ $spf['record'] }}
                </div>
                <button @click="navigator.clipboard.writeText('{{ addslashes($spf['record']) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="absolute top-2 right-2 p-1.5 text-muted hover:text-muted  rounded-md hover:bg-surface  transition-colors"
                        :title="__('Copy record')">
                    <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <svg x-show="copied" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </button>
            </div>
            @endif

            @if(isset($spf['lookup_count']))
            <div class="flex items-center gap-2 mt-2 text-xs text-muted">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                DNS lookups: {{ $spf['lookup_count'] }} / 10
            </div>
            @endif
        </div>

        {{-- DKIM Card --}}
        @php $dkim = $results['dkim'] ?? []; $dkimStatus = $dkim['status'] ?? 'fail'; @endphp
        <div class="bg-surface-2 rounded-2xl border border-border p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center
                    {{ $dkimStatus === 'pass' ? 'bg-success/10' : ($dkimStatus === 'warning' ? 'bg-warning/10' : 'bg-danger/10') }}">
                    @if($dkimStatus === 'pass')
                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @elseif($dkimStatus === 'warning')
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    @else
                        <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    @endif
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-ink">{{ __('DKIM Record') }}</h4>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full
                        {{ $dkimStatus === 'pass' ? 'bg-success/15 text-success' : ($dkimStatus === 'warning' ? 'bg-warning/15 text-warning' : 'bg-danger/15 text-danger') }}">
                        {{ ucfirst($dkimStatus) }}
                    </span>
                </div>
            </div>

            <p class="text-sm text-muted  mb-3">{{ $dkim['details'] ?? __('No data available.') }}</p>

            @if(!empty($dkim['selectors_found']))
            <div class="space-y-2">
                @foreach($dkim['selectors_found'] as $sel)
                <div class="bg-surface rounded-lg p-3 border border-border">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-semibold text-ink/80">{{ __('Selector:') }}</span>
                        <span class="text-xs font-mono text-primary-600">{{ $sel['selector'] }}</span>
                        <span class="text-xs text-muted">({{ $sel['type'] }})</span>
                    </div>
                    @if(isset($sel['record']))
                    <div class="relative" x-data="{ copied: false }">
                        <p class="font-mono text-xs text-muted  break-all pr-8">{{ Str::limit($sel['record'], 200) }}</p>
                        <button @click="navigator.clipboard.writeText(@js($sel['record'])); copied = true; setTimeout(() => copied = false, 2000)"
                                class="absolute top-0 right-0 p-1 text-muted hover:text-muted  rounded-md hover:bg-gray-200 dark:bg-gray-700/50 transition-colors"
                                :title="__('Copy record')">
                            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <svg x-show="copied" class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </button>
                    </div>
                    @elseif(isset($sel['target']))
                    <p class="font-mono text-xs text-muted ">CNAME -> {{ $sel['target'] }}</p>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- DMARC Card --}}
        @php $dmarc = $results['dmarc'] ?? []; $dmarcStatus = $dmarc['status'] ?? 'fail'; @endphp
        <div class="bg-surface-2 rounded-2xl border border-border p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center
                    {{ $dmarcStatus === 'pass' ? 'bg-success/10' : ($dmarcStatus === 'warning' ? 'bg-warning/10' : 'bg-danger/10') }}">
                    @if($dmarcStatus === 'pass')
                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @elseif($dmarcStatus === 'warning')
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    @else
                        <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    @endif
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-ink">{{ __('DMARC Record') }}</h4>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full
                        {{ $dmarcStatus === 'pass' ? 'bg-success/15 text-success' : ($dmarcStatus === 'warning' ? 'bg-warning/15 text-warning' : 'bg-danger/15 text-danger') }}">
                        {{ ucfirst($dmarcStatus) }}
                    </span>
                </div>
            </div>

            <p class="text-sm text-muted  mb-3">{{ $dmarc['details'] ?? __('No data available.') }}</p>

            @if(!empty($dmarc['policy']))
            <div class="flex flex-wrap gap-2 mb-3">
                <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-lg
                    {{ $dmarc['policy'] === 'reject' ? 'bg-success/10 text-success' : ($dmarc['policy'] === 'quarantine' ? 'bg-info/10 text-info' : 'bg-warning/10 text-warning') }}">
                    Policy: {{ $dmarc['policy'] }}
                </span>
                @if(!empty($dmarc['has_rua']))
                <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-lg bg-success/10 text-success">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Aggregate Reports
                </span>
                @endif
                @if(!empty($dmarc['has_ruf']))
                <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-lg bg-success/10 text-success">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Forensic Reports
                </span>
                @endif
            </div>
            @endif

            @if(!empty($dmarc['record']))
            <div class="relative" x-data="{ copied: false }">
                <div class="bg-surface rounded-lg p-3 pr-10 font-mono text-xs text-ink/80 break-all border border-border">
                    {{ $dmarc['record'] }}
                </div>
                <button @click="navigator.clipboard.writeText(@js($dmarc['record'])); copied = true; setTimeout(() => copied = false, 2000)"
                        class="absolute top-2 right-2 p-1.5 text-muted hover:text-muted  rounded-md hover:bg-surface  transition-colors"
                        :title="__('Copy record')">
                    <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <svg x-show="copied" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </button>
            </div>
            @endif
        </div>

        {{-- MX Card --}}
        @php $mx = $results['mx'] ?? []; $mxStatus = $mx['status'] ?? 'fail'; @endphp
        <div class="bg-surface-2 rounded-2xl border border-border p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center
                    {{ $mxStatus === 'pass' ? 'bg-success/10' : 'bg-danger/10' }}">
                    @if($mxStatus === 'pass')
                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @else
                        <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    @endif
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-ink">{{ __('MX Records') }}</h4>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full
                        {{ $mxStatus === 'pass' ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger' }}">
                        {{ ucfirst($mxStatus) }}
                    </span>
                </div>
            </div>

            <p class="text-sm text-muted  mb-3">{{ $mx['details'] ?? __('No data available.') }}</p>

            @if(!empty($mx['records']))
            <div class="bg-surface rounded-lg border border-border overflow-hidden">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="text-left px-3 py-2 font-semibold text-muted ">{{ __('Priority') }}</th>
                            <th class="text-left px-3 py-2 font-semibold text-muted ">{{ __('Mail Server') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mx['records'] as $record)
                        <tr class="border-b border-border last:border-0">
                            <td class="px-3 py-2 text-muted font-mono">{{ $record['priority'] }}</td>
                            <td class="px-3 py-2 text-ink/80 font-mono">{{ $record['host'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Recommendations --}}
    @if(!empty($results['recommendations']))
    <div class="bg-surface-2 rounded-2xl border border-border p-6" wire:loading.remove wire:target="checkDeliverability">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-warning/10 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Recommendations') }}</h3>
                <p class="text-sm text-muted">{{ __('Steps to improve your email deliverability.') }}</p>
            </div>
        </div>

        <div class="space-y-3">
            @foreach($results['recommendations'] as $rec)
            <div class="rounded-xl border p-4
                {{ ($rec['severity'] ?? '') === 'critical' ? 'border-danger/20 bg-danger/10/50' : (($rec['severity'] ?? '') === 'high' ? 'border-orange-200 bg-orange-50/50' : (($rec['severity'] ?? '') === 'medium' ? 'border-warning/20 bg-warning/10/50' : 'border-border bg-surface')) }}">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 mt-0.5">
                        @if(($rec['severity'] ?? '') === 'critical')
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-md bg-danger/15 text-danger">{{ __('Critical') }}</span>
                        @elseif(($rec['severity'] ?? '') === 'high')
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-md bg-orange-100 text-orange-700">{{ __('High') }}</span>
                        @elseif(($rec['severity'] ?? '') === 'medium')
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-md bg-warning/15 text-warning">{{ __('Medium') }}</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-md bg-surface  text-muted ">{{ __('Low') }}</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-semibold text-muted uppercase">{{ $rec['area'] ?? '' }}</span>
                        </div>
                        <p class="text-sm text-ink/80">{{ $rec['message'] }}</p>

                        @if(!empty($rec['dns_record']))
                        <div class="mt-2 relative" x-data="{ copied: false }">
                            <div class="bg-surface-2 rounded-lg p-3 pr-10 font-mono text-xs text-ink/80 break-all border border-border">
                                <span class="text-muted select-none">TXT &nbsp;</span>{{ $rec['dns_record'] }}
                            </div>
                            <button @click="navigator.clipboard.writeText(@js($rec['dns_record'])); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="absolute top-2 right-2 p-1.5 text-muted hover:text-muted  rounded-md hover:bg-surface  transition-colors"
                                    :title="__('Copy DNS record')">
                                <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <svg x-show="copied" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Score Explanation --}}
    @if($results)
    <div class="bg-surface rounded-2xl border border-border p-5" wire:loading.remove wire:target="checkDeliverability">
        <h4 class="text-sm font-semibold text-ink/80 mb-2">{{ __('How the score is calculated') }}</h4>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-muted">
            <div class="bg-surface-2 rounded-lg p-3 border border-border text-center">
                <p class="font-semibold text-ink/80 text-lg mb-0.5">30</p>
                <p>{{ __('SPF points') }}</p>
            </div>
            <div class="bg-surface-2 rounded-lg p-3 border border-border text-center">
                <p class="font-semibold text-ink/80 text-lg mb-0.5">30</p>
                <p>{{ __('DKIM points') }}</p>
            </div>
            <div class="bg-surface-2 rounded-lg p-3 border border-border text-center">
                <p class="font-semibold text-ink/80 text-lg mb-0.5">25</p>
                <p>{{ __('DMARC points') }}</p>
            </div>
            <div class="bg-surface-2 rounded-lg p-3 border border-border text-center">
                <p class="font-semibold text-ink/80 text-lg mb-0.5">15</p>
                <p>{{ __('MX points') }}</p>
            </div>
        </div>
        <p class="text-xs text-muted mt-3">{{ __('Partial configurations (warnings) receive half points. A score of 100 means all email authentication standards are properly configured.') }}</p>

        @if(!empty($results['checked_at']))
        <p class="text-xs text-muted mt-2">{{ __('Checked at:') }} {{ \Carbon\Carbon::parse($results['checked_at'])->format('M j, Y g:i A') }}</p>
        @endif
    </div>
    @endif

    @endif
</div>
