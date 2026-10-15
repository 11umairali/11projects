<x-layouts.admin :title="__('Payment Gateways')" :subtitle="__('Configure payment providers, credentials, and modes.')">
    @php
        $categories = [
            'popular' => ['label' => __('Popular'), 'icon' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'],
            'regional' => ['label' => __('Regional'), 'icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9'],
            'crypto' => ['label' => __('Crypto'), 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
            'other' => ['label' => __('Other'), 'icon' => 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2'],
        ];
        $grouped = $gateways->groupBy(fn($g) => $g->category ?? 'other');
    @endphp

    <div class="space-y-6" x-data="{ search: '', activeCategory: 'popular' }">

        {{-- Flash --}}
        @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="p-4 bg-success/10 border border-success/20 rounded-xl text-sm font-medium text-success flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
            <button @click="show = false" class="ml-auto opacity-60 hover:opacity-100">&times;</button>
        </div>
        @endif
        @if(session('error'))
        <div x-data="{ show: true }" x-show="show" class="p-4 bg-danger/10 border border-danger/20 rounded-xl text-sm font-medium text-danger flex items-center gap-2">
            {{ session('error') }}
            <button @click="show = false" class="ml-auto opacity-60 hover:opacity-100">&times;</button>
        </div>
        @endif

        {{-- Header + Search --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Gateway Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ $gateways->count() }} {{ __('gateways available.') }} {{ $gateways->where('is_active', true)->count() }} {{ __('active.') }}</p>
                </div>
            </div>
            <div class="mt-5 relative">
                <svg viewBox="0 0 24 24" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input type="search" class="input-field pl-10" placeholder="{{ __('Search by gateway name...') }}" x-model="search">
            </div>
        </div>

        {{-- Category Tabs --}}
        <div class="panel p-1.5">
            <div class="flex flex-wrap gap-1">
                @foreach($categories as $catKey => $cat)
                @php $catCount = ($grouped[$catKey] ?? collect())->count(); @endphp
                <button @click="activeCategory = '{{ $catKey }}'"
                        :class="activeCategory === '{{ $catKey }}' ? 'bg-brand text-white shadow-soft' : 'text-muted hover:bg-surface hover:text-ink'"
                        class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-xl transition-all duration-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $cat['icon'] }}"/></svg>
                    <span>{{ $cat['label'] }}</span>
                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold rounded-full" :class="activeCategory === '{{ $catKey }}' ? 'bg-white/20 text-white' : 'bg-surface-2 text-muted'">{{ $catCount }}</span>
                </button>
                @endforeach
            </div>
        </div>

        {{-- Gateway List per Category --}}
        @foreach($categories as $catKey => $cat)
        <div x-show="activeCategory === '{{ $catKey }}'" x-cloak class="space-y-3">
            @foreach($grouped[$catKey] ?? [] as $gateway)
            <div class="panel overflow-hidden" x-data="{ expanded: false }"
                 x-show="!search || {{ Js::from(strtolower($gateway->name)) }}.includes(search.toLowerCase()) || {{ Js::from(strtolower($gateway->slug)) }}.includes(search.toLowerCase())"
                 x-transition>

                {{-- Header --}}
                <div class="flex items-center justify-between gap-4 px-6 py-4">
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-4 text-left" @click="expanded = !expanded">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface-2/70 text-muted">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate font-semibold text-ink">{{ $gateway->name }}</p>
                                @if($gateway->is_active)
                                <span class="inline-flex items-center rounded-md bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-600 ring-1 ring-inset ring-emerald-500/20">{{ __('Active') }}</span>
                                @else
                                <span class="inline-flex items-center rounded-md bg-slate-500/10 px-2 py-0.5 text-[11px] font-medium text-slate-500 ring-1 ring-inset ring-slate-500/20">{{ __('Inactive') }}</span>
                                @endif
                                @if($gateway->is_sandbox)
                                <span class="inline-flex items-center rounded-md bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-600 ring-1 ring-inset ring-amber-500/20">{{ __('Sandbox') }}</span>
                                @else
                                <span class="inline-flex items-center rounded-md bg-blue-500/10 px-2 py-0.5 text-[11px] font-medium text-blue-600 ring-1 ring-inset ring-blue-500/20">{{ __('Live') }}</span>
                                @endif
                            </div>
                            @if(!empty($gateway->supported_currencies))
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach(array_slice($gateway->supported_currencies, 0, 6) as $cur)
                                <span class="inline-flex items-center rounded bg-surface-2/70 px-1.5 py-0.5 text-[10px] font-medium text-muted">{{ $cur }}</span>
                                @endforeach
                                @if(count($gateway->supported_currencies) > 6)
                                <span class="inline-flex items-center rounded bg-surface-2/70 px-1.5 py-0.5 text-[10px] font-medium text-muted">+{{ count($gateway->supported_currencies) - 6 }} more</span>
                                @endif
                            </div>
                            @endif
                        </div>
                        <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-muted transition-transform duration-200" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 9l6 6 6-6"/></svg>
                    </button>

                    <form method="POST" action="{{ route('admin.payment-gateways.toggle', $gateway) }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $gateway->is_active ? 'bg-brand' : 'bg-border' }}" title="{{ $gateway->is_active ? 'Disable' : 'Enable' }}">
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition" style="transform: translateX({{ $gateway->is_active ? '1.25rem' : '0.125rem' }})"></span>
                        </button>
                    </form>
                </div>

                {{-- Expanded Config Form --}}
                <div x-show="expanded" x-collapse>
                    <div class="border-t border-border/60 px-6 py-6">
                        <form method="POST" action="{{ route('admin.payment-gateways.configure', $gateway) }}" class="space-y-6">
                            @csrf

                            <div class="panel p-5">
                                <p class="panel-heading">{{ __('Environment') }}</p>
                                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                    <div>
                                        <label for="mode_{{ $gateway->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Mode') }}</label>
                                        <select id="mode_{{ $gateway->id }}" name="is_sandbox" class="input-field">
                                            <option value="1" @selected($gateway->is_sandbox)>{{ __('Sandbox / Test') }}</option>
                                            <option value="0" @selected(!$gateway->is_sandbox)>{{ __('Live / Production') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            @if(!empty($gateway->supported_currencies))
                            <div class="panel p-5">
                                <p class="panel-heading">{{ __('Supported Currencies') }}</p>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    @foreach($gateway->supported_currencies as $cur)
                                    <span class="inline-flex items-center rounded-lg bg-surface-2/70 px-2.5 py-1 text-xs font-medium text-ink ring-1 ring-inset ring-border/40">{{ $cur }}</span>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            @if(count($gateway->credentialFields ?? []) > 0)
                            <div class="panel p-5">
                                <p class="panel-heading">{{ __('Credentials') }}</p>
                                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                    @foreach($gateway->credentialFields as $field)
                                    <div>
                                        <label for="cred_{{ $gateway->id }}_{{ $field['key'] }}" class="block text-sm font-medium text-ink mb-1.5">{{ $field['label'] }}</label>
                                        @if(($field['type'] ?? 'text') === 'password')
                                        <div class="relative">
                                            <input id="cred_{{ $gateway->id }}_{{ $field['key'] }}" name="credentials[{{ $field['key'] }}]" type="password" class="input-field pr-12" autocomplete="new-password"
                                                   placeholder="{{ !empty($gateway->decryptedCredentials[$field['key']]) ? '••••••••' : ($field['placeholder'] ?? '') }}">
                                            <button type="button" class="absolute inset-y-0 right-3 flex items-center text-muted/70 hover:text-ink transition"
                                                    onclick="const i=document.getElementById('cred_{{ $gateway->id }}_{{ $field['key'] }}');i.type=i.type==='password'?'text':'password'">
                                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </button>
                                        </div>
                                        @if(!empty($gateway->decryptedCredentials[$field['key']]))
                                        <p class="mt-1 text-xs text-muted">{{ __('Saved (hidden). Leave blank to keep current value.') }}</p>
                                        @endif
                                        @else
                                        <input id="cred_{{ $gateway->id }}_{{ $field['key'] }}" name="credentials[{{ $field['key'] }}]" type="text" class="input-field mt-1"
                                               value="{{ old('credentials.' . $field['key'], $gateway->decryptedCredentials[$field['key']] ?? '') }}"
                                               @if(!empty($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif>
                                        @endif
                                        @if(!empty($field['hint']))
                                        <p class="mt-1 text-xs text-muted">{{ $field['hint'] }}</p>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @else
                            <div class="rounded-2xl border border-dashed border-border/70 bg-surface-2/70 p-6 text-center text-sm text-muted">
                                {{ __('No driver found for') }} <span class="font-semibold text-ink">{{ $gateway->slug }}</span>.
                                Create <code class="rounded bg-surface-2 px-1.5 py-0.5 text-xs font-mono">App\Services\Payment\Drivers\{{ \Illuminate\Support\Str::studly($gateway->slug) }}Driver</code>
                            </div>
                            @endif

                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary">{{ __('Save') }} {{ $gateway->name }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach

            @if(($grouped[$catKey] ?? collect())->isEmpty())
            <div class="panel p-6 text-center">
                <p class="text-sm text-muted">No {{ strtolower($cat['label']) }} gateways found.</p>
            </div>
            @endif
        </div>
        @endforeach
    </div>
</x-layouts.admin>
