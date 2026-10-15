<x-layouts.admin :title="__('Content Management')" :subtitle="__('Manage static pages, blog posts, and SEO settings.')">
    <div class="space-y-6">

        {{-- Success/Error Messages --}}
        @if(session('success'))
        <div class="p-4 bg-success/10 border border-success/30 rounded-xl text-sm text-success font-medium flex items-center gap-2">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="p-4 bg-danger/10 border border-danger/30 rounded-xl text-sm text-danger font-medium flex items-center gap-2">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('error') }}
        </div>
        @endif

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('CMS Pages') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Manage landing pages, static content, and blog posts.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.cms.create') }}" class="btn-primary">{{ __('Create Page') }}</a>
                </div>
            </div>

            <form method="get" action="{{ route('admin.cms.index') }}" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by title or slug...') }}">
                </div>
                <div>
                    <label class="sr-only" for="type">{{ __('Type') }}</label>
                    <select id="type" name="type" class="input-field">
                        <option value="">{{ __('All types') }}</option>
                        <option value="landing" @selected(request('type') === 'landing')>{{ __('Landing Pages') }}</option>
                        <option value="static" @selected(request('type') === 'static')>{{ __('Static Pages') }}</option>
                        <option value="blog" @selected(request('type') === 'blog')>{{ __('Blog Posts') }}</option>
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="input-field">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="published" @selected(request('status') === 'published')>{{ __('Published') }}</option>
                        <option value="draft" @selected(request('status') === 'draft')>{{ __('Draft') }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn-secondary" type="submit">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.cms.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Pages Table Panel --}}
        <div class="panel p-6" x-data="{
            selected: [],
            selectAll: false,
            allIds: [{{ $pages->pluck('id')->join(',') }}]
        }" x-init="$watch('selectAll', v => { selected = v ? [...allIds] : [] })">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Page Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $pages->count() }} {{ __('of') }} {{ $pages->total() }} {{ __('pages.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.cms.export', request()->query()) }}" class="btn-secondary">{{ __('Export') }}</a>
                    <button x-show="selected.length > 0" x-cloak type="button" class="btn-danger" @click="$dispatch('open-modal', 'bulk-delete-pages')" x-text="'Bulk Delete (' + selected.length + ')'"></button>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-[0.2em] text-muted">
                        <tr>
                            <th class="pb-3">
                                <input type="checkbox" x-model="selectAll" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </th>
                            <th class="pb-3">{{ __('Page') }}</th>
                            <th class="pb-3">{{ __('Slug') }}</th>
                            <th class="pb-3">{{ __('Type') }}</th>
                            <th class="pb-3">{{ __('Meta Title') }}</th>
                            <th class="pb-3">{{ __('Status') }}</th>
                            <th class="pb-3">{{ __('Last Updated') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @php
                            $typeIcons = [
                                'landing' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                                'static' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                                'blog' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
                            ];
                            $typeLabels = ['landing' => __('Landing'), 'static' => __('Static'), 'blog' => __('Blog')];
                        @endphp
                        @forelse($pages as $page)
                        <tr class="hover:bg-surface transition-colors">
                            <td class="py-4">
                                <input type="checkbox" value="{{ $page->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </td>
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-brand/10 rounded-lg flex items-center justify-center">
                                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $typeIcons[$page->type] ?? $typeIcons['static'] }}"/></svg>
                                    </div>
                                    <span class="font-semibold text-ink">{{ $page->title }}</span>
                                </div>
                            </td>
                            <td class="py-4">
                                <code class="text-xs bg-surface text-muted px-2 py-1 rounded-lg font-mono">{{ $page->slug }}</code>
                            </td>
                            <td class="py-4">
                                <span class="text-sm font-semibold text-ink">{{ $typeLabels[$page->type] ?? ucfirst($page->type) }}</span>
                            </td>
                            <td class="py-4 text-muted text-xs max-w-[200px] truncate">{{ $page->meta_title ?? '--' }}</td>
                            <td class="py-4">
                                @if($page->is_published)
                                <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-success/15 text-success">{{ __('Published') }}</span>
                                @else
                                <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-warning/15 text-warning">{{ __('Draft') }}</span>
                                @endif
                            </td>
                            <td class="py-4 text-muted text-xs">{{ $page->updated_at ? $page->updated_at->format('M j, Y') : '--' }}</td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.cms.edit', $page->id) }}" class="btn-secondary">{{ __('Edit') }}</a>
                                    <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'confirm-delete-{{ $page->id }}')">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>

                        {{-- Delete confirmation modal --}}
                        <x-admin-modal name="confirm-delete-{{ $page->id }}">
                            <form method="POST" action="{{ route('admin.cms.destroy', $page->id) }}" class="p-6 space-y-4">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <p class="text-lg font-semibold text-ink">{{ __('Delete page') }} "{{ $page->title }}"?</p>
                                    <p class="mt-2 text-sm text-muted">{{ __('This will') }} <strong class="text-danger">{{ __('permanently delete') }}</strong> {{ __('this page. This action cannot be undone.') }}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Delete Page') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center">
                                <div class="w-16 h-16 bg-brand/15 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                                </div>
                                <h3 class="text-lg font-bold text-ink mb-1">{{ __('No pages yet') }}</h3>
                                <p class="text-sm text-muted max-w-md mx-auto mb-6">{{ __('Create your first page to get started with your public-facing content.') }}</p>
                                <a href="{{ route('admin.cms.create') }}" class="btn-primary">{{ __('Create Page') }}</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($pages->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $pages->withQueryString()->links() }}
            </div>
            @endif
        </div>

        {{-- SEO Defaults --}}
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @php
                $getSetting = function($key, $default = '') {
                    static $cache = null;
                    if ($cache === null) {
                        try {
                            $cache = \Illuminate\Support\Facades\DB::table('system_settings')->pluck('value', 'key')->toArray();
                        } catch (\Exception $e) {
                            $cache = [];
                        }
                    }
                    return $cache[$key] ?? $default;
                };
            @endphp
            <div class="panel overflow-hidden">
                <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
                    <h2 class="text-lg font-bold text-ink">{{ __('Global SEO Defaults') }}</h2>
                    <p class="text-sm text-muted mt-0.5">{{ __('Default SEO meta tags applied when page-specific values are not set.') }}</p>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('Default Meta Title') }}</label>
                            <input type="text" name="seo_default_title" value="{{ $getSetting('seo_default_title', config('app.name') . ' - AI-Powered Communication Automation') }}" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('OG Image URL') }}</label>
                            <input type="url" name="seo_og_image" value="{{ $getSetting('seo_og_image', '') }}" placeholder="https://example.com/og-image.png" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-ink/80 uppercase tracking-wider mb-1.5">{{ __('Default Meta Description') }}</label>
                        <textarea rows="2" name="seo_default_description" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all resize-none">{{ $getSetting('seo_default_description', config('app.name') . ' automates email communication with AI. Manage your inbox, contacts, and campaigns with intelligent automation.') }}</textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-brand rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ __('Save SEO Defaults') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

    </div>

    {{-- Bulk Delete Modal --}}
    <x-admin-modal name="bulk-delete-pages">
        <form method="POST" action="{{ route('admin.cms.bulk-destroy') }}" class="p-6 space-y-4"
              x-data @submit="
                  const checkboxes = document.querySelectorAll('input[type=checkbox][x-model\\.number=selected]:checked');
                  checkboxes.forEach(cb => {
                      const input = document.createElement('input');
                      input.type = 'hidden'; input.name = 'ids[]'; input.value = cb.value;
                      $el.appendChild(input);
                  });
              ">
            @csrf
            <div>
                <p class="text-lg font-semibold text-ink">{{ __('Delete selected pages?') }}</p>
                <p class="mt-2 text-sm text-muted">{{ __('You are about to') }} <strong class="text-danger">{{ __('permanently delete') }}</strong> {{ __('the selected page(s). This action cannot be undone.') }}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('Delete Permanently') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
