<div>
    <div class="flex items-center justify-between mb-5">
        <h2 id="contact-import-modal-title" class="text-lg font-semibold text-ink">{{ __('Import Contacts') }}</h2>
        <button wire:click="close" class="p-1.5 text-muted/60 hover:text-ink rounded-lg hover:bg-surface">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    @if (session()->has('error'))
        <div class="mb-4 p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm">{{ session('error') }}</div>
    @endif

    @if($importComplete)
        {{-- Import results --}}
        <div class="text-center py-6">
            <div class="w-16 h-16 bg-success/15 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-ink">{{ __('Import Complete') }}</h3>
            <p class="text-sm text-muted mt-2">
                <span class="font-medium text-success">{{ $importedCount }}</span> {{ __('contacts imported') }},
                <span class="font-medium text-yellow-600">{{ $skippedCount }}</span> {{ __('skipped (duplicates or invalid)') }}
            </p>
            <button wire:click="close" class="btn-primary mt-4 px-5 py-2.5 text-sm">{{ __('Done') }}</button>
        </div>
    @elseif(!$hasPreview)
        {{-- File upload --}}
        <div x-data="{ dragging: false }" class="space-y-4">
            <div @dragover.prevent="dragging = true"
                 @dragleave.prevent="dragging = false"
                 @drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                 :class="dragging ? 'border-primary-400 bg-primary-50 dark:bg-primary-900/20' : 'border-border'"
                 class="border-2 border-dashed rounded-2xl p-8 text-center transition-colors cursor-pointer">

                <input type="file" wire:model="csvFile" accept=".csv,.txt" class="hidden" x-ref="fileInput">

                <div class="w-12 h-12 bg-surface  rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                </div>

                <button type="button" @click="$refs.fileInput.click()" class="text-sm font-medium text-ink/80">
                    <span x-show="!dragging">{{ __('Drag and drop a CSV file, or') }} <span class="text-primary-600">{{ __('browse') }}</span></span>
                    <span x-show="dragging" class="text-primary-600">{{ __('Drop CSV file here') }}</span>
                </button>
                <p class="text-xs text-muted mt-1">{{ __('CSV or TXT file, max 10MB') }}</p>
            </div>

            <div wire:loading wire:target="csvFile" class="text-center py-2">
                <div class="inline-flex items-center gap-2 text-sm text-muted">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ __('Processing file...') }}
                </div>
            </div>

            @error('csvFile') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
        </div>
    @else
        {{-- Preview & Field Mapping --}}
        <div class="space-y-5">
            <div class="bg-surface rounded-xl p-4">
                <p class="text-sm text-ink/80">
                    <span class="font-medium">{{ number_format($totalRows) }}</span> {{ __('rows found in CSV.') }}
                    {{ __('Preview of first') }} {{ min(5, count($previewRows)) }} {{ __('rows below.') }}
                </p>
            </div>

            {{-- Field Mapping --}}
            <div>
                <h3 class="text-sm font-semibold text-ink mb-3">{{ __('Map CSV Columns') }}</h3>
                <div class="space-y-2">
                    @foreach($csvHeaders as $index => $header)
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-muted  w-40 truncate flex-shrink-0" title="{{ $header }}">{{ $header }}</span>
                        <svg class="w-4 h-4 text-muted/50 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        <select wire:model="fieldMapping.{{ $index }}" class="flex-1 text-sm border border-border rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary-500 bg-surface-2">
                            @foreach($this->contactFields as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Preview Table --}}
            <div>
                <h3 class="text-sm font-semibold text-ink mb-3">{{ __('Preview') }}</h3>
                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-xs">
                        <thead class="bg-surface">
                            <tr>
                                @foreach($csvHeaders as $header)
                                <th class="px-3 py-2 text-left text-muted font-semibold whitespace-nowrap">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @foreach($previewRows as $row)
                            <tr>
                                @foreach($row as $cell)
                                <td class="px-3 py-2 text-muted  whitespace-nowrap max-w-[150px] truncate">{{ $cell }}</td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-2">
                <button wire:click="close" class="px-4 py-2 text-sm font-medium text-muted  hover:text-ink dark:text-gray-200 transition-colors">{{ __('Cancel') }}</button>
                <button wire:click="importContacts" wire:loading.attr="disabled" class="btn-primary px-5 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    <span wire:loading.remove wire:target="importContacts">{{ __('Import') }} {{ number_format($totalRows) }} {{ __('Contacts') }}</span>
                    <span wire:loading wire:target="importContacts" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        {{ __('Importing...') }}
                    </span>
                </button>
            </div>
        </div>
    @endif
</div>
