@php($can = !empty($p['can_talk']) || !empty($p['is_friend']))
{{-- one person: photo, name (opens the profile), type, and the ways to reach them. NO remove button here – it is on the profile. --}}
<div class="bg-surface-2 rounded-2xl border border-border p-4 flex flex-wrap items-center gap-3">
    <a href="{{ (!empty($p['username']) ? url('/people/u/' . rawurlencode($p['username'])) : url('/people/' . $p['id'])) }}" class="flex items-center gap-3 min-w-0 flex-1 group">
        <div class="w-12 h-12 rounded-full profile-initial flex items-center justify-center font-semibold flex-shrink-0 overflow-hidden relative">
            @if($p['avatar_url'])<img src="{{ $p['avatar_url'] }}" alt="" class="w-full h-full object-cover">@else{{ strtoupper(mb_substr($p['name'], 0, 1)) }}@endif
        </div>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-ink truncate group-hover:underline">{{ $p['name'] }}</span>
                @if(!empty($p['is_friend']))<span class="text-[10px] rounded-full bg-green-100 text-green-800 px-2 py-0.5">{{ __('Friend') }}</span>@endif
                @if(!empty($p['is_former']))<span class="text-[10px] rounded-full bg-gray-200 text-gray-700 px-2 py-0.5">{{ __('Unfriended') }}@if(!empty($p['ended'])) · {{ $p['ended'] }}@endif</span>@endif
                @if(!empty($p['is_team']))<span class="text-[10px] rounded-full bg-blue-100 text-blue-800 px-2 py-0.5">{{ __('Team') }}@if(!empty($p['role'])) · {{ $p['role'] }}@endif</span>@endif
                @if(!empty($p['online']))<span class="w-2 h-2 rounded-full bg-green-500" title="{{ __('Online') }}"></span>@endif
            </div>
            <div class="text-xs text-muted truncate">@if(!empty($p['username'])){{ $p['username'] }} · @endif{{ $p['email'] ?? '' }}</div>
            @if(!empty($p['call_status']))<div class="text-xs text-green-600">{{ $p['call_status'] === 'ringing' ? __('Ringing…') : __('Call in progress') }}</div>@endif
            @if(!empty($p['preview']))<div class="text-sm text-ink truncate">{{ $p['preview'] }}</div>@endif
            @if(!empty($p['status_label']))<div class="text-xs {{ !empty($p['online']) ? 'text-green-600 font-medium' : 'text-muted' }} truncate">{{ $p['status_label'] }}</div>@endif
        </div>
    </a>
    @if(\App\Support\FriendSettings::chatEnabled() && !empty($p['is_former']))
        <a href="{{ $p['chat_url'] }}" class="btn-secondary" title="{{ __('Chat history') }}" aria-label="{{ __('Chat history') }}"><x-action-icon name="chat" /></a>
    @endif
    @if(\App\Support\FriendSettings::chatEnabled() && $can && empty($p['is_former']))
        <a href="{{ $p['chat_url'] }}" class="btn-primary" title="{{ __('Chat') }}" aria-label="{{ __('Chat') }}"><x-action-icon name="chat" />@if(($p['unread'] ?? 0) > 0)<span class="ml-1 text-xs rounded-full bg-white/25 px-1.5 py-0.5">{{ $p['unread'] }}</span>@endif</a>
    @endif
    @if(\App\Support\FriendSettings::callsEnabled() && $can && empty($p['is_former']))
        <button type="button" onclick="window.FriendCall && window.FriendCall.start({{ $p['id'] }}, @js($p['name']))" class="btn-secondary" title="{{ __('Call') }}" aria-label="{{ __('Call') }}"><x-action-icon name="phone" /></button>
        <button type="button" onclick="window.FriendCall && window.FriendCall.startVideo({{ $p['id'] }}, @js($p['name']))" class="btn-secondary" title="{{ __('Video') }}" aria-label="{{ __('Video') }}"><x-action-icon name="video" /></button>
    @endif
    @if($can && empty($p['is_former']))<a href="{{ url('/meetings').'?invite_user_id='.$p['id'] }}" class="btn-secondary" title="{{ __('Meeting') }}" aria-label="{{ __('Meeting') }}"><x-action-icon name="calendar" /></a>@endif
    <a href="mailto:{{ $p['email'] ?? '' }}" class="btn-secondary" title="{{ __('Email') }}" aria-label="{{ __('Email') }}"><x-action-icon name="mail" /></a>
    <a href="{{ (!empty($p['username']) ? url('/people/u/' . rawurlencode($p['username'])) : url('/people/' . $p['id'])) }}" class="text-xs text-muted hover:text-ink">{{ __('Profile') }}</a>
</div>
