<div class="space-y-6">
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-brand/10 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="8" rx="2" stroke-width="2"/><rect x="2" y="13" width="20" height="8" rx="2" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M6 7h.01M6 17h.01"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-ink">{{ __('Mail server settings') }}</h2>
                <p class="text-sm text-muted mt-0.5">
                    @if($info['available'] ?? false)
                        {{ __('Use these details to read and send your :email mailbox from another email app or website.', ['email' => $info['email']]) }}
                    @else
                        {{ $info['message'] ?? '' }}
                    @endif
                </p>
            </div>
        </div>
    </div>

    @if($info['available'] ?? false)
        <div class="grid gap-6 md:grid-cols-2">
            @foreach([['key' => 'imap', 'title' => __('Incoming mail (IMAP)'), 'hint' => __('Lets another app read and sync your mail.')], ['key' => 'smtp', 'title' => __('Outgoing mail (SMTP)'), 'hint' => __('Lets another app send mail from your address.')]] as $sec)
                @php($s = $info[$sec['key']])
                <div class="bg-surface-2 rounded-2xl border border-border p-6">
                    <h3 class="text-base font-semibold text-ink">{{ $sec['title'] }}</h3>
                    <p class="text-xs text-muted mb-3">{{ $sec['hint'] }}</p>
                    @foreach([[__('Server'), $s['host']], [__('Port'), (string) $s['port']], [__('Security'), $s['security']], [__('Username'), $info['username']]] as [$label, $value])
                        <div class="flex items-center justify-between gap-3 py-2.5 border-b border-border" x-data="{ copied: false }">
                            <div class="min-w-0">
                                <div class="text-xs text-muted">{{ $label }}</div>
                                <div class="text-sm font-medium text-ink break-all">{{ $value }}</div>
                            </div>
                            <button type="button" @click="navigator.clipboard.writeText(@js($value)); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="text-xs font-medium text-brand hover:underline flex-shrink-0">
                                <span x-show="!copied">{{ __('Copy') }}</span><span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                            </button>
                        </div>
                    @endforeach
                    <div class="py-2.5 border-b border-border">
                        <div class="text-xs text-muted">{{ __('Password') }}</div>
                        <div class="text-sm text-ink">{{ $info['password_hint'] }}</div>
                    </div>
                    <p class="text-xs text-muted pt-3">{{ __('Alternative: port :port with :sec.', ['port' => $s['alt_port'], 'sec' => $s['alt_security']]) }}</p>
                </div>
            @endforeach
        </div>

        @if(!empty($info['pop3']))
            <div class="bg-surface-2 rounded-2xl border border-border p-6">
                <h3 class="text-base font-semibold text-ink">{{ __('POP3 (only if an app asks for it)') }}</h3>
                <p class="text-sm text-muted mt-1">{{ __('Server') }}: <span class="font-medium text-ink">{{ $info['pop3']['host'] }}</span> &middot; {{ __('Port') }}: <span class="font-medium text-ink">{{ $info['pop3']['port'] }}</span> &middot; {{ $info['pop3']['security'] }}. {{ __('IMAP is recommended: it keeps all your devices in sync.') }}</p>
            </div>
        @endif

        @if(!empty($info['webmail_url']))
            <a href="{{ $info['webmail_url'] }}" target="_blank" rel="noopener" class="btn-primary inline-flex items-center gap-2">{{ __('Open webmail') }}</a>
        @endif

        <p class="text-xs text-muted">{{ __('Keep your password private. If you change it on :app, update it in the other app too.', ['app' => $info['app_name']]) }}</p>
    @endif
</div>
