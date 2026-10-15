<div class="space-y-6">
    @if(session('message'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm">{{ session('message') }}</div>
    @endif

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('Team Members') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('Manage your workspace team and invitations.') }}</p>
        </div>
        <button wire:click="openInviteForm" class="btn-primary flex items-center gap-2 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('Invite Member') }}
        </button>
    </div>

    {{-- Members list --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Member') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Role') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden sm:table-cell">{{ __('Joined') }}</th>
                        <th class="w-16 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($members as $member)
                    <tr class="hover:bg-surface" wire:key="member-{{ $member->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 bg-primary-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                    {{ strtoupper(substr($member->name, 0, 1)) }}{{ strtoupper(substr(explode(' ', $member->name)[1] ?? '', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-ink">
                                        {{ $member->name }}
                                        @if($member->id === auth()->id())
                                        <span class="text-xs text-muted">{{ __('(You)') }}</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-muted">{{ $member->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($member->id === auth()->id() || $member->role === 'owner')
                            <span class="px-2.5 py-1 rounded-lg text-xs font-medium {{ $member->role === 'owner' ? 'bg-warning/15 text-yellow-800' : 'bg-surface  text-muted ' }}">{{ ucfirst($member->role) }}</span>
                            @else
                            <select wire:change="changeRole({{ $member->id }}, $event.target.value)" class="text-xs bg-surface border border-border rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                @foreach(['admin', 'agent', 'viewer'] as $role)
                                <option value="{{ $role }}" {{ $member->role === $role ? 'selected' : '' }}>{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-muted hidden sm:table-cell">
                            {{ \Carbon\Carbon::parse($member->created_at)->format('M j, Y') }}
                        </td>
                        <td class="px-4 py-3">
                            @if($member->id !== auth()->id() && $member->role !== 'owner')
                            <button wire:click="removeMember({{ $member->id }})" wire:confirm="{{ __('Remove this team member? They will lose access to this workspace.') }}"
                                    class="p-1.5 text-muted hover:text-red-500 rounded-lg hover:bg-danger/10">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pending invitations --}}
    @if($pendingInvites->isNotEmpty())
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Pending Invitations') }}</h2>
        <div class="space-y-3">
            @foreach($pendingInvites as $invite)
            <div wire:key="invite-{{ $invite->id }}" class="flex items-center justify-between p-3 bg-surface rounded-xl">
                <div>
                    <p class="text-sm font-medium text-ink/80">{{ $invite->email }}</p>
                    <p class="text-xs text-muted">
                        {{ __('Role:') }} {{ ucfirst($invite->role) }}
                        &middot; {{ __('Expires') }} <span title="{{ \Carbon\Carbon::parse($invite->expires_at)->format('M j, Y g:i A') }}">{{ \Carbon\Carbon::parse($invite->expires_at)->diffForHumans() }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="resendInvite({{ $invite->id }})" class="px-3 py-1.5 text-xs text-muted  bg-surface-2 border border-border rounded-lg hover:bg-surface">{{ __('Resend') }}</button>
                    <button wire:click="cancelInvite({{ $invite->id }})" class="px-3 py-1.5 text-xs text-danger bg-surface-2 border border-danger/20 rounded-lg hover:bg-danger/10">{{ __('Cancel') }}</button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Invite Modal --}}
    @if($showInviteForm)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog" aria-labelledby="team-invite-modal-title">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-surface/50" wire:click="closeInviteForm"></div>
            <div class="relative bg-surface-2 rounded-2xl shadow-xl max-w-md w-full p-6" x-trap="$wire.showInviteForm">
                <h2 id="team-invite-modal-title" class="text-lg font-semibold text-ink mb-4">{{ __('Invite Team Member') }}</h2>
                <form wire:submit="sendInvite" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Email Address') }}</label>
                        <input type="email" wire:model="inviteEmail" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="{{ __('colleague@example.com') }}" autofocus>
                        @error('inviteEmail') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Role') }}</label>
                        <select wire:model="inviteRole" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="admin">{{ __('Admin - Full workspace access') }}</option>
                            <option value="agent">{{ __('Agent - Handle conversations & contacts') }}</option>
                            <option value="viewer">{{ __('Viewer - Read-only access') }}</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeInviteForm" class="btn-secondary text-sm">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn-primary px-4 py-2 text-sm">
                            <span wire:loading.remove wire:target="sendInvite">{{ __('Send Invitation') }}</span>
                            <span wire:loading wire:target="sendInvite" class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Sending...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
