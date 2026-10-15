<x-layouts.admin :title="$plan->name" :subtitle="__('Plan details, features, and subscribers.')">
    <div class="space-y-6">

        {{-- Plan Summary Panel --}}
        <div class="panel p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    @if($plan->is_active)
                        <span class="badge badge-success">{{ __('Active') }}</span>
                    @else
                        <span class="badge badge-ghost">{{ __('Inactive') }}</span>
                    @endif
                    @if($plan->is_popular)
                        <span class="badge badge-warning">{{ __('Popular') }}</span>
                    @endif
                    <code class="text-xs bg-surface text-muted px-2 py-1 rounded-lg font-mono border border-border">{{ $plan->slug }}</code>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('admin.plans.edit', $plan->id) }}" class="btn-secondary">{{ __('Edit Plan') }}</a>
                </div>
            </div>
            <p class="text-xs text-muted mt-3">
                {{ __('Created') }} {{ $plan->created_at->format('M j, Y') }}
                &middot; {{ __('Sort order:') }} {{ $plan->sort_order }}
                &middot; {{ number_format($plan->active_subscriptions_count) }} {{ __('active subscriber(s)') }}
            </p>
        </div>

        {{-- Flash Messages --}}
        @if(session('error'))
            <div class="p-4 bg-danger/10 border border-danger/20 rounded-xl text-sm text-danger font-medium">
                {{ session('error') }}
            </div>
        @endif
        @if(session('success'))
            <div class="p-4 bg-success/10 border border-success/20 rounded-xl text-sm text-success font-medium">
                {{ session('success') }}
            </div>
        @endif

        {{-- Plan Details --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Pricing & Info --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-bold text-ink">{{ __('Pricing & Info') }}</h2>
                </div>
                <div class="divide-y divide-border">
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Monthly Price') }}</span>
                        <span class="text-lg font-extrabold text-ink">
                            @if($plan->isFree())
                                {{ __('Free') }}
                            @else
                                @currency($plan->monthly_price)
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Yearly Price') }}</span>
                        <span class="text-lg font-extrabold text-ink">
                            @if($plan->isFree())
                                {{ __('Free') }}
                            @else
                                @currency($plan->yearly_price)
                            @endif
                        </span>
                    </div>
                    @if(!$plan->isFree())
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Yearly Savings') }}</span>
                        <span class="text-sm font-medium text-success">
                            @php $savings = ($plan->monthly_price * 12) - $plan->yearly_price; @endphp
                            @if($savings > 0)
                                @currency($savings) {{ __('saved/yr') }} ({{ round(($savings / ($plan->monthly_price * 12)) * 100) }}% {{ __('off') }})
                            @else
                                {{ __('No savings') }}
                            @endif
                        </span>
                    </div>
                    @endif
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Status') }}</span>
                        <span>
                            @if($plan->is_active)
                                <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-success/15 text-success">{{ __('Active') }}</span>
                            @else
                                <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-surface text-ink/80">{{ __('Inactive') }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Popular Badge') }}</span>
                        <span>
                            @if($plan->is_popular)
                                <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-warning/15 text-warning">{{ __('Yes') }}</span>
                            @else
                                <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-surface text-ink/80">{{ __('No') }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Sort Order') }}</span>
                        <span class="text-sm font-medium text-ink">{{ $plan->sort_order }}</span>
                    </div>
                    @if($plan->stripe_monthly_price_id || $plan->stripe_yearly_price_id)
                    <div class="px-6 py-4 space-y-2">
                        <span class="text-sm text-muted">{{ __('Stripe Price IDs') }}</span>
                        <div class="space-y-1 mt-1">
                            @if($plan->stripe_monthly_price_id)
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] text-muted uppercase">{{ __('Monthly:') }}</span>
                                    <code class="text-xs bg-surface text-ink/80 px-2 py-0.5 rounded-lg border border-border">{{ $plan->stripe_monthly_price_id }}</code>
                                </div>
                            @endif
                            @if($plan->stripe_yearly_price_id)
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] text-muted uppercase">{{ __('Yearly:') }}</span>
                                    <code class="text-xs bg-surface text-ink/80 px-2 py-0.5 rounded-lg border border-border">{{ $plan->stripe_yearly_price_id }}</code>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Description & Subscribers Summary --}}
            <div class="space-y-6">
                {{-- Description --}}
                <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                    <div class="px-6 py-4 border-b border-border">
                        <h2 class="text-lg font-bold text-ink">{{ __('Description') }}</h2>
                    </div>
                    <div class="px-6 py-4">
                        @if($plan->description)
                            <p class="text-sm text-ink leading-relaxed">{{ $plan->description }}</p>
                        @else
                            <p class="text-sm text-muted italic">{{ __('No description provided.') }}</p>
                        @endif
                    </div>
                </div>

                {{-- Subscribers Summary --}}
                <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                    <div class="px-6 py-4 border-b border-border">
                        <h2 class="text-lg font-bold text-ink">{{ __('Subscribers') }}</h2>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-purple-50 border border-brand/20 rounded-xl">
                            <div>
                                <p class="text-2xl font-extrabold text-ink">{{ number_format($plan->active_subscriptions_count) }}</p>
                                <p class="text-xs text-muted mt-0.5">{{ __('Active subscriptions') }}</p>
                            </div>
                            <div class="w-12 h-12 bg-brand/15 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Feature Limits --}}
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-bold text-ink">{{ __('Feature Limits') }}</h2>
                <p class="text-sm text-muted mt-1">{{ __('Configured feature keys and their limits for this plan.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface border-b border-border">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Feature Key') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Limit') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($plan->planFeatures as $feature)
                        <tr class="hover:bg-surface transition-colors">
                            <td class="px-6 py-3 font-medium text-ink">
                                <code class="text-xs bg-surface text-ink/80 px-2 py-0.5 rounded-lg border border-border">{{ $feature->feature_key }}</code>
                            </td>
                            <td class="px-6 py-3">
                                @if($feature->enabled)
                                    <span class="px-2.5 py-1 text-xs font-medium bg-success/15 text-success rounded-full">{{ __('Enabled') }}</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-medium bg-surface text-ink/80 rounded-full">{{ __('Disabled') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-ink">
                                @if($feature->enabled && $feature->limit !== null)
                                    <span class="font-semibold">{{ number_format($feature->limit) }}</span>
                                @elseif($feature->enabled)
                                    <span class="text-success font-medium">{{ __('Unlimited') }}</span>
                                @else
                                    <span class="text-muted">--</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center">
                                <p class="text-sm text-muted">{{ __('No feature limits configured for this plan.') }}</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent Subscribers --}}
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-bold text-ink">{{ __('Recent Subscribers') }}</h2>
                <p class="text-sm text-muted mt-1">{{ __('Latest active subscriptions on this plan (up to 10).') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface border-b border-border">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Workspace') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Owner') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Billing Cycle') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Subscribed') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($plan->subscriptions as $sub)
                        @php $owner = $sub->workspace?->members?->firstWhere('pivot.role', 'owner'); @endphp
                        <tr class="hover:bg-surface transition-colors">
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-brand/15 rounded-lg flex items-center justify-center text-xs font-bold text-brand">
                                        {{ strtoupper(substr($sub->workspace->name ?? '??', 0, 2)) }}
                                    </div>
                                    <span class="font-medium text-ink">{{ $sub->workspace->name ?? __('Deleted workspace') }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-3">
                                @if($owner)
                                    <div>
                                        <p class="font-medium text-ink text-xs">{{ $owner->name }}</p>
                                        <p class="text-[10px] text-muted">{{ $owner->email }}</p>
                                    </div>
                                @else
                                    <span class="text-muted text-xs">--</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $sub->billing_cycle === 'yearly' ? 'bg-brand/15 text-brand' : 'bg-info/15 text-info' }}">
                                    {{ ucfirst($sub->billing_cycle ?? 'monthly') }}
                                </span>
                            </td>
                            <td class="px-6 py-3">
                                <span class="px-2.5 py-1 text-xs font-medium bg-success/15 text-success rounded-full">{{ __('Active') }}</span>
                            </td>
                            <td class="px-6 py-3 text-xs text-muted">
                                {{ $sub->created_at->format('M j, Y') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center">
                                <p class="text-sm text-muted">{{ __('No active subscribers on this plan.') }}</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.plans.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-muted border border-border rounded-xl hover:bg-surface transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('Back to Plans') }}
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.plans.edit', $plan->id) }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-brand border border-brand/20 rounded-xl hover:bg-brand/10 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    {{ __('Edit Plan') }}
                </a>
                <form method="POST" action="{{ route('admin.plans.destroy', $plan->id) }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            onclick="if(!confirm('{{ __('Are you sure you want to delete this plan? This action cannot be undone.') }}')){event.preventDefault();return;} this.disabled=true; this.closest('form').submit();"
                            class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-danger border border-danger/20 rounded-xl hover:bg-danger/10 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        {{ __('Delete Plan') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
