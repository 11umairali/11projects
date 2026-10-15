<div class="space-y-6">
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button @click="show = false" class="text-success hover:text-success/80">&times;</button>
    </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('Contact Groups') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('Organize contacts into groups for campaigns, inbox, and workflows.') }}</p>
        </div>
        <button wire:click="$set('showForm', true)" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            {{ __('New Group') }}
        </button>
    </div>

    {{-- Create/Edit Form --}}
    @if($showForm)
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">{{ $editingId ? __('Edit Group') : __('Create Group') }}</h2>
        <div class="space-y-4 max-w-lg">
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Group Name') }}</label>
                <input type="text" wire:model="name" placeholder="{{ __('e.g. VIP Customers, Newsletter Subscribers') }}"
                       class="w-full px-4 py-2.5 text-sm border border-border rounded-xl bg-surface focus:outline-none focus:ring-2 focus:ring-brand/40">
                @error('name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Description') }} <span class="text-muted">({{ __('optional') }})</span></label>
                <textarea wire:model="description" rows="2" placeholder="{{ __('What is this group for?') }}"
                          class="w-full px-4 py-2.5 text-sm border border-border rounded-xl bg-surface focus:outline-none focus:ring-2 focus:ring-brand/40"></textarea>
            </div>
            <div class="flex items-center gap-3">
                <button wire:click="createList" class="px-5 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong transition-colors">
                    {{ $editingId ? __('Update Group') : __('Create Group') }}
                </button>
                <button wire:click="resetForm" class="px-4 py-2.5 text-sm text-muted hover:text-ink transition-colors">{{ __('Cancel') }}</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Groups List --}}
    @if($lists->isEmpty() && !$showForm)
    <div class="bg-surface-2 rounded-2xl border border-border p-12 text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand/10 text-brand mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
            </svg>
        </div>
        <h3 class="text-base font-semibold text-ink mb-1">{{ __('No groups yet') }}</h3>
        <p class="text-sm text-muted max-w-sm mx-auto mb-4">{{ __('Create your first group to organize contacts for campaigns, inbox, and workflows.') }}</p>
        <button wire:click="$set('showForm', true)" class="inline-flex items-center gap-2 px-4 py-2 bg-brand text-white text-sm font-medium rounded-xl hover:bg-brand-strong transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            {{ __('Create Group') }}
        </button>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($lists as $list)
        <div class="bg-surface-2 rounded-2xl border border-border p-5 flex flex-col hover:border-brand/30 transition-colors group">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand/10 flex items-center justify-center text-brand">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-ink">{{ $list->name }}</h3>
                        <span class="text-xs text-muted">{{ $list->contacts_count ?? 0 }} {{ ($list->contacts_count ?? 0) !== 1 ? __('contacts') : __('contact') }}</span>
                    </div>
                </div>

                {{-- Actions dropdown --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-surface transition-colors opacity-0 group-hover:opacity-100">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z"/></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                         class="absolute right-0 top-8 w-40 bg-surface-2 border border-border rounded-xl shadow-lg z-10 py-1">
                        <button wire:click="openAddContacts({{ $list->id }})" @click="open = false"
                                class="w-full text-left px-3 py-2 text-sm text-ink hover:bg-surface transition-colors">{{ __('Add Contacts') }}</button>
                        <button wire:click="editList({{ $list->id }})" @click="open = false"
                                class="w-full text-left px-3 py-2 text-sm text-ink hover:bg-surface transition-colors">{{ __('Edit') }}</button>
                        <button wire:click="confirmDelete({{ $list->id }})" @click="open = false"
                                class="w-full text-left px-3 py-2 text-sm text-danger hover:bg-danger/5 transition-colors">{{ __('Delete') }}</button>
                    </div>
                </div>
            </div>

            @if($list->description)
            <p class="text-xs text-muted mb-3 line-clamp-2">{{ $list->description }}</p>
            @endif

            <div class="mt-auto pt-3 border-t border-border/60 flex items-center gap-2">
                <button wire:click="openAddContacts({{ $list->id }})" class="flex-1 text-center py-1.5 text-xs font-medium text-brand bg-brand/5 rounded-lg hover:bg-brand/10 transition-colors">
                    {{ __('Add Contacts') }}
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Delete Confirmation --}}
    @if($confirmDeleteId)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.4);">
        <div class="bg-surface-2 rounded-2xl border border-border p-6 max-w-sm w-full shadow-xl">
            <h3 class="text-lg font-semibold text-ink mb-2">{{ __('Delete Group?') }}</h3>
            <p class="text-sm text-muted mb-5">{{ __('This will remove the group and unlink all contacts from it. The contacts themselves will not be deleted.') }}</p>
            <div class="flex items-center justify-end gap-3">
                <button wire:click="$set('confirmDeleteId', null)" class="px-4 py-2 text-sm text-muted hover:text-ink">{{ __('Cancel') }}</button>
                <button wire:click="deleteList" class="px-4 py-2 bg-danger text-white text-sm font-semibold rounded-xl hover:bg-red-600 transition-colors">{{ __('Delete') }}</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Add Contacts Modal --}}
    @if($showAddContacts && $addingToListId)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.4);">
        <div class="bg-surface-2 rounded-2xl border border-border p-6 max-w-lg w-full shadow-xl max-h-[80vh] flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-ink">{{ __('Add Contacts to Group') }}</h3>
                <button wire:click="$set('showAddContacts', false)" class="p-1 text-muted hover:text-ink">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Search --}}
            <div class="mb-4">
                <input type="text" wire:model.live.debounce.300ms="contactSearch" placeholder="{{ __('Search contacts by name, email, or company...') }}"
                       class="w-full px-4 py-2.5 text-sm border border-border rounded-xl bg-surface focus:outline-none focus:ring-2 focus:ring-brand/40">
            </div>

            {{-- Contact list --}}
            <div class="flex-1 overflow-y-auto space-y-1 min-h-0 mb-4">
                @forelse($availableContacts as $contact)
                <button wire:click="toggleContact({{ $contact->id }})"
                        class="w-full flex items-center gap-3 p-3 rounded-xl text-left transition-colors {{ in_array($contact->id, $selectedContactIds) ? 'bg-brand/10 border border-brand/20' : 'hover:bg-surface border border-transparent' }}">
                    <div class="w-8 h-8 rounded-full bg-brand/10 flex items-center justify-center text-brand text-xs font-bold shrink-0">
                        {{ $contact->initials ?? '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-ink truncate">{{ $contact->full_name ?? $contact->email }}</div>
                        <div class="text-xs text-muted truncate">{{ $contact->email }}</div>
                    </div>
                    @if(in_array($contact->id, $selectedContactIds))
                    <svg class="w-5 h-5 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </button>
                @empty
                <p class="text-sm text-muted text-center py-8">
                    @if(strlen($contactSearch) < 2)
                        {{ __('Type at least 2 characters to search contacts...') }}
                    @else
                        {{ __('No contacts found for') }} "{{ $contactSearch }}"
                    @endif
                </p>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between pt-3 border-t border-border">
                <span class="text-xs text-muted">{{ count($selectedContactIds) }} {{ __('selected') }}</span>
                <div class="flex items-center gap-3">
                    <button wire:click="$set('showAddContacts', false)" class="px-4 py-2 text-sm text-muted hover:text-ink">{{ __('Cancel') }}</button>
                    <button wire:click="addSelectedContacts" {{ empty($selectedContactIds) ? 'disabled' : '' }}
                            class="px-5 py-2 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        {{ __('Add to Group') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
