<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('Team Performance') }}</h1>
            <p class="text-sm text-muted mt-0.5">{{ __('Agent leaderboard and productivity metrics') }}</p>
        </div>

        {{-- Date range selector --}}
        <div class="flex items-center gap-2">
            <div class="flex items-center bg-surface-2 border border-border rounded-xl p-0.5">
                @foreach(['today' => __('Today'), '7d' => __('7 days'), '30d' => __('30 days'), '90d' => __('90 days')] as $key => $label)
                <button wire:click="$set('dateRange', '{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $dateRange === $key ? 'bg-primary-600 text-white shadow-sm' : 'text-muted  hover:text-ink' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="flex items-center gap-1.5 px-3 py-2 text-sm text-muted  bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ __('Custom') }}
                </button>
                <div x-show="open" @click.away="open = false" x-transition
                     class="absolute right-0 mt-1 w-64 bg-surface-2 rounded-xl shadow-lg border border-border p-4 z-20" style="display: none;">
                    <div class="space-y-2">
                        <div>
                            <label class="text-xs text-muted">{{ __('From') }}</label>
                            <input type="date" wire:model="customFrom" class="w-full px-3 py-1.5 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="text-xs text-muted">{{ __('To') }}</label>
                            <input type="date" wire:model="customTo" class="w-full px-3 py-1.5 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                        <button @click="open = false" wire:click="applyCustomRange" class="btn-primary w-full px-3 py-1.5 text-sm">{{ __('Apply') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Agent Leaderboard Table --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-5 py-4 border-b border-border">
            <h3 class="text-sm font-semibold text-ink">{{ __('Agent Leaderboard') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 w-8">#</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">
                            <button wire:click="sortBy('name')" class="flex items-center gap-1 hover:text-ink/80">
                                {{ __('Agent') }}
                                @if($sortField === 'name')
                                <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">
                            <button wire:click="sortBy('conversations')" class="flex items-center gap-1 hover:text-ink/80">
                                {{ __('Conversations') }}
                                @if($sortField === 'conversations')
                                <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">
                            <button wire:click="sortBy('resolved')" class="flex items-center gap-1 hover:text-ink/80">
                                {{ __('Resolved') }}
                                @if($sortField === 'resolved')
                                <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">
                            <button wire:click="sortBy('response_time')" class="flex items-center gap-1 hover:text-ink/80">
                                {{ __('Avg Response') }}
                                @if($sortField === 'response_time')
                                <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">
                            <button wire:click="sortBy('messages')" class="flex items-center gap-1 hover:text-ink/80">
                                {{ __('Messages Sent') }}
                                @if($sortField === 'messages')
                                <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                @endif
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @forelse($agents as $index => $agent)
                    <tr class="hover:bg-surface transition-colors">
                        <td class="px-4 py-3 text-sm font-medium text-muted">{{ $index + 1 }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @php
                                    $colors = ['bg-info/100', 'bg-success/100', 'bg-brand/100', 'bg-pink-500', 'bg-warning/100', 'bg-rose-500', 'bg-violet-500', 'bg-emerald-500'];
                                    $bgColor = $colors[$index % count($colors)];
                                @endphp
                                <div class="w-8 h-8 {{ $bgColor }} rounded-full flex items-center justify-center text-white text-xs font-semibold flex-shrink-0">
                                    {{ $agent['initials'] }}
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-ink">{{ $agent['name'] }}</span>
                                    <p class="text-xs text-muted capitalize">{{ $agent['role'] }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm font-semibold text-ink">{{ number_format($agent['conversations']) }}</td>
                        <td class="px-4 py-3 text-sm text-muted  hidden md:table-cell">{{ number_format($agent['resolved']) }}</td>
                        <td class="px-4 py-3 text-sm text-muted  hidden md:table-cell">{{ $agent['avg_response_formatted'] }}</td>
                        <td class="px-4 py-3 text-sm text-muted  hidden lg:table-cell">{{ number_format($agent['messages_sent']) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-muted">{{ __('No team members found.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
