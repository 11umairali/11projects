<x-layouts.admin :title="__('Content Management')" :subtitle="__('Manage static pages, blog posts, and SEO settings.')">
    <div class="space-y-6">

        @if($pages->count() === 0)
            {{-- Empty state --}}
            <div class="panel p-12 text-center">
                <div class="w-16 h-16 bg-brand/15 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-ink mb-1">{{ __('No pages yet') }}</h3>
                <p class="text-sm text-muted max-w-md mx-auto mb-6">{{ __('Create your first page to get started with your public-facing content. Pages can be landing pages, static content (terms, privacy), or blog posts.') }}</p>
                <p class="text-xs text-muted">{{ __('Pages can be created via database seeder or a future CMS editor.') }}</p>
            </div>
        @else
            {{-- Pages grouped by type --}}
            @php
                $grouped = $pages->groupBy('type');
                $typeLabels = ['landing' => __('Landing Pages'), 'static' => __('Static Pages'), 'blog' => __('Blog Posts')];
                $typeIcons = [
                    'landing' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                    'static' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                    'blog' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
                ];
            @endphp

            @foreach($typeLabels as $typeKey => $typeLabel)
                @if(isset($grouped[$typeKey]) && $grouped[$typeKey]->count() > 0)
                <div>
                    <h2 class="text-xs font-bold text-muted uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $typeIcons[$typeKey] }}"/></svg>
                        {{ $typeLabel }}
                    </h2>
                    <div class="panel overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface border-b border-border/50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Page') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Slug') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Meta Title') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Status') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Last Updated') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    @foreach($grouped[$typeKey] as $page)
                                    <tr class="hover:bg-surface transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 bg-brand/10 rounded-lg flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $typeIcons[$typeKey] }}"/></svg>
                                                </div>
                                                <span class="font-semibold text-ink">{{ $page->title }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <code class="text-xs bg-surface text-muted px-2 py-1 rounded-lg font-mono">{{ $page->slug }}</code>
                                        </td>
                                        <td class="px-6 py-4 text-muted text-xs max-w-[200px] truncate">{{ $page->meta_title ?? '--' }}</td>
                                        <td class="px-6 py-4">
                                            @if($page->is_published)
                                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-success/15 text-success">{{ __('Published') }}</span>
                                            @else
                                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-warning/15 text-warning">{{ __('Draft') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-muted text-xs">{{ $page->updated_at ? \Carbon\Carbon::parse($page->updated_at)->format('M j, Y') : '--' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            @endforeach
        @endif

        {{-- SEO Defaults --}}
        @if(session('success')){{-- already shown above --}}@endif
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
</x-layouts.admin>
