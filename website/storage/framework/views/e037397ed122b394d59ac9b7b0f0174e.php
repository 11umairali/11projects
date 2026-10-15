



<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Support\AccountFeatures::tour() && !request()->routeIs('onboarding.*')): ?>
<div
    x-data="onboardingTour()"
    x-show="active"
    x-cloak
    @keydown.escape.window="active && skipTour()"
    @restart-tour.window="restartTour()"
    role="dialog"
    aria-modal="true"
    aria-label="Product onboarding tour"
    class="fixed inset-0 z-[9998] pointer-events-none"
>
    
    <div class="fixed inset-0 transition-opacity duration-300 pointer-events-auto"
         @click="skipTour()"
         :class="active ? 'opacity-100' : 'opacity-0'">
        <svg class="absolute inset-0 w-full h-full" aria-hidden="true">
            <defs>
                <mask id="onboarding-spotlight">
                    <rect width="100%" height="100%" fill="white"/>
                    <rect
                        x-show="currentStep.target"
                        :x="spotlight.left - 6"
                        :y="spotlight.top - 6"
                        :width="spotlight.width + 12"
                        :height="spotlight.height + 12"
                        rx="14"
                        fill="black"
                    />
                </mask>
            </defs>
            <rect
                width="100%"
                height="100%"
                fill="rgba(0,0,0,0.6)"
                mask="url(#onboarding-spotlight)"
            />
        </svg>

        
        <div
            x-show="currentStep.target && spotlight.width > 0"
            class="fixed pointer-events-none rounded-2xl border-2 border-brand transition-all duration-300 ease-out"
            :style="`top:${spotlight.top - 8}px;left:${spotlight.left - 8}px;width:${spotlight.width + 16}px;height:${spotlight.height + 16}px;`"
            aria-hidden="true"
        >
            <div class="absolute inset-0 rounded-2xl border-2 border-brand animate-[onboarding-ping_2s_ease-out_infinite]"></div>
        </div>
    </div>

    
    <div
        x-show="active"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed z-[9999] pointer-events-auto"
        :class="currentStep.target ? 'w-[360px] max-w-[calc(100vw-2rem)]' : 'inset-0 flex items-center justify-center p-4'"
        :style="currentStep.target ? tooltipStyle() : ''"
        x-ref="tourTooltip"
    >
        <div :class="currentStep.target ? '' : 'w-full max-w-md'">
            
            <div
                x-show="currentStep.target"
                class="absolute w-3 h-3 bg-surface-2 border border-border rotate-45"
                :class="{
                    '-left-1.5 top-6 border-r-0 border-t-0': placement === 'right',
                    '-right-1.5 top-6 border-l-0 border-b-0': placement === 'left',
                    '-top-1.5 left-10 border-b-0 border-r-0': placement === 'bottom',
                    '-bottom-1.5 left-10 border-t-0 border-l-0': placement === 'top',
                }"
                aria-hidden="true"
            ></div>

            <div class="rounded-2xl border border-border bg-surface-2 shadow-2xl overflow-hidden">
                
                <div class="h-1 bg-surface flex" aria-hidden="true">
                    <template x-for="(s, i) in steps" :key="i">
                        <div
                            class="flex-1 transition-all duration-500"
                            :class="i <= step ? 'bg-brand' : 'bg-transparent'"
                            :style="`margin-right: ${i < steps.length - 1 ? '2px' : '0'}`"
                        ></div>
                    </template>
                </div>

                <div class="p-6">
                    
                    <div x-show="!currentStep.target" class="flex justify-center mb-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand/10 text-brand">
                            <svg x-show="step === 0" viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a7 7 0 0 1 7 7c0 2.4-1.2 4.5-3 5.7V17a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-2.3C6.2 13.5 5 11.4 5 9a7 7 0 0 1 7-7z"/><path d="M9 22h6"/></svg>
                            <svg x-show="step === steps.length - 1" viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                    </div>

                    
                    <div x-show="currentStep.target" class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-brand/10 text-brand" x-html="currentStep.icon"></span>
                        <span class="text-xs font-medium text-muted tabular-nums" x-text="`Step ${step + 1} of ${steps.length}`"></span>
                    </div>

                    
                    <h3
                        class="font-bold text-ink mb-2"
                        :class="currentStep.target ? 'text-base' : 'text-xl text-center'"
                        x-text="currentStep.title"
                    ></h3>

                    
                    <p
                        class="text-sm text-muted leading-relaxed"
                        :class="currentStep.target ? 'mb-5' : 'mb-6 text-center'"
                        x-text="currentStep.desc"
                    ></p>

                    
                    <div x-show="!currentStep.target" class="flex justify-center mb-5">
                        <span class="text-xs font-medium text-muted tabular-nums" x-text="`Step ${step + 1} of ${steps.length}`"></span>
                    </div>

                    
                    <div x-show="step === steps.length - 1" class="flex flex-col gap-2 mb-4">
                        <a href="<?php echo e(url('/settings/email')); ?>" wire:navigate class="btn-primary justify-center">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            Connect Email
                        </a>
                        <div class="flex gap-2">
                            <a href="<?php echo e(url('/contacts')); ?>" wire:navigate class="btn-secondary flex-1 justify-center text-xs">Import Contacts</a>
                            <button @click="completeTour()" class="btn-secondary flex-1 justify-center text-xs">Explore</button>
                        </div>
                    </div>

                    
                    <div class="flex justify-center gap-1.5 mb-4" aria-hidden="true">
                        <template x-for="(s, i) in steps" :key="i">
                            <button
                                @click="goToStep(i)"
                                class="w-2 h-2 rounded-full transition-all duration-200"
                                :class="i === step ? 'bg-brand w-5' : i < step ? 'bg-brand/40' : 'bg-border'"
                            ></button>
                        </template>
                    </div>

                    
                    <div class="flex items-center justify-between" x-show="step < steps.length - 1 || currentStep.target">
                        <button
                            @click="skipTour"
                            class="text-xs font-medium text-muted hover:text-ink transition-colors rounded px-1 py-0.5 focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-2"
                            aria-label="Skip onboarding tour"
                        >
                            Skip tour
                        </button>
                        <div class="flex items-center gap-2">
                            <button
                                x-show="step > 0"
                                @click="prevStep"
                                class="px-3 py-2 text-sm font-medium text-ink/70 hover:text-ink rounded-lg hover:bg-surface transition-colors focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-2"
                                aria-label="Previous step"
                            >
                                Back
                            </button>
                            <button
                                @click="nextStep"
                                class="px-4 py-2 bg-brand text-white text-sm font-semibold rounded-lg hover:bg-brand-strong transition-colors shadow-sm focus-visible:outline-2 focus-visible:outline-brand focus-visible:outline-offset-2"
                                x-ref="nextBtn"
                            >
                                <span x-text="step < steps.length - 1 ? 'Next' : 'Finish'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes onboarding-ping {
        0% { opacity: 0.6; transform: scale(1); }
        70% { opacity: 0; transform: scale(1.12); }
        100% { opacity: 0; transform: scale(1.12); }
    }
</style>

<script>
function onboardingTour() {
    return {
        active: false,
        step: 0,
        placement: 'right',
        spotlight: { top: 0, left: 0, width: 0, height: 0 },

        steps: [
            {
                target: null,
                title: 'Welcome to ' + <?php echo json_encode(config('app.name'), 15, 512) ?> + '!',
                desc: 'Let us show you around. This quick tour will help you discover the key features of your new workspace.',
                icon: '',
            },
            {
                target: '#main-content',
                title: 'This is your Dashboard',
                desc: 'Your command center. See key metrics, recent activity, and quick actions all in one place.',
                icon: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
                prefer: 'bottom',
            },
            {
                target: 'a[href$="/inbox"]',
                title: 'Your Unified Inbox',
                desc: 'All your emails, WhatsApp, SMS, and chat messages in one place. AI can draft replies for you automatically.',
                icon: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
                prefer: 'right',
            },
            {
                target: 'a[href$="/contacts"]',
                title: 'Manage Contacts',
                desc: 'Your CRM with custom fields, tags, and lead scoring. Import contacts from CSV or let them sync from your inbox.',
                icon: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 19c1.8-3 4.8-4.5 6-4.5s4.2 1.5 6 4.5"/><path d="M15.5 11a3 3 0 1 0 0-6"/></svg>',
                prefer: 'right',
            },
            {
                target: 'a[href$="/campaigns"]',
                title: 'Create Campaigns',
                desc: 'Send newsletters, promotions, and drip sequences. A/B test subject lines and track opens, clicks, and conversions.',
                icon: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
                prefer: 'right',
            },
            {
                target: 'a[href$="/workflows"]',
                title: 'Automate with Workflows',
                desc: 'Build visual automations: auto-tag contacts, send follow-ups, assign conversations -- all without code.',
                icon: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
                prefer: 'right',
            },
            {
                target: 'a[href$="/settings/ai"], a[href*="/knowledge-base"]',
                title: 'AI-Powered Replies',
                desc: 'Train the AI with your knowledge base so it can draft on-brand replies. The more you teach it, the smarter it gets.',
                icon: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a7 7 0 0 1 7 7c0 2.4-1.2 4.5-3 5.7V17a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-2.3C6.2 13.5 5 11.4 5 9a7 7 0 0 1 7-7z"/><path d="M9 22h6"/></svg>',
                prefer: 'right',
            },
            {
                target: null,
                title: "You're all set!",
                desc: "You're ready to start using " + <?php echo json_encode(config('app.name'), 15, 512) ?> + ". Connect your email, import contacts, or just explore on your own.",
                icon: '',
            },
        ],

        get currentStep() {
            return this.steps[this.step] || this.steps[0];
        },

        init() {
            if (localStorage.getItem('onboarding_tour_completed')) return;

            // Delay to let the page fully render
            setTimeout(() => {
                this.active = true;
                this.$nextTick(() => this.positionStep());
            }, 1800);

            // Reposition on resize
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (this.active) this.positionStep();
                }, 100);
            });
        },

        positionStep() {
            const s = this.currentStep;
            if (!s.target) {
                this.spotlight = { top: 0, left: 0, width: 0, height: 0 };
                return;
            }

            const el = document.querySelector(s.target);
            if (!el) {
                // Skip to next if target not found
                if (this.step < this.steps.length - 1) {
                    this.step++;
                    this.$nextTick(() => this.positionStep());
                } else {
                    this.completeTour();
                }
                return;
            }

            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            requestAnimationFrame(() => {
                const rect = el.getBoundingClientRect();
                this.spotlight = {
                    top: rect.top,
                    left: rect.left,
                    width: rect.width,
                    height: rect.height,
                };
                this.placement = this.computePlacement(rect, s.prefer || 'right');
            });
        },

        computePlacement(rect, prefer) {
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const tw = 360;
            const th = 280;
            const gap = 24;

            const fits = {
                right: rect.right + gap + tw < vw,
                left: rect.left - gap - tw > 0,
                bottom: rect.bottom + gap + th < vh,
                top: rect.top - gap - th > 0,
            };

            if (fits[prefer]) return prefer;
            for (const dir of ['right', 'bottom', 'left', 'top']) {
                if (fits[dir]) return dir;
            }
            return 'bottom';
        },

        tooltipStyle() {
            const h = this.spotlight;
            const gap = 24;

            switch (this.placement) {
                case 'right':
                    return `left:${h.left + h.width + gap}px;top:${Math.max(16, h.top - 12)}px;`;
                case 'left':
                    return `left:${h.left - 360 - gap}px;top:${Math.max(16, h.top - 12)}px;`;
                case 'bottom':
                    return `left:${Math.max(16, Math.min(h.left, window.innerWidth - 376))}px;top:${h.top + h.height + gap}px;`;
                case 'top':
                    return `left:${Math.max(16, Math.min(h.left, window.innerWidth - 376))}px;top:${h.top - 280 - gap}px;`;
                default:
                    return `left:${h.left + h.width + gap}px;top:${h.top - 12}px;`;
            }
        },

        goToStep(i) {
            this.step = i;
            this.$nextTick(() => this.positionStep());
        },

        nextStep() {
            if (this.step < this.steps.length - 1) {
                this.step++;
                this.$nextTick(() => this.positionStep());
            } else {
                this.completeTour();
            }
        },

        prevStep() {
            if (this.step > 0) {
                this.step--;
                this.$nextTick(() => this.positionStep());
            }
        },

        skipTour() {
            this.completeTour();
        },

        restartTour() {
            localStorage.removeItem('onboarding_tour_completed');
            this.step = 0;
            this.active = true;
            this.$nextTick(() => this.positionStep());
        },

        completeTour() {
            this.active = false;
            localStorage.setItem('onboarding_tour_completed', 'true');

            // Persist to server (fire-and-forget)
            if (typeof Livewire !== 'undefined') {
                try {
                    fetch('/onboarding/complete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        },
                        body: JSON.stringify({ tour_completed: true }),
                    }).catch(() => {});
                } catch (e) {}
            }
        },
    };
}
</script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /home/dahimail.com/public_html/resources/views/components/onboarding-tour.blade.php ENDPATH**/ ?>