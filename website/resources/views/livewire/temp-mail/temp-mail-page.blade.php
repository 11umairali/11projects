<div class="space-y-6" wire:poll.keep-alive.3s="refreshInbox">

    {{-- Toast --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button @click="show = false" class="opacity-60 hover:opacity-100">&times;</button>
    </div>
    @endif
    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
         class="p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm flex items-center justify-between">
        <span>{{ session('error') }}</span>
        <button @click="show = false" class="opacity-60 hover:opacity-100">&times;</button>
    </div>
    @endif

    {{-- Page title / plan badge row --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('Temp Mail') }}</h1>
            <p class="text-sm text-muted mt-0.5">{{ __('Generate disposable email addresses for testing and privacy.') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-2 px-4 py-2 text-sm font-bold text-brand bg-brand/10 border border-brand/20 rounded-xl">
                {{ $this->planUsage['used'] }} / {{ $this->planUsage['unlimited'] ? __('Unlimited') : $this->planUsage['limit'] }} {{ __('addresses') }}
            </span>
            <button wire:click="$toggle('showHistory')" class="btn-secondary text-sm">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $showHistory ? __('Active') : __('History') }}
            </button>
        </div>
    </div>

    {{-- History panel — vertical list of past / expired addresses.
         Only rendered when the user toggles the History button on.
         There is no "active addresses" pill bar anymore: the workspace
         has at most one live address at a time, and pressing "Change"
         (or "Generate") replaces it. --}}
    @if($showHistory && $this->activeAddresses->isNotEmpty())
    <div class="panel p-4">
        <div class="space-y-2">
            @foreach($this->activeAddresses as $addr)
                @php
                    // Expired rows keep a red selection highlight — using
                    // the same brand-blue/green for "selected + expired"
                    // as for "selected + active" made the two look
                    // identical, so the user couldn't tell the chosen
                    // address was dead.
                    $isActiveSel = $this->activeAddressId === $addr->id;
                    $isExpired   = $addr->isExpired();
                    if ($isActiveSel && $isExpired) {
                        $btnCls = 'bg-danger/10 border-danger/40 text-danger';
                    } elseif ($isActiveSel) {
                        $btnCls = 'bg-brand/10 border-brand/30 text-brand';
                    } else {
                        $btnCls = 'bg-surface border-border text-ink hover:border-border';
                    }
                @endphp
            <button wire:click="selectAddress({{ $addr->id }})"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all border text-left {{ $btnCls }}">
                <span class="w-2 h-2 rounded-full shrink-0 {{ $isExpired ? 'bg-danger' : ($addr->remainingMinutes() < 60 ? 'bg-warning' : 'bg-success') }}"></span>
                <span class="font-mono text-xs flex-1 truncate {{ $isExpired ? 'line-through opacity-70' : '' }}">{{ $addr->full_address }}</span>
                @if($addr->messages_count > 0)
                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-[10px] font-bold rounded-full {{ $isExpired ? 'bg-danger' : 'bg-brand' }} text-white">{{ $addr->messages_count }}</span>
                @endif
                <span class="text-[10px] shrink-0 font-semibold {{ $isExpired ? 'text-danger' : 'text-muted' }}">{{ $isExpired ? __('Expired') : $addr->remainingTime() }}</span>
            </button>
            @endforeach
        </div>
    </div>
    @endif

    @if($this->selectedAddress)
    @if($this->selectedAddress->isExpired())
    {{-- Unmistakable red banner when the user is looking at a dead
         address. No new mail will ever arrive here because the catch-
         all mapping + TTL on the temp_mail_addresses row have already
         expired — without this banner the page reads as if the
         address is still live, which was confusing when selecting a
         historical entry. --}}
    <div class="p-4 rounded-xl bg-danger/10 border border-danger/30 flex items-start gap-3">
        <svg class="w-5 h-5 text-danger shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.74-2.99l-7.07-12.04a2 2 0 00-3.48 0L3.19 16.01A2 2 0 004.93 19z"/></svg>
        <div class="flex-1">
            <p class="text-sm font-bold text-danger">{{ __('This address has expired') }}</p>
            <p class="text-xs text-danger/80 mt-0.5">{{ __("Any mail sent to it now will be rejected. Click Change above to generate a new live address.") }}</p>
        </div>
    </div>
    @endif
    {{-- Two-column layout: left (address hero) takes 4/12 of the row,
         right (action bar + inbox list) takes 8/12 — so the inbox has
         roughly twice the width of the hero. Both columns are top-
         aligned (items-start) so their first visual element sits on
         the same horizontal line. Collapses to a single column on
         narrow viewports. --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    {{-- LEFT: HERO "Your Temporary Email Address" — big pill with the
         address, a QR trigger, and a copy button (temp-mail.org style). --}}
    <div class="panel p-6 text-center lg:col-span-4" x-data="{
        qrOpen: false,
        copied: false,
        copyAddress() {
            const addr = '{{ $this->selectedAddress->full_address }}';
            navigator.clipboard.writeText(addr).then(() => {
                this.copied = true;
                setTimeout(() => { this.copied = false }, 1500);
            }).catch(() => {
                // Clipboard API can fail on non-HTTPS/older browsers —
                // fall back to a hidden textarea + execCommand so the
                // user still gets visible confirmation.
                const ta = document.createElement('textarea');
                ta.value = addr;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); this.copied = true; setTimeout(() => { this.copied = false }, 1500); } catch (e) {}
                document.body.removeChild(ta);
            });
        }
    }">
        <h2 class="text-base font-semibold tracking-wide text-ink uppercase mb-4">{{ __('Your Temporary Email Address') }}</h2>
        <div class="flex items-center justify-center gap-2 flex-wrap relative">
            <div class="flex-1 max-w-md flex items-center gap-2 px-5 py-3 rounded-full bg-surface border border-border">
                <input type="text" readonly value="{{ $this->selectedAddress->full_address }}"
                       class="flex-1 bg-transparent text-center text-base font-mono font-semibold text-brand focus:outline-none" />
                <button @click="qrOpen = true" title="{{ __('Show QR') }}" class="shrink-0 w-8 h-8 rounded-full bg-surface-2 border border-border flex items-center justify-center text-muted hover:text-ink transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h2m-2 4h2m2-4h2m-2 4h2"/></svg>
                </button>
            </div>
            <button
                @click="copyAddress()"
                :title="copied ? '{{ __('Copied!') }}' : '{{ __('Copy address') }}'"
                class="shrink-0 w-11 h-11 rounded-full text-white flex items-center justify-center transition shadow-soft"
                :class="copied ? 'bg-brand' : 'bg-success hover:opacity-90'">
                {{-- Default copy icon --}}
                <svg x-show="!copied" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                {{-- Checkmark after copy succeeds --}}
                <svg x-show="copied" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </button>

            {{-- Floating confirmation toast — pops up above the pill so
                 the user has an unmistakable signal the address was
                 copied, even if they weren't watching the icon swap. --}}
            <span x-show="copied" x-cloak
                  x-transition:enter="transition ease-out duration-150"
                  x-transition:enter-start="opacity-0 translate-y-1"
                  x-transition:enter-end="opacity-100 translate-y-0"
                  class="absolute -top-6 right-0 px-2.5 py-1 rounded-md bg-brand text-white text-[11px] font-bold shadow-soft pointer-events-none">
                {{ __('Copied!') }}
            </span>
        </div>
        <p class="text-xs text-muted mt-5 max-w-xl mx-auto leading-relaxed">
            {{ __('Forget about spam, advertising mailings, hacking and attacking robots. Keep your real mailbox clean and secure. Temp Mail provides temporary, secure, anonymous, free, disposable email address.') }}
        </p>

        {{-- Meta strip: label, expiry, message count --}}
        <p class="text-[11px] text-muted mt-3">
            @if($this->selectedAddress->label) {{ $this->selectedAddress->label }} · @endif
            {{ $this->selectedAddress->isExpired() ? __('Expired') : __('Expires') . ' ' . $this->selectedAddress->expires_at->diffForHumans() }}
            · {{ $this->selectedAddress->messages_count }} {{ __('message(s)') }}
        </p>

        {{-- QR modal --}}
        <div x-show="qrOpen" x-cloak @click.self="qrOpen = false"
             class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 backdrop-blur-sm px-4">
            <div class="rounded-2xl bg-white p-6 max-w-xs w-full text-center">
                <h3 class="text-sm font-bold text-gray-800 mb-3">{{ __('Scan to copy') }}</h3>
                <img class="mx-auto" alt="QR"
                     src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($this->selectedAddress->full_address) }}" />
                <p class="text-xs font-mono text-gray-600 mt-3 break-all">{{ $this->selectedAddress->full_address }}</p>
                <button @click="qrOpen = false" class="mt-4 btn-secondary w-full">{{ __('Close') }}</button>
            </div>
        </div>
    </div>

    {{-- RIGHT column wrapper: action bar + message list stacked.
         Wrapped in a `panel` so the column's top edge aligns with the
         left hero panel's top edge (both share the same border/padding
         weight instead of the right side starting higher as bare
         pill-buttons). --}}
    <div class="panel p-6 space-y-4 lg:col-span-8">
    {{-- Action bar: Copy / Refresh / Change / Delete --}}
    <div class="flex flex-wrap items-center justify-center gap-3">
        <button
            onclick="var btn=this;navigator.clipboard.writeText('{{ $this->selectedAddress->full_address }}').then(function(){var l=btn.querySelector('.lbl');var old=l.textContent;l.textContent='{{ __('Copied!') }}';setTimeout(function(){l.textContent=old},1500)})"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-surface-2 border border-border text-sm font-semibold text-ink hover:bg-surface transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            <span class="lbl">{{ __('Copy') }}</span>
        </button>
        <button wire:click="syncNow"
                wire:loading.attr="disabled"
                wire:target="syncNow"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-surface-2 border border-border text-sm font-semibold text-ink hover:bg-surface transition">
            <svg wire:loading.remove wire:target="syncNow" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            <svg wire:loading wire:target="syncNow" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span wire:loading.remove wire:target="syncNow">{{ __('Refresh') }}</span>
            <span wire:loading wire:target="syncNow">{{ __('Syncing…') }}</span>
        </button>
        <button wire:click="generateAddress"
                wire:loading.attr="disabled"
                wire:target="generateAddress"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-surface-2 border border-border text-sm font-semibold text-ink hover:bg-surface transition">
            <svg wire:loading.remove wire:target="generateAddress" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            <svg wire:loading wire:target="generateAddress" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            {{ __('Change') }}
        </button>
        <button wire:click="deleteAddress({{ $this->selectedAddress->id }})"
                wire:confirm="{{ __('Delete this address and all messages?') }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-surface-2 border border-border text-sm font-semibold text-danger hover:bg-danger/10 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ __('Delete') }}
        </button>
    </div>

    {{-- Message list / detail area — swaps between a table of messages
         and a single message detail view when one is clicked.
         Constrained width + auto-centered so the card doesn't stretch
         edge-to-edge; feels more focused and matches temp-mail.org.
         The panel chrome is dropped entirely when the inbox is empty
         so the "No messages yet" state reads as a light note instead
         of a boxy empty card. --}}
    @php $hasInboxContent = $this->selectedMessageDetail || $this->messages->isNotEmpty(); @endphp
    {{-- The outer right column is already a `panel`, so the inner
         list area just gets a subtle border + overflow-hidden to keep
         the table's rounded corners clean. No nested panel chrome. --}}
    <div class="{{ $hasInboxContent ? 'rounded-xl border border-border overflow-hidden' : '' }}">
        @if($this->selectedMessageDetail)
            {{-- DETAIL VIEW --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-border bg-surface/40">
                <button wire:click="$set('selectedMessageId', null)"
                        class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-ink hover:text-brand transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    {{ __('Back to list') }}
                </button>
                <div class="flex items-center gap-4">
                    <button onclick="window.open('data:text/html;charset=utf-8,' + encodeURIComponent(document.getElementById('tm-msg-src').value), '_blank')"
                            class="text-xs font-bold uppercase tracking-wider text-ink hover:text-brand transition">{{ __('Source') }}</button>
                </div>
            </div>
            <textarea id="tm-msg-src" class="hidden">{{ $this->selectedMessageDetail->body_html ?: $this->selectedMessageDetail->body_text }}</textarea>

            <div class="px-6 py-5 border-b border-border">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-xs font-bold">
                        {{ strtoupper(substr($this->selectedMessageDetail->from_name ?: $this->selectedMessageDetail->from_email, 0, 2)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-ink truncate">{{ $this->selectedMessageDetail->from_name ?: __('Unknown') }}</p>
                        <p class="text-xs text-muted truncate">{{ $this->selectedMessageDetail->from_email }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted">{{ __('Date') }}</p>
                        <p class="text-xs text-ink">{{ $this->selectedMessageDetail->created_at->format('d-m-Y H:i:s') }}</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <span class="text-xs text-muted">{{ __('Subject') }}:</span>
                    <span class="text-sm font-medium text-ink">{{ $this->selectedMessageDetail->subject ?: __('(No Subject)') }}</span>
                </div>
            </div>

            <div class="px-6 py-5">
                <div class="prose prose-sm max-w-none text-ink">
                    {!! $this->selectedMessageDetail->safe_body_html ?? nl2br(e($this->selectedMessageDetail->body_text ?? '')) !!}
                </div>
                @if($this->selectedMessageDetail->attachments && $this->selectedMessageDetail->attachments->isNotEmpty())
                <div class="mt-6 pt-4 border-t border-border">
                    <p class="text-xs font-bold text-muted uppercase tracking-wider mb-3">{{ __('Attachments') }} ({{ $this->selectedMessageDetail->attachments->count() }})</p>
                    @foreach($this->selectedMessageDetail->attachments as $att)
                    <div class="flex items-center gap-3 p-3 bg-surface-2 rounded-xl border border-border mb-2">
                        <svg class="w-5 h-5 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        <p class="text-xs font-medium text-ink truncate flex-1">{{ $att->file_name ?? $att->filename ?? __('Attachment') }}</p>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        @elseif($this->messages->isNotEmpty())
            {{-- LIST VIEW — three-column table: SENDER / SUBJECT / VIEW,
                 all on one line. Uses inline flex widths (not Tailwind
                 grid-cols-[...]) because the JIT build doesn't always
                 have the arbitrary grid-template-columns compiled —
                 that's what was making the header labels stack. --}}
            <div class="flex items-center px-5 py-2 border-b border-border bg-surface/40 text-[11px] font-bold uppercase tracking-wider text-muted">
                <span style="flex: 1 1 0%; min-width: 0;">{{ __('Sender') }}</span>
                <span style="flex: 1.5 1 0%; min-width: 0;">{{ __('Subject') }}</span>
                <span class="text-right" style="width: 60px; flex-shrink: 0;">{{ __('View') }}</span>
            </div>
            @foreach($this->messages as $msg)
            <button wire:click="selectMessage({{ $msg->id }})"
                    class="w-full flex items-center px-5 py-2.5 border-b border-border/40 hover:bg-surface-2 transition text-left">
                {{-- Sender column --}}
                <div class="flex items-center gap-3" style="flex: 1 1 0%; min-width: 0;">
                    <span class="w-1.5 h-1.5 rounded-full bg-success shrink-0"></span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink truncate">{{ $msg->from_name ?: __('Unknown') }}</p>
                        <p class="text-[11px] text-muted truncate">{{ $msg->from_email }}</p>
                    </div>
                </div>
                {{-- Subject column --}}
                <p class="text-sm text-ink truncate" style="flex: 1.5 1 0%; min-width: 0;">{{ $msg->subject ?: __('(No Subject)') }}</p>
                {{-- View chevron column --}}
                <div class="flex justify-end" style="width: 60px; flex-shrink: 0;">
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
            </button>
            @endforeach
        @else
            {{-- EMPTY state — tight layout, no picture-frame icon (it
                 read as a broken-image placeholder on the dark bg) and
                 no giant blank space above/below. One short headline +
                 one line telling the user which address to mail. --}}
            <div class="px-6 py-8 text-center space-y-1.5">
                <p class="text-sm font-semibold text-muted">{{ __('No messages yet') }}</p>
                <p class="text-[12px] text-muted/70">
                    {{ __('Send an email to') }}
                    <span class="font-mono font-semibold text-ink">{{ $this->selectedAddress->full_address }}</span>
                    {{ __('and it will appear here.') }}
                </p>
            </div>
        @endif
    </div>
    </div> {{-- /right column --}}
    </div> {{-- /grid --}}

    @else
    <div class="panel p-0 overflow-hidden">
        <div class="p-16 text-center">
            <svg class="w-14 h-14 text-muted/15 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <p class="text-lg font-semibold text-muted">{{ __('Temp Mail') }}</p>
            <p class="text-sm text-muted/50 mt-1">{{ __('Generate a disposable email address to get started') }}</p>
            <button wire:click="generateAddress" class="btn-primary mt-4">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Generate Address') }}
            </button>
        </div>
    </div>
    @endif

    {{-- Generate Modal (kept for users who want to pick a specific
         domain / add a label — still wired up via an advanced flow). --}}
    @if($showGenerator)
    <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] flex items-center justify-center px-4" x-transition>
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showGenerator', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-border bg-surface-2 shadow-soft overflow-hidden">
                <div class="px-6 pt-6 pb-4 border-b border-border">
                    <h3 class="text-lg font-bold text-ink">{{ __('Generate Temp Email') }}</h3>
                    <p class="text-sm text-muted mt-1">{{ __('Create a new disposable email address.') }}</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink mb-1.5">{{ __('Domain') }}</label>
                        <select wire:model="selectedDomainId" class="input-field">
                            <option value="">{{ __('Random domain') }}</option>
                            @foreach($this->availableDomains as $d)
                            <option value="{{ $d->id }}">{{ $d->domain }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink mb-1.5">{{ __('Label (optional)') }}</label>
                        <input wire:model="addressLabel" type="text" class="input-field" placeholder="{{ __('e.g. Newsletter test') }}">
                    </div>
                </div>
                <div class="px-6 py-4 bg-surface border-t border-border flex justify-end gap-2">
                    <button wire:click="$set('showGenerator', false)" class="btn-secondary">{{ __('Cancel') }}</button>
                    <button wire:click="generateAddress" class="btn-primary">{{ __('Generate') }}</button>
                </div>
            </div>
        </div>
    </template>
    @endif
</div>
