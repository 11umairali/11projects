    <script>
        function friendSearch() {
            return {
                pending: {}, q: '', res: [], hint: '', busy: false, t: null, sequence: 0, poll: null,
                init() {
                    this.poll = setInterval(() => { if (!document.hidden && !this.busy) this.refresh(); }, 5000);
                },
                destroy() { clearTimeout(this.t); clearInterval(this.poll); this.sequence++; },
                refresh() { if (this.q.trim().length >= 3) this.go(); },
                type() { this.sequence++; this.busy = false; clearTimeout(this.t); this.hint = ''; if (this.q.trim().length < 3) { this.res = []; this.hint = this.q.trim() ? @js(__('Type at least 3 characters.')) : ''; return; } this.t = setTimeout(() => this.go(), 450); },
                go() {
                    const sequence = ++this.sequence;
                    this.busy = true;
                    fetch('/friends/api/search?q=' + encodeURIComponent(this.q.trim()), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(r => { if (!r.ok) throw new Error('Search failed'); return r.json(); }).then(j => { if (sequence !== this.sequence) return; this.res = j.data || []; this.hint = j.hint || (this.res.length ? '' : @js(__('Nobody found. Check the spelling, or type the full e-mail address or the phone number.'))); })
                        .catch(() => { if (sequence !== this.sequence) return; this.hint = @js(__('Search failed. Try again.')); }).finally(() => { if (sequence === this.sequence) this.busy = false; });
                },
                add(p) {
                    if (this.pending[p.id]) return;
                    this.pending[p.id] = true;
                    fetch('/friends/api/request', { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ user_id: p.id }) })
                        .then(r => r.json().then(j => ({ ok: r.ok, j }))).then(x => { if (x.ok) { this.sequence++; this.busy = false; p.relation = 'sent'; window.dispatchEvent(new CustomEvent('dahi:sync', { detail: { type: 'friends' } })); } else { alert(x.j.message || @js(__('Could not send the request.'))); } })
                        .catch(() => alert(@js(__('Could not send the request.')))).finally(() => { this.pending[p.id] = false; });
                },
            };
        }
    </script>
    <div class="friend-search space-y-3" x-data="friendSearch()" @dahi:realtime.window="refresh()" @friends-updated.window="refresh()">
        <div class="bg-surface-2 rounded-2xl border border-border p-4">
            <div class="font-medium text-ink mb-1">{{ __('A new conversation starts here') }}</div>
            <p class="text-xs text-muted mb-2">{{ __('Search by username, full name, full e-mail address (name@example.com) or phone number. Send a request; you become friends when they accept.') }}</p>
            <div class="flex items-center gap-2"><x-action-icon name="search" /><input type="search" x-model="q" @input="type()" class="input w-full" aria-label="{{ __('Search friends') }}" placeholder="{{ __('Username, name, e-mail or phone number') }}" autocomplete="off" autocapitalize="off"></div>
            <div class="text-xs text-muted mt-2" x-show="busy">{{ __('Searching…') }}</div>
            <div class="text-sm text-muted mt-2" x-show="hint" x-text="hint"></div>
            <div class="mt-3 divide-y divide-border" x-show="res.length">
                <template x-for="p in res" :key="p.id">
                    <div class="flex items-center gap-3 py-2.5">
                        <div class="w-10 h-10 rounded-full profile-initial flex items-center justify-center font-semibold overflow-hidden flex-shrink-0">
                            <template x-if="p.avatar_url"><img :src="p.avatar_url" alt="" class="w-full h-full object-cover"></template>
                            <template x-if="!p.avatar_url"><span x-text="(p.name || '?').charAt(0).toUpperCase()"></span></template>
                        </div>
                        <div class="min-w-0 flex-1"><div class="font-medium text-ink truncate" x-text="p.name"></div><div class="text-xs text-muted truncate" x-text="p.username ? p.username : ''"></div></div>
                        <template x-if="p.relation === 'none' || p.relation === 'team'"><button type="button" class="btn-primary" :disabled="!!pending[p.id]" @click="add(p)">{{ __('Add friend') }}</button></template>
                        <template x-if="p.relation === 'sent'"><span class="text-sm text-muted">{{ __('Request sent') }}</span></template>
                        <template x-if="p.relation === 'received'"><span class="text-sm text-brand">{{ __('Sent you a request – open Requests') }}</span></template>
                        <template x-if="p.relation === 'friend'"><a :href="p.chat_url" class="btn-secondary">{{ __('Chat') }}</a></template>
                    </div>
                </template>
            </div>
        </div>
    </div>
