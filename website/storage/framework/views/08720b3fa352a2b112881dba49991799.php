<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Support\AccountFeatures::checklist()): ?>




<?php
    $wsId = auth()->user()?->active_workspace_id;
    // Auto-detection queries. Each one maps 1:1 with a checklist item
    // below so the tick renders green the moment the underlying action
    // has been taken — no manual clicking.
    $autoDone = [
        'connect_email'   => $wsId && \App\Models\EmailAccount::where('workspace_id', $wsId)->where('status', 'connected')->exists(),
        'import_contacts' => $wsId && \App\Models\Contact::where('workspace_id', $wsId)->exists(),
        'send_campaign'   => $wsId && \App\Models\Campaign::where('workspace_id', $wsId)->whereIn('status', ['sent', 'sending', 'completed'])->exists(),
        'create_workflow' => $wsId && \App\Models\Workflow::where('workspace_id', $wsId)->exists(),
        'train_ai'        => $wsId && (
            \App\Models\KbDocument::where('workspace_id', $wsId)->exists()
            || \App\Models\AiConfig::where('workspace_id', $wsId)->exists()
        ),
        'invite_team'     => $wsId && \App\Models\Workspace::find($wsId)?->members()->count() > 1,
    ];
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!request()->routeIs('onboarding.*')): ?>
<div
    x-data="onboardingChecklist()"
    x-show="visible"
    x-cloak
    class="fixed bottom-20 right-6 z-40"
>
    
    <button
        x-show="collapsed"
        @click="collapsed = false"
        class="flex items-center gap-2.5 rounded-2xl border border-border bg-surface-2 px-4 py-3 shadow-soft hover:shadow-lg transition-all duration-200 group"
        aria-label="Open getting started checklist"
    >
        
        <div class="relative w-8 h-8 shrink-0">
            <svg class="w-8 h-8 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                <circle cx="18" cy="18" r="15" fill="none" stroke="currentColor" stroke-width="3" class="text-border" />
                <circle cx="18" cy="18" r="15" fill="none" stroke="currentColor" stroke-width="3"
                    class="text-brand transition-all duration-700"
                    :stroke-dasharray="`${progress * 94.25 / 100} 94.25`"
                    stroke-linecap="round"
                />
            </svg>
            <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-brand" x-text="`${Math.round(progress)}%`"></span>
        </div>
        <div class="text-left">
            <p class="text-sm font-semibold text-ink">Getting Started</p>
            <p class="text-xs text-muted" x-text="`${completedCount} of ${items.length} complete`"></p>
        </div>
        <svg class="w-4 h-4 text-muted group-hover:text-ink transition-colors ml-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>
    </button>

    
    <div
        x-show="!collapsed"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        class="w-80 rounded-2xl border border-border bg-surface-2 shadow-2xl overflow-hidden"
    >
        
        <div class="px-5 pt-4 pb-3 border-b border-border/50">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-ink">Getting Started</h3>
                <div class="flex items-center gap-1">
                    <button
                        @click="collapsed = true"
                        class="p-1 rounded-lg text-muted hover:text-ink hover:bg-surface transition-colors"
                        aria-label="Minimize checklist"
                    >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                    <button
                        @click="dismiss()"
                        class="p-1 rounded-lg text-muted hover:text-ink hover:bg-surface transition-colors"
                        aria-label="Dismiss checklist permanently"
                    >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>

            
            <div class="flex items-center gap-3">
                <div class="flex-1 h-2 rounded-full bg-surface overflow-hidden">
                    <div
                        class="h-full rounded-full bg-brand transition-all duration-700 ease-out"
                        :style="`width: ${progress}%`"
                    ></div>
                </div>
                <span class="text-xs font-semibold text-brand tabular-nums" x-text="`${Math.round(progress)}%`"></span>
            </div>
        </div>

        
        <div class="px-3 py-2 max-h-80 overflow-y-auto">
            <template x-for="(item, index) in items" :key="item.key">
                <div
                    class="flex items-start gap-3 rounded-xl px-2 py-2.5 transition-colors"
                    :class="item.done ? 'opacity-60' : 'hover:bg-surface-2/80'"
                >
                    
                    <button
                        @click="toggleItem(item.key)"
                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 transition-all duration-200"
                        :class="item.done ? 'bg-success border-success text-white' : 'border-border hover:border-brand'"
                        :aria-label="item.done ? 'Mark ' + item.label + ' as incomplete' : 'Mark ' + item.label + ' as complete'"
                    >
                        <svg x-show="item.done" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>

                    
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium" :class="item.done ? 'line-through text-muted' : 'text-ink'" x-text="item.label"></p>
                        <a
                            x-show="!item.done"
                            :href="item.href"
                            wire:navigate
                            class="inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-strong mt-0.5 transition-colors"
                        >
                            Do this
                            <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </template>
        </div>

        
        <div class="px-5 py-3 border-t border-border/50">
            <button
                @click="dismiss()"
                class="text-xs font-medium text-muted hover:text-ink transition-colors"
            >
                Don't show again
            </button>
        </div>
    </div>
</div>

<script>
function onboardingChecklist() {
    return {
        visible: false,
        collapsed: true,

        // `done` is seeded from the server's real workspace state so the
        // checklist reflects reality without the user ticking anything.
        // localStorage overrides below only ADD completions (a manual tick
        // never un-ticks an auto-detected one).
        items: [
            { key: 'connect_email', label: 'Connect email account', href: '<?php echo e(url('/settings/email')); ?>', done: <?php echo json_encode((bool) ($autoDone['connect_email'] ?? false), 15, 512) ?> },
            { key: 'import_contacts', label: 'Import contacts', href: '<?php echo e(url('/contacts')); ?>', done: <?php echo json_encode((bool) ($autoDone['import_contacts'] ?? false), 15, 512) ?> },
            { key: 'send_campaign', label: 'Send first campaign', href: '<?php echo e(url('/campaigns/create')); ?>', done: <?php echo json_encode((bool) ($autoDone['send_campaign'] ?? false), 15, 512) ?> },
            { key: 'create_workflow', label: 'Create a workflow', href: '<?php echo e(url('/workflows/create')); ?>', done: <?php echo json_encode((bool) ($autoDone['create_workflow'] ?? false), 15, 512) ?> },
            { key: 'train_ai', label: 'Train AI with knowledge base', href: '<?php echo e(url('/knowledge-base')); ?>', done: <?php echo json_encode((bool) ($autoDone['train_ai'] ?? false), 15, 512) ?> },
            { key: 'invite_team', label: 'Invite a team member', href: '<?php echo e(url('/settings/team')); ?>', done: <?php echo json_encode((bool) ($autoDone['invite_team'] ?? false), 15, 512) ?> },
        ],

        get completedCount() {
            return this.items.filter(i => i.done).length;
        },

        get progress() {
            return this.items.length > 0 ? (this.completedCount / this.items.length) * 100 : 0;
        },

        init() {
            // Check if dismissed
            if (localStorage.getItem('onboarding_checklist_dismissed')) return;

            // Merge in any manual ticks from localStorage, but never
            // un-tick a step that's already auto-detected from the server.
            // (Saved completions ADD to the set, they don't replace it.)
            const saved = localStorage.getItem('onboarding_checklist');
            if (saved) {
                try {
                    const completed = JSON.parse(saved);
                    this.items.forEach(item => {
                        if (completed.includes(item.key)) {
                            item.done = true;
                        }
                    });
                } catch (e) {}
            }

            // If all completed, don't show
            if (this.completedCount >= this.items.length) return;

            // Show after a brief delay
            setTimeout(() => {
                this.visible = true;
            }, 2500);
        },

        toggleItem(key) {
            const item = this.items.find(i => i.key === key);
            if (item) {
                item.done = !item.done;
                this.saveProgress();
            }

            // Auto-hide when all complete
            if (this.completedCount >= this.items.length) {
                setTimeout(() => {
                    this.visible = false;
                    localStorage.setItem('onboarding_checklist_dismissed', 'true');
                }, 1500);
            }
        },

        saveProgress() {
            const completed = this.items.filter(i => i.done).map(i => i.key);
            localStorage.setItem('onboarding_checklist', JSON.stringify(completed));
        },

        dismiss() {
            this.visible = false;
            localStorage.setItem('onboarding_checklist_dismissed', 'true');
        },
    };
}
</script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /home/dahimail.com/public_html/resources/views/components/onboarding-checklist.blade.php ENDPATH**/ ?>