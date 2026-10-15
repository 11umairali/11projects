<div class="space-y-6">
    {{-- Flash messages --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         role="alert" aria-live="polite"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button @click="show = false" class="text-green-500 hover:text-success">&times;</button>
    </div>
    @endif

    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         role="alert" aria-live="assertive"
         class="p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm flex items-center justify-between">
        <span>{{ session('error') }}</span>
        <button @click="show = false" class="text-danger hover:text-red-700">&times;</button>
    </div>
    @endif

    {{-- Top bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('contacts') }}" class="text-sm text-brand hover:text-brand/80 flex items-center gap-1" wire:navigate>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    {{ __('Back to Contacts') }}
                </a>
            </div>
            <h1 class="text-2xl font-bold text-ink mt-2">{{ __('Trash') }}</h1>
            <p class="text-sm text-muted mt-0.5">{{ __('Deleted contacts are kept for 30 days before permanent removal.') }}</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($contacts->total() > 0)
            <button wire:click="restoreAll" wire:confirm="Restore all {{ $contacts->total() }} contact(s)?"
                    wire:loading.attr="disabled"
                    class="flex items-center gap-1.5 px-3 py-2 text-sm text-muted bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" wire:loading.remove wire:target="restoreAll" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                <svg class="w-4 h-4 animate-spin" wire:loading wire:target="restoreAll" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ __('Restore All') }}
            </button>
            <button wire:click="emptyTrash" wire:confirm="Permanently delete all contacts in trash? This cannot be undone."
                    wire:loading.attr="disabled"
                    class="flex items-center gap-1.5 px-3 py-2 text-sm text-white bg-danger rounded-xl hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" wire:loading.remove wire:target="emptyTrash" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <svg class="w-4 h-4 animate-spin" wire:loading wire:target="emptyTrash" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ __('Empty Trash') }}
            </button>
            @endif
        </div>
    </div>

    {{-- Search --}}
    <div class="flex items-center">
        <div class="relative">
            <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search deleted contacts...') }}"
                   class="pl-9 pr-4 py-2 text-sm bg-surface-2 border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent placeholder-gray-400 w-full sm:w-80">
        </div>
    </div>

    {{-- Loading indicator --}}
    <div wire:loading.delay class="text-center py-2">
        <div class="inline-flex items-center gap-2 text-sm text-muted">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            {{ __('Loading...') }}
        </div>
    </div>

    {{-- Table --}}
    @if($contacts->count() > 0)
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Name') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Email') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">{{ __('Deleted') }}</th>
                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($contacts as $contact)
                    <tr class="hover:bg-surface transition-colors" wire:key="trash-{{ $contact->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 bg-gray-400 rounded-full flex items-center justify-center text-white text-sm font-semibold flex-shrink-0 opacity-60">
                                    {{ $contact->initials }}
                                </div>
                                <span class="text-sm font-medium text-ink whitespace-nowrap">{{ $contact->full_name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-muted">{{ $contact->email }}</td>
                        <td class="px-4 py-3 text-sm text-muted hidden lg:table-cell" title="{{ $contact->deleted_at->format('M j, Y g:i A') }}">{{ $contact->deleted_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button wire:click="restore({{ $contact->id }})"
                                        class="text-xs text-brand hover:text-brand/80 font-medium transition-colors">
                                    {{ __('Restore') }}
                                </button>
                                <button wire:click="forceDelete({{ $contact->id }})"
                                        wire:confirm="Permanently delete {{ $contact->first_name }}? This cannot be undone."
                                        class="text-xs text-danger hover:text-red-700 font-medium transition-colors">
                                    {{ __('Delete Forever') }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-4 py-3 border-t border-border">
            {{ $contacts->links() }}
        </div>
    </div>
    @else
    <x-empty-state
        type="contacts"
        :title="__('Trash is empty')"
        :description="__('Deleted contacts will appear here for 30 days before permanent removal.')"
    />
    @endif
</div>
