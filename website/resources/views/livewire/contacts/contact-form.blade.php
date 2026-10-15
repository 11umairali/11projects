<div>
    <div class="flex items-center justify-between mb-5">
        <h2 id="contact-form-modal-title" class="text-lg font-semibold text-ink">{{ $contactId ? __('Edit Contact') : __('New Contact') }}</h2>
        <button wire:click="close" class="p-1.5 text-muted/60 hover:text-ink rounded-lg hover:bg-surface">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('First Name') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="first_name" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="{{ __('John') }}">
                @error('first_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Last Name') }}</label>
                <input type="text" wire:model="last_name" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="{{ __('Doe') }}">
                @error('last_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Email') }} <span class="text-red-500">*</span></label>
            <input type="email" wire:model="email" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="{{ __('john@example.com') }}">
            @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Phone') }}</label>
            <input type="text" wire:model="phone" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="{{ __('+ 1 (555) 123-4567') }}">
            @error('phone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Company') }}</label>
                <input type="text" wire:model="company" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="{{ __('Acme Corp') }}">
                @error('company') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Job Title') }}</label>
                <input type="text" wire:model="job_title" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="{{ __('CEO') }}">
                @error('job_title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('City') }}</label>
                <input type="text" wire:model="city" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="{{ __('New York') }}">
                @error('city') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Country') }}</label>
                <input type="text" wire:model="country" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="{{ __('United States') }}">
                @error('country') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Tags --}}
        @if($this->tags->isNotEmpty())
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-2">{{ __('Tags') }}</label>
            <div class="flex flex-wrap gap-2">
                @foreach($this->tags as $tag)
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" wire:model="selectedTags" value="{{ $tag->id }}"
                           class="w-3.5 h-3.5 text-primary-600 border-border rounded focus:ring-primary-500">
                    <span class="text-sm text-muted ">{{ $tag->name }}</span>
                </label>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Custom fields --}}
        @foreach($this->customFields as $field)
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">
                {{ $field->name }}
                @if($field->required) <span class="text-red-500">*</span> @endif
            </label>
            @if($field->type === 'dropdown' && $field->options)
                <select wire:model="customFieldValues.{{ $field->key }}"
                        class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="">{{ __('Select...') }}</option>
                    @foreach($field->options as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
            @elseif($field->type === 'checkbox')
                <input type="checkbox" wire:model="customFieldValues.{{ $field->key }}"
                       class="w-4 h-4 text-primary-600 border-border rounded focus:ring-primary-500">
            @else
                <input type="{{ match($field->type) { 'number' => 'number', 'date' => 'date', 'email' => 'email', 'url' => 'url', 'phone' => 'tel', default => 'text' } }}"
                       wire:model="customFieldValues.{{ $field->key }}"
                       class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            @endif
            @error("customFieldValues.{$field->key}") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>
        @endforeach

        <div class="flex items-center justify-end gap-3 pt-2">
            <button type="button" wire:click="close" class="btn-secondary text-sm">{{ __('Cancel') }}</button>
            <button type="submit" class="btn-primary text-sm flex items-center gap-2">
                <span wire:loading.remove wire:target="save">{{ $contactId ? __('Update') : __('Create') }} {{ __('Contact') }}</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ __('Saving...') }}
                </span>
            </button>
        </div>
    </form>
</div>
