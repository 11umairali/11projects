{{-- Product Tour / Walkthrough for first-time users --}}
{{-- Self-contained Alpine.js component — no external dependencies --}}
{{-- Shows automatically on first visit, completable or skippable --}}

@if(\App\Support\AccountFeatures::tour() && !request()->routeIs('onboarding.*'))
<div
    x-data="productTour()"
    x-show="show"
    x-cloak
    @keydown.escape.window="if (show) skip()"
    role="dialog"
    aria-modal="true"
    aria-label="Product tour"
    class="fixed inset-0 z-[9998]"
>
    {{-- Backdrop with cutout highlight --}}
    <div class="fixed inset-0 transition-opacity duration-300"
         :class="show ? 'opacity-100' : 'opacity-0'">
        {{-- SVG mask creates the spotlight cutout around the target element --}}
        <svg class="absolute inset-0 w-full h-full" x-ref="backdrop">
            <defs>
                <mask id="tour-spotlight">
                    <rect width="100%" height="100%" fill="white"/>
                    <rect
                        :x="highlight.left"
                        :y="highlight.top"
                        :width="highlight.width"
                        :height="highlight.height"
                        rx="12"
                        fill="black"
                    />
                </mask>
            </defs>
            <rect
                width="100%"
                height="100%"
                fill="rgba(0,0,0,0.55)"
                mask="url(#tour-spotlight)"
                @click="skip"
            />
        </svg>

        {{-- Pulsing ring around target --}}
        <div
            class="fixed pointer-events-none rounded-xl border-2 border-brand transition-all duration-300 ease-out"
            :style="`top:${highlight.top - 4}px;left:${highlight.left - 4}px;width:${highlight.width + 8}px;height:${highlight.height + 8}px;`"
        >
            <div class="absolute inset-0 rounded-xl border-2 border-brand animate-[tour-ping_2s_ease-out_infinite]"></div>
        </div>
    </div>

    {{-- Tooltip popover --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="fixed z-[9999] w-[340px] max-w-[calc(100vw-2rem)]"
        :style="tooltipPosition()"
        x-ref="tooltip"
    >
        {{-- Arrow (pointing toward target) --}}
        <div
            class="absolute w-3 h-3 bg-surface-2 border border-border rotate-45"
            :class="{
                '-left-1.5 top-5 border-r-0 border-t-0': placement === 'right',
                '-right-1.5 top-5 border-l-0 border-b-0': placement === 'left',
                '-top-1.5 left-8 border-b-0 border-r-0': placement === 'bottom',
                '-bottom-1.5 left-8 border-t-0 border-l-0': placement === 'top',
            }"
        ></div>

        <div class="rounded-2xl border border-border bg-surface-2 shadow-2xl overflow-hidden">
            {{-- Step progress bar --}}
            <div class="h-1 bg-surface flex">
                <template x-for="(s, i) in steps" :key="i">
                    <div
                        class="flex-1 transition-all duration-500"
                        :class="i <= step ? 'bg-brand' : 'bg-transparent'"
                        :style="`margin-right: ${i < steps.length - 1 ? '2px' : '0'}`"
                    ></div>
                </template>
            </div>

            <div class="p-5">
                {{-- Icon + step count --}}
                <div class="flex items-center justify-between mb-3">
                    <span
                        class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-brand/10 text-brand"
                        x-html="steps[step]?.icon"
                    ></span>
                    <span class="text-xs font-medium text-muted tabular-nums" x-text="`Step ${step + 1} of ${steps.length}`"></span>
                </div>

                {{-- Title + description --}}
                <h3 class="text-base font-bold text-ink mb-1.5" x-text="steps[step]?.title"></h3>
                <p class="text-sm text-muted leading-relaxed mb-5" x-text="steps[step]?.desc"></p>

                {{-- Actions --}}
                <div class="flex items-center justify-between">
                    <button
                        @click="skip"
                        class="text-xs font-medium text-muted hover:text-ink transition-colors focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-2 rounded px-1 py-0.5"
                        aria-label="Skip product tour"
                    >
                        Skip tour
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            x-show="step > 0"
                            @click="prev"
                            class="px-3 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface transition-colors focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-2"
                            aria-label="Previous step"
                        >
                            Back
                        </button>
                        <button
                            @click="next"
                            class="px-4 py-2 bg-brand text-white text-sm font-semibold rounded-lg hover:bg-brand-strong transition-colors shadow-sm focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-2"
                            x-ref="nextBtn"
                        >
                            <span x-text="step < steps.length - 1 ? 'Next' : 'Get Started'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes tour-ping {
        0% { opacity: 0.6; transform: scale(1); }
        70% { opacity: 0; transform: scale(1.15); }
        100% { opacity: 0; transform: scale(1.15); }
    }
</style>

<script>
function productTour() {
    return {
        show: false,
        step: 0,
        placement: 'right',
        highlight: { top: 0, left: 0, width: 0, height: 0 },

        steps: [
            {
                target: 'a[href$="/inbox"]',
                title: 'Unified Inbox',
                desc: 'All your emails, WhatsApp, SMS, and chat messages in one place. AI can draft replies for you automatically.',
                icon: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
                prefer: 'right',
            },
            {
                target: 'a[href$="/contacts"]',
                title: 'Smart Contacts',
                desc: 'Your CRM with custom fields, tags, and lead scoring. Import contacts from CSV or let them sync from your inbox.',
                icon: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 19c1.8-3 4.8-4.5 6-4.5s4.2 1.5 6 4.5"/><path d="M15.5 11a3 3 0 1 0 0-6"/></svg>',
                prefer: 'right',
            },
            {
                target: 'a[href$="/campaigns"]',
                title: 'Email Campaigns',
                desc: 'Send newsletters, promotions, and drip sequences. A/B test subject lines and track opens, clicks, and conversions.',
                icon: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
                prefer: 'right',
            },
            {
                target: 'a[href$="/workflows"]',
                title: 'Workflow Automation',
                desc: 'Build visual automations: auto-tag contacts, send follow-ups, assign conversations — all without code.',
                icon: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
                prefer: 'right',
            },
            {
                target: 'a[href$="/knowledge-base"]',
                title: 'AI Knowledge Base',
                desc: 'Upload documents or scrape your website so AI replies are accurate and on-brand. The more you train, the smarter it gets.',
                icon: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
                prefer: 'right',
            },
        ],

        init() {
            if (localStorage.getItem('tour_completed')) return;

            // Delay to let sidebar finish rendering + any transitions settle
            setTimeout(() => {
                this.show = true;
                this.$nextTick(() => this.positionStep());
            }, 1500);

            // Reposition on window resize (debounced)
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (this.show) this.positionStep();
                }, 100);
            });
        },

        positionStep() {
            const step = this.steps[this.step];
            if (!step) return;

            const el = document.querySelector(step.target);
            if (!el) {
                // Target not found — advance past it
                if (this.step < this.steps.length - 1) {
                    this.step++;
                    this.$nextTick(() => this.positionStep());
                } else {
                    this.complete();
                }
                return;
            }

            // Scroll target into view if needed
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            // Small delay for scroll to settle
            requestAnimationFrame(() => {
                const rect = el.getBoundingClientRect();
                this.highlight = {
                    top: rect.top,
                    left: rect.left,
                    width: rect.width,
                    height: rect.height,
                };
                this.placement = this.computePlacement(rect, step.prefer || 'right');
            });
        },

        computePlacement(rect, prefer) {
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const tooltipW = 340;
            const tooltipH = 240;
            const gap = 20;

            // Try preferred direction first, then fall back
            const checks = {
                right: rect.right + gap + tooltipW < vw,
                left: rect.left - gap - tooltipW > 0,
                bottom: rect.bottom + gap + tooltipH < vh,
                top: rect.top - gap - tooltipH > 0,
            };

            if (checks[prefer]) return prefer;
            for (const dir of ['right', 'bottom', 'left', 'top']) {
                if (checks[dir]) return dir;
            }
            return 'bottom';
        },

        tooltipPosition() {
            const h = this.highlight;
            const gap = 20;

            switch (this.placement) {
                case 'right':
                    return `left:${h.left + h.width + gap}px;top:${h.top - 8}px;`;
                case 'left':
                    return `left:${h.left - 340 - gap}px;top:${h.top - 8}px;`;
                case 'bottom':
                    return `left:${h.left}px;top:${h.top + h.height + gap}px;`;
                case 'top':
                    return `left:${h.left}px;top:${h.top - 240 - gap}px;`;
                default:
                    return `left:${h.left + h.width + gap}px;top:${h.top - 8}px;`;
            }
        },

        next() {
            if (this.step < this.steps.length - 1) {
                this.step++;
                this.$nextTick(() => this.positionStep());
            } else {
                this.complete();
            }
        },

        prev() {
            if (this.step > 0) {
                this.step--;
                this.$nextTick(() => this.positionStep());
            }
        },

        skip() {
            this.complete();
        },

        complete() {
            this.show = false;
            localStorage.setItem('tour_completed', 'true');
        },
    };
}
</script>
@endif
