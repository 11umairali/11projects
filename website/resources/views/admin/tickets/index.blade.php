<x-layouts.admin :title="__('Support Tickets')" :subtitle="__('Manage and respond to customer support tickets.')">
    <div class="space-y-6">

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Tickets') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Manage support tickets, priorities, and resolutions.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'import-tickets')">{{ __('Import CSV') }}</button>
                    <a href="{{ route('admin.tickets.export', ['template' => 1]) }}" class="btn-secondary">{{ __('Download Template') }}</a>
                    <button type="button" class="btn-primary" x-data @click="$dispatch('open-modal', 'add-ticket')">{{ __('Add Ticket') }}</button>
                </div>
            </div>

            <form method="get" action="{{ route('admin.tickets.index') }}" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by subject, name or email') }}">
                </div>
                <div>
                    <label class="sr-only" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="input-field">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="open" @selected(request('status') === 'open')>{{ __('Open') }}</option>
                        <option value="in_progress" @selected(request('status') === 'in_progress')>{{ __('In Progress') }}</option>
                        <option value="waiting" @selected(request('status') === 'waiting')>{{ __('Waiting') }}</option>
                        <option value="resolved" @selected(request('status') === 'resolved')>{{ __('Resolved') }}</option>
                        <option value="closed" @selected(request('status') === 'closed')>{{ __('Closed') }}</option>
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="priority">{{ __('Priority') }}</label>
                    <select id="priority" name="priority" class="input-field">
                        <option value="">{{ __('All priorities') }}</option>
                        <option value="low" @selected(request('priority') === 'low')>{{ __('Low') }}</option>
                        <option value="medium" @selected(request('priority') === 'medium')>{{ __('Medium') }}</option>
                        <option value="high" @selected(request('priority') === 'high')>{{ __('High') }}</option>
                        <option value="urgent" @selected(request('priority') === 'urgent')>{{ __('Urgent') }}</option>
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="sort">{{ __('Sort') }}</label>
                    <select id="sort" name="sort" class="input-field">
                        <option value="latest" @selected(request('sort', 'latest') === 'latest')>{{ __('Created (Newest)') }}</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('Created (Oldest)') }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn-secondary" type="submit">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.tickets.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Tickets Table Panel --}}
        <div class="panel p-6" x-data="{
            selected: [],
            selectAll: false,
            view: localStorage.getItem('admin-tickets-view') || 'list',
            allIds: [{{ $tickets->pluck('id')->join(',') }}]
        }" x-init="$watch('view', v => localStorage.setItem('admin-tickets-view', v)); $watch('selectAll', v => { selected = v ? [...allIds] : [] })">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Ticket Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $tickets->firstItem() ?? 0 }}-{{ $tickets->lastItem() ?? 0 }} {{ __('of') }} {{ $tickets->total() }} {{ __('tickets.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="view = 'list'" :class="view === 'list' ? 'btn-primary' : 'btn-secondary'">{{ __('List') }}</button>
                    <button type="button" @click="view = 'grid'" :class="view === 'grid' ? 'btn-primary' : 'btn-secondary'">{{ __('Grid') }}</button>
                    <button type="button" class="btn-secondary" onclick="window.print()">{{ __('Print') }}</button>
                    <a href="{{ route('admin.tickets.export', request()->query()) }}" class="btn-secondary">{{ __('Export') }}</a>
                    <button x-show="selected.length > 0" x-cloak type="button" class="btn-danger" @click="$dispatch('open-modal', 'bulk-delete-tickets')" x-text="'Bulk Delete (' + selected.length + ')'"></button>
                </div>
            </div>

            {{-- List View --}}
            <div x-show="view === 'list'" class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-[0.2em] text-muted">
                        <tr>
                            <th class="pb-3">
                                <input type="checkbox" x-model="selectAll" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </th>
                            <th class="pb-3">{{ __('Subject') }}</th>
                            <th class="pb-3">{{ __('User') }}</th>
                            <th class="pb-3">{{ __('Priority') }}</th>
                            <th class="pb-3">{{ __('Status') }}</th>
                            <th class="pb-3">{{ __('Date') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($tickets as $ticket)
                        <tr>
                            <td class="py-4">
                                <input type="checkbox" value="{{ $ticket->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </td>
                            <td class="py-4">
                                <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="font-semibold text-ink hover:text-brand transition-colors">
                                    {{ Str::limit($ticket->subject, 50) }}
                                </a>
                                @if($ticket->category)
                                    <p class="text-xs text-muted mt-0.5">{{ ucfirst($ticket->category) }}</p>
                                @endif
                            </td>
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-xs font-bold">
                                        {{ $ticket->user?->initials ?? '??' }}
                                    </span>
                                    <div>
                                        <span class="font-semibold text-ink">{{ $ticket->user?->name ?? __('Unknown') }}</span>
                                        <p class="text-xs text-muted">{{ $ticket->user?->email ?? __('N/A') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4">
                                <span class="text-sm font-semibold text-ink">{{ ucfirst($ticket->priority) }}</span>
                            </td>
                            <td class="py-4">
                                <span class="text-sm font-semibold text-ink">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</span>
                            </td>
                            <td class="py-4 text-sm text-ink">{{ $ticket->created_at->format('M j, Y') }}</td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="btn-secondary">{{ __('View') }}</a>
                                    <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'confirm-delete-{{ $ticket->id }}')">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>

                        {{-- Delete confirmation modal --}}
                        <x-admin-modal name="confirm-delete-{{ $ticket->id }}">
                            <form method="POST" action="{{ route('admin.tickets.destroy', $ticket->id) }}" class="p-6 space-y-4">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <p class="text-lg font-semibold text-ink">{{ __('Delete ticket') }} "{{ Str::limit($ticket->subject, 40) }}"?</p>
                                    <p class="mt-2 text-sm text-muted">{{ __('This will') }} <strong class="text-danger">{{ __('permanently delete') }}</strong> {{ __('this ticket and all its replies. This action cannot be undone.') }}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Delete Ticket') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-sm text-muted">{{ __('No tickets found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Grid View --}}
            <div x-show="view === 'grid'" x-cloak class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($tickets as $ticket)
                <div class="panel p-4 space-y-3">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" value="{{ $ticket->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-sm font-bold">
                            {{ $ticket->user?->initials ?? '??' }}
                        </span>
                        <div class="min-w-0">
                            <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="font-semibold text-ink hover:text-brand transition-colors truncate block">{{ Str::limit($ticket->subject, 40) }}</a>
                            <p class="text-xs text-muted">{{ $ticket->user?->name ?? __('Unknown') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-ink">{{ ucfirst($ticket->priority) }}</span>
                        <span class="font-semibold text-ink">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-muted">
                        @if($ticket->category)
                            <span>{{ ucfirst($ticket->category) }}</span>
                        @else
                            <span></span>
                        @endif
                        <span>{{ $ticket->created_at->format('M j, Y') }}</span>
                    </div>
                    <div class="pt-2 border-t border-border/60">
                        <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="btn-secondary text-xs">{{ __('View') }}</a>
                    </div>
                </div>
                @empty
                <div class="col-span-full py-6 text-center text-sm text-muted">{{ __('No tickets found.') }}</div>
                @endforelse
            </div>

            @if($tickets->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $tickets->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Import Modal --}}
    <x-admin-modal name="import-tickets">
        <form method="POST" action="{{ route('admin.tickets.import') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Import Tickets') }}</h3>
                <p class="mt-1 text-sm text-muted">{{ __('Upload a CSV file that matches the provided template.') }}</p>
            </div>
            <x-file-uploader name="file" accept=".csv,.txt" :label="__('CSV File')" />
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-primary">{{ __('Upload') }}</button>
            </div>
        </form>
    </x-admin-modal>

    {{-- Add Ticket Modal — submits to admin.tickets.store which creates
         a new Ticket row. Admin can log it on behalf of a user (enter
         their email in the User Email field — looked up by the backend)
         or leave the user blank for an unattributed internal ticket. --}}
    <x-admin-modal name="add-ticket">
        <form method="POST" action="{{ route('admin.tickets.store') }}" class="p-6 space-y-4">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Add Ticket') }}</h3>
                <p class="mt-1 text-sm text-muted">{{ __('Create a support ticket on behalf of a user.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-ink mb-1">{{ __('Subject') }} <span class="text-red-500">*</span></label>
                <input type="text" name="subject" required maxlength="255" class="input-field" placeholder="{{ __('Short summary of the issue') }}">
            </div>
            <div>
                <label class="block text-sm font-medium text-ink mb-1">{{ __('Message') }} <span class="text-red-500">*</span></label>
                <textarea name="body" required rows="4" class="input-field" placeholder="{{ __('What did the user report?') }}"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-ink mb-1">{{ __('Priority') }}</label>
                    <select name="priority" class="input-field">
                        <option value="low">{{ __('Low') }}</option>
                        <option value="medium" selected>{{ __('Medium') }}</option>
                        <option value="high">{{ __('High') }}</option>
                        <option value="urgent">{{ __('Urgent') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1">{{ __('Status') }}</label>
                    <select name="status" class="input-field">
                        <option value="open" selected>{{ __('Open') }}</option>
                        <option value="in_progress">{{ __('In Progress') }}</option>
                        <option value="waiting">{{ __('Waiting') }}</option>
                        <option value="resolved">{{ __('Resolved') }}</option>
                        <option value="closed">{{ __('Closed') }}</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-ink mb-1">{{ __('User email (optional)') }}</label>
                    <input type="email" name="user_email" class="input-field" placeholder="user@example.com">
                    <p class="text-xs text-muted mt-1">{{ __('Leave blank for an unattributed ticket.') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1">{{ __('Category (optional)') }}</label>
                    <input type="text" name="category" class="input-field" placeholder="{{ __('billing, bug, feature…') }}">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-primary">{{ __('Create Ticket') }}</button>
            </div>
        </form>
    </x-admin-modal>

    {{-- Bulk Delete Modal --}}
    <x-admin-modal name="bulk-delete-tickets">
        <form method="POST" action="{{ route('admin.tickets.bulk-destroy') }}" class="p-6 space-y-4"
              x-data @submit="
                  const checkboxes = document.querySelectorAll('input[type=checkbox][x-model\\.number=selected]:checked');
                  checkboxes.forEach(cb => {
                      const input = document.createElement('input');
                      input.type = 'hidden'; input.name = 'ids[]'; input.value = cb.value;
                      $el.appendChild(input);
                  });
              ">
            @csrf
            <div>
                <p class="text-lg font-semibold text-ink">{{ __('Delete selected tickets?') }}</p>
                <p class="mt-2 text-sm text-muted">{{ __('You are about to') }} <strong class="text-danger">{{ __('permanently delete') }}</strong> {{ __('the selected ticket(s) and all their replies. This action cannot be undone.') }}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('Delete Permanently') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
