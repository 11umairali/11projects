<div class="space-y-6">
    {{-- Flash messages --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm">{{ session('error') }}</div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-ink">{{ __('Drip Sequence') }}</h2>
            <p class="text-sm text-muted mt-0.5">{{ __('Send automated follow-up emails on a schedule.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($sequenceId && count($steps) > 0)
                @if($sequence?->status === 'active')
                <button wire:click="pauseSequence" class="px-4 py-2 text-sm font-medium text-warning bg-warning/10 border border-warning/20 rounded-xl hover:bg-warning/20 transition-colors">
                    {{ __('Pause Sequence') }}
                </button>
                @else
                <button wire:click="activateSequence" class="px-4 py-2 text-sm font-medium text-success bg-success/10 border border-success/20 rounded-xl hover:bg-success/20 transition-colors">
                    {{ __('Activate Sequence') }}
                </button>
                @endif
            @endif
        </div>
    </div>

    {{-- Sequence name --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-5">
        <div class="flex items-end gap-3">
            <div class="flex-1">
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Sequence Name') }}</label>
                <input type="text" wire:model="sequenceName" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="e.g., Welcome Series">
                @error('sequenceName') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <button wire:click="saveSequence" class="btn-primary px-4 py-2.5 text-sm shrink-0">
                <span wire:loading.remove wire:target="saveSequence">{{ __('Save') }}</span>
                <span wire:loading wire:target="saveSequence">{{ __('Saving...') }}</span>
            </button>
        </div>

        @if($sequence)
        <div class="flex items-center gap-4 mt-3 text-xs text-muted">
            <span class="inline-flex items-center gap-1">
                <span class="w-2 h-2 rounded-full {{ $sequence->status === 'active' ? 'bg-success' : ($sequence->status === 'paused' ? 'bg-warning' : 'bg-muted') }}"></span>
                {{ ucfirst($sequence->status ?? 'draft') }}
            </span>
            <span>{{ $enrollmentCount }} {{ __('enrolled') }}</span>
            <span>{{ $activeEnrollments }} {{ __('active') }}</span>
        </div>
        @endif
    </div>

    {{-- Steps timeline --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-ink">Steps ({{ count($steps) }})</h3>
            <button wire:click="toggleAddStep" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/30 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Add Step') }}
            </button>
        </div>

        @if(empty($steps))
        <div class="text-center py-10">
            <svg class="w-12 h-12 text-muted/40 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
            <p class="text-sm text-muted">{{ __('No steps yet. Add your first step to start building the drip sequence.') }}</p>
        </div>
        @else
        <div class="space-y-0">
            @foreach($steps as $index => $step)
            <div class="relative flex items-start gap-4 {{ !$loop->last ? 'pb-6' : '' }}">
                {{-- Timeline connector --}}
                @if(!$loop->last)
                <div class="absolute left-[17px] top-9 bottom-0 w-0.5 bg-border"></div>
                @endif

                {{-- Step number circle --}}
                <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 flex items-center justify-center text-sm font-bold shrink-0 z-10">
                    {{ $index + 1 }}
                </div>

                {{-- Step content --}}
                <div class="flex-1 bg-surface rounded-xl border border-border p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-muted bg-surface-2 px-2 py-0.5 rounded-md">
                                {{ $this->getDelayLabel($step['delay_value'], $step['delay_unit']) }}
                            </span>
                            <span class="text-xs font-medium text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2 py-0.5 rounded-md">
                                {{ $this->getActionLabel($step['action_type']) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1">
                            @if($index > 0)
                            <button wire:click="moveStepUp({{ $index }})" class="p-1 text-muted hover:text-ink rounded transition-colors" :title="__('Move up')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                            </button>
                            @endif
                            @if($index < count($steps) - 1)
                            <button wire:click="moveStepDown({{ $index }})" class="p-1 text-muted hover:text-ink rounded transition-colors" :title="__('Move down')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            @endif
                            <button wire:click="removeStep({{ $index }})" wire:confirm="Remove this step?" class="p-1 text-muted hover:text-danger rounded transition-colors" :title="__('Remove step')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Action details --}}
                    @if($step['action_type'] === 'send_email')
                    <p class="text-sm text-ink">{{ $step['action_data']['subject'] ?? __('No subject') }}</p>
                    <p class="text-xs text-muted mt-1 line-clamp-2">{{ strip_tags($step['action_data']['body'] ?? '') ?: __('No body content') }}</p>
                    @elseif($step['action_type'] === 'add_tag')
                    <p class="text-sm text-ink">Add tag: <span class="font-medium">{{ $step['action_data']['tag_name'] ?? '—' }}</span></p>
                    @elseif($step['action_type'] === 'remove_tag')
                    <p class="text-sm text-ink">Remove tag: <span class="font-medium">{{ $step['action_data']['tag_name'] ?? '—' }}</span></p>
                    @elseif($step['action_type'] === 'update_field')
                    <p class="text-sm text-ink">Set {{ $step['action_data']['field'] ?? 'field' }} to <span class="font-medium">{{ $step['action_data']['value'] ?? '—' }}</span></p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Add step form --}}
        @if($showAddStep)
        <div class="mt-4 bg-primary-50/50 dark:bg-primary-900/10 rounded-xl border border-primary-200 dark:border-primary-800 p-5 space-y-4">
            <h4 class="text-sm font-semibold text-ink">{{ __('Add New Step') }}</h4>

            {{-- Delay --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-ink/70 mb-1">{{ __('Wait Time') }}</label>
                    <input type="number" wire:model="newDelayValue" min="1" max="365" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-lg focus:ring-2 focus:ring-primary-500">
                    @error('newDelayValue') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/70 mb-1">{{ __('Unit') }}</label>
                    <select wire:model="newDelayUnit" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="minutes">{{ __('Minutes') }}</option>
                        <option value="hours">{{ __('Hours') }}</option>
                        <option value="days">{{ __('Days') }}</option>
                    </select>
                </div>
            </div>

            {{-- Action type --}}
            <div>
                <label class="block text-xs font-medium text-ink/70 mb-1">{{ __('Action') }}</label>
                <select wire:model.live="newActionType" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-lg focus:ring-2 focus:ring-primary-500">
                    <option value="send_email">{{ __('Send Email') }}</option>
                    <option value="add_tag">{{ __('Add Tag') }}</option>
                    <option value="remove_tag">{{ __('Remove Tag') }}</option>
                    <option value="update_field">{{ __('Update Contact Field') }}</option>
                </select>
            </div>

            {{-- Action-specific fields --}}
            @if($newActionType === 'send_email')
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-ink/70 mb-1">{{ __('Email Subject') }}</label>
                    <input type="text" wire:model="newActionSubject" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Follow-up: {{contact_name}}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/70 mb-1">{{ __('Email Body') }}</label>
                    <textarea wire:model="newActionBody" rows="4" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-lg focus:ring-2 focus:ring-primary-500 resize-none" placeholder="Hi {{contact_name}},&#10;&#10;Just following up..."></textarea>
                </div>
            </div>
            @elseif($newActionType === 'add_tag' || $newActionType === 'remove_tag')
            <div>
                <label class="block text-xs font-medium text-ink/70 mb-1">{{ __('Tag Name') }}</label>
                <input type="text" wire:model="newActionTagName" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="e.g., engaged">
            </div>
            @elseif($newActionType === 'update_field')
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-ink/70 mb-1">{{ __('Field Name') }}</label>
                    <input type="text" wire:model="newActionFieldName" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="e.g., status">
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink/70 mb-1">{{ __('New Value') }}</label>
                    <input type="text" wire:model="newActionFieldValue" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="e.g., qualified">
                </div>
            </div>
            @endif

            {{-- Actions --}}
            <div class="flex items-center gap-2 pt-1">
                <button wire:click="addStep" class="btn-primary px-4 py-2 text-sm">
                    <span wire:loading.remove wire:target="addStep">{{ __('Add Step') }}</span>
                    <span wire:loading wire:target="addStep">{{ __('Adding...') }}</span>
                </button>
                <button wire:click="toggleAddStep" class="px-4 py-2 text-sm font-medium text-muted hover:text-ink transition-colors">{{ __('Cancel') }}</button>
            </div>
        </div>
        @endif
    </div>
</div>
