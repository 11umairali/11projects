<x-layouts.admin :title="__('Edit Plan')" :subtitle="__('Update') . ' ' . $plan->name . ' ' . __('plan configuration.')">
    <div class="space-y-6" x-data="planEditForm()">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('admin.plans.index') }}" class="btn-secondary">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('Back to Plans') }}
            </a>
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand/10 text-brand text-xs font-bold">{{ strtoupper(substr($plan->name, 0, 2)) }}</span>
                <div>
                    <p class="text-sm font-semibold text-ink">{{ $plan->name }}</p>
                    <p class="text-xs text-muted font-mono">{{ $plan->slug }}</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.plans.update', $plan->id) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Tab Navigation (same style as System Settings) --}}
            <div class="panel p-1.5">
                <div class="flex flex-wrap gap-1">
                    @php
                    $planTabs = [
                        ['id' => 'basic',    'label' => __('Basic Info'),       'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['id' => 'limits',   'label' => __('Resource Limits'),  'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                        ['id' => 'features', 'label' => __('Feature Access'),   'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                        ['id' => 'bullets',  'label' => __('Pricing Bullets'),  'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
                    ];
                    @endphp
                    @foreach($planTabs as $tab)
                    <button type="button" @click="activeTab = '{{ $tab['id'] }}'"
                            :class="activeTab === '{{ $tab['id'] }}' ? 'bg-brand text-white shadow-soft' : 'text-muted hover:bg-surface hover:text-ink'"
                            class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-xl transition-all duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"/></svg>
                        <span class="hidden sm:inline">{{ $tab['label'] }}</span>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- ═══ TAB 1: Basic Information ═══ --}}
            <div x-show="activeTab === 'basic'" x-cloak class="panel p-6 space-y-5">
                <p class="panel-heading">{{ __('Basic Information') }}</p>
                <p class="text-sm text-muted">{{ __('Update the plan identity and pricing.') }}</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Plan Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" required value="{{ old('name', $plan->name) }}" placeholder="{{ __('e.g. Professional') }}" class="input-field @error('name') !border-danger @enderror">
                        @error('name') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="slug" class="block text-sm font-medium text-ink mb-1.5">{{ __('Slug') }}</label>
                        <input type="text" id="slug" value="{{ $plan->slug }}" readonly class="input-field text-muted font-mono cursor-not-allowed opacity-60">
                        <p class="text-xs text-muted mt-1">{{ __('Slug cannot be changed after creation.') }}</p>
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-ink mb-1.5">{{ __('Description') }}</label>
                    <textarea name="description" id="description" rows="3" placeholder="{{ __('A short description...') }}" class="input-field resize-none @error('description') !border-danger @enderror">{{ old('description', $plan->description) }}</textarea>
                    @error('description') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                @php $activeSubs = $plan->subscriptions()->where('status', 'active')->count(); @endphp
                @if($activeSubs > 0)
                <div class="p-3 bg-warning/10 border border-warning/20 rounded-xl text-sm text-warning flex items-start gap-2">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <span><strong>{{ $activeSubs }} {{ __('active subscription(s)') }}</strong> {{ __('on this plan. Price changes may affect billing.') }}</span>
                </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="monthly_price" class="block text-sm font-medium text-ink mb-1.5">{{ __('Monthly Price') }} <span class="text-danger">*</span></label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-muted font-medium">$</span>
                            <input type="number" name="monthly_price" id="monthly_price" required min="0" step="0.01" x-model="monthlyPrice" value="{{ old('monthly_price', $plan->monthly_price) }}" class="input-field pl-8">
                        </div>
                        @error('monthly_price') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="yearly_price" class="block text-sm font-medium text-ink mb-1.5">
                            {{ __('Yearly Price') }} <span class="text-danger">*</span>
                            <template x-if="yearlySavings > 0">
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-success/10 text-success" x-text="'Save ' + yearlySavings + '%'"></span>
                            </template>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-muted font-medium">$</span>
                            <input type="number" name="yearly_price" id="yearly_price" required min="0" step="0.01" x-model="yearlyPrice" value="{{ old('yearly_price', $plan->yearly_price) }}" class="input-field pl-8">
                        </div>
                        <p class="text-xs text-muted mt-1" x-show="monthlyPrice > 0" x-text="'Equivalent to $' + (yearlyPrice / 12).toFixed(2) + '/mo'"></p>
                        @error('yearly_price') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-ink mb-1.5">{{ __('Sort Order') }} <span class="text-danger">*</span></label>
                        <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $plan->sort_order) }}" required min="0" class="input-field">
                    </div>
                    <div class="flex items-center gap-3 sm:pt-7">
                        <input type="hidden" name="is_active" value="0">
                        <label class="inline-flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $plan->is_active) ? 'checked' : '' }} class="peer sr-only">
                            <span class="relative inline-flex h-6 w-11 items-center rounded-full bg-border transition peer-checked:bg-brand [--switch-x:0.125rem] peer-checked:[--switch-x:1.25rem]">
                                <span class="inline-block h-5 w-5 translate-x-[var(--switch-x)] rounded-full bg-white shadow transition"></span>
                            </span>
                            <span class="text-sm font-medium text-ink">{{ __('Active') }}</span>
                        </label>
                    </div>
                    <div class="flex items-center gap-3 sm:pt-7">
                        <input type="hidden" name="is_popular" value="0">
                        <label class="inline-flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_popular" value="1" {{ old('is_popular', $plan->is_popular) ? 'checked' : '' }} class="peer sr-only">
                            <span class="relative inline-flex h-6 w-11 items-center rounded-full bg-border transition peer-checked:bg-warning [--switch-x:0.125rem] peer-checked:[--switch-x:1.25rem]">
                                <span class="inline-block h-5 w-5 translate-x-[var(--switch-x)] rounded-full bg-white shadow transition"></span>
                            </span>
                            <span class="text-sm font-medium text-ink">{{ __('Popular Badge') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ═══ TAB 2: Resource Limits ═══ --}}
            <div x-show="activeTab === 'limits'" x-cloak class="panel p-6">
                <p class="panel-heading">{{ __('Resource Limits') }}</p>
                <p class="text-sm text-muted mt-1">{{ __('Set numeric limits for each resource. Check "Unlimited" to remove the cap.') }}</p>

                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <template x-for="(item, index) in resourceLimits" :key="item.key">
                        <div class="p-4 bg-surface/50 rounded-xl border border-border">
                            <div class="flex items-center justify-between mb-3">
                                <label class="text-sm font-medium text-ink" x-text="item.label"></label>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer" x-show="item.key !== 'kb_file_size_mb'">
                                    <input type="checkbox" class="sr-only peer" x-model="item.unlimited" @change="if(item.unlimited) item.value = null">
                                    <span class="relative inline-flex h-5 w-9 items-center rounded-full bg-border transition peer-checked:bg-brand [--switch-x:0.125rem] peer-checked:[--switch-x:1rem]">
                                        <span class="inline-block h-4 w-4 translate-x-[var(--switch-x)] rounded-full bg-white shadow transition"></span>
                                    </span>
                                    <span class="text-xs text-muted">{{ __('Unlimited') }}</span>
                                </label>
                            </div>
                            <div class="relative">
                                <input type="number" min="0" :name="'limits[' + item.key + ']'" x-model="item.value" :disabled="item.unlimited" :placeholder="item.unlimited ? '{{ __('Unlimited') }}' : '0'" :class="item.unlimited ? 'opacity-40 cursor-not-allowed' : ''" class="input-field">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted" x-show="item.suffix" x-text="item.suffix"></span>
                            </div>
                            <input type="hidden" :name="'limits_unlimited[' + item.key + ']'" :value="item.unlimited ? '1' : '0'">
                            <p class="text-xs text-muted mt-1.5" x-text="item.help"></p>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ═══ TAB 3: Feature Access ═══ --}}
            <div x-show="activeTab === 'features'" x-cloak class="panel p-6">
                <p class="panel-heading">{{ __('Feature Access') }}</p>
                <p class="text-sm text-muted mt-1">{{ __('Toggle which integrations and capabilities are included.') }}</p>

                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <template x-for="(toggle, index) in featureToggles" :key="toggle.key">
                        <div class="flex items-center justify-between p-4 bg-surface/50 rounded-xl border border-border transition-colors" :class="toggle.enabled ? 'border-brand/20 bg-brand/[0.02]' : ''">
                            <div class="flex-1 min-w-0 mr-4">
                                <p class="text-sm font-medium text-ink" x-text="toggle.label"></p>
                                <p class="text-xs text-muted mt-0.5" x-text="toggle.description"></p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="hidden" :name="'toggles[' + toggle.key + ']'" :value="toggle.enabled ? '1' : '0'">
                                <input type="checkbox" x-model="toggle.enabled" class="peer sr-only">
                                <span class="relative inline-flex h-6 w-11 items-center rounded-full bg-border transition peer-checked:bg-brand [--switch-x:0.125rem] peer-checked:[--switch-x:1.25rem]">
                                    <span class="inline-block h-5 w-5 translate-x-[var(--switch-x)] rounded-full bg-white shadow transition"></span>
                                </span>
                            </label>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ═══ TAB 4: Plan Features List ═══ --}}
            <div x-show="activeTab === 'bullets'" x-cloak class="panel p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="panel-heading">{{ __('Pricing Page Bullets') }}</p>
                        <p class="text-sm text-muted mt-1">{{ __('Bullet points displayed on the pricing page.') }}</p>
                    </div>
                    <button type="button" @click="addPricingFeature()" class="btn-secondary text-xs">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('Add Feature') }}
                    </button>
                </div>

                <div class="mt-6">
                    <template x-if="pricingFeatures.length === 0">
                        <div class="text-center py-8">
                            <svg class="w-10 h-10 text-muted/40 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            <p class="text-sm text-muted">{{ __('No features listed yet.') }}</p>
                            <button type="button" @click="addPricingFeature()" class="mt-2 text-sm text-brand hover:underline font-medium">{{ __('Add your first feature bullet') }}</button>
                        </div>
                    </template>

                    <div class="space-y-2">
                        <template x-for="(feature, index) in pricingFeatures" :key="index">
                            <div class="flex items-center gap-3 group">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-success/10 text-success text-xs font-bold" x-text="index + 1"></span>
                                <input type="text" :name="'features[' + index + ']'" x-model="pricingFeatures[index]" placeholder="{{ __('e.g. 10 email accounts') }}" class="input-field flex-1">
                                <button type="button" @click="pricingFeatures.splice(index, 1)" class="p-1.5 text-muted hover:text-danger hover:bg-danger/10 rounded-lg transition-colors opacity-0 group-hover:opacity-100" title="{{ __('Remove') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Form Actions (always visible) --}}
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.plans.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn-primary">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Update Plan') }}
                </button>
            </div>
        </form>
    </div>

    @php
        $featureLookup = $plan->planFeatures->keyBy('feature_key');
        $limitKeys = ['email_accounts', 'ai_replies', 'contacts', 'team_members', 'storage_mb', 'campaigns_per_month', 'workflows', 'kb_documents', 'kb_file_size_mb', 'temp_mail_addresses', 'temp_mail_lifetime_hours'];
        $toggleKeys = ['whatsapp', 'sms', 'telegram', 'slack', 'live_chat', 'api_access', 'priority_support', 'custom_roles', 'white_label', 'sso_saml', 'temp_mail'];
    @endphp

    <script>
    function planEditForm() {
        return {
            activeTab: '{{ request('tab', 'basic') }}',
            monthlyPrice: {{ old('monthly_price', $plan->monthly_price) }},
            yearlyPrice: {{ old('yearly_price', $plan->yearly_price) }},

            get yearlySavings() {
                if (this.monthlyPrice <= 0 || this.yearlyPrice <= 0) return 0;
                const fullYearly = this.monthlyPrice * 12;
                const pct = Math.round(((fullYearly - this.yearlyPrice) / fullYearly) * 100);
                return pct > 0 ? pct : 0;
            },

            resourceLimits: [
                @foreach([
                    ['key' => 'email_accounts', 'label' => 'Email Accounts', 'suffix' => '', 'help' => 'Number of email accounts the user can connect.'],
                    ['key' => 'ai_replies', 'label' => 'AI Replies / Month', 'suffix' => '/mo', 'help' => 'AI-generated replies allowed per month.'],
                    ['key' => 'contacts', 'label' => 'Contacts', 'suffix' => '', 'help' => 'Maximum number of contacts in address book.'],
                    ['key' => 'team_members', 'label' => 'Team Members', 'suffix' => '', 'help' => 'Max workspace members including owner.'],
                    ['key' => 'storage_mb', 'label' => 'Storage', 'suffix' => 'MB', 'help' => 'File storage space in megabytes.'],
                    ['key' => 'campaigns_per_month', 'label' => 'Campaigns / Month', 'suffix' => '/mo', 'help' => 'Email campaigns allowed per month.'],
                    ['key' => 'workflows', 'label' => 'Workflows', 'suffix' => '', 'help' => 'Automation workflows the user can create.'],
                    ['key' => 'kb_documents', 'label' => 'KB Documents', 'suffix' => '', 'help' => 'Knowledge base documents for AI training.'],
                    ['key' => 'kb_file_size_mb', 'label' => 'KB Max File Size', 'suffix' => 'MB', 'help' => 'Maximum single file upload size.'],
                    ['key' => 'temp_mail_addresses', 'label' => 'Temp Mail Addresses', 'suffix' => '', 'help' => 'Number of active temp mail addresses allowed.'],
                    ['key' => 'temp_mail_lifetime_hours', 'label' => 'Temp Mail Lifetime (Hours)', 'suffix' => 'hrs', 'help' => 'How long temp mail addresses remain active.'],
                ] as $r)
                    @php
                        $pf = $featureLookup->get($r['key']);
                        $isUnlimited = $pf && $pf->enabled && $pf->limit === null && $r['key'] !== 'kb_file_size_mb';
                        $val = $pf ? ($pf->limit ?? 'null') : 'null';
                    @endphp
                    { key: '{{ $r['key'] }}', label: '{{ $r['label'] }}', value: {{ old("limits.{$r['key']}", $val) }}, unlimited: {{ old("limits_unlimited.{$r['key']}", $isUnlimited ? '1' : '0') == '1' ? 'true' : 'false' }}, suffix: '{{ $r['suffix'] }}', help: '{{ $r['help'] }}' },
                @endforeach
            ],

            featureToggles: [
                @foreach([
                    ['key' => 'knowledge_base', 'label' => 'Knowledge Base', 'desc' => 'AI training with documents, website scraping, and Q&A.'],
                    ['key' => 'campaigns', 'label' => 'Email Campaigns', 'desc' => 'Create and send bulk email campaigns.'],
                    ['key' => 'deal_pipeline', 'label' => 'Deals & Pipeline', 'desc' => 'Sales pipeline and deal management.'],
                    ['key' => 'analytics', 'label' => 'Analytics & Reports', 'desc' => 'Advanced analytics dashboard and reports.'],
                    ['key' => 'whatsapp', 'label' => 'WhatsApp Integration', 'desc' => 'Send and receive WhatsApp messages from inbox.'],
                    ['key' => 'sms', 'label' => 'SMS Integration', 'desc' => 'Two-way SMS messaging via Twilio or other providers.'],
                    ['key' => 'telegram', 'label' => 'Telegram Integration', 'desc' => 'Connect Telegram bots and channels.'],
                    ['key' => 'slack', 'label' => 'Slack Integration', 'desc' => 'Receive notifications and reply from Slack.'],
                    ['key' => 'live_chat', 'label' => 'Live Chat', 'desc' => 'Embeddable live chat widget for websites.'],
                    ['key' => 'api_access', 'label' => 'API Access', 'desc' => 'REST API access for custom integrations.'],
                    ['key' => 'priority_support', 'label' => 'Priority Support', 'desc' => 'Faster response times and dedicated support.'],
                    ['key' => 'custom_roles', 'label' => 'Custom Roles', 'desc' => 'Create custom team roles with granular permissions.'],
                    ['key' => 'white_label', 'label' => 'White Label', 'desc' => 'Remove ' . config('app.name') . ' branding and use your own.'],
                    ['key' => 'sso_saml', 'label' => 'SSO / SAML', 'desc' => 'Enterprise single sign-on via SAML 2.0.'],
                    ['key' => 'temp_mail', 'label' => 'Temp Mail', 'desc' => 'Generate disposable temporary email addresses.'],
                    // Premium AI / Campaign features (Phase 2/3)
                    ['key' => 'ai_own_key', 'label' => 'AI: Use Own API Key', 'desc' => 'Allow users to plug in their own OpenAI / Anthropic API key (otherwise platform keys are used).'],
                    ['key' => 'ai_per_channel', 'label' => 'AI: Per-Channel Settings', 'desc' => 'Custom AI prompt, send-mode, and confidence per channel (Email / WhatsApp / SMS / Live Chat / Telegram).'],
                    ['key' => 'ai_auto_escalation', 'label' => 'AI: Auto-Escalation', 'desc' => 'Automatically assign low-confidence conversations to a human agent and notify them.'],
                    ['key' => 'sms_campaigns', 'label' => 'SMS Bulk Campaigns', 'desc' => 'Send bulk SMS broadcasts to contact lists via Twilio.'],
                    // Free-by-default — admins can disable on tightly locked plans.
                    ['key' => 'email_templates', 'label' => 'Workflow Email Templates', 'desc' => 'Template dropdown inside the workflow Send Email action.'],
                    ['key' => 'workflow_in_app_notify', 'label' => 'Workflow In-App Notifications', 'desc' => 'Send Notification action with the in-app (bell icon) channel.'],
                ] as $t)
                    @php
                        $pf = $featureLookup->get($t['key']);
                        $isEnabled = $pf ? (bool)$pf->enabled : false;
                    @endphp
                    { key: '{{ $t['key'] }}', label: '{{ $t['label'] }}', description: '{{ $t['desc'] }}', enabled: {{ old("toggles.{$t['key']}", $isEnabled ? '1' : '0') == '1' ? 'true' : 'false' }} },
                @endforeach
            ],

            pricingFeatures: @json(old('features', $plan->features ?? [])),

            addPricingFeature() {
                this.pricingFeatures.push('');
                this.$nextTick(() => {
                    const inputs = this.$el.querySelectorAll('input[name^="features["]');
                    if (inputs.length) inputs[inputs.length - 1].focus();
                });
            },
        };
    }
    </script>
</x-layouts.admin>
