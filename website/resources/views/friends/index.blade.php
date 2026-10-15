<x-layouts.app :title="__('Friends & chats')">
    <x-friends.workspace>
        <div class="messenger-empty">
            <x-action-icon name="chat" />
            <h1 class="text-2xl font-semibold">{{ __('Select a conversation') }}</h1>
            <p class="text-muted">{{ __('Choose a chat on the left, or select New chat to message a friend.') }}</p>
        </div>
    </x-friends.workspace>
</x-layouts.app>
