<div class="space-y-6">
    {{-- Flash messages --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 8000)"
         class="p-3 bg-success/10 dark:bg-green-900/30 border border-success/20 dark:border-green-800 text-success dark:text-green-300 rounded-xl text-sm flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('success') }}</span>
        </div>
        <div class="flex items-center gap-2">
            @if($lastMergeAuditId)
            <button wire:click="undoLastMerge" wire:confirm="Undo this merge? Deleted contacts will be restored."
                    class="text-xs font-semibold px-3 py-1 bg-white dark:bg-gray-800 text-ink border border-border rounded-lg hover:bg-surface transition-colors">
                {{ __('Undo') }}
            </button>
            @endif
            <button @click="show = false" class="text-green-500 hover:text-success dark:hover:text-green-200">&times;</button>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div x-data="{ show: true }" x-show="show"
         class="p-3 bg-danger/10 dark:bg-red-900/30 border border-danger/20 dark:border-red-800 text-danger dark:text-red-300 rounded-xl text-sm flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
        <button @click="show = false" class="text-red-500 hover:text-danger dark:hover:text-red-200">&times;</button>
    </div>
    @endif

    {{-- Page header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('contacts') }}" class="text-muted hover:text-muted dark:hover:text-muted/50 transition-colors" :title="__('Back to Contacts')">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-2xl font-bold text-ink">{{ __('Combine Duplicate Contacts') }}</h1>
            </div>
            <p class="text-sm text-muted mt-1 ml-8">{{ __('Find and merge duplicate contacts in your workspace') }}</p>
        </div>
        <div class="flex items-center gap-3">
            @if($mergedCount > 0)
            <span class="text-sm text-muted">{{ $mergedCount }} {{ __('merged this session') }}</span>
            @endif
            <button wire:click="scanForDuplicates"
                    wire:loading.attr="disabled"
                    wire:target="scanForDuplicates"
                    class="btn-primary inline-flex items-center gap-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                <svg wire:loading.remove wire:target="scanForDuplicates" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <svg wire:loading wire:target="scanForDuplicates" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span wire:loading.remove wire:target="scanForDuplicates">{{ __('Find Duplicates') }}</span>
                <span wire:loading wire:target="scanForDuplicates">{{ __('Scanning...') }}</span>
            </button>
        </div>
    </div>

    {{-- Scan stats --}}
    @if(!empty($duplicateGroups) || $scannedContactCount > 0)
    <div class="flex flex-wrap items-center gap-4 text-sm">
        @if($scannedContactCount > 0)
        <div class="flex items-center gap-1.5 text-muted">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <span>{{ number_format($scannedContactCount) }} {{ __('contacts scanned') }}</span>
        </div>
        @endif
        <div class="flex items-center gap-1.5 text-muted">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            <span>{{ count($duplicateGroups) }} {{ __('duplicate group(s) found') }}</span>
        </div>
        <div class="flex items-center gap-1.5 text-muted">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            @php
                $totalDupeContacts = collect($duplicateGroups)->sum(fn ($g) => count($g['contacts']));
            @endphp
            <span>{{ number_format($totalDupeContacts) }} {{ __('contacts involved') }}</span>
        </div>
    </div>
    @endif

    {{-- Main content --}}
    @if(empty($duplicateGroups) && $scannedContactCount === 0)
        {{-- Initial state: no scan run yet --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-12 text-center">
            <div class="w-16 h-16 bg-surface dark:bg-gray-700 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-muted " fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-ink/80">{{ __('Find Duplicate Contacts') }}</h3>
            <p class="text-sm text-muted  mt-1 max-w-md mx-auto">
                {{ __('Scan your workspace for contacts that share the same email address or have matching names and companies. Review and merge them to keep your CRM clean.') }}
            </p>
            <button wire:click="scanForDuplicates"
                    wire:loading.attr="disabled"
                    wire:target="scanForDuplicates"
                    class="btn-primary mt-6 inline-flex items-center gap-2 text-sm disabled:opacity-50">
                <svg wire:loading.remove wire:target="scanForDuplicates" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <svg wire:loading wire:target="scanForDuplicates" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span wire:loading.remove wire:target="scanForDuplicates">{{ __('Scan for Duplicates') }}</span>
                <span wire:loading wire:target="scanForDuplicates">{{ __('Scanning...') }}</span>
            </button>
        </div>
    @elseif(empty($duplicateGroups) && $scannedContactCount > 0)
        {{-- No duplicates found --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-12 text-center">
            <div class="w-16 h-16 bg-success/10 dark:bg-green-900/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-ink/80">{{ __('No Duplicates Found') }}</h3>
            <p class="text-sm text-muted  mt-1 max-w-md mx-auto">
                {{ __('Your contact list looks clean. No duplicate contacts were detected based on email address or name + company matching.') }}
            </p>
        </div>
    @else
        {{-- Split view: groups list + comparison --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Left: Duplicate groups list --}}
            <div class="lg:col-span-4">
                <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                    <div class="px-4 py-3 border-b border-border">
                        <h2 class="text-sm font-semibold text-ink/80">{{ __('Duplicate Groups') }}</h2>
                    </div>
                    <div class="max-h-[600px] overflow-y-auto divide-y divide-border/60 dark:divide-gray-700">
                        @foreach($duplicateGroups as $index => $group)
                        <div wire:click="selectGroup({{ $index }})"
                             wire:key="group-{{ $index }}"
                             class="px-4 py-3 cursor-pointer transition-colors {{ $selectedGroupIndex === $index ? 'bg-primary-50 dark:bg-primary-900/20 border-l-2 border-primary-500' : 'hover:bg-surface dark:hover:bg-gray-700/50 border-l-2 border-transparent' }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    {{-- Show the match value as the group title --}}
                                    <p class="text-sm font-medium text-ink truncate">
                                        {{ $group['match_value'] }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="inline-flex items-center gap-1 text-xs px-1.5 py-0.5 rounded-full {{ $group['match_type'] === 'email' ? 'bg-info/15 text-info dark:bg-blue-900/40 dark:text-blue-300' : 'bg-warning/15 text-warning dark:bg-amber-900/40 dark:text-amber-300' }}">
                                            @if($group['match_type'] === 'email')
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            {{ __('Email') }}
                                            @else
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            {{ __('Name') }}
                                            @endif
                                        </span>
                                        <span class="text-xs text-muted ">{{ count($group['contacts']) }} {{ __('contacts') }}</span>
                                    </div>
                                </div>
                                <button wire:click.stop="dismissGroup({{ $index }})"
                                        class="p-1 text-muted/50 hover:text-red-500  dark:hover:text-red-400 rounded transition-colors flex-shrink-0"
                                        :title="__('Not duplicates')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Right: Comparison view --}}
            <div class="lg:col-span-8">
                @if($selectedGroupIndex !== null && !empty($selectedGroupContacts))
                    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                        <div class="px-4 py-3 border-b border-border flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-ink/80">{{ __('Compare & Merge') }}</h2>
                            <span class="text-xs text-muted ">{{ __('Select the primary contact to keep') }}</span>
                        </div>

                        {{-- Contact comparison cards --}}
                        <div class="p-4 overflow-x-auto">
                            <div class="flex gap-4" style="min-width: {{ count($selectedGroupContacts) * 280 }}px;">
                                @foreach($selectedGroupContacts as $contact)
                                @php
                                    $isPrimary = $primaryContactId === $contact['id'];
                                @endphp
                                <div wire:key="compare-{{ $contact['id'] }}"
                                     class="flex-1 min-w-[260px] max-w-[360px] rounded-xl border-2 transition-all {{ $isPrimary ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-900/10' : 'border-border bg-surface-2' }}">
                                    {{-- Card header --}}
                                    <div class="p-4 border-b border-border dark:border-gray-700">
                                        <div class="flex items-center justify-between mb-3">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio"
                                                       name="primary_contact"
                                                       value="{{ $contact['id'] }}"
                                                       wire:click="setPrimary({{ $contact['id'] }})"
                                                       {{ $isPrimary ? 'checked' : '' }}
                                                       class="w-4 h-4 text-primary-600 border-border focus:ring-primary-500">
                                                <span class="text-xs font-medium {{ $isPrimary ? 'text-primary-700 dark:text-primary-400' : 'text-muted' }}">
                                                    {{ $isPrimary ? __('Primary (Keep)') : __('Secondary (Merge)') }}
                                                </span>
                                            </label>
                                            @if($isPrimary)
                                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300 rounded-full">{{ __('Master') }}</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-primary-500 rounded-full flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                                {{ strtoupper(substr($contact['first_name'] ?? '', 0, 1)) }}{{ strtoupper(substr($contact['last_name'] ?? '', 0, 1)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-ink truncate">{{ $contact['full_name'] }}</p>
                                                <p class="text-xs text-muted truncate">{{ $contact['email'] ?? __('No email') }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Field comparison --}}
                                    <div class="p-4 space-y-2.5 text-sm">
                                        @php
                                            $fields = [
                                                'phone' => __('Phone'),
                                                'company' => __('Company'),
                                                'job_title' => __('Job Title'),
                                                'city' => __('City'),
                                                'country' => __('Country'),
                                                'timezone' => __('Timezone'),
                                                'lead_score' => __('Engagement Score'),
                                                'status' => __('Status'),
                                            ];

                                            // Compute which fields differ across the group.
                                            $diffFields = [];
                                            foreach ($fields as $fKey => $fLabel) {
                                                $values = collect($selectedGroupContacts)->pluck($fKey)->unique()->filter(fn($v) => $v !== null && $v !== '')->values();
                                                if ($values->count() > 1) {
                                                    $diffFields[] = $fKey;
                                                }
                                            }
                                        @endphp

                                        @foreach($fields as $fieldKey => $fieldLabel)
                                        @php
                                            $isDiff = in_array($fieldKey, $diffFields);
                                            $value = $contact[$fieldKey] ?? null;
                                        @endphp
                                        <div class="flex items-start gap-2 {{ $isDiff ? 'bg-warning/10 dark:bg-amber-900/20 -mx-2 px-2 py-1 rounded-lg' : '' }}">
                                            <span class="text-xs text-muted  w-20 flex-shrink-0 pt-0.5">{{ $fieldLabel }}</span>
                                            <span class="text-xs font-medium {{ $value ? 'text-ink dark:text-gray-200' : 'text-muted/50  italic' }} break-all">
                                                {{ $value !== null && $value !== '' ? $value : __('Empty') }}
                                                @if($isDiff && $value)
                                                <svg class="w-3 h-3 text-amber-500 inline ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                @endif
                                            </span>
                                        </div>
                                        @endforeach

                                        {{-- Tags --}}
                                        <div class="flex items-start gap-2">
                                            <span class="text-xs text-muted  w-20 flex-shrink-0 pt-0.5">{{ __('Tags') }}</span>
                                            <div class="flex flex-wrap gap-1">
                                                @forelse($contact['tags'] as $tagName)
                                                <span class="px-1.5 py-0.5 text-[10px] font-medium bg-surface dark:bg-gray-700 text-muted /50 rounded">{{ $tagName }}</span>
                                                @empty
                                                <span class="text-xs text-muted/50  italic">{{ __('None') }}</span>
                                                @endforelse
                                            </div>
                                        </div>

                                        {{-- Relations count --}}
                                        <div class="flex items-start gap-2 pt-1 border-t border-border dark:border-gray-700">
                                            <span class="text-xs text-muted  w-20 flex-shrink-0 pt-0.5">{{ __('Relations') }}</span>
                                            <div class="text-xs text-muted /50">
                                                {{ $contact['conversations_count'] }} {{ __('conversation(s)') }}, {{ $contact['deals_count'] }} {{ __('deal(s)') }}
                                            </div>
                                        </div>

                                        {{-- Created date --}}
                                        <div class="flex items-start gap-2">
                                            <span class="text-xs text-muted  w-20 flex-shrink-0 pt-0.5">{{ __('Created') }}</span>
                                            <span class="text-xs text-muted /50">
                                                {{ $contact['created_at'] ? \Carbon\Carbon::parse($contact['created_at'])->format('M j, Y') : __('Unknown') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Merge explanation --}}
                        <div class="mx-4 mb-4 p-3 bg-surface dark:bg-gray-700/50 rounded-xl">
                            <h4 class="text-xs font-semibold text-muted /50 mb-1.5">{{ __('What happens when you merge:') }}</h4>
                            <ul class="text-xs text-muted space-y-1">
                                <li class="flex items-start gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-primary-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    {{ __('All conversations and deals move to the primary contact') }}
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-primary-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    {{ __('Tags from all contacts are combined') }}
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-primary-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    {{ __('Empty fields on primary are filled from secondary data') }}
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-primary-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    {{ __('Lead score is set to the highest value') }}
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    {{ __('Secondary contacts are soft-deleted (recoverable)') }}
                                </li>
                            </ul>
                        </div>

                        {{-- Action buttons --}}
                        <div class="px-4 py-3 border-t border-border flex items-center justify-between">
                            <button wire:click="dismissGroup({{ $selectedGroupIndex }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-muted  bg-surface-2 dark:bg-gray-700 border border-border dark:border-gray-600 rounded-xl hover:bg-surface dark:hover:bg-gray-600 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                {{ __('Not Duplicates') }}
                            </button>
                            <button wire:click="mergeContacts"
                                    wire:confirm="This will merge {{ count($selectedGroupContacts) - 1 }} contact(s) into the primary contact. This action cannot be easily undone. Continue?"
                                    wire:loading.attr="disabled"
                                    wire:target="mergeContacts"
                                    class="btn-primary inline-flex items-center gap-2 text-sm disabled:opacity-50">
                                <svg wire:loading.remove wire:target="mergeContacts" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                <svg wire:loading wire:target="mergeContacts" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span wire:loading.remove wire:target="mergeContacts">{{ __('Merge into Primary') }}</span>
                                <span wire:loading wire:target="mergeContacts">{{ __('Merging...') }}</span>
                            </button>
                        </div>
                    </div>
                @else
                    {{-- No group selected --}}
                    <div class="bg-surface-2 rounded-2xl border border-border p-12 text-center h-full flex flex-col items-center justify-center min-h-[400px]">
                        <div class="w-12 h-12 bg-surface dark:bg-gray-700 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-muted/50 " fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                        </div>
                        <p class="text-sm text-muted">{{ __('Select a duplicate group from the left to compare and merge contacts') }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
