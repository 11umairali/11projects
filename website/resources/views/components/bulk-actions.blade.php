@props([
    'actions' => [],
    'modelName' => 'item',
])

{{--
    Bulk Actions Toolbar
    ────────────────────
    A floating bottom bar that appears when items are selected in a table/list.

    Usage:
    <x-bulk-actions :actions="[
        ['label' => 'Delete', 'value' => 'delete', 'icon' => 'trash', 'variant' => 'danger'],
        ['label' => 'Export', 'value' => 'export', 'icon' => 'download', 'variant' => 'secondary'],
        ['label' => 'Add Tag', 'value' => 'add-tag', 'icon' => 'tag', 'variant' => 'secondary'],
    ]" model-name="contact" />

    Alpine events consumed:
      - bulk-select-toggle  { id, checked }   — from <x-bulk-select-checkbox>
      - bulk-select-all     { checked }        — from <x-select-all-checkbox>
      - bulk-select-all-pages                  — "Select all X items" across pages

    Alpine events dispatched:
      - bulk-action-confirmed  { action, ids }
      - bulk-deselect-all
--}}
<div
    x-data="bulkActions(@js($actions), @js($modelName))"
    x-on:bulk-select-toggle.window="toggleItem($event.detail)"
    x-on:bulk-select-all.window="selectAllVisible($event.detail)"
    x-on:bulk-select-all-pages.window="selectAllPages($event.detail)"
    x-on:bulk-items-loaded.window="setVisibleIds($event.detail)"
    class="contents"
>
    {{-- Floating bottom bar --}}
    <div
        x-show="selectedIds.length > 0"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-full opacity-0"
        x-cloak
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 rounded-2xl border border-border bg-surface-2/95 px-5 py-3 shadow-lg backdrop-blur"
        role="toolbar"
        aria-label="Bulk actions"
    >
        {{-- Selected count --}}
        <div class="flex items-center gap-2 text-sm font-medium text-ink whitespace-nowrap">
            <span class="flex items-center justify-center h-6 min-w-[1.5rem] rounded-full bg-brand/15 px-2 text-xs font-bold text-brand" x-text="selectedIds.length"></span>
            <span>
                <span x-text="selectedIds.length === 1 ? modelName : modelName + 's'"></span>
                selected
            </span>
            <template x-if="allPagesSelected">
                <span class="text-xs text-brand font-semibold">(all)</span>
            </template>
        </div>

        {{-- Divider --}}
        <div class="h-6 w-px bg-border"></div>

        {{-- "Select all across pages" nudge --}}
        <template x-if="allVisibleSelected && !allPagesSelected && totalItems > visibleIds.length">
            <button
                type="button"
                x-on:click="$dispatch('bulk-select-all-pages', { total: totalItems })"
                class="text-xs font-medium text-brand hover:underline whitespace-nowrap"
            >
                Select all <span x-text="totalItems"></span> <span x-text="modelName + 's'"></span>
            </button>
        </template>

        {{-- Action dropdown --}}
        <div class="relative" x-data="{ open: false }">
            <button
                type="button"
                x-on:click="open = !open"
                class="btn-secondary btn-sm gap-1.5"
                aria-haspopup="true"
                :aria-expanded="open"
            >
                Actions
                <svg class="w-3.5 h-3.5 text-muted transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div
                x-show="open"
                x-on:click.outside="open = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute bottom-full left-0 mb-2 min-w-[200px] rounded-xl border border-border bg-surface-2 p-1.5 shadow-lg"
                role="menu"
                x-cloak
            >
                <template x-for="action in actions" :key="action.value">
                    <button
                        type="button"
                        x-on:click="executeAction(action); open = false"
                        class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="action.variant === 'danger'
                            ? 'text-danger hover:bg-danger/10'
                            : 'text-ink hover:bg-surface'"
                        role="menuitem"
                    >
                        {{-- Action icon --}}
                        <template x-if="action.icon === 'trash'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </template>
                        <template x-if="action.icon === 'download'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </template>
                        <template x-if="action.icon === 'tag'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        </template>
                        <template x-if="action.icon === 'tag-off'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4zM1 1l22 22"/></svg>
                        </template>
                        <template x-if="action.icon === 'user-plus'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        </template>
                        <template x-if="action.icon === 'archive'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                        </template>
                        <template x-if="action.icon === 'merge'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4-4m-4 4l4 4"/></svg>
                        </template>
                        <span x-text="action.label"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Deselect all --}}
        <button
            type="button"
            x-on:click="deselectAll()"
            class="text-sm font-medium text-muted hover:text-ink transition whitespace-nowrap"
            aria-label="Deselect all"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    {{-- Confirmation modal for destructive actions --}}
    <template x-if="confirmAction !== null">
        <div
            x-show="confirmAction !== null"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:keydown.escape.window="confirmAction = null"
            class="modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="bulk-confirm-title"
        >
            <div
                x-on:click.outside="confirmAction = null"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="modal-box max-w-md"
            >
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-danger/10">
                        <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                    <div class="flex-1">
                        <h3 id="bulk-confirm-title" class="text-base font-semibold text-ink">
                            Confirm <span x-text="confirmAction?.label"></span>
                        </h3>
                        <p class="text-sm text-muted mt-1">
                            Are you sure you want to <span class="lowercase" x-text="confirmAction?.label"></span>
                            <strong x-text="selectedIds.length"></strong>
                            <span x-text="selectedIds.length === 1 ? modelName : modelName + 's'"></span>?
                            This action cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="modal-action">
                    <button type="button" x-on:click="confirmAction = null" class="btn-secondary">
                        Cancel
                    </button>
                    <button type="button" x-on:click="confirmAndExecute()" class="btn-danger">
                        <span x-text="confirmAction?.label"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('bulkActions', (actions, modelName) => ({
        actions: actions,
        modelName: modelName,
        selectedIds: [],
        visibleIds: [],
        allPagesSelected: false,
        totalItems: 0,
        confirmAction: null,

        get allVisibleSelected() {
            return this.visibleIds.length > 0 &&
                this.visibleIds.every(id => this.selectedIds.includes(id));
        },

        setVisibleIds(detail) {
            this.visibleIds = detail.ids || [];
            this.totalItems = detail.total || this.visibleIds.length;
        },

        toggleItem(detail) {
            const { id, checked } = detail;
            if (checked) {
                if (!this.selectedIds.includes(id)) {
                    this.selectedIds.push(id);
                }
            } else {
                this.selectedIds = this.selectedIds.filter(i => i !== id);
                this.allPagesSelected = false;
            }
        },

        selectAllVisible(detail) {
            const { checked } = detail;
            if (checked) {
                this.visibleIds.forEach(id => {
                    if (!this.selectedIds.includes(id)) {
                        this.selectedIds.push(id);
                    }
                });
            } else {
                this.selectedIds = this.selectedIds.filter(id => !this.visibleIds.includes(id));
                this.allPagesSelected = false;
            }
        },

        selectAllPages(detail) {
            this.allPagesSelected = true;
            this.totalItems = detail.total || this.totalItems;
            // In "all pages" mode, the Livewire component handles the full ID set
        },

        deselectAll() {
            this.selectedIds = [];
            this.allPagesSelected = false;
            this.$dispatch('bulk-deselect-all');
        },

        executeAction(action) {
            if (action.variant === 'danger') {
                this.confirmAction = action;
                return;
            }
            this.dispatchAction(action);
        },

        confirmAndExecute() {
            if (this.confirmAction) {
                this.dispatchAction(this.confirmAction);
                this.confirmAction = null;
            }
        },

        dispatchAction(action) {
            this.$dispatch('bulk-action-confirmed', {
                action: action.value,
                ids: [...this.selectedIds],
                allPages: this.allPagesSelected,
            });
        },
    }));
});
</script>
