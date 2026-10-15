@extends('layouts.onboarding', ['currentStep' => 1])
@section('title', __('Create Workspace'))
@section('step-name', __('Workspace'))

@section('content')
    <div class="bg-surface-2 rounded-2xl border border-border/60 p-6 shadow-sm">

        {{-- Header --}}
        <div class="text-center mb-5">
            <div class="w-12 h-12 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-ink">{{ __('Create your workspace') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('Set up your workspace to start automating communications') }}</p>
        </div>

        {{-- Form --}}
        <form method="POST" action="{{ route('onboarding.store-step-1') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            {{-- Workspace Name --}}
            <div>
                <label for="workspace_name" class="block text-sm font-medium text-muted mb-1">{{ __('Workspace Name') }}</label>
                <input id="workspace_name" type="text" name="workspace_name"
                       value="{{ old('workspace_name', $defaultName ?? '') }}" required
                       class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                       placeholder="{{ __('My Workspace') }}">
                @error('workspace_name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            {{-- Company Logo --}}
            <div x-data="{ fileName: '', preview: null, setFile(f) { this.fileName = f.name; const r = new FileReader(); r.onload = e => this.preview = e.target.result; r.readAsDataURL(f); } }">
                <label class="block text-sm font-medium text-muted mb-1">{{ __('Company Logo') }} <span class="text-muted">{{ __('(optional)') }}</span></label>
                <div class="relative border-2 border-dashed border-primary-300 rounded-xl p-4 text-center hover:border-primary-500 transition-colors cursor-pointer"
                     @dragover.prevent @drop.prevent="setFile($event.dataTransfer.files[0])" @click="$refs.fileInput.click()">
                    <template x-if="preview">
                        <div class="flex items-center justify-center gap-3">
                            <img :src="preview" class="w-10 h-10 object-cover rounded-lg" alt="{{ __('Logo preview') }}">
                            <span class="text-sm text-muted" x-text="fileName"></span>
                            <button type="button" @click.stop="preview=null;fileName='';$refs.fileInput.value=''" class="text-xs text-red-500 hover:text-danger font-medium">{{ __('Remove') }}</button>
                        </div>
                    </template>
                    <template x-if="!preview">
                        <p class="text-sm text-muted"><span class="text-primary-600 font-semibold">{{ __('Click to upload') }}</span> {{ __('or drag and drop') }} &middot; {{ __('PNG, JPG, SVG up to 2MB') }}</p>
                    </template>
                    <input type="file" name="logo" x-ref="fileInput" accept="image/*" class="hidden" @change="setFile($event.target.files[0])">
                </div>
                @error('logo') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            {{-- Industry --}}
            <div>
                <label for="industry" class="block text-sm font-medium text-muted mb-1">{{ __('Industry') }}</label>
                <select id="industry" name="industry" required
                        class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">
                    <option value="" disabled {{ old('industry') ? '' : 'selected' }}>{{ __('Select your industry') }}</option>
                    @foreach(['saas' => __('SaaS / Software'), 'ecommerce' => __('E-commerce / Retail'), 'finance' => __('Finance / Banking'), 'healthcare' => __('Healthcare'), 'education' => __('Education'), 'real-estate' => __('Real Estate'), 'agency' => __('Agency / Consulting'), 'travel' => __('Travel / Hospitality'), 'nonprofit' => __('Non-profit'), 'other' => __('Other')] as $val => $lbl)
                    <option value="{{ $val }}" {{ old('industry') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
                @error('industry') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            {{-- Team Size --}}
            <div>
                <label class="block text-sm font-medium text-muted mb-2">{{ __('Team Size') }}</label>
                <div class="grid grid-cols-4 gap-2" x-data="{ selected: '{{ old('team_size', '1') }}' }">
                    @foreach(['1' => __('Just me'), '2-5' => __('2 - 5'), '6-20' => __('6 - 20'), '20+' => __('20+')] as $value => $label)
                    <label class="relative cursor-pointer">
                        <input type="radio" name="team_size" value="{{ $value }}" class="peer sr-only" x-model="selected" {{ old('team_size', '1') === $value ? 'checked' : '' }}>
                        <div class="flex flex-col items-center justify-center py-2.5 border-2 rounded-xl transition-all peer-checked:border-primary-600 peer-checked:bg-primary-900/20 border-border hover:border-border">
                            <span class="text-sm font-medium" :class="selected === '{{ $value }}' ? 'text-primary-700' : 'text-ink/80'">{{ $label }}</span>
                        </div>
                    </label>
                    @endforeach
                </div>
                @error('team_size') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            {{-- Submit --}}
            <div class="pt-2">
                <button type="submit" class="w-full py-2.5 px-6 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors flex items-center justify-center gap-2">
                    {{ __('Continue') }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </form>
    </div>
@endsection
