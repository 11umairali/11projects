<x-layouts.admin :title="__('Ticket') . ' #' . $ticket->id" :subtitle="Str::limit($ticket->subject, 60)">
    <div class="space-y-6">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main content (conversation) --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Ticket header --}}
                <div class="panel p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-xs font-bold text-muted bg-surface px-2 py-1 rounded-lg">#{{ $ticket->id }}</span>
                                {{-- Status badge --}}
                                @switch($ticket->status)
                                    @case('open')
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-info/15 text-info">{{ __('Open') }}</span>
                                        @break
                                    @case('in_progress')
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-warning/15 text-warning">{{ __('In Progress') }}</span>
                                        @break
                                    @case('waiting')
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-orange-100 text-orange-700">{{ __('Waiting') }}</span>
                                        @break
                                    @case('resolved')
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-success/15 text-success">{{ __('Resolved') }}</span>
                                        @break
                                    @case('closed')
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-surface text-ink/80">{{ __('Closed') }}</span>
                                        @break
                                    @default
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-surface text-ink/80">{{ ucfirst($ticket->status) }}</span>
                                @endswitch
                                {{-- Priority badge --}}
                                @switch($ticket->priority)
                                    @case('low')
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-info/15 text-info">{{ __('Low Priority') }}</span>
                                        @break
                                    @case('medium')
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-warning/15 text-warning">{{ __('Medium Priority') }}</span>
                                        @break
                                    @case('high')
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-danger/15 text-danger">{{ __('High Priority') }}</span>
                                        @break
                                    @case('urgent')
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-red-200 text-red-800 animate-pulse">{{ __('Urgent') }}</span>
                                        @break
                                @endswitch
                            </div>
                            <p class="text-base font-semibold text-ink">{{ $ticket->subject }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-muted">
                        @if($ticket->category)
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            {{ ucfirst($ticket->category) }}
                        </span>
                        @endif
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ __('Created:') }} {{ $ticket->created_at->format('M j, Y \\a\\t g:i A') }}
                        </span>
                        @if($ticket->updated_at->ne($ticket->created_at))
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('Updated:') }} <span title="{{ $ticket->updated_at->format('M j, Y g:i A') }}">{{ $ticket->updated_at->diffForHumans() }}</span>
                        </span>
                        @endif
                    </div>
                </div>

                {{-- Original ticket body --}}
                <div class="panel p-5">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-9 h-9 bg-info/15 text-info rounded-full flex items-center justify-center text-xs font-bold">
                            {{ $ticket->user?->initials ?? '??' }}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ $ticket->user?->name ?? __('Unknown User') }}</p>
                            <p class="text-[10px] text-muted">{{ $ticket->created_at->format('M j, Y \\a\\t g:i A') }}</p>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-info/15 text-info rounded-full ml-auto">{{ __('Customer') }}</span>
                    </div>
                    <div class="text-sm text-ink/80 leading-relaxed prose prose-sm max-w-none">
                        {!! nl2br(e($ticket->body)) !!}
                    </div>
                </div>

                {{-- Conversation thread (replies) --}}
                @if($ticket->ticketReplies->count())
                <div class="space-y-4">
                    @foreach($ticket->ticketReplies as $reply)
                    <div class="{{ $reply->is_admin_reply ? 'bg-gradient-to-r from-indigo-50 to-purple-50 border-brand/20' : 'bg-surface-2 border-border' }} rounded-2xl border p-5">
                        <div class="flex items-center gap-3 mb-3">
                            @if($reply->is_admin_reply)
                                <div class="w-9 h-9 bg-gradient-to-br from-gray-800 to-gray-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                                    {{ $reply->user ? $reply->user->initials : 'AD' }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-ink">{{ $reply->user?->name ?? __('Admin') }}</p>
                                    <p class="text-[10px] text-muted">{{ $reply->created_at->format('M j, Y \\a\\t g:i A') }}</p>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-bold bg-brand/15 text-brand rounded-full ml-auto">{{ __('Admin') }}</span>
                            @else
                                <div class="w-9 h-9 bg-info/15 text-info rounded-full flex items-center justify-center text-xs font-bold">
                                    {{ $reply->user?->initials ?? '??' }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-ink">{{ $reply->user?->name ?? __('Unknown User') }}</p>
                                    <p class="text-[10px] text-muted">{{ $reply->created_at->format('M j, Y \\a\\t g:i A') }}</p>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-bold bg-info/15 text-info rounded-full ml-auto">{{ __('Customer') }}</span>
                            @endif
                        </div>
                        <div class="text-sm text-ink/80 leading-relaxed prose prose-sm max-w-none">
                            {!! nl2br(e($reply->body)) !!}
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Reply form --}}
                @if($ticket->status !== 'closed')
                <div class="panel p-6">
                    <h3 class="text-sm font-bold text-ink mb-3">{{ __('Reply to Ticket') }}</h3>
                    <form method="POST" action="{{ route('admin.tickets.reply', $ticket->id) }}">
                        @csrf
                        <textarea name="body" rows="5" placeholder="{{ __('Type your reply here...') }}" required aria-required="true"
                            class="w-full px-4 py-3 text-sm border border-border rounded-xl focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all resize-none @error('body') !border-danger !ring-danger/20 @enderror">{{ old('body') }}</textarea>
                        @error('body')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @enderror
                        <div class="flex items-center justify-end mt-4">
                            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-brand rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                {{ __('Send Reply') }}
                            </button>
                        </div>
                    </form>
                </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Customer Info --}}
                <div class="panel p-5">
                    <h3 class="text-xs font-bold text-muted uppercase tracking-wider mb-4">{{ __('Customer Info') }}</h3>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-info/15 text-info rounded-full flex items-center justify-center text-sm font-bold">
                            {{ $ticket->user?->initials ?? '??' }}
                        </div>
                        <div>
                            <p class="text-sm font-bold text-ink">{{ $ticket->user?->name ?? __('Unknown User') }}</p>
                            <p class="text-xs text-muted">{{ $ticket->user?->email ?? __('N/A') }}</p>
                        </div>
                    </div>
                    <div class="space-y-3 text-sm">
                        @if($ticket->workspace)
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('Workspace') }}</span>
                            <span class="font-medium text-ink">{{ $ticket->workspace->name }}</span>
                        </div>
                        @endif
                        @if($ticket->user)
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('Member Since') }}</span>
                            <span class="text-ink/80">{{ $ticket->user->created_at->format('M j, Y') }}</span>
                        </div>
                        @endif
                    </div>
                    @if($ticket->user)
                    <a href="{{ route('admin.users.show', $ticket->user->id) }}" class="block mt-4 text-center px-4 py-2 text-xs font-semibold text-brand bg-brand/10 border border-brand/20 rounded-xl hover:bg-brand/15 transition-colors">
                        {{ __('View Full Profile') }}
                    </a>
                    @endif
                </div>

                {{-- Status Update --}}
                <div class="panel p-5">
                    <h3 class="text-xs font-bold text-muted uppercase tracking-wider mb-4">{{ __('Update Status') }}</h3>
                    <form method="POST" action="{{ route('admin.tickets.status', $ticket->id) }}">
                        @csrf
                        @method('PATCH')
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-ink/80 mb-1.5">{{ __('Status') }}</label>
                                <select name="status" class="w-full px-3 py-2 text-sm border border-border rounded-xl bg-surface-2 focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-500 transition-all">
                                    <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>{{ __('Open') }}</option>
                                    <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>{{ __('In Progress') }}</option>
                                    <option value="waiting" {{ $ticket->status === 'waiting' ? 'selected' : '' }}>{{ __('Waiting') }}</option>
                                    <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>{{ __('Resolved') }}</option>
                                    <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>{{ __('Closed') }}</option>
                                </select>
                            </div>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-brand rounded-xl hover:bg-brand-strong shadow-sm transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ __('Update Status') }}
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Ticket Details --}}
                <div class="panel p-5">
                    <h3 class="text-xs font-bold text-muted uppercase tracking-wider mb-4">{{ __('Ticket Details') }}</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('Ticket ID') }}</span>
                            <span class="font-mono text-xs text-ink/80">#{{ $ticket->id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('Priority') }}</span>
                            <span class="font-medium text-ink">{{ ucfirst($ticket->priority) }}</span>
                        </div>
                        @if($ticket->category)
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('Category') }}</span>
                            <span class="font-medium text-ink">{{ ucfirst($ticket->category) }}</span>
                        </div>
                        @endif
                        @if($ticket->assignedTo)
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('Assigned To') }}</span>
                            <span class="font-medium text-ink">{{ $ticket->assignedTo->name }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('Replies') }}</span>
                            <span class="font-medium text-ink">{{ $ticket->ticketReplies->count() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('Created') }}</span>
                            <span class="text-ink/80">{{ $ticket->created_at->format('M j, Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('Last Updated') }}</span>
                            <span class="text-ink/80" title="{{ $ticket->updated_at->format('M j, Y g:i A') }}">{{ $ticket->updated_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-layouts.admin>
