<div class="space-y-6">
    {{-- Flash messages --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         role="alert" aria-live="polite"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button type="button" @click="show = false" class="text-green-500 hover:text-success">&times;</button>
    </div>
    @endif

    {{-- Top bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('Contacts') }}</h1>
            <p class="text-sm text-muted mt-0.5">{{ number_format($totalContacts) }} {{ __('contacts total') }}</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            {{-- Search --}}
            <div class="relative">
                <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search contacts...') }}"
                       class="pl-9 pr-4 py-2 text-sm bg-surface-2 border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent placeholder-gray-400 w-64">
            </div>

            {{-- Tag filter --}}
            <select wire:model.live="selectedTag" class="text-sm bg-surface-2 border border-border rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">{{ __('All Tags') }}</option>
                @foreach($this->tags as $tag)
                <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                @endforeach
            </select>

            {{-- Group filter --}}
            <select wire:model.live="selectedGroup" class="text-sm bg-surface-2 border border-border rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">{{ __('All Groups') }}</option>
                @foreach($contactGroups as $group)
                <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>

            {{-- Import --}}
            <button type="button" wire:click="openImport" class="flex items-center gap-1.5 px-3 py-2 text-sm text-muted  bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                {{ __('Import') }}
            </button>

            {{-- Export --}}
            <button type="button" wire:click="exportCsv" wire:loading.attr="disabled" class="flex items-center gap-1.5 px-3 py-2 text-sm text-muted  bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" wire:loading.remove wire:target="exportCsv" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <svg class="w-4 h-4 animate-spin" wire:loading wire:target="exportCsv" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ __('Export') }}
            </button>

            {{-- Add contact --}}
            <button type="button" wire:click="openForm" class="btn-primary flex items-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Add Contact') }}
            </button>
        </div>
    </div>

    {{-- Bulk action bar --}}
    @if(count($selectedIds) > 0)
    <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <span class="text-sm text-primary-700 dark:text-primary-300 font-medium">{{ count($selectedIds) }} {{ __('contact(s) selected') }}</span>
        <div class="flex items-center gap-2 flex-wrap">
            <select wire:model.live="bulkAction" class="text-sm bg-surface-2 border border-border rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">{{ __('Choose action...') }}</option>
                <option value="delete">{{ __('Move to Trash') }}</option>
                <option value="tag">{{ __('Add Tag') }}</option>
                <option value="add_to_group">{{ __('Add to Group') }}</option>
                <option value="remove_from_group">{{ __('Remove from Group') }}</option>
                <option value="export">{{ __('Export Selected') }}</option>
                <option value="activate">{{ __('Set Active') }}</option>
                <option value="unsubscribe">{{ __('Unsubscribe') }}</option>
            </select>
            @if($bulkAction === 'tag')
            <select wire:model="bulkTagId" class="text-sm bg-surface-2 border border-border rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">{{ __('Select tag...') }}</option>
                @foreach($this->tags as $tag)
                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                @endforeach
            </select>
            @endif
            @if($bulkAction === 'add_to_group' || $bulkAction === 'remove_from_group')
            <select wire:model="bulkGroupId" class="text-sm bg-surface-2 border border-border rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">{{ __('Select group...') }}</option>
                @foreach($contactGroups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
            @endif
            <button type="button" wire:click="executeBulkAction"
                    wire:confirm="{{ $bulkAction === 'delete' ? 'Move ' . count($selectedIds) . ' contacts to trash?' : 'Apply action to ' . count($selectedIds) . ' contacts?' }}"
                    wire:loading.attr="disabled"
                    class="btn-primary px-3 py-1.5 text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    @if(!$bulkAction) disabled @endif>
                <span wire:loading.remove wire:target="executeBulkAction">{{ __('Apply') }}</span>
                <span wire:loading wire:target="executeBulkAction" class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ __('Applying...') }}
                </span>
            </button>
            <button type="button" wire:click="$set('selectedIds', [])" class="px-3 py-1.5 text-sm text-muted hover:text-ink transition-colors">
                {{ __('Clear') }}
            </button>
        </div>
    </div>
    @endif

    {{-- Loading indicator --}}
    <div wire:loading.delay class="text-center py-2" aria-busy="true">
        <div class="inline-flex items-center gap-2 text-sm text-muted">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            {{ __('Loading...') }}
        </div>
    </div>

    {{-- Table --}}
    @if($contacts->count() > 0)
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <p class="text-xs text-muted text-center py-1 md:hidden">{{ __('Swipe to see more columns') }}</p>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="w-12 px-4 py-3">
                            <input type="checkbox" wire:model.live="selectAll" wire:change="toggleSelectAll"
                                   aria-label="Select all contacts"
                                   class="w-4 h-4 text-primary-600 border-border rounded focus:ring-primary-500">
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 cursor-pointer hover:text-ink/80" wire:click="sortBy('first_name')">
                            {{ __('Name') }}
                            @if($sortField === 'first_name')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '&#9650;' : '&#9660;' }}</span>
                            @endif
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 cursor-pointer hover:text-ink/80" wire:click="sortBy('email')">
                            {{ __('Email') }}
                            @if($sortField === 'email')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '&#9650;' : '&#9660;' }}</span>
                            @endif
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">{{ __('Phone') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell cursor-pointer hover:text-ink/80" wire:click="sortBy('company')">
                            {{ __('Company') }}
                            @if($sortField === 'company')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '&#9650;' : '&#9660;' }}</span>
                            @endif
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 cursor-pointer hover:text-ink/80" wire:click="sortBy('lead_score')">
                            {{ __('Score') }}
                            @if($sortField === 'lead_score')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '&#9650;' : '&#9660;' }}</span>
                            @endif
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden xl:table-cell">{{ __('Tags') }}</th>
                        <th class="w-12 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($contacts as $contact)
                    <tr class="hover:bg-surface transition-colors" wire:key="contact-{{ $contact->id }}">
                        <td class="px-4 py-3">
                            <input type="checkbox" value="{{ $contact->id }}" wire:model.live="selectedIds"
                                   aria-label="Select contact"
                                   class="w-4 h-4 text-primary-600 border-border rounded focus:ring-primary-500">
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 bg-primary-500 rounded-full flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                    {{ $contact->initials }}
                                </div>
                                <span class="text-sm font-medium text-ink whitespace-nowrap">{{ $contact->full_name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-muted ">{{ $contact->email }}</td>
                        <td class="px-4 py-3 text-sm text-muted  hidden lg:table-cell">{{ $contact->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-muted  hidden lg:table-cell">{{ $contact->company ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $score = $contact->lead_score ?? 0;
                                $scoreLabel = $score >= 80 ? 'Hot' : ($score >= 50 ? 'Warm' : 'Cold');
                                $scoreColor = match($scoreLabel) {
                                    'Hot' => 'bg-danger/15 text-danger',
                                    'Warm' => 'bg-warning/15 text-warning',
                                    'Cold' => 'bg-info/15 text-info',
                                };
                                $barColor = match($scoreLabel) {
                                    'Hot' => 'bg-danger/100',
                                    'Warm' => 'bg-warning/100',
                                    'Cold' => 'bg-info/100',
                                };
                            @endphp
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-16 h-1.5 bg-surface  rounded-full overflow-hidden">
                                    <div class="{{ $barColor }} h-full rounded-full" style="width: {{ $score }}%"></div>
                                </div>
                                <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full {{ $scoreColor }} whitespace-nowrap">{{ $score }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden xl:table-cell">
                            <div class="flex flex-wrap gap-1">
                                @foreach($contact->tags as $tag)
                                <span class="px-2 py-0.5 rounded-md text-xs font-medium bg-surface  text-muted " style="{{ $tag->color ? 'background-color:' . $tag->color . '20; color:' . $tag->color : '' }}">
                                    {{ $tag->name }}
                                </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div x-data="{
                                    open: false, menuTop: 0, menuLeft: 0,
                                    reposition() { const r = this.$refs.trigger.getBoundingClientRect(); this.menuLeft = r.right - 160; this.menuTop = r.bottom + 4; },
                                    toggle() { this.open = !this.open; if (this.open) this.$nextTick(() => this.reposition()); },
                                }"
                                 @scroll.window="open && reposition()" @resize.window="open && reposition()" @keydown.escape.window="open = false">
                                <button type="button" x-ref="trigger" @click="toggle()" class="p-1.5 text-muted/60 hover:text-ink rounded-lg hover:bg-surface" aria-label="More actions" aria-haspopup="true" :aria-expanded="open">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01"/></svg>
                                </button>
                                <template x-teleport="body">
                                    <div x-show="open" @click.outside="open = false" x-transition
                                         role="menu"
                                         :style="`position: fixed; top: ${menuTop}px; left: ${menuLeft}px; z-index: 9999;`"
                                         class="w-40 bg-surface-2 rounded-xl shadow-lg border border-border py-1" style="display: none;">
                                        <button type="button" role="menuitem" wire:click="openForm({{ $contact->id }})" @click="open = false" class="w-full text-left px-4 py-2 text-sm text-ink/80 hover:bg-surface">{{ __('Edit') }}</button>
                                        <button type="button" role="menuitem" wire:click="deleteContact({{ $contact->id }})" wire:confirm="Move this contact to trash?" @click="open = false" class="w-full text-left px-4 py-2 text-sm text-danger hover:bg-danger/10">{{ __('Move to Trash') }}</button>
                                    </div>
                                </template>
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
    {{-- Empty state --}}
    @if($search || $selectedTag || $selectedGroup)
        <x-empty-state
            type="search"
            :title="__('No contacts found')"
            :description="__('No contacts match your current filters. Try adjusting your search or filter criteria.')"
        />
    @else
        <x-empty-state
            type="contacts"
            :title="__('No contacts yet')"
            :description="__('Add your first contact to start building your CRM.')"
            action-url="/contacts"
            :action-label="__('Add Your First Contact')"
        />
    @endif
    @endif

    {{-- Contact Form Modal --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog" aria-labelledby="contact-form-modal-title">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-surface/50" wire:click="closeForm"></div>
            <div class="relative bg-surface-2 rounded-2xl shadow-xl max-w-lg w-full p-6" x-trap="$wire.showForm">
                <livewire:contacts.contact-form :contactId="$editingContactId" :key="'form-' . ($editingContactId ?? 'new')" />
            </div>
        </div>
    </div>
    @endif

    {{-- Import Modal --}}
    @if($showImport)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog" aria-labelledby="contact-import-modal-title">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-surface/50" wire:click="closeImport"></div>
            <div class="relative bg-surface-2 rounded-2xl shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto" x-trap="$wire.showImport">
                <livewire:contacts.contact-import :key="'import'" />
            </div>
        </div>
    </div>
    @endif
</div>
