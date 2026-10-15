<x-layouts.admin :title="__('Notification Settings')" :subtitle="__('Configure email and Slack notifications for admin events.')">
    <div class="space-y-6">

        {{-- Flash success --}}
        @if(session('success'))
        <div class="p-4 bg-success/10 border border-success/20 rounded-xl text-sm font-medium text-success flex items-center gap-2">
            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
        @endif

        @php
            $getSetting = function($key, $default = '') {
                static $cache = null;
                if ($cache === null) {
                    try {
                        $cache = \Illuminate\Support\Facades\DB::table('system_settings')->pluck('value', 'key')->toArray();
                    } catch (\Exception $e) {
                        $cache = [];
                    }
                }
                return $cache[$key] ?? $default;
            };
        @endphp

        {{-- Email Notifications --}}
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <div class="panel overflow-hidden">
                <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-brand/15 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-ink">{{ __('Email Notifications') }}</h2>
                            <p class="text-sm text-muted">{{ __('Choose which events trigger email alerts to admin') }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @php
                        $emailEvents = [
                            ['key' => 'notify_new_user', 'label' => __('New User Registration'), 'desc' => __('When a new user signs up for the platform'), 'default' => 'true'],
                            ['key' => 'notify_payment_success', 'label' => __('Successful Payment'), 'desc' => __('When a subscription payment is processed successfully'), 'default' => 'true'],
                            ['key' => 'notify_payment_failed', 'label' => __('Failed Payment'), 'desc' => __('When a subscription payment fails or is declined'), 'default' => 'true'],
                            ['key' => 'notify_new_ticket', 'label' => __('New Support Ticket'), 'desc' => __('When a user creates a new support ticket'), 'default' => 'true'],
                            ['key' => 'notify_system_alert', 'label' => __('System Alert'), 'desc' => __('Critical system errors, high resource usage, or downtime'), 'default' => 'true'],
                            ['key' => 'notify_plan_change', 'label' => __('Plan Upgrade/Downgrade'), 'desc' => __('When a user changes their subscription plan'), 'default' => 'false'],
                            ['key' => 'notify_user_suspension', 'label' => __('User Suspension'), 'desc' => __('When a user account is suspended or banned'), 'default' => 'true'],
                            ['key' => 'notify_ai_budget', 'label' => __('AI Budget Alert'), 'desc' => __('When AI spending reaches the configured threshold'), 'default' => 'true'],
                            ['key' => 'notify_high_bounce', 'label' => __('High Bounce Rate'), 'desc' => __('When email bounce rate exceeds 5%'), 'default' => 'false'],
                            ['key' => 'notify_daily_summary', 'label' => __('Daily Summary Report'), 'desc' => __('Receive a daily digest of platform activity'), 'default' => 'false'],
                        ];
                        @endphp

                        @foreach($emailEvents as $event)
                        @php $isEnabled = $getSetting($event['key'], $event['default']) === 'true'; @endphp
                        <div class="flex items-center justify-between p-4 bg-surface rounded-xl hover:bg-surface transition-colors">
                            <div>
                                <h4 class="text-sm font-semibold text-ink">{{ $event['label'] }}</h4>
                                <p class="text-xs text-muted mt-0.5">{{ $event['desc'] }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="{{ $event['key'] }}" value="false">
                                <input type="checkbox" name="{{ $event['key'] }}" value="true" class="sr-only peer" {{ $isEnabled ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-brand/40/20 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-surface-2 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-success/100"></div>
                            </label>
                        </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end mt-5">
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-brand rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ __('Save Email Preferences') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Slack Notifications --}}
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <div class="panel overflow-hidden">
                <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-brand/15 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-ink">{{ __('Slack Notifications') }}</h2>
                            <p class="text-sm text-muted">{{ __('Send alerts to your Slack workspace') }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('Slack Webhook URL') }}</label>
                        <input type="url" name="slack_webhook_url" value="{{ $getSetting('slack_webhook_url', '') }}" placeholder="https://hooks.slack.com/services/..." class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all font-mono text-xs">
                    </div>

                    <div class="space-y-3">
                        @php
                        $slackEvents = [
                            ['key' => 'slack_notify_new_user', 'label' => __('New User Registration'), 'default' => 'true'],
                            ['key' => 'slack_notify_payment_failed', 'label' => __('Failed Payment'), 'default' => 'true'],
                            ['key' => 'slack_notify_new_ticket', 'label' => __('New Support Ticket'), 'default' => 'true'],
                            ['key' => 'slack_notify_system_alert', 'label' => __('System Alert'), 'default' => 'true'],
                            ['key' => 'slack_notify_ai_budget', 'label' => __('AI Budget Alert'), 'default' => 'false'],
                        ];
                        @endphp

                        @foreach($slackEvents as $se)
                        @php $seEnabled = $getSetting($se['key'], $se['default']) === 'true'; @endphp
                        <div class="flex items-center justify-between p-3 bg-surface rounded-xl">
                            <span class="text-sm font-medium text-ink/80">{{ $se['label'] }}</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="{{ $se['key'] }}" value="false">
                                <input type="checkbox" name="{{ $se['key'] }}" value="true" class="sr-only peer" {{ $seEnabled ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-brand/40/20 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-surface-2 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-success/100"></div>
                            </label>
                        </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-brand rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ __('Save Slack Settings') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Admin Email Recipients --}}
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <div class="panel overflow-hidden">
                <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-ink">{{ __('Admin Email Recipients') }}</h2>
                            <p class="text-sm text-muted">{{ __('Comma-separated list of emails that receive admin notifications') }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div>
                        <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('Notification Recipients') }}</label>
                        <textarea rows="3" name="admin_notification_emails" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all resize-none font-mono" placeholder="admin@example.com, dev@example.com">{{ $getSetting('admin_notification_emails', '') }}</textarea>
                        <p class="text-[10px] text-muted mt-1">{{ __('One email per line or comma-separated. These addresses receive all enabled admin notifications.') }}</p>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-brand rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ __('Save Recipients') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

    </div>
</x-layouts.admin>
