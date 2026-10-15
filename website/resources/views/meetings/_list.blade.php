        @php
            $groups = [[__('Upcoming'), $lists['upcoming'], true], [__('Recent'), $lists['recent'], false]];
        @endphp
        @foreach($groups as [$label, $items, $active])
            <div class="space-y-3">
                <h2 class="text-base font-semibold text-ink">{{ $label }}</h2>
                @forelse($items as $m)
                    <div class="bg-surface-2 rounded-2xl border border-border p-4 flex flex-wrap items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2"><span class="font-medium text-ink truncate">{{ $m['title'] }}</span>@if($m['status'] === 'live')<span class="text-[10px] rounded-full bg-red-100 text-red-700 px-2 py-0.5">{{ __('Live') }} · {{ $m['participants'] }}</span>@endif</div>
                            <div class="text-xs text-brand font-medium" data-start="{{ $m['scheduled_at'] ?? '' }}" data-live="{{ $m['status'] === 'live' ? 1 : 0 }}"></div>
                            <div class="text-xs text-muted">{{ $m['when'] ?: __('Instant meeting') }} · {{ $m['is_host'] ? __('You are the host') : __('Host: :n', ['n' => $m['host']]) }} · {{ $m['pretty'] }}</div>
                        </div>
                        @if($active)
                            <a href="{{ url('/meet/' . $m['code']) }}" class="btn-primary">{{ $m['status'] === 'live' ? __('Join') : __('Open') }}</a>
                            <button type="button" class="btn-secondary" onclick="navigator.clipboard.writeText(@js($m['url'])).then(function(){alert('{{ __('Link copied') }}');})">{{ __('Copy link') }}</button>
                            <button type="button" class="btn-secondary" onclick="shareMeeting(@js($m['title']), @js($m['url']), @js($m['pretty']), @js($m['when']))">{{ __('Share') }}</button>
                            <a class="btn-secondary" href="{{ url('/meet/' . $m['code'] . '/ics') }}" title="{{ __('Add to Google, Outlook or Apple calendar') }}">{{ __('Add to calendar') }}</a>
                            @if($m['is_host'])
                                <details class="w-full">
                                    <summary class="text-sm text-brand cursor-pointer">{{ __('Change title, time or length (reschedule)') }}</summary>
                                    <form method="POST" action="{{ url('/meetings/' . $m['code'] . '/update') }}" class="mt-3 grid sm:grid-cols-4 gap-3 items-end">
                                        @csrf
                                        <label class="block sm:col-span-4"><span class="text-xs text-muted">{{ __('Title') }}</span><input name="title" class="input" value="{{ $m['title'] }}" maxlength="150"></label>
                                        <label class="block"><span class="text-xs text-muted">{{ __('New date') }}</span><input type="date" name="date" class="input" value="{{ $m['scheduled_at'] ? \Illuminate\Support\Carbon::parse($m['scheduled_at'])->setTimezone(auth()->user()->timezone ?: config('app.timezone'))->format('Y-m-d') : '' }}"></label>
                                        <label class="block"><span class="text-xs text-muted">{{ __('New time') }}</span><input type="time" name="time" class="input" value="{{ $m['scheduled_at'] ? \Illuminate\Support\Carbon::parse($m['scheduled_at'])->setTimezone(auth()->user()->timezone ?: config('app.timezone'))->format('H:i') : '' }}"></label>
                                        <label class="block"><span class="text-xs text-muted">{{ __('Length (min)') }}</span><input type="number" name="duration" min="15" max="480" step="15" class="input" value="{{ $m['duration'] }}"></label>
                                        <button class="btn-primary" type="submit">{{ __('Save changes') }}</button>
                                    </form>
                                    <p class="text-xs text-muted mt-1">{{ __('People you invited get a notification about the change.') }}</p>
                                </details>
                            @endif
                            @if($m['is_host'] && $m['status'] !== 'live')
                                <form method="POST" action="{{ url('/meetings/' . $m['code'] . '/cancel') }}" onsubmit="return confirm(@js(__('Cancel this meeting?')))">@csrf<button type="submit" class="text-sm text-red-600">{{ __('Cancel') }}</button></form>
                            @endif
                        @endif
                    </div>
                @empty
                    <div class="text-sm text-muted py-4">{{ $active ? __('No upcoming meetings. Start one above.') : __('Nothing yet.') }}</div>
                @endforelse
            </div>
        @endforeach
