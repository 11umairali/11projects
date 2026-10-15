{{--
    Unsaved Changes / Draft Restoration Banner

    Place inside a form that uses x-data="autosave('form-name')".
    Automatically shows when a restorable draft exists.

    Usage:
        <form x-data="autosave('settings')" @input="onFormInput()" @submit="onSubmit()">
            <x-unsaved-changes-banner />
            ...fields...
        </form>
--}}

{{-- Draft restoration banner --}}
<div
    x-show="hasDraft"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-2"
    class="mb-5 flex flex-col gap-3 rounded-xl border border-warning/30 bg-warning/5 p-4 sm:flex-row sm:items-center sm:justify-between"
    role="alert"
    aria-live="polite"
>
    <div class="flex items-start gap-3 sm:items-center">
        {{-- Clock icon --}}
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-warning/10">
            <svg class="h-4 w-4 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-medium text-ink">
                You have a saved draft
            </p>
            <p class="text-xs text-muted">
                Saved <span x-text="draftTimestamp"></span>
            </p>
        </div>
    </div>

    <div class="flex items-center gap-2 pl-11 sm:pl-0">
        <button
            type="button"
            x-on:click="restoreDraft()"
            class="btn-primary btn-sm"
        >
            Restore
        </button>
        <button
            type="button"
            x-on:click="discardDraft()"
            class="btn-secondary btn-sm"
        >
            Discard
        </button>
    </div>
</div>

{{-- Autosave status indicator --}}
<div
    x-show="lastSavedText"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    class="mb-4 flex items-center gap-1.5"
    aria-live="polite"
>
    <svg class="h-3 w-3 text-success" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="12" r="5"/>
    </svg>
    <span class="text-xs text-muted" x-text="lastSavedText"></span>
</div>
