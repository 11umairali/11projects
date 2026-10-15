<x-layouts.app :title="__('Meetings')">
    <div class="max-w-4xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-ink">{{ __('Meetings') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('Video meetings built into :app. Share a link, let people in from the waiting room, share your screen and chat. Best with up to 6–8 people.', ['app' => config('app.name')]) }}</p>
        </div>

        @if(session('status'))<div class="rounded-xl border border-green-300 bg-green-50 text-green-800 px-4 py-3 text-sm break-all">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="rounded-xl border border-red-300 bg-red-50 text-red-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="rounded-xl border border-red-300 bg-red-50 text-red-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

        <div class="grid gap-4 sm:grid-cols-2">
            <form method="POST" action="{{ url('/meetings') }}" class="bg-surface-2 rounded-2xl border border-border p-5 space-y-3">
                @csrf
                <input type="hidden" name="mode" value="now">
                <input type="hidden" name="waiting_room" value="1"><input type="hidden" name="allow_guests" value="1">
                <div class="font-medium text-ink">{{ __('Start a meeting now') }}</div>
                <p class="text-sm text-muted">{{ __('Opens a room right away. Then share the link.') }}</p>
                <button type="submit" class="btn-primary">{{ __('New meeting') }}</button>
            </form>
            <div class="bg-surface-2 rounded-2xl border border-border p-5 space-y-3">
                <div class="font-medium text-ink">{{ __('Join with a code or link') }}</div>
                <div class="flex gap-2">
                    <input id="mt-code" type="text" class="input flex-1" placeholder="abc-defg-hij" autocomplete="off">
                    <button type="button" class="btn-secondary" onclick="(function(){var v=document.getElementById('mt-code').value.trim();var m=v.match(/meet\/([a-z0-9\-]+)/i);var c=(m?m[1]:v).replace(/[^a-z0-9]/gi,'').toLowerCase();if(c)location.href='/meet/'+c;})()">{{ __('Join') }}</button>
                </div>
            </div>
        </div>

        <details class="bg-surface-2 rounded-2xl border border-border p-5" @if($errors->any() || request()->filled('invite_user_id')) open @endif>
            <summary class="font-medium text-ink cursor-pointer">{{ __('Schedule a meeting') }}</summary>
            <form method="POST" action="{{ url('/meetings') }}" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="mode" value="schedule">
                <div><label class="block text-xs text-muted mb-1">{{ __('Title') }}</label><input type="text" name="title" value="{{ old('title') }}" maxlength="150" class="input w-full" placeholder="{{ __('Weekly sync') }}"></div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div><label class="block text-xs text-muted mb-1">{{ __('Date') }}</label><input type="date" name="date" value="{{ old('date', now()->format('Y-m-d')) }}" class="input w-full" required></div>
                    <div><label class="block text-xs text-muted mb-1">{{ __('Time') }}</label><input type="time" name="time" value="{{ old('time', '10:00') }}" class="input w-full" required></div>
                    <div><label class="block text-xs text-muted mb-1">{{ __('Length (minutes)') }}</label><input type="number" name="duration" value="{{ old('duration', 45) }}" min="15" max="480" step="5" class="input w-full"></div>
                </div>
                @if(count($people))
                    <div>
                        <label class="block text-xs text-muted mb-1">{{ __('Invite friends and teammates') }}</label>
                        <div class="max-h-44 overflow-y-auto rounded-xl border border-border divide-y divide-border">
                            @foreach($people as $p)
                                <label class="flex items-center gap-3 px-3 py-2 cursor-pointer"><input type="checkbox" name="invitees[]" value="{{ $p['id'] }}" @checked(in_array($p['id'], old('invitees', [(int) request('invite_user_id')])) )><span class="text-sm text-ink">{{ $p['name'] }}</span></label>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="space-y-2 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="waiting_room" value="1" checked> {{ __('Waiting room: I let people in') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="allow_guests" value="1" checked> {{ __('Allow guests with the link (they always wait for me)') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="mute_on_entry" value="1"> {{ __('Mute people when they join') }}</label>
                </div>
                <button type="submit" class="btn-primary">{{ __('Schedule') }}</button>
            </form>
        </details>

        <div id="meeting-lists" class="space-y-6">
            @include('meetings._list')
        </div>

    </div>
    <script src="{{ asset('js/meeting-list.js') }}?v=20261007"></script>
    <script>
        function shareMeeting(title, url, code, when) {
            var text = title + (when ? '\n' + when : '') + '\nJoin: ' + url + '\nCode: ' + code;
            if (navigator.share) { navigator.share({ title: title, text: text, url: url }).catch(function () {}); return; }
            (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { alert('{{ __('Invitation copied') }}'); }).catch(function () { window.prompt('{{ __('Copy the invitation:') }}', text); });
        }
        (function () {
            function f(s) { s = Math.abs(s); var d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60); return d ? d + 'd ' + h + 'h' : h ? h + 'h ' + m + 'min' : m + ' min'; }
            function tick() {
                document.querySelectorAll('[data-start]').forEach(function (e) {
                    if (e.dataset.live === '1') { e.textContent = '{{ __('In progress') }}'; return; }
                    if (!e.dataset.start) { e.textContent = ''; return; }
                    var s = Math.round((new Date(e.dataset.start).getTime() - Date.now()) / 1000);
                    e.textContent = s > 0 ? '{{ __('Starts in') }} ' + f(s) : '{{ __('Time to start') }}';
                });
            }
            tick(); setInterval(tick, 30000);
        })();
    </script>
</x-layouts.app>
