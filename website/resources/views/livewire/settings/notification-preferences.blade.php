<div class="space-y-6">
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm">{{ session('success') }}</div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-ink">{{ __('Notification Preferences') }}</h1>
        <p class="text-sm text-muted mt-1">{{ __('Choose how and when you want to be notified.') }}</p>
    </div>

    {{-- Global toggles --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Notification Channels') }}</h2>
        <div class="space-y-4">
            <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-info/10 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-ink">{{ __('Email Notifications') }}</p>
                        <p class="text-xs text-muted">{{ __('Receive notifications via email') }}</p>
                    </div>
                </div>
                <button wire:click="$toggle('emailNotifs')"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $emailNotifs ? 'bg-primary-600' : 'bg-gray-200' }}">
                    <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $emailNotifs ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>

            <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-primary-50 dark:bg-primary-900/20 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-ink">{{ __('In-App Notifications') }}</p>
                        <p class="text-xs text-muted">{{ __('Show notifications inside') }} {{ config('app.name') }}</p>
                    </div>
                </div>
                <button wire:click="$toggle('inAppNotifs')"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $inAppNotifs ? 'bg-primary-600' : 'bg-gray-200' }}">
                    <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $inAppNotifs ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>

            <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-brand/10 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-ink">{{ __('Slack Notifications') }}</p>
                        <p class="text-xs text-muted">{{ __('Send notifications to your Slack channel') }}</p>
                    </div>
                </div>
                <button wire:click="$toggle('slackNotifs')"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $slackNotifs ? 'bg-primary-600' : 'bg-gray-200' }}">
                    <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $slackNotifs ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Per-event toggles --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="p-6 border-b border-border">
            <h2 class="text-lg font-semibold text-ink">{{ __('Event Notifications') }}</h2>
            <p class="text-sm text-muted mt-1">{{ __('Fine-tune which events trigger notifications on each channel.') }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Event') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Email') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-muted uppercase tracking-wider">{{ __('In-App') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Slack') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @php
                    $eventLabels = [
                        'announcement_general' => ['label' => __('General announcements'), 'desc' => __('Updates from the app administrator')],
                        'announcement_system' => ['label' => __('System announcements'), 'desc' => __('Updates from the app administrator')],
                        'announcement_marketing' => ['label' => __('Marketing announcements'), 'desc' => __('Updates from the app administrator')],
                        'announcement_advertising' => ['label' => __('Advertising announcements'), 'desc' => __('Updates from the app administrator')],
                        'announcement_misc' => ['label' => __('Misc announcements'), 'desc' => __('Updates from the app administrator')],
                        'newConversation' => ['label' => __('New conversation'), 'desc' => __('When a new email or message arrives')],
                        'assignment' => ['label' => __('Assignment'), 'desc' => __('When a conversation is assigned to you')],
                        'aiDraftReady' => ['label' => __('AI draft ready'), 'desc' => __('When an AI draft is ready for review')],
                        'teamMention' => ['label' => __('Team mention'), 'desc' => __('When someone mentions you in a note')],
                        'contactReply' => ['label' => __('Contact reply'), 'desc' => __('When a contact replies to your message')],
                        'campaignComplete' => ['label' => __('Campaign complete'), 'desc' => __('When an email campaign finishes sending')],
                        'weeklyDigest' => ['label' => __('Weekly digest'), 'desc' => __('Summary of your weekly activity')],
                        'billingAlerts' => ['label' => __('Billing alerts'), 'desc' => __('Payment reminders and plan changes')],
                    ];
                    @endphp

                    @foreach($eventLabels as $key => $event)
                    @continue($key === 'billingAlerts' && \App\Support\BillingPolicy::isFree())
                    <tr class="hover:bg-surface">
                        <td class="px-6 py-4">
                            <p class="font-medium text-ink">{{ $event['label'] }}</p>
                            <p class="text-xs text-muted">{{ $event['desc'] }}</p>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button wire:click="$set('events.{{ $key }}.email', {{ ($events[$key]['email'] ?? false) ? 'false' : 'true' }})"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors mx-auto {{ ($events[$key]['email'] ?? false) ? 'bg-primary-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-3.5 w-3.5 rounded-full bg-surface-2 shadow transition-transform {{ ($events[$key]['email'] ?? false) ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button wire:click="$set('events.{{ $key }}.inApp', {{ ($events[$key]['inApp'] ?? false) ? 'false' : 'true' }})"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors mx-auto {{ ($events[$key]['inApp'] ?? false) ? 'bg-primary-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-3.5 w-3.5 rounded-full bg-surface-2 shadow transition-transform {{ ($events[$key]['inApp'] ?? false) ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button wire:click="$set('events.{{ $key }}.slack', {{ ($events[$key]['slack'] ?? false) ? 'false' : 'true' }})"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors mx-auto {{ ($events[$key]['slack'] ?? false) ? 'bg-primary-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-3.5 w-3.5 rounded-full bg-surface-2 shadow transition-transform {{ ($events[$key]['slack'] ?? false) ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Save --}}
    <div class="flex items-center justify-end">
        <button wire:click="save" wire:loading.attr="disabled"
                class="btn-primary px-6 py-2.5 text-sm disabled:opacity-60">
            <span wire:loading.remove wire:target="save">{{ __('Save Notification Preferences') }}</span>
            <span wire:loading wire:target="save" class="flex items-center gap-1.5">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Saving...
            </span>
        </button>
    </div>
</div>
