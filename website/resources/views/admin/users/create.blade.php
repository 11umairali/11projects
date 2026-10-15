<x-layouts.admin :title="__('Create User')" :subtitle="__('Add a new user account and assign roles instantly.')">
    <div class="space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-muted border border-border rounded-xl hover:bg-surface-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('Back to Users') }}
            </a>
            <p class="text-sm text-muted">{{ __('All required fields must be completed.') }}</p>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="grid gap-6 lg:grid-cols-2">

                {{-- ── LEFT COLUMN: Personal Details ── --}}
                <div class="panel p-6">
                    <p class="panel-heading">{{ __('Personal Details') }}</p>
                    <div class="mt-4 grid gap-4">

                        {{-- Profile Photo --}}
                        <div class="flex items-center gap-4" x-data="{ preview: null, readFile(e) { var f = e.target.files[0]; if (f) { var r = new FileReader(); var self = this; r.onload = function(ev) { self.preview = ev.target.result; }; r.readAsDataURL(f); } } }">
                            <div class="h-16 w-16 overflow-hidden rounded-2xl border border-border bg-surface-2/80 shrink-0">
                                <template x-if="preview">
                                    <img :src="preview" alt="{{ __('Preview') }}" class="h-full w-full object-cover">
                                </template>
                                <template x-if="!preview">
                                    <div class="h-full w-full flex items-center justify-center text-muted">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    </div>
                                </template>
                            </div>
                            <div>
                                <label for="avatar" class="block text-sm font-medium text-ink">{{ __('Profile Photo') }}</label>
                                <input id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-1 text-sm text-muted"
                                       @change="readFile($event)">
                                <p class="mt-1 text-xs text-muted">{{ __('JPG, PNG, or WEBP. Max 5MB.') }}</p>
                                @error('avatar') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Full Name --}}
                        <div>
                            <label for="name" class="block text-sm font-medium text-ink">{{ __('Full Name') }} <span class="text-danger">*</span></label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="60"
                                   placeholder="{{ __('e.g. John Doe') }}"
                                   class="input-field mt-1">
                            @error('name') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Email --}}
                        <div>
                            <label for="email" class="block text-sm font-medium text-ink">{{ __('Email Address') }} <span class="text-danger">*</span></label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="100"
                                   placeholder="{{ __('e.g. john@example.com') }}"
                                   class="input-field mt-1">
                            @error('email') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Phone --}}
                        <div>
                            <label for="phone" class="block text-sm font-medium text-ink">{{ __('Mobile Number') }}</label>
                            <input id="phone" name="phone" type="text" value="{{ old('phone') }}" maxlength="20" inputmode="tel"
                                   placeholder="+91XXXXXXXXXX"
                                   class="input-field mt-1">
                            @error('phone') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Email Verified --}}
                        <div>
                            <label class="block text-sm font-medium text-ink">{{ __('Email Verified') }}</label>
                            <input type="hidden" name="email_verified" value="0">
                            <label class="mt-2 inline-flex items-center gap-3 text-sm text-muted cursor-pointer">
                                <input type="checkbox" name="email_verified" value="1" class="peer sr-only" @checked(old('email_verified', true))>
                                <span class="relative inline-flex h-6 w-11 items-center rounded-full bg-border transition peer-checked:bg-brand [--switch-x:0.125rem] peer-checked:[--switch-x:1.25rem]">
                                    <span class="inline-block h-5 w-5 translate-x-[var(--switch-x)] rounded-full bg-white shadow transition"></span>
                                </span>
                                <span>{{ __('Mark email as verified on creation') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- ── RIGHT COLUMN: Access & Status ── --}}
                <div class="panel p-6">
                    <p class="panel-heading">{{ __('Access & Status') }}</p>
                    <div class="mt-4 grid gap-4">

                        {{-- Roles --}}
                        <div>
                            <label class="block text-sm font-medium text-ink">{{ __('User Roles') }}</label>
                            <div class="mt-2 grid gap-2 text-sm text-muted sm:grid-cols-2">
                                @foreach($roles as $role)
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                           class="h-4 w-4 rounded border-border text-brand focus:ring-brand/40"
                                           @checked(in_array($role->name, old('roles', []), true))>
                                    {{ $role->name }}
                                </label>
                                @endforeach
                            </div>
                            @error('roles') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                            @error('roles.*') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Plan --}}
                        <div>
                            <label for="plan_id" class="block text-sm font-medium text-ink">{{ __('Plan') }}</label>
                            <select id="plan_id" name="plan_id" class="input-field mt-1">
                                <option value="">{{ __('No plan assigned') }}</option>
                                @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>{{ $plan->name }}</option>
                                @endforeach
                            </select>
                            @error('plan_id') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Password --}}
                        <div x-data="{ showPw: false }">
                            <label for="password" class="block text-sm font-medium text-ink">{{ __('Password') }} <span class="text-danger">*</span></label>
                            <div class="relative mt-1">
                                <input id="password" name="password" :type="showPw ? 'text' : 'password'" required autocomplete="new-password"
                                       placeholder="{{ __('Min 10 characters') }}"
                                       class="input-field pr-12">
                                <button type="button" @click="showPw = !showPw" class="absolute inset-y-0 right-3 flex items-center text-muted/70 hover:text-ink transition" aria-label="{{ __('Toggle password') }}">
                                    <svg x-show="!showPw" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg x-show="showPw" x-cloak viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 5l16 16"/><path d="M10.5 10.5a2.5 2.5 0 003 3"/><path d="M7.5 7.5C5 9 3 12 3 12s3.5 6 9 6c1.6 0 3.1-.3 4.4-.9"/><path d="M14.5 14.5c1.9-1.4 3.5-3.5 3.5-3.5s-1.3-2.3-3.5-3.8"/></svg>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-muted">{{ __('Must include uppercase, lowercase, number, and symbol.') }}</p>
                            @error('password') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Confirm Password --}}
                        <div x-data="{ showCpw: false }">
                            <label for="password_confirmation" class="block text-sm font-medium text-ink">{{ __('Confirm Password') }} <span class="text-danger">*</span></label>
                            <div class="relative mt-1">
                                <input id="password_confirmation" name="password_confirmation" :type="showCpw ? 'text' : 'password'" required autocomplete="new-password"
                                       placeholder="{{ __('Repeat password') }}"
                                       class="input-field pr-12">
                                <button type="button" @click="showCpw = !showCpw" class="absolute inset-y-0 right-3 flex items-center text-muted/70 hover:text-ink transition" aria-label="{{ __('Toggle password') }}">
                                    <svg x-show="!showCpw" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg x-show="showCpw" x-cloak viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 5l16 16"/><path d="M10.5 10.5a2.5 2.5 0 003 3"/><path d="M7.5 7.5C5 9 3 12 3 12s3.5 6 9 6c1.6 0 3.1-.3 4.4-.9"/><path d="M14.5 14.5c1.9-1.4 3.5-3.5 3.5-3.5s-1.3-2.3-3.5-3.8"/></svg>
                                </button>
                            </div>
                            @error('password_confirmation') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Active Status --}}
                        <div>
                            <label class="block text-sm font-medium text-ink">{{ __('Status') }}</label>
                            <input type="hidden" name="is_active" value="0">
                            <label class="mt-2 inline-flex items-center gap-3 text-sm text-muted cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" class="peer sr-only" @checked(old('is_active', true))>
                                <span class="relative inline-flex h-6 w-11 items-center rounded-full bg-border transition peer-checked:bg-brand [--switch-x:0.125rem] peer-checked:[--switch-x:1.25rem]">
                                    <span class="inline-block h-5 w-5 translate-x-[var(--switch-x)] rounded-full bg-white shadow transition"></span>
                                </span>
                                <span>{{ __('Active') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- ── FULL WIDTH: Admin Access ── --}}
                <div class="panel p-6 lg:col-span-2" x-data="{ isAdmin: {{ old('is_admin') ? 'true' : 'false' }} }">
                    <p class="panel-heading">{{ __('Admin Access') }}</p>
                    <p class="mt-1 text-sm text-muted">{{ __('Grant this user access to the admin panel.') }}</p>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div>
                            <input type="hidden" name="is_admin" value="0">
                            <label class="inline-flex items-center gap-3 text-sm text-muted cursor-pointer">
                                <input type="checkbox" name="is_admin" value="1" class="peer sr-only" x-model="isAdmin">
                                <span class="relative inline-flex h-6 w-11 items-center rounded-full bg-border transition peer-checked:bg-brand [--switch-x:0.125rem] peer-checked:[--switch-x:1.25rem]">
                                    <span class="inline-block h-5 w-5 translate-x-[var(--switch-x)] rounded-full bg-white shadow transition"></span>
                                </span>
                                <span>{{ __('Enable Admin Access') }}</span>
                            </label>
                        </div>
                        <div x-show="isAdmin" x-cloak>
                            <label for="admin_role" class="block text-sm font-medium text-ink">{{ __('Admin Role') }}</label>
                            <select id="admin_role" name="admin_role" class="input-field mt-1">
                                <option value="">{{ __('Select role...') }}</option>
                                <option value="super_admin" @selected(old('admin_role') === 'super_admin')>{{ __('Super Admin') }}</option>
                                <option value="admin" @selected(old('admin_role') === 'admin')>{{ __('Admin') }}</option>
                                <option value="moderator" @selected(old('admin_role') === 'moderator')>{{ __('Moderator') }}</option>
                                <option value="support" @selected(old('admin_role') === 'support')>{{ __('Support') }}</option>
                            </select>
                            @error('admin_role') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap items-center justify-end gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    {{ __('Save User') }}
                </button>
                <button type="submit" name="save_and_new" value="1" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-muted border border-border rounded-xl hover:bg-surface-2 transition-colors">
                    {{ __('Save & New') }}
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
