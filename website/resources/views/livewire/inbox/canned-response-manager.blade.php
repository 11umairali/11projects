<div>
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-ink">{{ __('Quick Replies') }}</h3>
        <button wire:click="$toggle('showForm')" class="text-xs text-brand hover:text-brand-strong font-medium">
            {{ $showForm ? __('Cancel') : __('+ New') }}
        </button>
    </div>

    @if($showForm)
    <div class="bg-surface rounded-xl border border-border p-4 mb-4 space-y-3">
        <div>
            <label class="block text-xs font-medium text-muted mb-1">{{ __('Title') }} <span class="text-danger">*</span></label>
            <input type="text" wire:model="title" class="input w-full text-sm" placeholder="{{ __('e.g. Greeting') }}">
            @error('title') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-muted mb-1">{{ __('Shortcut') }}</label>
            <div class="flex items-center gap-1">
                <span class="text-muted text-sm">/</span>
                <input type="text" wire:model="shortcut" class="input w-full text-sm" placeholder="{{ __('greeting') }}">
            </div>
            <p class="text-xs text-muted mt-1">{{ __('Type /shortcut in the composer to quickly insert') }}</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-muted mb-1">{{ __('Content') }} <span class="text-danger">*</span></label>
            <textarea wire:model="content" class="input w-full text-sm" rows="4" placeholder="{{ __('Type your quick reply...') }}"></textarea>
            @error('content') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-muted mb-1">{{ __('Visibility') }}</label>
            <select wire:model="scope" class="input w-full text-sm">
                <option value="personal">{{ __('Only me') }}</option>
                <option value="team">{{ __('Entire team') }}</option>
            </select>
        </div>
        <button wire:click="save" class="btn-primary w-full text-sm">
            {{ $editingId ? __('Update') : __('Save') }} {{ __('Quick Reply') }}
        </button>
    </div>
    @endif

    <div class="space-y-2">
        @forelse($responses as $response)
        <div class="group flex items-start gap-3 p-3 rounded-lg border border-border hover:border-brand/30 bg-surface-2 transition-colors cursor-pointer"
             wire:click="insert({{ $response->id }})">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <p class="text-sm font-medium text-ink truncate">{{ $response->title }}</p>
                    @if($response->shortcut)
                    <span class="text-xs text-muted bg-surface px-1.5 py-0.5 rounded font-mono">/{{ $response->shortcut }}</span>
                    @endif
                    @if($response->scope === 'team')
                    <span class="text-xs text-brand bg-brand/10 px-1.5 py-0.5 rounded">{{ __('Team') }}</span>
                    @endif
                </div>
                <p class="text-xs text-muted mt-0.5 line-clamp-2">{{ $response->content }}</p>
            </div>
            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                <button wire:click.stop="edit({{ $response->id }})" class="p-1 text-muted hover:text-ink rounded" title="{{ __('Edit') }}"">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button wire:click.stop="delete({{ $response->id }})" wire:confirm="Delete this quick reply? This action cannot be undone." class="p-1 text-muted hover:text-danger rounded" title="{{ __('Delete') }}"">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>
        @empty
        <div class="text-center py-6">
            <p class="text-sm text-muted">{{ __('No quick replies yet') }}</p>
            <p class="text-xs text-muted mt-1">{{ __('Create one to speed up your responses') }}</p>
        </div>
        @endforelse
    </div>
</div>
