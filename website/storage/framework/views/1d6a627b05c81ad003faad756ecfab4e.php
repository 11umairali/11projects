
<div x-data="{ open: false }"
     @toggle-shortcuts.window="open = !open"
     @keydown.escape.window="open = false"
     x-cloak>
<script>
window.addEventListener('keydown', function(e) {
    if (e.key === '?' && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName) && !document.activeElement.isContentEditable) {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('toggle-shortcuts'));
    }
});
</script>

    
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false"
         class="fixed inset-0 z-[60] bg-black/40 backdrop-blur-sm"
         style="display: none;"></div>

    
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-[61] flex items-center justify-center p-4 pointer-events-none"
         style="display: none;">

        <div @click.away="open = false"
             class="relative w-full max-w-md bg-surface-2 rounded-2xl shadow-2xl border border-border overflow-hidden pointer-events-auto">

            
            <div class="flex items-center justify-between px-5 py-4 border-b border-border">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-brand/10 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-base font-semibold text-ink">Keyboard Shortcuts</h2>
                </div>
                <button @click="open = false"
                        class="p-1.5 text-muted hover:text-ink hover:bg-surface rounded-lg transition-colors"
                        aria-label="Close shortcuts">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            
            <div class="px-5 py-4 space-y-5 max-h-[60vh] overflow-y-auto">

                
                <div>
                    <h3 class="text-[10px] font-bold text-muted uppercase tracking-wider mb-2.5">Navigation</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-ink">Global search</span>
                            <div class="flex items-center gap-1">
                                <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">Ctrl</kbd>
                                <span class="text-muted text-xs">+</span>
                                <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">K</kbd>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-ink">Focus search</span>
                            <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">/</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-ink">Show shortcuts</span>
                            <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">?</kbd>
                        </div>
                    </div>
                </div>

                <div class="border-t border-border"></div>

                
                <div>
                    <h3 class="text-[10px] font-bold text-muted uppercase tracking-wider mb-2.5">Actions</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-ink">Compose new email</span>
                            <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">C</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-ink">New contact</span>
                            <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">N</kbd>
                        </div>
                    </div>
                </div>

                <div class="border-t border-border"></div>

                
                <div>
                    <h3 class="text-[10px] font-bold text-muted uppercase tracking-wider mb-2.5">Workflow Builder</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-ink">Undo</span>
                            <div class="flex items-center gap-1">
                                <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">Ctrl</kbd>
                                <span class="text-muted text-xs">+</span>
                                <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">Z</kbd>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-ink">Redo</span>
                            <div class="flex items-center gap-1">
                                <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">Ctrl</kbd>
                                <span class="text-muted text-xs">+</span>
                                <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">Y</kbd>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-border"></div>

                
                <div>
                    <h3 class="text-[10px] font-bold text-muted uppercase tracking-wider mb-2.5">General</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-ink">Toggle theme</span>
                            <div class="flex items-center gap-1">
                                <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">Ctrl</kbd>
                                <span class="text-muted text-xs">+</span>
                                <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">.</kbd>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-ink">Close modal / panel</span>
                            <kbd class="inline-flex items-center px-2 py-0.5 rounded-md border border-border bg-surface text-[11px] font-medium text-muted font-mono">Esc</kbd>
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="px-5 py-3 border-t border-border bg-surface">
                <p class="text-[10px] text-muted text-center">Press <kbd class="inline-flex items-center px-1.5 py-0.5 rounded border border-border bg-surface-2 text-[10px] font-medium text-muted font-mono">?</kbd> anytime to toggle this panel</p>
            </div>
        </div>
    </div>
</div>
<?php /**PATH /home/dahimail.com/public_html/resources/views/components/keyboard-shortcuts.blade.php ENDPATH**/ ?>