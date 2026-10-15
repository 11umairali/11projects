@props(['active' => false, 'selectedId' => 0])
<link rel="stylesheet" href="{{ asset('css/messenger.css') }}?v={{ substr(hash_file('sha256', public_path('css/messenger.css')), 0, 12) }}">
<div class="messenger {{ $active ? 'has-conversation' : '' }}" x-data="{ profileOpen: false, discoveryOpen: false }" @keydown.escape.window="profileOpen = false">
    <aside class="messenger-sidebar" aria-label="{{ __('Friends and chats') }}">
        <div class="messenger-directory"><livewire:friends.chat-list /></div>
    </aside>
    <main class="messenger-conversation" aria-label="{{ __('Conversation') }}">{{ $slot }}</main>
    <section x-cloak x-show="discoveryOpen" class="messenger-discovery-panel" aria-label="{{ __('Find people') }}" @keydown.escape.window="discoveryOpen = false">
        <header><div><p class="messenger-eyebrow">{{ __('Grow your circle') }}</p><h2>{{ __('Find people') }}</h2></div><button type="button" class="messenger-icon" @click="discoveryOpen = false" aria-label="{{ __('Close search') }}"><x-action-icon name="close" /></button></header>
        @include('friends._search')
    </section>
    @isset($profile)
        <aside x-cloak class="messenger-profile" :class="{ 'is-open': profileOpen }" aria-label="{{ __('Contact details') }}">
            <button type="button" class="messenger-profile-close messenger-icon" aria-label="{{ __('Close contact details') }}" @click="profileOpen = false"><x-action-icon name="close" /></button>
            {{ $profile }}
        </aside>
    @endisset
</div>
