@props([
    'itemId',
])

{{--
    Bulk Select Checkbox — Individual Row
    ──────────────────────────────────────
    Renders a styled checkbox for a single row inside a table/list.

    Usage:
    <x-bulk-select-checkbox :item-id="$contact->id" />

    Listens for:
      - bulk-deselect-all  — unchecks this checkbox

    Dispatches:
      - bulk-select-toggle { id, checked }
--}}
<div
    x-data="{ checked: false }"
    x-on:bulk-deselect-all.window="checked = false"
    class="flex items-center"
>
    <label class="relative flex items-center justify-center cursor-pointer">
        <input
            type="checkbox"
            x-model="checked"
            x-on:change="$dispatch('bulk-select-toggle', { id: {{ json_encode($itemId) }}, checked: checked })"
            class="peer h-[18px] w-[18px] shrink-0 appearance-none rounded-md border border-border bg-surface/80 transition
                   checked:border-brand checked:bg-brand
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 focus-visible:ring-offset-2 focus-visible:ring-offset-surface-2
                   hover:border-brand/50"
            aria-label="Select {{ $modelName ?? 'item' }}"
        />
        {{-- Checkmark --}}
        <svg
            class="pointer-events-none absolute h-3 w-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity"
            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
    </label>
</div>
