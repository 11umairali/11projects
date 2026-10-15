{{--
    Toast Container Component
    -------------------------
    A global toast notification system. Place once in your layout (before </body>).

    Usage in layout:
        <x-toast-container />

    Trigger from anywhere:
        <!-- Alpine dispatch -->
        $dispatch('toast', { type: 'success', message: 'Contact saved!', duration: 5000 })

        <!-- Livewire -->
        $this->dispatch('toast', type: 'error', message: 'Something went wrong.');

        <!-- Blade session flash (auto-dispatched by this component) -->
        session()->flash('success', 'Saved!');

    Props on the event:
        type     - success | error | warning | info (default: "info")
        message  - Toast text (required)
        duration - Auto-dismiss in ms, 0 = sticky (default: 5000)
--}}

<div
    x-data="{
        toasts: [],
        _counter: 0,

        add(detail) {
            const id = ++this._counter;
            const duration = detail.duration ?? 5000;
            const toast = {
                id,
                type: detail.type || 'info',
                message: detail.message || '',
                duration,
                remaining: duration,
                paused: false,
                _timer: null,
                _startTime: null,
            };
            this.toasts.push(toast);

            if (duration > 0) {
                this.$nextTick(() => this._startTimer(id));
            }
        },

        dismiss(id) {
            const idx = this.toasts.findIndex(t => t.id === id);
            if (idx !== -1) {
                clearTimeout(this.toasts[idx]._timer);
                this.toasts.splice(idx, 1);
            }
        },

        _startTimer(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (!toast || toast.duration <= 0) return;
            toast._startTime = Date.now();
            toast._timer = setTimeout(() => this.dismiss(id), toast.remaining);
        },

        pauseTimer(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (!toast || toast.duration <= 0) return;
            clearTimeout(toast._timer);
            const elapsed = Date.now() - toast._startTime;
            toast.remaining = Math.max(0, toast.remaining - elapsed);
            toast.paused = true;
        },

        resumeTimer(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (!toast || toast.duration <= 0) return;
            toast.paused = false;
            toast._startTime = Date.now();
            toast._timer = setTimeout(() => this.dismiss(id), toast.remaining);
        },

        typeConfig(type) {
            switch (type) {
                case 'success': return {
                    bg: 'bg-surface-2 border-success/30',
                    iconBg: 'bg-success/10',
                    iconColor: 'text-success',
                    progressColor: 'bg-success',
                };
                case 'error': return {
                    bg: 'bg-surface-2 border-danger/30',
                    iconBg: 'bg-danger/10',
                    iconColor: 'text-danger',
                    progressColor: 'bg-danger',
                };
                case 'warning': return {
                    bg: 'bg-surface-2 border-warning/30',
                    iconBg: 'bg-warning/10',
                    iconColor: 'text-warning',
                    progressColor: 'bg-warning',
                };
                case 'info': default: return {
                    bg: 'bg-surface-2 border-info/30',
                    iconBg: 'bg-info/10',
                    iconColor: 'text-info',
                    progressColor: 'bg-info',
                };
            }
        }
    }"
    x-on:toast.window="add($event.detail)"
    class="fixed top-4 right-4 z-[9999] flex flex-col items-end gap-3 pointer-events-none max-w-sm w-full"
    role="region"
    aria-label="Notifications"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-data="{ visible: false }"
            x-init="$nextTick(() => visible = true)"
            x-show="visible"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-8 scale-95"
            x-transition:enter-end="opacity-100 translate-x-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0 scale-100"
            x-transition:leave-end="opacity-0 translate-x-8 scale-95"
            @mouseenter="pauseTimer(toast.id)"
            @mouseleave="resumeTimer(toast.id)"
            class="pointer-events-auto w-full rounded-2xl border shadow-soft overflow-hidden"
            :class="typeConfig(toast.type).bg"
            role="alert"
            :aria-live="toast.type === 'error' ? 'assertive' : 'polite'"
        >
            <div class="flex items-start gap-3 p-4">
                {{-- Icon --}}
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="typeConfig(toast.type).iconBg">
                    {{-- Success icon --}}
                    <svg x-show="toast.type === 'success'" class="h-4 w-4" :class="typeConfig(toast.type).iconColor" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    {{-- Error icon --}}
                    <svg x-show="toast.type === 'error'" class="h-4 w-4" :class="typeConfig(toast.type).iconColor" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    {{-- Warning icon --}}
                    <svg x-show="toast.type === 'warning'" class="h-4 w-4" :class="typeConfig(toast.type).iconColor" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    {{-- Info icon --}}
                    <svg x-show="toast.type === 'info'" class="h-4 w-4" :class="typeConfig(toast.type).iconColor" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </div>

                {{-- Message --}}
                <p class="flex-1 text-sm font-medium text-ink pt-1 leading-snug" x-text="toast.message"></p>

                {{-- Dismiss --}}
                <button
                    @click="dismiss(toast.id)"
                    class="shrink-0 rounded-lg p-1 text-muted hover:text-ink hover:bg-surface transition"
                    aria-label="Dismiss notification"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            {{-- Progress bar --}}
            <div x-show="toast.duration > 0" class="h-1 w-full bg-border/30">
                <div
                    class="h-full rounded-full transition-none"
                    :class="typeConfig(toast.type).progressColor"
                    :style="`animation: toast-progress ${toast.duration}ms linear forwards; animation-play-state: ${toast.paused ? 'paused' : 'running'};`"
                ></div>
            </div>
        </div>
    </template>
</div>
