<x-layouts.admin :title="$user->name" :subtitle="__('User account details and activity.')">
    <div class="space-y-6">

        {{-- User Info Panel --}}
        <div class="panel p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand/10 text-brand text-lg font-bold">
                        {{ $user->initials }}
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            @if($user->suspended_at)
                                <span class="badge badge-error">{{ __('Suspended') }}</span>
                            @elseif($user->status === 'active')
                                <span class="badge badge-success">{{ __('Active') }}</span>
                            @elseif($user->status === 'pending_deletion')
                                <span class="badge badge-warning">{{ __('Pending Deletion') }}</span>
                            @elseif($user->status === 'banned')
                                <span class="badge badge-error">{{ __('Banned') }}</span>
                            @else
                                <span class="badge badge-ghost">{{ ucfirst($user->status) }}</span>
                            @endif
                            @if($user->is_admin)
                                <span class="badge">{{ __('Admin') }}</span>
                            @endif
                        </div>
                        <p class="text-sm text-muted mt-1">{{ $user->email }}</p>
                        <p class="text-xs text-muted mt-0.5">
                            {{ __('Joined') }} {{ $user->created_at->format('M j, Y') }}
                            @if($user->email_verified_at)
                                &middot; {{ __('Email verified') }} {{ $user->email_verified_at->format('M j, Y') }}
                            @else
                                &middot; <span class="text-warning">{{ __('Email not verified') }}</span>
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.users.impersonate', $user->id) }}">
                        @csrf
                        <button type="submit" class="btn-secondary">{{ __('Impersonate') }}</button>
                    </form>
                    @if($user->suspended_at)
                        <form method="POST" action="{{ route('admin.users.unsuspend', $user->id) }}">
                            @csrf
                            <button type="submit" class="btn-secondary">{{ __('Unsuspend') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.users.suspend', $user->id) }}" x-data @submit.prevent="if(confirm('{{ __('Are you sure you want to suspend this user? They will lose access until unsuspended.') }}')) $el.submit()">
                            @csrf
                            <button type="submit" class="btn-secondary">{{ __('Suspend') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Subscription Info --}}
            <div class="panel overflow-hidden">
                <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
                    <h2 class="text-lg font-bold text-ink">{{ __('Subscription') }}</h2>
                </div>
                <div class="p-6 space-y-4">
                    @if($user->activeWorkspace?->subscription?->plan)
                        @php $subscription = $user->activeWorkspace->subscription; $plan = $subscription->plan; @endphp
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-purple-50 border border-brand/20 rounded-xl">
                            <div>
                                <p class="text-sm font-bold text-ink">{{ $plan->name }} {{ __('Plan') }}</p>
                                <p class="text-xs text-muted">@currency($plan->monthly_price){{ __('/month') }}</p>
                            </div>
                            <span class="px-2.5 py-1 text-xs font-bold {{ $subscription->status === 'active' ? 'bg-success/15 text-success' : 'bg-warning/15 text-warning' }} rounded-full">{{ ucfirst($subscription->status) }}</span>
                        </div>
                    @else
                        <div class="flex items-center justify-between p-4 bg-surface border border-border rounded-xl">
                            <div>
                                <p class="text-sm font-bold text-ink">{{ __('No Active Subscription') }}</p>
                                <p class="text-xs text-muted">{{ __('This user does not have an active plan.') }}</p>
                            </div>
                            <span class="px-2.5 py-1 text-xs font-bold bg-surface text-ink/80 rounded-full">{{ __('None') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Workspaces --}}
            <div class="panel overflow-hidden">
                <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
                    <h2 class="text-lg font-bold text-ink">{{ __('Workspaces') }}</h2>
                </div>
                <div class="p-6 space-y-3">
                    @forelse($workspaces as $ws)
                    <div class="flex items-center justify-between p-4 bg-surface rounded-xl hover:bg-surface transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-brand/15 rounded-xl flex items-center justify-center text-xs font-bold text-brand">
                                {{ strtoupper(substr($ws->name, 0, 2)) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-ink">{{ $ws->name }}</p>
                                <p class="text-[10px] text-muted">
                                    {{ $ws->contacts_count ?? 0 }} {{ __('contacts') }} &middot;
                                    {{ $ws->conversations_count ?? 0 }} {{ __('conversations') }}
                                </p>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full
                            {{ $ws->pivot->role === 'owner' ? 'bg-brand/15 text-brand' : ($ws->pivot->role === 'admin' ? 'bg-info/15 text-info' : 'bg-surface text-ink/80') }}">
                            {{ ucfirst($ws->pivot->role) }}
                        </span>
                    </div>
                    @empty
                    <div class="text-center py-6">
                        <p class="text-sm text-muted">{{ __('No workspaces found.') }}</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Recent Login Activity --}}
            <div class="panel overflow-hidden">
                <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
                    <h2 class="text-lg font-bold text-ink">{{ __('Recent Login Activity') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface border-b border-border/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('IP Address') }}</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('User Agent') }}</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @forelse($loginHistory as $index => $log)
                            <tr class="hover:bg-surface transition-colors">
                                <td class="px-4 py-2.5 text-xs font-mono text-ink/80">
                                    {{ $log->ip_address ?? __('N/A') }}
                                    @if($index === 0)
                                    <span class="ml-1 px-1.5 py-0.5 text-[9px] font-bold bg-success/15 text-success rounded">{{ __('Latest') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-muted max-w-[200px] truncate">{{ Str::limit($log->user_agent ?? __('N/A'), 40) }}</td>
                                <td class="px-4 py-2.5 text-xs text-muted">{{ \Carbon\Carbon::parse($log->created_at)->format('M j, Y g:i A') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-xs text-muted">{{ __('No login activity recorded.') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recent Payments --}}
            <div class="panel overflow-hidden">
                <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
                    <h2 class="text-lg font-bold text-ink">{{ __('Recent Payments') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface border-b border-border/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Amount') }}</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Status') }}</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @forelse($payments as $payment)
                            <tr class="hover:bg-surface transition-colors">
                                <td class="px-4 py-2.5 text-sm font-bold text-ink">@currency($payment->amount)</td>
                                <td class="px-4 py-2.5">
                                    @if($payment->status === 'succeeded')
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-success/15 text-success rounded-full">{{ __('Succeeded') }}</span>
                                    @elseif($payment->status === 'failed')
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-danger/15 text-danger rounded-full">{{ __('Failed') }}</span>
                                    @elseif($payment->status === 'refunded')
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-surface text-ink/80 rounded-full">{{ __('Refunded') }}</span>
                                    @elseif($payment->status === 'pending')
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-warning/15 text-warning rounded-full">{{ __('Pending') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-surface text-ink/80 rounded-full">{{ ucfirst($payment->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-muted">{{ \Carbon\Carbon::parse($payment->created_at)->format('M j, Y') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-xs text-muted">{{ __('No payments recorded.') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</x-layouts.admin>
