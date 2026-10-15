<div class="space-y-6" x-data="{ dirty: false }" @change="dirty = true" @saved.window="dirty = false"
     x-on:livewire:navigating.window="if(dirty && !confirm('You have unsaved changes. Leave anyway?')) $event.preventDefault()"
     x-init="window.addEventListener('beforeunload', (e) => { if(dirty) { e.preventDefault(); e.returnValue = '' } })">
    @if(session('success'))<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm">{{ session('error') }}</div>@endif

    <div><h1 class="text-2xl font-bold text-ink">{{ __('Workspace Settings') }}</h1><p class="text-sm text-muted mt-1">{{ __('Configure your workspace profile and preferences.') }}</p></div>

    {{-- Workspace info --}}
    <form wire:submit="save" class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">
        <h2 class="text-lg font-semibold text-ink">{{ __('Workspace Profile') }}</h2>

        {{-- Logo --}}
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-2">{{ __('Workspace Logo') }}</label>
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-primary-600 rounded-2xl flex items-center justify-center text-white text-xl font-bold flex-shrink-0 overflow-hidden">
                    @if($logo)
                        <img src="{{ $logo->temporaryUrl() }}" class="w-16 h-16 rounded-2xl object-cover" alt="Preview">
                    @elseif($currentLogoUrl)
                        <img src="{{ $currentLogoUrl }}" class="w-16 h-16 rounded-2xl object-cover" alt="Logo">
                    @else
                        {{ strtoupper(substr($name, 0, 1)) }}
                    @endif
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border border-border rounded-xl hover:bg-surface transition-colors cursor-pointer">
                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ __('Upload Logo') }}
                        <input type="file" wire:model="logo" accept="image/*" class="hidden">
                    </label>
                    <p class="text-xs text-muted mt-1">{{ __('PNG, JPG or SVG. 256x256 recommended.') }}</p>
                    @error('logo') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('Workspace Name') }}</label>
                <input type="text" wire:model="name" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('Workspace Slug') }}</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 text-sm text-muted bg-surface border border-r-0 border-border rounded-l-xl">app.mailtrixy.com/</span>
                    <input type="text" wire:model="slug" class="flex-1 px-4 py-2.5 text-sm border border-border rounded-r-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                @error('slug') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('Industry') }}</label>
                <select wire:model="industry" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 bg-surface-2">
                    <option value="">{{ __('Select industry...') }}</option>
                    <option value="saas">{{ __('SaaS / Software') }}</option>
                    <option value="ecommerce">{{ __('E-commerce') }}</option>
                    <option value="education">{{ __('Education') }}</option>
                    <option value="healthcare">{{ __('Healthcare') }}</option>
                    <option value="finance">{{ __('Finance') }}</option>
                    <option value="consulting">{{ __('Consulting') }}</option>
                    <option value="agency">{{ __('Agency') }}</option>
                    <option value="real-estate">{{ __('Real Estate') }}</option>
                    <option value="other">{{ __('Other') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('Timezone') }}</label>
                <select wire:model="timezone" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 bg-surface-2">
                    <option value="UTC">UTC</option>
                    <option value="America/New_York">{{ __('Eastern Time (US)') }}</option>
                    <option value="America/Chicago">{{ __('Central Time (US)') }}</option>
                    <option value="America/Los_Angeles">{{ __('Pacific Time (US)') }}</option>
                    <option value="Europe/London">{{ __('London (GMT)') }}</option>
                    <option value="Europe/Berlin">{{ __('Berlin (CET)') }}</option>
                    <option value="Asia/Tokyo">{{ __('Tokyo (JST)') }}</option>
                    <option value="Asia/Kolkata">{{ __('Mumbai (IST)') }}</option>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-end pt-2">
            <button type="submit" class="btn-primary px-6 py-2.5 text-sm">
                <span wire:loading.remove wire:target="save">{{ __('Save Changes') }}</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Saving...
                </span>
            </button>
        </div>
    </form>

    {{-- Danger zone --}}
    <div class="bg-surface-2 rounded-2xl border-2 border-danger/20 p-6">
        <h2 class="text-lg font-semibold text-danger mb-2">{{ __('Danger Zone') }}</h2>
        <p class="text-sm text-muted mb-4">{{ __('Irreversible and destructive actions. Please proceed with caution.') }}</p>

        @if(!$showDeleteConfirm)
        <div class="flex items-center justify-between p-4 bg-danger/10 rounded-xl border border-danger/20">
            <div>
                <p class="text-sm font-medium text-red-900">{{ __('Delete Workspace') }}</p>
                <p class="text-xs text-danger mt-0.5">{{ __('This will permanently delete all data. This cannot be undone.') }}</p>
            </div>
            <button wire:click="showDeleteWorkspace" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-xl hover:bg-red-700 transition-colors flex-shrink-0 ml-4">{{ __('Delete Workspace') }}</button>
        </div>
        @else
        <div class="p-4 bg-danger/10 rounded-xl border border-danger/20 space-y-4">
            <p class="text-sm text-red-900 font-medium">{{ __('Type your workspace name to confirm deletion:') }}</p>
            <p class="text-sm text-danger font-mono bg-danger/15 px-3 py-1 rounded inline-block">{{ $name }}</p>
            <div>
                <input type="text" wire:model="deleteConfirmation" placeholder="{{ __('Type workspace name here...') }}" class="w-full px-4 py-2.5 text-sm border border-red-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500">
                @error('deleteConfirmation') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm text-danger font-medium mb-1 block">{{ __('Enter your password to confirm:') }}</label>
                <input type="password" wire:model="deletePassword" placeholder="{{ __('Your account password') }}" class="w-full px-4 py-2.5 text-sm border border-red-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500">
                @error('deletePassword') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="deleteWorkspace" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-xl hover:bg-red-700 transition-colors">{{ __('Yes, Delete Forever') }}</button>
                <button wire:click="cancelDelete" class="px-4 py-2 text-sm font-medium text-muted  border border-border rounded-xl hover:bg-surface transition-colors">{{ __('Cancel') }}</button>
            </div>
        </div>
        @endif
    </div>
</div>
