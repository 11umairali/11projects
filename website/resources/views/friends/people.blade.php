<x-layouts.app :title="__('Friends')">
    <div class="max-w-4xl mx-auto space-y-4">
        <nav class="flex justify-between gap-4"><a class="btn-secondary" href="{{ url('/friends') }}">← {{ __('Chats') }}</a><a class="btn-secondary" href="{{ url('/people/calls') }}">{{ __('Call history') }}</a></nav>
        <livewire:friends.friends-hub />
    </div>
</x-layouts.app>
