<div wire:poll.5s.visible="reload" class="{{ $compact ? 'messenger-hub space-y-3' : 'max-w-4xl mx-auto space-y-6' }}">
    @unless($compact)
    <div>
        <h1 class="text-2xl font-semibold text-ink">{{ __('Friends') }}</h1>
        <p class="text-sm text-muted mt-1">{{ __('Your friends and teammates in one place. Open a profile to see details, or remove a friend.') }}</p>
    </div>

    @endunless

    @if(session('status'))
        <div class="rounded-xl border border-green-300 bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if($notice)
        <div class="rounded-xl border px-4 py-3 text-sm {{ $ok ? 'border-green-300 bg-green-50 text-green-800' : 'border-red-300 bg-red-50 text-red-800' }}">{{ $notice }}</div>
    @endif

    @if(($phone['discovery_available'] ?? ($phone['verification_enabled'] ?? false)) && !($phone['discoverable'] ?? false))
        <div class="bg-surface-2 rounded-2xl border border-border p-5 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="font-medium text-ink">{{ __('Let friends find you') }}</div>
                <div class="text-sm text-muted">{{ __('Add your phone number and switch discovery on so people who saved your number can find you. You stay hidden until you do.') }}</div>
            </div>
            <a href="{{ url('/settings/phone') }}" class="btn-primary">{{ __('Set up') }}</a>
        </div>
    @endif

    <div class="messenger-filters flex flex-wrap gap-1 border-b border-border">
        @foreach(['all' => [__('All'), count($everyone)], 'friends' => [__('Friends'), count($friendList)], 'team' => [__('Team'), count($data['team'] ?? [])], 'requests' => [__('Received requests'), count($data['incoming'] ?? [])], 'sent' => [__('Sent requests'), count($data['outgoing'] ?? [])], 'find' => [__('Find people'), 0], 'suggestions' => [__('Suggestions'), count($data['suggestions'] ?? [])], 'former' => [__('Former'), count($data['former'] ?? [])]] as $key => [$label, $count])
            <button type="button" wire:click="setTab('{{ $key }}')" class="px-4 py-2.5 text-sm font-medium -mb-px border-b-2 {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink' }}">
                {{ $label }} @if($count > 0)<span class="ml-1 text-xs rounded-full profile-initial px-2 py-0.5">{{ $count }}</span>@endif
            </button>
        @endforeach
        <a href="{{ url('/contacts') }}" class="px-4 py-2.5 text-sm font-medium -mb-px border-b-2 border-transparent text-muted hover:text-ink">{{ __('Customers') }} &rarr;</a>
    </div>

    @if(in_array($tab, ['requests', 'sent']))<h2 class="text-lg font-semibold">{{ $tab === 'requests' ? __('Received requests') : __('Sent requests') }} @php($requestCount = count($data[$tab === 'requests' ? 'incoming' : 'outgoing'] ?? [])) @if($requestCount)<span class="text-sm">({{ $requestCount }})</span>@endif</h2>@endif
    @php($row = 'bg-surface-2 rounded-2xl border border-border p-4 flex flex-wrap items-center gap-4')
    <div class="space-y-3">
    @if($tab === 'find')
        @include('friends._search')
    @elseif($tab === 'all')
        @forelse($everyone as $p)@include($compact ? 'livewire.friends.chat-row' : 'livewire.friends.person-row', ['p' => $p])@empty
            <div class="text-center text-sm text-muted py-12">{{ __('Nobody here yet. Find friends under Suggestions, or invite teammates in Settings → Team.') }}</div>
        @endforelse
    @elseif($tab === 'friends')
        @forelse($friendList as $p)@include($compact ? 'livewire.friends.chat-row' : 'livewire.friends.person-row', ['p' => $p])@empty
            <div class="text-center text-sm text-muted py-12">{{ __('No friends yet.') }}</div>
        @endforelse
    @elseif($tab === 'former')
        <p class="text-xs text-muted mb-3">{{ __('People you are no longer friends with. Your chats, voice messages, files and call recordings are kept – you can read them, but nobody can write or call until you become friends again.') }}</p>
        @forelse($data['former'] ?? [] as $p)@include($compact ? 'livewire.friends.chat-row' : 'livewire.friends.person-row', ['p' => $p + ['is_former' => true]])@empty
            <div class="text-center text-sm text-muted py-12">{{ __('Nobody here. When you unfriend someone, the person and your chat stay here.') }}</div>
        @endforelse
    @elseif($tab === 'team')
        @forelse($data['team'] ?? [] as $p)@include($compact ? 'livewire.friends.chat-row' : 'livewire.friends.person-row', ['p' => $p + ['is_team' => true]])@empty
            <div class="text-center text-sm text-muted py-12">{{ __('No teammates in this workspace yet.') }}</div>
        @endforelse
    @elseif($tab === 'requests')
        @forelse($data['incoming'] ?? [] as $p)
            <div class="{{ $row }}">
                <a href="{{ (!empty($p['username']) ? url('/people/u/' . rawurlencode($p['username'])) : url('/people/' . $p['id'])) }}" class="w-11 h-11 rounded-full profile-initial flex items-center justify-center font-semibold overflow-hidden flex-shrink-0">@if($p['avatar_url'])<img src="{{ $p['avatar_url'] }}" alt="" class="w-full h-full object-cover">@else{{ strtoupper(mb_substr($p['name'], 0, 1)) }}@endif</a>
                <div class="min-w-0 flex-1"><div class="font-medium text-ink truncate">{{ $p['name'] }}</div><div class="text-xs text-muted">{{ __('Wants to be your friend') }} · {{ $p['when'] }}</div></div>
                <button type="button" wire:click="respond({{ $p['request_id'] }}, 'block')" wire:confirm="{{ __('Block :n? They will not be able to send you requests.', ['n' => $p['name']]) }}" class="text-xs text-muted hover:text-red-600">{{ __('Block') }}</button>
                <button type="button" wire:click="respond({{ $p['request_id'] }}, 'decline')" wire:confirm="{{ __('Decline the request from :n? They will be notified and can send another request.', ['n' => $p['name']]) }}" class="btn-secondary">{{ __('Decline') }}</button>
                <button type="button" wire:click="respond({{ $p['request_id'] }}, 'accept')" wire:confirm="{{ __('Accept :n as a friend? They will be able to message and call you and see your email address.', ['n' => $p['name']]) }}" class="btn-primary">{{ __('Accept') }}</button>
            </div>
        @empty
            <div class="text-center text-sm text-muted py-8">{{ __('No friend requests.') }}</div>
        @endforelse
    @elseif($tab === 'sent')
        @if(!empty($data['outgoing']))
            <h3 class="text-sm font-semibold text-ink pt-2">{{ __('Sent requests') }} ({{ count($data['outgoing']) }})</h3>
            @foreach($data['outgoing'] as $p)
                <div class="{{ $row }}">
                    <div class="w-11 h-11 rounded-full profile-initial flex items-center justify-center font-semibold flex-shrink-0">{{ strtoupper(mb_substr($p['name'], 0, 1)) }}</div>
                    <div class="min-w-0 flex-1"><div class="font-medium text-ink truncate">{{ $p['name'] }}</div><div class="text-xs text-muted">{{ __('Waiting for an answer') }} · {{ $p['when'] }}</div></div>
                    <button type="button" wire:click="cancel({{ $p['request_id'] }})" wire:confirm="{{ __('Cancel your request to :n?', ['n' => $p['name']]) }}" class="btn-secondary">{{ __('Cancel') }}</button>
                </div>
            @endforeach
        @else
            <p class="text-center text-muted py-8">{{ __('No sent requests.') }}</p>
        @endif
    @else
        @forelse($data['suggestions'] ?? [] as $p)
            <div class="{{ $row }}">
                <div class="w-11 h-11 rounded-full profile-initial flex items-center justify-center font-semibold flex-shrink-0 overflow-hidden">@if($p['avatar_url'])<img src="{{ $p['avatar_url'] }}" alt="" class="w-full h-full object-cover">@else{{ strtoupper(mb_substr($p['name'], 0, 1)) }}@endif</div>
                <div class="min-w-0 flex-1">
                    <div class="font-medium text-ink truncate">{{ $p['name'] }}</div>
                    <div class="text-xs text-muted truncate">@if($p['saved_as']){{ __('Saved in your contacts as :n', ['n' => $p['saved_as']]) }}@else{{ __('Has your number or you have theirs') }}@endif @if(($p['verified'] ?? true) === false) · {{ __('number not verified') }}@endif</div>
                </div>
                <button type="button" wire:click="dismiss({{ $p['id'] }})" class="text-xs text-muted hover:text-ink">{{ __('Hide') }}</button>
                <button type="button" wire:click="sendRequest({{ $p['id'] }})" class="btn-primary">{{ __('Add friend') }}</button>
            </div>
        @empty
            <div class="text-center text-sm text-muted py-12">{{ __('No suggestions yet. When someone whose number you saved joins and allows discovery, they appear here.') }}</div>
        @endforelse
    @endif
    </div>
</div>
