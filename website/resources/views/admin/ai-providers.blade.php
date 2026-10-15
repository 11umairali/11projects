<x-layouts.admin :title="__('AI Providers')" :subtitle="__('Configure AI model providers and vector database connections.')">
    <div class="space-y-6">

        {{-- Flash success --}}
        @if(session('success'))
        <div class="p-4 bg-success/10 border border-success/20 rounded-xl text-sm font-medium text-success flex items-center gap-2">
            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
        @endif

        @php
            $sensitiveKeys = ['openai_api_key', 'anthropic_api_key', 'gemini_api_key', 'mistral_api_key', 'pinecone_api_key', 'smtp_password'];
            $getSetting = function($key, $default = '') use ($sensitiveKeys) {
                static $cache = null;
                if ($cache === null) {
                    try {
                        $cache = \Illuminate\Support\Facades\DB::table('system_settings')->pluck('value', 'key')->toArray();
                    } catch (\Exception $e) {
                        $cache = [];
                    }
                }
                $value = $cache[$key] ?? $default;
                // Decrypt sensitive values
                if (in_array($key, $sensitiveKeys) && !empty($value) && $value !== $default) {
                    try {
                        $value = decrypt($value);
                    } catch (\Exception $e) {
                        // Value may not be encrypted yet (legacy data), return as-is
                    }
                }
                return $value;
            };

            // Load AI usage stats per provider from db
            $providerStats = [];
            try {
                $providerStats = \Illuminate\Support\Facades\DB::table('ai_usage_logs')
                    ->selectRaw("provider, COUNT(*) as requests, COALESCE(SUM(cost), 0) as cost, COALESCE(SUM(tokens_in + tokens_out), 0) as tokens")
                    ->groupBy('provider')
                    ->pluck(null, 'provider')
                    ->toArray();
            } catch (\Exception $e) {
                // ignore
            }

            $formatTokens = function($t) {
                if ($t >= 1000000) return number_format($t / 1000000, 1) . 'M';
                if ($t >= 1000) return number_format($t / 1000, 1) . 'K';
                return number_format($t);
            };
        @endphp

        {{-- Default Provider & Model Selection --}}
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-950/30 dark:to-purple-950/30 rounded-2xl border border-brand/20 p-6 shadow-sm"
                 x-data="{
                     provider: '{{ $getSetting('ai_default_provider', 'openai') }}',
                     savedModel: '{{ $getSetting('ai_default_model', 'gpt-4o') }}',
                     models: {
                         openai: ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'o1-mini', 'o3-mini'],
                         anthropic: ['claude-sonnet-4-20250514', 'claude-opus-4-20250514', 'claude-haiku-4-5-20251001'],
                         gemini: ['gemini-2.0-flash', 'gemini-1.5-pro'],
                         mistral: ['mistral-large-latest', 'mistral-medium-latest', 'mistral-small-latest']
                     }
                 }">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-brand/15 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-ink">{{ __('Default Configuration') }}</h2>
                        <p class="text-sm text-muted">{{ __('Set the default AI provider and model for all workspaces') }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('Default Provider') }}</label>
                        <select name="ai_default_provider" x-model="provider" class="w-full px-4 py-2.5 text-sm border border-brand/20 rounded-xl bg-surface-2 focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all font-medium">
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic</option>
                            <option value="gemini">Google Gemini</option>
                            <option value="mistral">Mistral</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('Default Model') }}</label>
                        <select name="ai_default_model" class="w-full px-4 py-2.5 text-sm border border-brand/20 rounded-xl bg-surface-2 focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all font-medium">
                            <template x-for="model in models[provider]" :key="model">
                                <option :value="model" x-text="model" :selected="model === savedModel"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 text-xs font-semibold text-white bg-brand rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        {{ __('Save Defaults') }}
                    </button>
                </div>
            </div>
        </form>

        {{-- AI Provider Cards --}}
        <div>
            <h2 class="text-xs font-bold text-muted uppercase tracking-wider mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                {{ __('Language Model Providers') }}
            </h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                @php
                $providers = [
                    [
                        'name' => 'OpenAI',
                        'key' => 'openai',
                        'setting_key' => 'openai_api_key',
                        'desc' => __('GPT-4o, GPT-4 Turbo, o1/o3 Reasoning'),
                        'gradient' => 'from-green-500 to-emerald-600',
                        'shadow' => 'shadow-green-200',
                        'abbr' => 'AI',
                        'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'o1-mini', 'o3-mini', 'text-embedding-3-large'],
                        'model_colors' => ['bg-brand/15 text-brand', 'bg-brand/10 text-brand', 'bg-brand/10 text-brand', 'bg-brand/10 text-brand', 'bg-surface text-muted', 'bg-surface text-muted'],
                        'stat_colors' => ['bg-success/10 border-success/20 text-success', 'bg-warning/10 border-warning/20 text-warning', 'bg-info/10 border-info/20 text-info'],
                    ],
                    [
                        'name' => 'Anthropic',
                        'key' => 'anthropic',
                        'setting_key' => 'anthropic_api_key',
                        'desc' => __('Claude Sonnet 4, Opus 4, Haiku'),
                        'gradient' => 'from-amber-600 to-orange-700',
                        'shadow' => 'shadow-amber-500/20',
                        'abbr' => 'An',
                        'models' => ['claude-sonnet-4-20250514', 'claude-opus-4-20250514', 'claude-haiku-4-5-20251001'],
                        'model_colors' => ['bg-warning/15 text-warning', 'bg-warning/10 text-warning', 'bg-surface text-muted'],
                        'stat_colors' => ['bg-warning/10 border-warning/20 text-warning', 'bg-warning/10 border-warning/20 text-warning', 'bg-warning/10 border-warning/20 text-warning'],
                    ],
                    [
                        'name' => 'Google Gemini',
                        'key' => 'gemini',
                        'setting_key' => 'gemini_api_key',
                        'desc' => __('Gemini 2.0 Flash, Gemini Pro'),
                        'gradient' => 'from-blue-500 to-cyan-600',
                        'shadow' => 'shadow-blue-500/20',
                        'abbr' => 'Gm',
                        'models' => ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash'],
                        'model_colors' => ['bg-info/15 text-info', 'bg-info/10 text-info', 'bg-surface text-muted'],
                        'stat_colors' => ['bg-info/10 border-info/20 text-info', 'bg-info/10 border-info/20 text-info', 'bg-info/10 border-info/20 text-info'],
                    ],
                    [
                        'name' => 'Mistral',
                        'key' => 'mistral',
                        'setting_key' => 'mistral_api_key',
                        'desc' => __('Mistral Large, Medium, Small'),
                        'gradient' => 'from-rose-500 to-pink-600',
                        'shadow' => 'shadow-rose-500/20',
                        'abbr' => 'Mi',
                        'models' => ['mistral-large-latest', 'mistral-medium-latest', 'mistral-small-latest'],
                        'model_colors' => ['bg-danger/15 text-danger', 'bg-danger/10 text-danger', 'bg-surface text-muted'],
                        'stat_colors' => ['bg-danger/10 border-danger/20 text-danger', 'bg-danger/10 border-danger/20 text-danger', 'bg-danger/10 border-danger/20 text-danger'],
                    ],
                ];
                @endphp

                @foreach($providers as $p)
                @php
                    $apiKeyVal = $getSetting($p['setting_key'], '');
                    $hasKey = !empty($apiKeyVal);
                    $pStats = $providerStats[$p['key']] ?? null;
                @endphp
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <div class="panel hover:shadow-md transition-all duration-200 overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-gradient-to-br {{ $p['gradient'] }} rounded-xl flex items-center justify-center shadow-lg {{ $p['shadow'] }}">
                                        <span class="text-lg font-extrabold text-white">{{ $p['abbr'] }}</span>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-ink">{{ $p['name'] }}</h3>
                                        <p class="text-xs text-muted">{{ $p['desc'] }}</p>
                                    </div>
                                </div>
                                @if($hasKey)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-bold rounded-full bg-success/15 text-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success/100"></span>
                                    {{ __('Key Saved') }}
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-bold rounded-full bg-surface text-muted">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                    {{ __('Not Connected') }}
                                </span>
                                @endif
                            </div>

                            {{-- API Key --}}
                            <div class="mb-4">
                                <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('API Key') }}</label>
                                <div class="flex items-center gap-2">
                                    <input type="password" name="{{ $p['setting_key'] }}" value="" placeholder="{{ $hasKey ? '••••••••••••••••' : __('Enter your') . ' ' . $p['name'] . ' ' . __('API key...') }}" class="flex-1 px-3 py-2.5 text-sm border border-border rounded-xl bg-surface focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all font-mono">
                                </div>
                                <p class="text-[10px] text-muted mt-1">{{ $hasKey ? __('A key is already saved. Enter a new key to replace it, or leave blank to keep the current key.') : __('Keys are validated when first used. Save your key to enable this provider.') }}</p>
                            </div>

                            {{-- Models --}}
                            <div class="mb-4">
                                <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('Available Models') }}</label>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($p['models'] as $idx => $model)
                                    <span class="px-2 py-1 text-[10px] font-bold rounded-lg border {{ $p['model_colors'][$idx] ?? 'bg-surface text-muted' }}">{{ $model }}</span>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Usage stats from real data --}}
                            <div class="grid grid-cols-3 gap-3 mb-4">
                                <div class="rounded-xl p-3 border {{ $p['stat_colors'][0] }}">
                                    <p class="text-[10px] font-bold uppercase tracking-wider">{{ __('Requests') }}</p>
                                    <p class="text-lg font-extrabold text-ink mt-0.5">{{ $pStats ? number_format($pStats->requests) : '--' }}</p>
                                </div>
                                <div class="rounded-xl p-3 border {{ $p['stat_colors'][1] }}">
                                    <p class="text-[10px] font-bold uppercase tracking-wider">{{ __('Cost') }}</p>
                                    <p class="text-lg font-extrabold text-ink mt-0.5">@if($pStats) @currency($pStats->cost) @else -- @endif</p>
                                </div>
                                <div class="rounded-xl p-3 border {{ $p['stat_colors'][2] }}">
                                    <p class="text-[10px] font-bold uppercase tracking-wider">{{ __('Tokens') }}</p>
                                    <p class="text-lg font-extrabold text-ink mt-0.5">{{ $pStats ? $formatTokens($pStats->tokens) : '--' }}</p>
                                </div>
                            </div>

                            {{-- Save --}}
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-brand rounded-xl hover:bg-brand-strong shadow-sm transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ __('Save') }} {{ $p['name'] }} {{ __('Key') }}
                            </button>
                        </div>
                    </div>
                </form>
                @endforeach
            </div>
        </div>

        {{-- Vector Database --}}
        <div>
            <h2 class="text-xs font-bold text-muted uppercase tracking-wider mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                {{ __('Vector Database') }}
            </h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Pinecone --}}
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <div class="panel hover:shadow-md transition-all duration-200 overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg shadow-teal-200">
                                        <span class="text-lg font-extrabold text-white">Pc</span>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-ink">Pinecone</h3>
                                        <p class="text-xs text-muted">{{ __('Vector database for embeddings') }}</p>
                                    </div>
                                </div>
                                @php $pineconeKey = $getSetting('pinecone_api_key', ''); @endphp
                                @if(!empty($pineconeKey))
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-bold rounded-full bg-success/15 text-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success/100"></span>
                                    {{ __('Key Saved') }}
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-bold rounded-full bg-surface text-muted">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                    {{ __('Not Connected') }}
                                </span>
                                @endif
                            </div>

                            <div class="mb-4">
                                <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('API Key') }}</label>
                                <input type="password" name="pinecone_api_key" value="" placeholder="{{ !empty($pineconeKey) ? '••••••••••••••••' : __('Enter your Pinecone API key...') }}" class="w-full px-3 py-2.5 text-sm border border-border rounded-xl bg-surface focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all font-mono">
                                <p class="text-[10px] text-muted mt-1">{{ !empty($pineconeKey) ? __('A key is already saved. Enter a new key to replace it, or leave blank to keep the current key.') : __('Save your key to enable Pinecone.') }}</p>
                            </div>

                            <div class="mb-4">
                                <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('Host') }}</label>
                                <input type="text" name="pinecone_host" value="{{ $getSetting('pinecone_host', '') }}" placeholder="us-east-1-aws.pinecone.io" class="w-full px-3 py-2.5 text-sm border border-border rounded-xl bg-surface focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all font-mono">
                            </div>

                            <div class="mb-4">
                                <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('Index Name') }}</label>
                                <input type="text" name="pinecone_index" value="{{ $getSetting('pinecone_index', '') }}" placeholder="mailtrixy-knowledge" class="w-full px-3 py-2.5 text-sm border border-border rounded-xl bg-surface focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all font-mono">
                            </div>

                            <p class="text-[10px] text-muted mb-4">{{ __('Connection is validated when first used. Save your settings to enable Pinecone.') }}</p>

                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-brand rounded-xl hover:bg-brand-strong shadow-sm transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ __('Save Pinecone Settings') }}
                            </button>
                        </div>
                    </div>
                </form>

                {{-- AI Cost Summary from real data --}}
                <div class="bg-gradient-to-br from-gray-900 to-indigo-950 rounded-2xl shadow-xl overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 bg-surface-2/10 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white">{{ __('AI Cost Summary') }}</h3>
                                <p class="text-xs text-muted">{{ __('Total AI spend from usage logs') }}</p>
                            </div>
                        </div>

                        @php
                            $totalAiCost = 0;
                        @endphp
                        <div class="space-y-4">
                            @forelse($providerStats as $pName => $pData)
                            @php $totalAiCost += $pData->cost; @endphp
                            <div class="flex items-center justify-between py-3 border-b border-white/10">
                                <span class="text-sm font-medium text-muted/50">{{ ucfirst($pName) }}</span>
                                <span class="text-sm font-bold text-white">@currency($pData->cost)</span>
                            </div>
                            @empty
                            <div class="py-3">
                                <span class="text-sm text-muted">{{ __('No AI usage recorded yet.') }}</span>
                            </div>
                            @endforelse

                            @if(!empty($providerStats))
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm font-bold text-white">{{ __('Total') }}</span>
                                <span class="text-2xl font-extrabold text-white">@currency($totalAiCost)</span>
                            </div>
                            @endif
                        </div>

                        @php
                            $aiBudget = (float) $getSetting('ai_monthly_budget', '500');
                            $budgetPercent = $aiBudget > 0 ? min(round(($totalAiCost / $aiBudget) * 100), 100) : 0;
                        @endphp
                        <div class="mt-4 pt-4 border-t border-white/10">
                            <div class="flex justify-between text-xs mb-2">
                                <span class="font-semibold text-muted">{{ __('Budget Used') }}</span>
                                <span class="font-bold text-indigo-400">{{ $budgetPercent }}%</span>
                            </div>
                            <div class="w-full h-2.5 bg-surface-2/10 rounded-full overflow-hidden">
                                <div class="h-2.5 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full" style="width: {{ $budgetPercent }}%"></div>
                            </div>
                            <p class="text-[10px] text-muted mt-2">@currency($totalAiCost) {{ __('of') }} @currency($aiBudget) {{ __('monthly budget') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-layouts.admin>
