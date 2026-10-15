@extends('layouts.onboarding', ['currentStep' => 5])
@section('title', __('Step 5'))
@section('content')
    <div class="bg-surface-2 rounded-2xl border border-border/60 p-4 sm:p-6 shadow-sm">
        {{-- Progress Bar --}}

        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-ink">{{ __('Invite your team') }}</h2>
            <p class="mt-2 text-sm text-muted">{{ __('Add team members to collaborate on customer communication') }}</p>
        </div>

        <form method="POST" action="{{ route('onboarding.store-step-5') }}"
              x-data="{
                  invites: [{ email: '', role: 'agent', error: '' }],
                  emailPattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                  addInvite() {
                      this.invites.push({ email: '', role: 'agent', error: '' });
                  },
                  removeInvite(index) {
                      if (this.invites.length > 1) {
                          this.invites.splice(index, 1);
                      }
                  },
                  validateEmail(index) {
                      const invite = this.invites[index];
                      if (invite.email === '') {
                          invite.error = '';
                      } else if (!this.emailPattern.test(invite.email)) {
                          invite.error = 'Invalid email address';
                      } else {
                          invite.error = '';
                      }
                  }
              }"
              class="space-y-6">
            @csrf

            {{-- Invite Rows --}}
            <div class="space-y-3">
                <label class="block text-sm font-medium text-ink/80">{{ __('Team Members') }}</label>
                <template x-for="(invite, index) in invites" :key="index">
                    <div class="flex items-center gap-3">
                        {{-- Email --}}
                        <div class="flex-1">
                            <input type="email" :name="'invites[' + index + '][email]'" x-model="invite.email"
                                   @blur="validateEmail(index)"
                                   :class="invite.error ? 'border-red-400 focus:ring-red-500' : 'border-border focus:ring-primary-500'"
                                   class="w-full px-4 py-2.5 text-sm border rounded-xl focus:outline-none focus:ring-2 focus:border-transparent"
                                   placeholder="colleague@company.com">
                            <p x-show="invite.error" x-text="invite.error" class="mt-1 text-xs text-danger"></p>
                        </div>

                        {{-- Role --}}
                        <div class="w-36">
                            <select :name="'invites[' + index + '][role]'" x-model="invite.role"
                                    class="w-full px-3 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">
                                <option value="admin">{{ __('Admin') }}</option>
                                <option value="agent">{{ __('Agent') }}</option>
                                <option value="viewer">{{ __('Viewer') }}</option>
                            </select>
                        </div>

                        {{-- Remove --}}
                        <button type="button" @click="removeInvite(index)"
                                class="p-2 text-muted hover:text-red-500 transition-colors rounded-lg hover:bg-danger/10"
                                :class="invites.length === 1 ? 'opacity-30 pointer-events-none' : ''">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </template>

                {{-- Add another --}}
                <button type="button" @click="addInvite()"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    {{ __('Add another') }}
                </button>
            </div>

            {{-- Role Explanation Cards --}}
            <div>
                <h4 class="text-sm font-medium text-ink/80 mb-3">{{ __('Role Permissions') }}</h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    {{-- Admin --}}
                    <div class="p-4 bg-brand/10 border border-purple-200 rounded-xl">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 bg-brand/15 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <h5 class="text-sm font-semibold text-purple-900">{{ __('Admin') }}</h5>
                        </div>
                        <ul class="space-y-1 text-xs text-brand">
                            <li class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ __('Manage team & settings') }}
                            </li>
                            <li class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ __('Configure AI & rules') }}
                            </li>
                            <li class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ __('View analytics & billing') }}
                            </li>
                        </ul>
                    </div>

                    {{-- Agent --}}
                    <div class="p-4 bg-info/10 border border-info/20 rounded-xl">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 bg-info/15 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <h5 class="text-sm font-semibold text-blue-900">{{ __('Agent') }}</h5>
                        </div>
                        <ul class="space-y-1 text-xs text-info">
                            <li class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ __('Handle conversations') }}
                            </li>
                            <li class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ __('Manage contacts & deals') }}
                            </li>
                            <li class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ __('Use AI suggestions') }}
                            </li>
                        </ul>
                    </div>

                    {{-- Viewer --}}
                    <div class="p-4 bg-surface border border-border rounded-xl">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 bg-surface rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </div>
                            <h5 class="text-sm font-semibold text-ink">{{ __('Viewer') }}</h5>
                        </div>
                        <ul class="space-y-1 text-xs text-muted">
                            <li class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ __('View conversations') }}
                            </li>
                            <li class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ __('View analytics reports') }}
                            </li>
                            <li class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 flex-shrink-0 text-muted" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                <span class="text-muted">{{ __('Cannot send messages') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-4">
                <a href="{{ route('onboarding.step-4') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-muted hover:text-ink transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ __('Back') }}
                </a>
                <div class="flex items-center gap-4">
                    <a href="{{ route('onboarding.complete') }}" class="text-sm text-muted hover:text-ink/80 font-medium transition-colors">{{ __('Skip for now') }}</a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 py-3 px-6 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        {{ __('Send Invites & Finish') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
