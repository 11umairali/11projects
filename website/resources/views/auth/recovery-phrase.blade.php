<x-layouts.guest :title="__('Save your recovery phrase')">
    <div>
        <h2 class="text-2xl font-bold text-ink">{{ __('Your recovery phrase') }}</h2>
        <p class="mt-1 text-sm text-muted">
            {{ __('Write these 12 words down in order and keep them somewhere safe and offline. We will not show them again. If you ever forget your password, this phrase is the only way back into your account.') }}
        </p>
    </div>

    <ol class="mt-6 grid grid-cols-2 sm:grid-cols-3 gap-2" aria-label="{{ __('Recovery phrase') }}" id="recovery-words">
        @foreach ($words as $i => $word)
            <li class="flex items-center gap-2 rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm">
                <span class="text-muted tabular-nums">{{ $i + 1 }}.</span>
                <span class="font-medium text-ink">{{ $word }}</span>
            </li>
        @endforeach
    </ol>

    <div class="mt-4 flex gap-3">
        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('recovery-words').innerText.replace(/\n+/g,' ').trim())"
                class="flex-1 py-2 px-3 bg-surface-2 border border-border rounded-xl text-sm font-medium text-ink hover:bg-surface transition-colors">
            {{ __('Copy') }}
        </button>
        <button type="button" onclick="window.print()"
                class="flex-1 py-2 px-3 bg-surface-2 border border-border rounded-xl text-sm font-medium text-ink hover:bg-surface transition-colors">
            {{ __('Print') }}
        </button>
    </div>

    <form method="POST" action="{{ route('recovery-phrase.confirm') }}" class="mt-6 space-y-4">
        @csrf
        <div class="flex items-start">
            <input id="saved" type="checkbox" name="saved" value="1" required
                   class="w-4 h-4 mt-0.5 rounded border-border text-brand focus:ring-brand">
            <label for="saved" class="ml-2 text-sm text-muted">{{ __("I've saved my recovery phrase somewhere safe.") }}</label>
        </div>
        @error('saved') <p class="text-xs text-danger">{{ $message }}</p> @enderror

        <button type="submit" class="btn-primary w-full py-2.5">
            {{ __('Continue') }}
        </button>
    </form>
</x-layouts.guest>
