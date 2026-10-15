<div class="space-y-6" x-data="{ dirty: false }" @change="dirty = true" @saved.window="dirty = false"
     x-on:livewire:navigating.window="if(dirty && !confirm('You have unsaved changes. Leave anyway?')) $event.preventDefault()"
     x-init="window.addEventListener('beforeunload', (e) => { if(dirty) { e.preventDefault(); e.returnValue = '' } })">
    @if(session('message'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm">{{ session('message') }}</div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-ink">{{ __('Profile Settings') }}</h1>
        <p class="text-sm text-muted mt-1">{{ __('Manage your personal information and preferences.') }}</p>
    </div>

    {{-- Avatar --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Profile Photo') }}</h2>
        <div class="flex items-center gap-6">
            <div class="w-20 h-20 rounded-2xl overflow-hidden bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 flex items-center justify-center text-2xl font-bold">
                @if($avatarPreview)
                <img src="{{ $avatarPreview }}" class="w-20 h-20 object-cover" alt="Avatar">
                @else
                {{ strtoupper(substr($name, 0, 2)) }}
                @endif
            </div>
            <div>
                <label class="btn-primary inline-flex items-center gap-2 text-sm cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ __('Upload Photo') }}
                    <input type="file" wire:model="avatar" accept="image/*" class="hidden">
                </label>
                @if($avatarPreview)
                <button wire:click="removeAvatar" class="ml-2 px-4 py-2 text-sm font-medium text-muted  hover:text-danger transition-colors">{{ __('Remove') }}</button>
                @endif
                <p class="text-xs text-muted mt-2">{{ __('JPG, PNG or GIF. Max 2MB.') }}</p>
                @error('avatar') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                <div wire:loading wire:target="avatar" class="text-xs text-primary-600 mt-1">{{ __('Uploading...') }}</div>
            </div>
        </div>
    </div>

    {{-- Personal info --}}
    <form wire:submit="save" class="bg-surface-2 rounded-2xl border border-border p-6 space-y-4">
        <h2 class="text-lg font-semibold text-ink mb-2">{{ __('Personal Information') }}</h2>

        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Full Name') }} <span class="text-red-500">*</span></label>
            <input type="text" wire:model="name" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Email') }} <span class="text-red-500">*</span></label>
            <input type="email" wire:model="email" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Phone') }}</label>
            <input type="text" wire:model="phone" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="+1 (555) 123-4567">
            @error('phone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Timezone') }}</label>
            <select wire:model="timezone" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                @foreach($timezones as $tz)
                <option value="{{ $tz }}">{{ $tz }}</option>
                @endforeach
            </select>
            @error('timezone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2">
            <button type="submit" class="btn-primary px-5 py-2.5 text-sm">
                <span wire:loading.remove wire:target="save">{{ __('Save Changes') }}</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Saving...
                </span>
            </button>
        </div>
    </form>
</div>
