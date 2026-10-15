{{--
    Confirm Modal Component
    -----------------------
    A branded confirmation dialog replacing cheap browser confirm().

    Usage:
        <x-confirm-modal
            id="delete-contact"
            title="Delete Contact"
            message="This action cannot be undone. The contact and all associated data will be permanently removed."
            confirm-text="Delete"
            cancel-text="Cancel"
            type="danger"
            icon="trash"
        />

        <!-- Trigger it via Alpine dispatch: -->
        <button @click="$dispatch('confirm-modal', { id: 'delete-contact', onConfirm: () => $wire.delete(contactId) })">
            Delete
        </button>

    Props:
        id          - Unique identifier for this modal instance (required)
        title       - Modal heading text (default: "Are you sure?")
        message     - Body text explaining the action (default: "This action cannot be undone.")
        confirmText - Confirm button label (default: "Confirm")
        cancelText  - Cancel button label (default: "Cancel")
        type        - Visual style: danger | warning | info (default: "danger")
        icon        - Icon name from x-icon component (optional, auto-selected by type)

    Events:
        Listens:  confirm-modal  { id, onConfirm?, onCancel?, title?, message? }
        Emits:    confirmed-{id}, cancelled-{id}
--}}

@props([
    'id',
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'confirmText' => 'Confirm',
    'cancelText' => 'Cancel',
    'type' => 'danger',
    'icon' => null,
])

@php
    $typeConfig = match($type) {
        'danger' => [
            'iconBg'    => 'bg-danger/10',
            'iconColor' => 'text-danger',
            'btnClass'  => 'btn-danger',
            'ringColor' => 'ring-danger/20',
        ],
        'warning' => [
            'iconBg'    => 'bg-warning/10',
            'iconColor' => 'text-warning',
            'btnClass'  => 'inline-flex items-center justify-center gap-2 rounded-xl bg-warning px-5 py-2.5 text-sm font-semibold text-warning-content transition hover:bg-warning/90 disabled:opacity-60 disabled:cursor-not-allowed',
            'ringColor' => 'ring-warning/20',
        ],
        'info' => [
            'iconBg'    => 'bg-info/10',
            'iconColor' => 'text-info',
            'btnClass'  => 'inline-flex items-center justify-center gap-2 rounded-xl bg-info px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-info/90 disabled:opacity-60 disabled:cursor-not-allowed',
            'ringColor' => 'ring-info/20',
        ],
        default => [
            'iconBg'    => 'bg-danger/10',
            'iconColor' => 'text-danger',
            'btnClass'  => 'btn-danger',
            'ringColor' => 'ring-danger/20',
        ],
    };

    $defaultIcon = match($type) {
        'danger'  => 'trash',
        'warning' => 'alert-triangle',
        'info'    => 'check-circle',
        default   => 'alert-triangle',
    };
    $resolvedIcon = $icon ?? $defaultIcon;
@endphp

<div
    x-data="{
        open: false,
        processing: false,
        _onConfirm: null,
        _onCancel: null,
        _title: @js($title),
        _message: @js($message),
        _previousFocus: null,
        _focusableEls: [],

        show(detail) {
            if (detail.title) this._title = detail.title;
            if (detail.message) this._message = detail.message;
            this._onConfirm = detail.onConfirm || null;
            this._onCancel = detail.onCancel || null;
            this._previousFocus = document.activeElement;
            this.open = true;
            this.processing = false;
            this.$nextTick(() => this._trapFocus());
        },

        confirm() {
            if (this.processing) return;
            this.processing = true;
            if (this._onConfirm) {
                try { this._onConfirm(); } catch(e) { console.error(e); }
            }
            this.$dispatch('confirmed-{{ $id }}');
            this.close();
        },

        cancel() {
            if (this._onCancel) {
                try { this._onCancel(); } catch(e) { console.error(e); }
            }
            this.$dispatch('cancelled-{{ $id }}');
            this.close();
        },

        close() {
            this.open = false;
            this.processing = false;
            this.$nextTick(() => {
                if (this._previousFocus) this._previousFocus.focus();
            });
        },

        _trapFocus() {
            const modal = this.$refs.modalPanel;
            if (!modal) return;
            this._focusableEls = [...modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex=\"-1\"])'
            )].filter(el => !el.disabled && el.offsetParent !== null);
            if (this._focusableEls.length) this._focusableEls[this._focusableEls.length - 1].focus();
        },

        _handleTab(e) {
            if (!this.open || !this._focusableEls.length) return;
            const first = this._focusableEls[0];
            const last = this._focusableEls[this._focusableEls.length - 1];
            if (e.shiftKey) {
                if (document.activeElement === first) { e.preventDefault(); last.focus(); }
            } else {
                if (document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        }
    }"
    x-on:confirm-modal.window="if ($event.detail.id === '{{ $id }}') show($event.detail)"
    x-on:keydown.escape.window="if (open) cancel()"
    x-on:keydown.enter.window="if (open && !processing) confirm()"
    x-on:keydown.tab.window="if (open) _handleTab($event)"
    x-cloak
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm"
        @click="cancel()"
        aria-hidden="true"
    ></div>

    {{-- Modal Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-2"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="alertdialog"
        aria-modal="true"
        :aria-labelledby="'confirm-title-{{ $id }}'"
        :aria-describedby="'confirm-desc-{{ $id }}'"
    >
        <div
            x-ref="modalPanel"
            @click.stop
            class="w-full max-w-md rounded-2xl border border-border bg-surface-2 p-6 shadow-soft"
        >
            {{-- Icon + Title --}}
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $typeConfig['iconBg'] }}">
                    <x-icon :name="$resolvedIcon" class="h-5 w-5 {{ $typeConfig['iconColor'] }}" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 id="confirm-title-{{ $id }}" class="text-base font-semibold text-ink" x-text="_title"></h3>
                    <p id="confirm-desc-{{ $id }}" class="mt-1.5 text-sm text-muted leading-relaxed" x-text="_message"></p>
                </div>
            </div>

            {{-- Custom content slot --}}
            @if($slot->isNotEmpty())
            <div class="mt-4">
                {{ $slot }}
            </div>
            @endif

            {{-- Actions --}}
            <div class="mt-6 flex items-center justify-end gap-3">
                <button
                    type="button"
                    @click="cancel()"
                    :disabled="processing"
                    class="btn-secondary"
                >
                    {{ $cancelText }}
                </button>
                <button
                    type="button"
                    @click="confirm()"
                    :disabled="processing"
                    class="{{ $typeConfig['btnClass'] }}"
                >
                    <svg x-show="processing" class="h-4 w-4 loading-spinner" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                    <span x-text="processing ? 'Please wait...' : @js($confirmText)"></span>
                </button>
            </div>
        </div>
    </div>
</div>
