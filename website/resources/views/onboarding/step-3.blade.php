@extends('layouts.onboarding', ['currentStep' => 3])
@section('title', __('Step 3'))
@section('content')
    <div class="bg-surface-2 rounded-2xl border border-border/60 p-4 sm:p-6 shadow-sm">
        {{-- Progress Bar --}}

        {{-- Header --}}
        <div class="text-center mb-4">
            <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center mx-auto mb-2">
                <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-ink">{{ __('Train your AI assistant') }}</h2>
            <p class="mt-2 text-sm text-muted">{{ __('Give :app knowledge about your business so it can reply accurately', ['app' => config('app.name')]) }}</p>
        </div>

        <form method="POST" action="{{ route('onboarding.store-step-3') }}" enctype="multipart/form-data"
              x-data="{
                  sections: { documents: true, website: false, qa: false },
                  documents: [],
                  uploading: false,
                  fileError: '',
                  maxFileSizeMB: 10,
                  allowedTypes: ['pdf', 'docx', 'doc', 'txt', 'csv'],
                  websiteUrl: '',
                  qaPairs: [{ question: '', answer: '' }],
                  personality: '{{ old('personality', 'professional') }}',
                  customPrompt: '',
                  addQA() { this.qaPairs.push({ question: '', answer: '' }); },
                  removeQA(index) { if (this.qaPairs.length > 1) this.qaPairs.splice(index, 1); },
                  submitting: false,
                  uploadProgress: 0,
                  validateFiles(files) {
                      this.fileError = '';
                      const maxBytes = this.maxFileSizeMB * 1024 * 1024;
                      const valid = [];
                      for (let f of files) {
                          const ext = f.name.split('.').pop().toLowerCase();
                          if (!this.allowedTypes.includes(ext)) {
                              this.fileError = f.name + ' has an unsupported file type. Accepted: ' + this.allowedTypes.join(', ').toUpperCase() + '.';
                              return [];
                          }
                          if (f.size > maxBytes) {
                              this.fileError = f.name + ' exceeds the ' + this.maxFileSizeMB + 'MB size limit. Please choose a smaller file.';
                              return [];
                          }
                          valid.push(f);
                      }
                      return valid;
                  },
                  handleDocDrop(e) {
                      const valid = this.validateFiles(e.dataTransfer.files);
                      if (valid.length === 0) return;
                      this.uploading = true;
                      for (let f of valid) { this.documents.push(f.name); }
                      setTimeout(() => this.uploading = false, 500);
                  }
              }"
              @submit="submitting = true"
              class="space-y-6">
            @csrf

            {{-- Knowledge Sources --}}

            {{-- Section 1: Upload Documents --}}
            <div class="border border-border rounded-xl overflow-hidden">
                <button type="button" @click="sections.documents = !sections.documents"
                        class="w-full flex items-center justify-between p-4 bg-surface-2 hover:bg-surface transition-colors text-left">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-info/15 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-ink">{{ __('Upload Documents') }}</h3>
                            <p class="text-xs text-muted">{{ __('PDFs, DOCX, TXT, or CSV files') }}</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-muted transition-transform" :class="sections.documents ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sections.documents" x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     class="border-t border-border p-4">
                    <div class="border-2 border-dashed border-border rounded-xl p-8 text-center hover:border-primary-400 transition-colors cursor-pointer"
                         @dragover.prevent="$el.classList.add('border-primary-500', 'bg-primary-900/20')"
                         @dragleave.prevent="$el.classList.remove('border-primary-500', 'bg-primary-900/20')"
                         @drop.prevent="$el.classList.remove('border-primary-500', 'bg-primary-900/20'); handleDocDrop($event)"
                         @click="$refs.docInput.click()">
                        <svg class="w-10 h-10 mx-auto text-muted mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-sm text-muted"><span class="text-primary-600 font-semibold">{{ __('Click to upload') }}</span> {{ __('or drag and drop') }}</p>
                        <p class="text-xs text-muted mt-1">{{ __('Accepted: PDF, DOCX, DOC, TXT, CSV -- max 10MB per file') }}</p>
                        <input type="file" name="documents[]" x-ref="docInput" multiple
                               accept=".pdf,.docx,.doc,.txt,.csv" class="hidden"
                               @change="
                                   const valid = validateFiles($event.target.files);
                                   if (valid.length === 0) { $event.target.value = ''; return; }
                                   uploading = true;
                                   for (let f of valid) { documents.push(f.name); }
                                   setTimeout(() => uploading = false, 500);
                               ">
                    </div>
                    {{-- File validation error --}}
                    <div x-show="fileError" x-transition class="mt-2" style="display: none;">
                        <div class="flex items-center gap-2 text-sm text-danger bg-danger/10 border border-danger/20 rounded-xl px-4 py-3">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <span x-text="fileError"></span>
                        </div>
                    </div>
                    {{-- Upload progress indicator --}}
                    <div x-show="uploading" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="mt-2" style="display: none;">
                        <div class="flex items-center gap-2 text-sm text-primary-600">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            {{ __('Processing files...') }}
                        </div>
                    </div>
                    {{-- File list --}}
                    <template x-if="documents.length > 0">
                        <div class="mt-3 space-y-2">
                            <template x-for="(doc, i) in documents" :key="i">
                                <div class="flex items-center justify-between py-2 px-3 bg-surface rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span class="text-sm text-ink/80" x-text="doc"></span>
                                    </div>
                                    <button type="button" @click="documents.splice(i, 1)" class="text-muted hover:text-red-500">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Section 2: Scrape Website --}}
            <div class="border border-border rounded-xl overflow-hidden">
                <button type="button" @click="sections.website = !sections.website"
                        class="w-full flex items-center justify-between p-4 bg-surface-2 hover:bg-surface transition-colors text-left">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-success/15 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-ink">{{ __('Scrape Website') }}</h3>
                            <p class="text-xs text-muted">{{ __('Import content from your website') }}</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-muted transition-transform" :class="sections.website ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sections.website" x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     class="border-t border-border p-4 space-y-3" style="display: none;">
                    <div>
                        <label class="block text-xs font-medium text-muted mb-1">{{ __('Website URL') }}</label>
                        <input type="url" name="website_url" x-model="websiteUrl"
                               class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               placeholder="{{ __('https://yourcompany.com') }}">
                    </div>
                    <div class="flex items-start gap-3 p-3.5 bg-warning/10 border border-warning/20 rounded-xl">
                        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-warning">{{ __('Website will be scanned after you complete setup') }}</p>
                            <p class="text-xs text-amber-600 mt-0.5">{{ __('Processing happens in the background. You can check progress in the Knowledge Base section.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Section 3: Q&A Pairs --}}
            <div class="border border-border rounded-xl overflow-hidden">
                <button type="button" @click="sections.qa = !sections.qa"
                        class="w-full flex items-center justify-between p-4 bg-surface-2 hover:bg-surface transition-colors text-left">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-brand/15 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-ink">{{ __('Q&A Pairs') }}</h3>
                            <p class="text-xs text-muted">{{ __('Manually add common questions and answers') }}</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-muted transition-transform" :class="sections.qa ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="sections.qa" x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     class="border-t border-border p-4 space-y-4" style="display: none;">
                    <template x-for="(pair, index) in qaPairs" :key="index">
                        <div class="relative bg-surface rounded-xl p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-muted uppercase tracking-wider" x-text="'Pair ' + (index + 1)"></span>
                                <button type="button" x-show="qaPairs.length > 1" @click="removeQA(index)"
                                        class="text-muted hover:text-red-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-muted mb-1">{{ __('Question') }}</label>
                                <input type="text" :name="'qa[' + index + '][question]'" x-model="pair.question"
                                       class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                       placeholder="{{ __('e.g., What are your business hours?') }}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-muted mb-1">{{ __('Answer') }}</label>
                                <textarea :name="'qa[' + index + '][answer]'" x-model="pair.answer" rows="2"
                                          class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                                          placeholder="{{ __("e.g., We're open Monday to Friday, 9 AM to 5 PM EST.") }}"></textarea>
                            </div>
                        </div>
                    </template>
                    <button type="button" @click="addQA()"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        {{ __('Add another pair') }}
                    </button>
                </div>
            </div>

            {{-- AI Personality --}}
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-3">{{ __('AI Personality') }}</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @php
                    $personalities = [
                        'professional' => ['icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'label' => __('Professional'), 'desc' => __('Formal & polished')],
                        'friendly' => ['icon' => 'M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => __('Friendly'), 'desc' => __('Warm & approachable')],
                        'casual' => ['icon' => 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z', 'label' => __('Casual'), 'desc' => __('Relaxed & natural')],
                        'sales' => ['icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', 'label' => __('Sales'), 'desc' => __('Persuasive & driven')],
                        'support' => ['icon' => 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z', 'label' => __('Support'), 'desc' => __('Helpful & empathetic')],
                        'custom' => ['icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'label' => __('Custom'), 'desc' => __('Write your own')],
                    ];
                    @endphp

                    @foreach($personalities as $value => $data)
                        <label class="cursor-pointer">
                            <input type="radio" name="personality" value="{{ $value }}" class="peer sr-only"
                                   x-model="personality" {{ old('personality', 'professional') === $value ? 'checked' : '' }}>
                            <div class="flex flex-col items-center p-4 border-2 rounded-xl transition-all duration-200 peer-checked:border-primary-600 peer-checked:bg-primary-900/20 peer-checked:ring-1 peer-checked:ring-primary-600 border-border hover:border-border">
                                <svg class="w-6 h-6 mb-2 peer-checked:text-primary-600 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                     :class="personality === '{{ $value }}' ? 'text-primary-600' : 'text-muted'">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $data['icon'] }}"/>
                                </svg>
                                <span class="text-sm font-medium text-ink">{{ $data['label'] }}</span>
                                <span class="text-xs text-muted">{{ $data['desc'] }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Custom Personality Textarea --}}
            <div x-show="personality === 'custom'" x-transition class="space-y-2" style="display: none;">
                <label for="custom_prompt" class="block text-sm font-medium text-ink/80">{{ __('Custom AI Personality') }}</label>
                <textarea id="custom_prompt" name="custom_prompt" rows="4" x-model="customPrompt"
                          class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                          placeholder="{{ __("Describe how you want the AI to communicate. e.g., 'Reply like a friendly tech expert who uses analogies to explain complex concepts...'") }}"></textarea>
            </div>

            {{-- Default Language --}}
            <div>
                <label for="language" class="block text-sm font-medium text-ink/80 mb-1">{{ __('Default Reply Language') }}</label>
                <select id="language" name="language"
                        class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">
                    <option value="en" {{ old('language', 'en') === 'en' ? 'selected' : '' }}>{{ __('English') }}</option>
                    <option value="es" {{ old('language') === 'es' ? 'selected' : '' }}>{{ __('Spanish') }}</option>
                    <option value="fr" {{ old('language') === 'fr' ? 'selected' : '' }}>{{ __('French') }}</option>
                    <option value="de" {{ old('language') === 'de' ? 'selected' : '' }}>{{ __('German') }}</option>
                    <option value="pt" {{ old('language') === 'pt' ? 'selected' : '' }}>{{ __('Portuguese') }}</option>
                    <option value="ja" {{ old('language') === 'ja' ? 'selected' : '' }}>{{ __('Japanese') }}</option>
                    <option value="zh" {{ old('language') === 'zh' ? 'selected' : '' }}>{{ __('Chinese') }}</option>
                    <option value="hi" {{ old('language') === 'hi' ? 'selected' : '' }}>{{ __('Hindi') }}</option>
                    <option value="ar" {{ old('language') === 'ar' ? 'selected' : '' }}>{{ __('Arabic') }}</option>
                    <option value="auto" {{ old('language') === 'auto' ? 'selected' : '' }}>{{ __("Auto-detect (match sender's language)") }}</option>
                </select>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-4">
                <a href="{{ route('onboarding.step-2') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-muted hover:text-ink transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ __('Back') }}
                </a>
                <div class="flex items-center gap-4">
                    <a href="{{ route('onboarding.step-4') }}" class="text-sm text-muted hover:text-ink/80 font-medium transition-colors">{{ __('Skip for now') }}</a>
                    <button type="submit" :disabled="submitting"
                            class="inline-flex items-center gap-2 py-3 px-6 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                        <template x-if="submitting">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </template>
                        <span x-text="submitting ? 'Uploading...' : 'Continue'"></span>
                        <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
