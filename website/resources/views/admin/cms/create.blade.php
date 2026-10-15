<x-layouts.admin :title="__('Create Page')" :subtitle="__('Add a new CMS page.')">
    <div class="space-y-6" x-data="pageForm()">

        <form method="POST" action="{{ route('admin.cms.store') }}" class="space-y-6">
            @csrf

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
                            <input type="text" name="title" id="title" value="{{ old('title') }}" required aria-required="true"
                                   placeholder="{{ __('e.g. About Us') }}"
                                   @input="!slugManual && (slug = slugify($el.value))"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('title') !border-danger !ring-danger/20 @enderror">
                            @error('title') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Slug --}}
                        <div>
                            <label for="slug" class="block text-sm font-medium text-ink mb-1.5">{{ __('Slug') }} <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" name="slug" id="slug" x-model="slug" required aria-required="true"
                                   placeholder="{{ __('auto-generated-from-title') }}"
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
                                <option value="landing" @selected(old('type') === 'landing')>{{ __('Landing Page') }}</option>
                                <option value="static" @selected(old('type', 'static') === 'static')>{{ __('Static Page') }}</option>
                                <option value="blog" @selected(old('type') === 'blog')>{{ __('Blog Post') }}</option>
                            </select>
                            @error('type') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Published --}}
                        <div class="flex items-center gap-3 sm:pt-7">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="is_published" value="0">
                                <input type="checkbox" name="is_published" value="1" {{ old('is_published') ? 'checked' : '' }}
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
                    <label for="content" class="sr-only">{{ __('Content') }}</label>
                    <textarea name="content" id="content" rows="16"
                              placeholder="{{ __('Enter page content (HTML supported)...') }}"
                              class="w-full px-4 py-3 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink font-mono resize-y @error('content') !border-danger !ring-danger/20 @enderror">{{ old('content') }}</textarea>
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
                            <input type="text" name="meta_title" id="meta_title" value="{{ old('meta_title') }}"
                                   placeholder="{{ __('SEO title (defaults to page title)') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('meta_title') !border-danger !ring-danger/20 @enderror">
                            @error('meta_title') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Meta Image --}}
                        <div>
                            <label for="meta_image" class="block text-sm font-medium text-ink mb-1.5">{{ __('OG Image URL') }}</label>
                            <input type="url" name="meta_image" id="meta_image" value="{{ old('meta_image') }}"
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
                                  class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink resize-none @error('meta_description') !border-danger !ring-danger/20 @enderror">{{ old('meta_description') }}</textarea>
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
                    {{ __('Create Page') }}
                </button>
            </div>
        </form>
    </div>

    <script>
    function pageForm() {
        return {
            slug: '{{ old('slug', '') }}',
            slugManual: {{ old('slug') ? 'true' : 'false' }},
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
