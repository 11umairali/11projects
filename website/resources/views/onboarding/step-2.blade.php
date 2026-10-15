@extends('layouts.onboarding', ['currentStep' => 2])
@section('title', __('Step 2'))
@section('content')
    <div class="bg-surface-2 rounded-2xl border border-border/60 p-4 sm:p-6 shadow-sm">
        {{-- Progress Bar --}}

        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-ink">{{ __('Connect your email') }}</h2>
            <p class="mt-2 text-sm text-muted">{{ __('Link an email account so :app can read and reply to messages', ['app' => config('app.name')]) }}</p>
        </div>

        <form method="POST" action="{{ route('onboarding.store-step-2') }}"
              x-data="{
                  provider: '',
                  showCustom: false,
                  testing: false,
                  testResult: null,
                  testMessage: '',
                  async testConnection() {
                      this.testing = true;
                      this.testResult = null;
                      this.testMessage = '';

                      const form = this.$el.closest('form');
                      const data = {
                          imap_host: form.querySelector('[name=imap_host]').value,
                          imap_port: form.querySelector('[name=imap_port]').value,
                          imap_username: form.querySelector('[name=imap_username]').value,
                          imap_password: form.querySelector('[name=imap_password]').value,
                          imap_encryption: form.querySelector('[name=imap_encryption]').value,
                          smtp_host: form.querySelector('[name=smtp_host]').value,
                          smtp_port: form.querySelector('[name=smtp_port]').value,
                          smtp_username: form.querySelector('[name=smtp_username]').value,
                          smtp_password: form.querySelector('[name=smtp_password]').value,
                          smtp_encryption: form.querySelector('[name=smtp_encryption]').value,
                      };

                      // Client-side validation
                      if (!data.imap_host || !data.imap_username || !data.imap_password) {
                          this.testResult = 'error';
                          this.testMessage = 'Please fill in all required fields before testing.';
                          this.testing = false;
                          return;
                      }

                      try {
                          const response = await fetch('{{ route('onboarding.test-connection') }}', {
                              method: 'POST',
                              headers: {
                                  'Content-Type': 'application/json',
                                  'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                  'Accept': 'application/json',
                              },
                              body: JSON.stringify(data),
                          });
                          const result = await response.json();
                          this.testResult = result.success ? 'success' : 'error';
                          this.testMessage = result.message;
                      } catch (e) {
                          this.testResult = 'error';
                          this.testMessage = 'Could not reach the server. Please try again.';
                      }
                      this.testing = false;
                  }
              }"
              class="space-y-6">
            @csrf

            {{-- Provider Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- Gmail --}}
                <div class="flex flex-col">
                    <button type="button"
                            @click="provider = 'gmail'; showCustom = false"
                            :class="provider === 'gmail' ? 'border-primary-600 bg-primary-900/20 ring-1 ring-primary-600' : 'border-border hover:border-border'"
                            class="flex flex-col items-center justify-center p-6 border-2 rounded-xl transition-all duration-200 cursor-pointer">
                        <svg class="w-10 h-10 mb-3" viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        <span class="text-sm font-semibold text-ink">{{ __('Gmail') }}</span>
                        <span class="text-xs text-muted mt-1">{{ __('Sign in with Google') }}</span>
                    </button>
                    <p x-show="provider === 'gmail'" x-transition class="mt-2 text-xs text-muted text-center px-1">{{ __("You'll be redirected to Google to securely authorize :app to read and send emails on your behalf. Your password is never shared with us.", ['app' => config('app.name')]) }}</p>
                </div>

                {{-- Outlook --}}
                <div class="flex flex-col">
                    <button type="button"
                            @click="provider = 'outlook'; showCustom = false"
                            :class="provider === 'outlook' ? 'border-primary-600 bg-primary-900/20 ring-1 ring-primary-600' : 'border-border hover:border-border'"
                            class="flex flex-col items-center justify-center p-6 border-2 rounded-xl transition-all duration-200 cursor-pointer">
                        <svg class="w-10 h-10 mb-3" viewBox="0 0 24 24">
                            <rect x="1" y="1" width="10" height="10" fill="#F25022"/>
                            <rect x="13" y="1" width="10" height="10" fill="#7FBA00"/>
                            <rect x="1" y="13" width="10" height="10" fill="#00A4EF"/>
                            <rect x="13" y="13" width="10" height="10" fill="#FFB900"/>
                        </svg>
                        <span class="text-sm font-semibold text-ink">{{ __('Outlook') }}</span>
                        <span class="text-xs text-muted mt-1">{{ __('Sign in with Microsoft') }}</span>
                    </button>
                    <p x-show="provider === 'outlook'" x-transition class="mt-2 text-xs text-muted text-center px-1">{{ __("You'll be redirected to Microsoft to securely authorize :app to read and send emails on your behalf. Your password is never shared with us.", ['app' => config('app.name')]) }}</p>
                </div>

                {{-- Manual Setup --}}
                <div class="flex flex-col">
                    <button type="button"
                            @click="provider = 'custom'; showCustom = true"
                            :class="provider === 'custom' ? 'border-primary-600 bg-primary-900/20 ring-1 ring-primary-600' : 'border-border hover:border-border'"
                            class="flex flex-col items-center justify-center p-6 border-2 rounded-xl transition-all duration-200 cursor-pointer">
                        <svg class="w-10 h-10 mb-3 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="text-sm font-semibold text-ink">{{ __('Custom') }}</span>
                        <span class="text-xs text-muted mt-1">{{ __('Manual Setup') }}</span>
                    </button>
                </div>
            </div>

            <input type="hidden" name="provider" x-model="provider">

            {{-- Manual Email Setup Expanded Form --}}
            <div x-show="showCustom" x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                 class="bg-surface-2 border border-border rounded-xl p-6 space-y-6" style="display: none;">

                {{-- Help info box --}}
                <div class="flex items-start gap-3 p-4 bg-warning/10 border border-warning/20 rounded-xl">
                    <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-warning">{{ __('Not sure about these settings?') }}</p>
                        <p class="text-xs text-amber-600 mt-0.5">{{ __("Check your email provider's help page or contact their support team. Most providers list their mail server settings in their help center.") }}</p>
                    </div>
                </div>

                {{-- Account Basics --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-muted mb-1">{{ __('Email Address') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               placeholder="{{ __('you@company.com') }}">
                    </div>
                    <div>
                        <label for="display_name" class="block text-sm font-medium text-muted mb-1">{{ __('Display Name') }}</label>
                        <input id="display_name" type="text" name="display_name" value="{{ old('display_name') }}"
                               class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               placeholder="{{ __('John Doe') }}">
                    </div>
                </div>

                {{-- Help notice for manual setup --}}
                <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl text-sm text-amber-700 dark:text-amber-300">
                    <strong>{{ __('Need help?') }}</strong> {{ __('Contact your email provider for these settings, or use Gmail/Outlook above for automatic setup.') }}
                </div>

                {{-- Incoming Mail Settings --}}
                <div>
                    <h4 class="text-sm font-semibold text-ink mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        {{ __('Incoming Mail Settings') }}
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Incoming Mail Server') }}</label>
                            <input type="text" name="imap_host" value="{{ old('imap_host') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="{{ __('imap.gmail.com') }}">
                            <p class="mt-1 text-xs text-muted">{{ __('e.g., imap.gmail.com, imap.mail.yahoo.com') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Incoming Port') }}</label>
                            <input type="number" name="imap_port" value="{{ old('imap_port', '993') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <p class="mt-1 text-xs text-muted">{{ __('Usually 993 for SSL, 143 for STARTTLS') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Username') }}</label>
                            <input type="text" name="imap_username" value="{{ old('imap_username') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="{{ __('you@company.com') }}">
                            <p class="mt-1 text-xs text-muted">{{ __('Usually your full email address') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Password') }}</label>
                            <input type="password" name="imap_password"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="{{ __('App password or mail password') }}">
                            <p class="mt-1 text-xs text-muted">{{ __('Use an app password if your provider requires it') }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Security') }}</label>
                            <select name="imap_encryption"
                                    class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface">
                                <option value="ssl" {{ old('imap_encryption', 'ssl') === 'ssl' ? 'selected' : '' }}>{{ __('SSL/TLS (Recommended)') }}</option>
                                <option value="tls" {{ old('imap_encryption') === 'tls' ? 'selected' : '' }}>{{ __('STARTTLS') }}</option>
                                <option value="none" {{ old('imap_encryption') === 'none' ? 'selected' : '' }}>{{ __('None (Less Secure)') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Outgoing Mail Settings --}}
                <div>
                    <h4 class="text-sm font-semibold text-ink mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        {{ __('Outgoing Mail Settings') }}
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Outgoing Mail Server') }}</label>
                            <input type="text" name="smtp_host" value="{{ old('smtp_host') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="{{ __('smtp.gmail.com') }}">
                            <p class="mt-1 text-xs text-muted">{{ __('e.g., smtp.gmail.com, smtp.mail.yahoo.com') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Outgoing Port') }}</label>
                            <input type="number" name="smtp_port" value="{{ old('smtp_port', '465') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <p class="mt-1 text-xs text-muted">{{ __('Usually 465 for SSL, 587 for STARTTLS') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Username') }}</label>
                            <input type="text" name="smtp_username" value="{{ old('smtp_username') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="{{ __('you@company.com') }}">
                            <p class="mt-1 text-xs text-muted">{{ __('Usually the same as your incoming username') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Password') }}</label>
                            <input type="password" name="smtp_password"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="{{ __('App password or mail password') }}">
                            <p class="mt-1 text-xs text-muted">{{ __('Usually the same as your incoming password') }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Security') }}</label>
                            <select name="smtp_encryption"
                                    class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface">
                                <option value="ssl" {{ old('smtp_encryption') === 'ssl' ? 'selected' : '' }}>{{ __('SSL/TLS (Recommended)') }}</option>
                                <option value="tls" {{ old('smtp_encryption', 'tls') === 'tls' ? 'selected' : '' }}>{{ __('STARTTLS') }}</option>
                                <option value="none" {{ old('smtp_encryption') === 'none' ? 'selected' : '' }}>{{ __('None (Less Secure)') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Test Connection Button + Result --}}
                <div>
                    <button type="button"
                            @click="testConnection()"
                            :disabled="testing"
                            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium border-2 border-primary-600 text-primary-600 rounded-xl hover:bg-primary-900/20 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <template x-if="testing">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                        </template>
                        <template x-if="!testing">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </template>
                        <span x-text="testing ? 'Testing...' : 'Test Connection'"></span>
                    </button>

                    {{-- Test result feedback --}}
                    <div x-show="testResult" x-transition class="mt-3">
                        <div x-show="testResult === 'success'" class="flex items-center gap-2 text-sm text-success bg-success/10 border border-success/20 rounded-xl px-4 py-3">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span x-text="testMessage"></span>
                        </div>
                        <div x-show="testResult === 'error'" class="flex items-center gap-2 text-sm text-danger bg-danger/10 border border-danger/20 rounded-xl px-4 py-3">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span x-text="testMessage"></span>
                        </div>
                    </div>
                </div>

                {{-- Connection note --}}
                <div class="flex items-start gap-3 p-4 bg-info/10 border border-info/20 rounded-xl">
                    <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="text-sm font-medium text-blue-800">{{ __('Your credentials are saved securely') }}</p>
                        <p class="text-xs text-blue-600 mt-0.5">{{ __('We encrypt all passwords before storing them. You can test and edit settings anytime in Settings') }} &rarr; {{ __('Email Accounts.') }}</p>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-4">
                <a href="{{ route('onboarding.step-1') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-muted hover:text-ink transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ __('Back') }}
                </a>
                <div class="flex items-center gap-4">
                    <a href="{{ route('onboarding.step-3') }}" class="text-sm text-muted hover:text-ink font-medium transition-colors">{{ __('Skip for now') }}</a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 py-3 px-6 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                        {{ __('Continue') }}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
