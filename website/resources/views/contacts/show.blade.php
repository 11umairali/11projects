<x-layouts.app title="Contact Details">
    <div class="space-y-6">
        {{-- Back navigation --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('contacts') }}" class="text-sm text-brand hover:text-brand/80 flex items-center gap-1" wire:navigate>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ __('Back to Contacts') }}
            </a>
        </div>

        {{-- Contact header --}}
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-primary-500 rounded-full flex items-center justify-center text-white text-lg font-semibold flex-shrink-0">
                {{ $contact->initials }}
            </div>
            <div>
                <h1 class="text-2xl font-bold text-ink">{{ $contact->full_name }}</h1>
                <p class="text-sm text-muted">{{ $contact->email }}</p>
            </div>
        </div>

        {{-- Contact details + Timeline side-by-side --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Contact info --}}
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-surface-2 rounded-2xl border border-border p-5 space-y-3">
                    <h3 class="text-sm font-semibold text-ink">{{ __('Details') }}</h3>

                    @if($contact->phone)
                    <div>
                        <p class="text-xs text-muted">{{ __('Phone') }}</p>
                        <p class="text-sm text-ink">{{ $contact->phone }}</p>
                    </div>
                    @endif

                    @if($contact->company)
                    <div>
                        <p class="text-xs text-muted">{{ __('Company') }}</p>
                        <p class="text-sm text-ink">{{ $contact->company }}</p>
                    </div>
                    @endif

                    @if($contact->job_title)
                    <div>
                        <p class="text-xs text-muted">{{ __('Job Title') }}</p>
                        <p class="text-sm text-ink">{{ $contact->job_title }}</p>
                    </div>
                    @endif

                    @if($contact->city || $contact->country)
                    <div>
                        <p class="text-xs text-muted">{{ __('Location') }}</p>
                        <p class="text-sm text-ink">{{ implode(', ', array_filter([$contact->city, $contact->country])) }}</p>
                    </div>
                    @endif

                    @if($contact->timezone)
                    <div>
                        <p class="text-xs text-muted">{{ __('Timezone') }}</p>
                        <p class="text-sm text-ink">{{ $contact->timezone }}</p>
                    </div>
                    @endif

                    <div>
                        <p class="text-xs text-muted">{{ __('Engagement Score') }}</p>
                        <p class="text-sm text-ink font-semibold">{{ $contact->lead_score ?? 0 }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-muted">{{ __('Status') }}</p>
                        <span @class([
                            'inline-block text-xs font-medium px-2 py-0.5 rounded-full',
                            'bg-success/15 text-success' => ($contact->status ?? 'active') === 'active',
                            'bg-danger/15 text-danger' => ($contact->status ?? 'active') === 'unsubscribed',
                        ])>{{ ucfirst($contact->status ?? 'active') }}</span>
                    </div>

                    @if($contact->last_contacted_at)
                    <div>
                        <p class="text-xs text-muted">{{ __('Last Contacted') }}</p>
                        <p class="text-sm text-ink" title="{{ $contact->last_contacted_at->format('M j, Y g:i A') }}">{{ $contact->last_contacted_at->diffForHumans() }}</p>
                    </div>
                    @endif
                </div>

                {{-- Tags --}}
                @if($contact->tags->count() > 0)
                <div class="bg-surface-2 rounded-2xl border border-border p-5">
                    <h3 class="text-sm font-semibold text-ink mb-3">{{ __('Tags') }}</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($contact->tags as $tag)
                        <span class="px-2 py-0.5 rounded-md text-xs font-medium bg-surface text-muted" style="{{ $tag->color ? 'background-color:' . $tag->color . '20; color:' . $tag->color : '' }}">
                            {{ $tag->name }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- Activity timeline --}}
            <div class="lg:col-span-2">
                <div class="bg-surface-2 rounded-2xl border border-border p-5">
                    <livewire:contacts.contact-timeline :contactId="$contact->id" />
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
