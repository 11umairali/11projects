<x-layouts.admin :title="__('Edit Page')" :subtitle="__('Update page:') . ' ' . $page->title">
    <div class="space-y-6" x-data="pageForm()">

        <form method="POST" action="{{ route('admin.cms.update', $page->id) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Page Details --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Page Details') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Basic information about this page.') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Title --}}
                        <div>
                            <label for="title" class="block text-sm font-medium text-ink mb-1.5">{{ __('Title') }} <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" name="title" id="title" value="{{ old('title', $page->title) }}" required aria-required="true"
                                   placeholder="{{ __('e.g. About Us') }}"
                                   @input="!slugManual && (slug = slugify($el.value))"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('title') !border-danger !ring-danger/20 @enderror">
                            @error('title') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Slug --}}
                        <div>
                            <label for="slug" class="block text-sm font-medium text-ink mb-1.5">{{ __('Slug') }} <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" name="slug" id="slug" x-model="slug" required aria-required="true"
                                   placeholder="{{ __('url-friendly-slug') }}"
                                   @input="slugManual = true"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink font-mono @error('slug') !border-danger !ring-danger/20 @enderror">
                            <p class="text-xs text-muted mt-1">{{ __('URL-friendly identifier. Auto-generated from title if left unchanged.') }}</p>
                            @error('slug') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Type --}}
                        <div>
                            <label for="type" class="block text-sm font-medium text-ink mb-1.5">{{ __('Type') }} <span class="text-danger" aria-hidden="true">*</span></label>
                            <select name="type" id="type" required aria-required="true"
                                    class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('type') !border-danger !ring-danger/20 @enderror">
                                <option value="landing" @selected(old('type', $page->type) === 'landing')>{{ __('Landing Page') }}</option>
                                <option value="static" @selected(old('type', $page->type) === 'static')>{{ __('Static Page') }}</option>
                                <option value="blog" @selected(old('type', $page->type) === 'blog')>{{ __('Blog Post') }}</option>
                            </select>
                            @error('type') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Published --}}
                        <div class="flex items-center gap-3 sm:pt-7">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="is_published" value="0">
                                <input type="checkbox" name="is_published" value="1" {{ old('is_published', $page->is_published) ? 'checked' : '' }}
                                       class="sr-only peer">
                                <div class="w-10 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-surface-2 after:border-border after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand"></div>
                            </label>
                            <span class="text-sm font-medium text-ink">{{ __('Published') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Content --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Content') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Page body content. Supports HTML markup.') }}</p>
                </div>
                <div class="p-6">
                    @php
                        // Page::$casts declares content as `array`, so landing-page
                        // rows return a PHP array of structured sections instead
                        // of a plain HTML string. We detect that here and surface
                        // it in the UI: show a blue banner explaining what's on
                        // screen, and offer a "Flatten to HTML" action that the
                        // user can opt into if they want to edit as plain text.
                        $rawContent = $page->content;
                        $isStructured = is_array($rawContent);
                        $contentForEditor = $isStructured
                            ? json_encode($rawContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                            : (string) ($rawContent ?? '');

                        // Build an HTML preview from the array structure so the
                        // "Flatten to HTML" button has something sensible to drop
                        // into the textarea. Walks known section shapes: title,
                        // subtitle, desc, reasons[], items[], features[], etc.
                        $flattenHtml = '';
                        if ($isStructured) {
                            $walk = function ($node) use (&$walk) {
                                if (is_string($node)) {
                                    return '<p>' . e($node) . '</p>' . "\n";
                                }
                                if (! is_array($node)) {
                                    return '';
                                }
                                $out = '';
                                if (! empty($node['title'])) {
                                    $out .= '<h2>' . e($node['title']) . '</h2>' . "\n";
                                }
                                if (! empty($node['subtitle'])) {
                                    $out .= '<h3>' . e($node['subtitle']) . '</h3>' . "\n";
                                }
                                foreach (['desc', 'description', 'body', 'text', 'content'] as $k) {
                                    if (! empty($node[$k]) && is_string($node[$k])) {
                                        $out .= '<p>' . e($node[$k]) . '</p>' . "\n";
                                    }
                                }
                                foreach (['reasons', 'items', 'features', 'sections', 'cards'] as $listKey) {
                                    if (! empty($node[$listKey]) && is_array($node[$listKey])) {
                                        $out .= "\n";
                                        foreach ($node[$listKey] as $item) {
                                            $out .= $walk($item);
                                        }
                                    }
                                }
                                return $out;
                            };
                            $flattenHtml = trim($walk($rawContent));
                        }
                    @endphp

                    @if($isStructured)
                    <div x-data="{ mode: 'json', flatten: @js($flattenHtml) }" class="mb-3">
                        <div class="rounded-xl border border-info/30 bg-info/10 px-4 py-3 flex items-start gap-3">
                            <svg class="w-5 h-5 text-info shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><line x1="12" y1="8" x2="12" y2="12" stroke-width="2"/><line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2"/></svg>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-ink">{{ __('This page uses structured sections') }}</p>
                                <p class="text-xs text-muted mt-0.5">{{ __('The content below is stored as JSON so your landing page can render it in sections (hero, reasons, features, etc.). Edit the JSON keys/values to change individual sections, or convert to plain HTML if you prefer a simple rich-text page.') }}</p>
                                <div class="flex gap-2 mt-3">
                                    <button type="button"
                                            x-on:click="mode = 'json'; document.getElementById('content').value = @js($contentForEditor)"
                                            :class="mode === 'json' ? 'bg-brand text-white' : 'bg-surface-2 text-muted hover:text-ink border border-border'"
                                            class="px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                        {{ __('Structured JSON (keeps sections)') }}
                                    </button>
                                    <button type="button"
                                            x-on:click="if(confirm('{{ __('Switch to plain HTML will flatten section structure. Your landing page layout may change. Continue?') }}')) { mode = 'html'; document.getElementById('content').value = flatten; }"
                                            :class="mode === 'html' ? 'bg-brand text-white' : 'bg-surface-2 text-muted hover:text-ink border border-border'"
                                            class="px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                        {{ __('Flatten to plain HTML') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <label for="content" class="sr-only">{{ __('Content') }}</label>
                    <textarea name="content" id="content" rows="16"
                              placeholder="{{ __('Enter page content (HTML supported)...') }}"
                              class="w-full px-4 py-3 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink font-mono resize-y @error('content') !border-danger !ring-danger/20 @enderror">{{ old('content', $contentForEditor) }}</textarea>
                    @error('content') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- SEO / Meta --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('SEO & Meta') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Search engine optimization settings for this page.') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Meta Title --}}
                        <div>
                            <label for="meta_title" class="block text-sm font-medium text-ink mb-1.5">{{ __('Meta Title') }}</label>
                            <input type="text" name="meta_title" id="meta_title" value="{{ old('meta_title', $page->meta_title) }}"
                                   placeholder="{{ __('SEO title (defaults to page title)') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('meta_title') !border-danger !ring-danger/20 @enderror">
                            @error('meta_title') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Meta Image --}}
                        <div>
                            <label for="meta_image" class="block text-sm font-medium text-ink mb-1.5">{{ __('OG Image URL') }}</label>
                            <input type="url" name="meta_image" id="meta_image" value="{{ old('meta_image', $page->meta_image) }}"
                                   placeholder="https://example.com/og-image.png"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('meta_image') !border-danger !ring-danger/20 @enderror">
                            @error('meta_image') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Meta Description --}}
                    <div>
                        <label for="meta_description" class="block text-sm font-medium text-ink mb-1.5">{{ __('Meta Description') }}</label>
                        <textarea name="meta_description" id="meta_description" rows="3"
                                  placeholder="{{ __('Brief description for search engines (max 500 characters)') }}"
                                  class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink resize-none @error('meta_description') !border-danger !ring-danger/20 @enderror">{{ old('meta_description', $page->meta_description) }}</textarea>
                        @error('meta_description') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between">
                <a href="{{ route('admin.cms.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-muted border border-border rounded-xl hover:bg-surface transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    {{ __('Back to Pages') }}
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Update Page') }}
                </button>
            </div>
        </form>
    </div>

    <script>
    function pageForm() {
        return {
            slug: '{{ old('slug', $page->slug) }}',
            slugManual: true,
            slugify(text) {
                return text.toString().toLowerCase().trim()
                    .replace(/\s+/g, '-')
                    .replace(/[^\w\-]+/g, '')
                    .replace(/\-\-+/g, '-')
                    .replace(/^-+/, '')
                    .replace(/-+$/, '');
            }
        };
    }
    </script>
</x-layouts.admin>
