<x-layouts.admin :title="__('Testimonials')" :subtitle="__('Manage client feedback and star ratings.')">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-ink">{{ __('Testimonial Library') }}</h3>
                    <p class="mt-1 text-sm text-muted">{{ __('Client reviews displayed on the landing page.') }}</p>
                </div>
                <a href="{{ route('admin.testimonials.create') }}" class="settings-save-btn">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('Add Testimonial') }}
                </a>
            </div>

            {{-- Filters --}}
            <form method="get" class="mt-5 flex flex-wrap gap-3">
                <input name="search" value="{{ request('search') }}" class="settings-input flex-1 min-w-[200px]" placeholder="{{ __('Search name, position, or review...') }}">
                <select name="status" class="settings-select w-auto">
                    <option value="">{{ __('All') }}</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                </select>
                <button type="submit" class="px-4 py-2 text-sm font-medium border border-border rounded-xl hover:bg-surface-2 transition-colors">{{ __('Filter') }}</button>
            </form>
        </div>

        {{-- Success --}}
        @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="p-4 bg-success/10 border border-success/20 rounded-xl text-sm font-medium text-success flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
        @endif

        {{-- Table --}}
        <div class="panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border/50 bg-surface/50">
                            <th class="px-6 py-3 text-left text-xs font-bold text-muted uppercase tracking-wider">{{ __('Client') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-muted uppercase tracking-wider">{{ __('Review') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-bold text-muted uppercase tracking-wider">{{ __('Rating') }}</th>
                            <th class="px-6 py-3 text-center text-xs font-bold text-muted uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-bold text-muted uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/30">
                        @forelse($testimonials as $t)
                        <tr class="hover:bg-surface-2/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @if($t->client_image)
                                    <img src="{{ asset('storage/' . $t->client_image) }}" class="w-10 h-10 rounded-full object-cover">
                                    @else
                                    <div class="w-10 h-10 rounded-full bg-brand/10 text-brand flex items-center justify-center text-sm font-bold">{{ substr($t->client_name, 0, 1) }}</div>
                                    @endif
                                    <div>
                                        <div class="font-semibold text-ink">{{ $t->client_name }}</div>
                                        <div class="text-xs text-muted">{{ $t->client_position }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 max-w-xs">
                                <p class="text-muted truncate">{{ Str::limit($t->review, 80) }}</p>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-0.5">
                                    @for($i = 0; $i < $t->rating; $i++)
                                    <svg class="w-3.5 h-3.5 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    @endfor
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($t->is_active)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-success/10 text-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success"></span> {{ __('Active') }}
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-muted/20 text-muted">
                                    <span class="w-1.5 h-1.5 rounded-full bg-muted"></span> {{ __('Inactive') }}
                                </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.testimonials.edit', $t) }}" class="p-2 text-muted hover:text-brand transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.testimonials.destroy', $t) }}" onsubmit="return confirm('Delete this testimonial?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-2 text-muted hover:text-danger transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-muted">
                                <p class="text-base font-semibold mb-1">{{ __('No testimonials yet') }}</p>
                                <p class="text-sm">{{ __('Add your first client testimonial to display on the landing page.') }}</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($testimonials->hasPages())
            <div class="px-6 py-4 border-t border-border/50">
                {{ $testimonials->links() }}
            </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
