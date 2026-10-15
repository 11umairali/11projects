<x-layouts.admin :title="__('Friends Settings')" :subtitle="__('Friend chat, file sharing and audio calls.')">
    <div class="space-y-6 max-w-3xl">
        @if(session('success'))
            <div class="rounded-xl border border-green-300 bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-red-300 bg-red-50 text-red-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.friends-settings.save') }}" class="space-y-6">
            @csrf
            <div class="panel p-6 space-y-4">
                <h3 class="text-base font-semibold text-ink">{{ __('Chat, files and audio calls') }}</h3>
                <p class="text-sm text-muted">{{ __('Applies to the website and the mobile app. Only accepted friends can chat and call each other.') }}</p>
                <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" name="friends_chat_enabled" value="1" @checked($chat)><span class="text-sm font-medium text-ink">{{ __('Friend chat') }}</span></label>
                <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" name="friends_files_enabled" value="1" @checked($files)><span class="text-sm font-medium text-ink">{{ __('Sending and receiving files') }}</span></label>
                <div class="ml-7 flex flex-wrap items-center gap-2 text-sm text-muted">{{ __('Largest file') }}
                    <input type="number" name="friends_file_max_mb" min="1" max="50" value="{{ old('friends_file_max_mb', $maxMb) }}" class="input w-24"> MB
                    <span class="text-xs">{{ __('(programs such as .exe, .apk, .sh and web pages are never accepted)') }}</span>
                </div>
                <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" name="friends_calls_enabled" value="1" @checked($calls)><span class="text-sm font-medium text-ink">{{ __('Audio calls') }}</span></label>
            </div>

            {{-- ───────── RECORDING POLICY (one mode for all audio calls, video calls and meetings) ───────── --}}
            <div class="panel p-6 space-y-4">
                <h3 class="text-base font-semibold text-ink">{{ __('Call & meeting recording') }}</h3>
                <p class="text-xs text-muted">{{ __('Choose ONE policy for all audio calls, video calls and meetings. When a recording ends, the file is posted into the chat as a normal media message (a voice message for audio, a video file for video). In meetings, a link appears in the meeting chat and every registered participant gets a notification. Guests without an account cannot receive files.') }}</p>

                <div class="rounded-xl border border-amber-300 bg-amber-50 text-amber-900 text-xs p-3">
                    <strong>{{ __('Always visible, by design:') }}</strong> {{ __('while anything is being recorded, every participant sees a REC icon or a banner (at least one of the two stays on). Recording people with no visible sign is illegal in many countries and is rejected by the App Store and Google Play, so these switches can hide buttons and sounds, never the recording sign itself. Mention recording in your privacy policy and terms.') }}
                </div>

                <div class="space-y-2">
                    <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-border p-3"><input type="radio" name="rec_mode" value="0" class="mt-1" @checked($recMode === 0)><span><span class="text-sm font-semibold text-ink">{{ __('Off') }}</span><span class="block text-xs text-muted">{{ __('Nothing can be recorded.') }}</span></span></label>
                    @foreach(\App\Services\Recording\RecordingPolicy::MODES as $n => $m)
                        <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-border p-3"><input type="radio" name="rec_mode" value="{{ $n }}" class="mt-1" @checked($recMode === $n)><span><span class="text-sm font-semibold text-ink">{{ __('Mode :n', ['n' => $n]) }} · {{ __($m['name']) }}</span><span class="block text-xs text-muted">{{ __($m['text']) }}</span></span></label>
                    @endforeach
                </div>

                <h4 class="text-sm font-semibold text-ink pt-2">{{ __('What people see and hear, per mode') }}</h4>
                <p class="text-xs text-muted">{{ __('Only the settings of the mode you selected above are used. You can prepare the others.') }}</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-xs text-muted"><th class="py-2 pr-3">{{ __('Mode') }}</th><th class="py-2 pr-3">{{ __('Record button visible to') }}</th><th class="py-2 pr-3">{{ __('REC icon') }}</th><th class="py-2 pr-3">{{ __('Banner "This call is being recorded"') }}</th><th class="py-2">{{ __('Chime / voice prompt on start & stop') }}</th></tr></thead>
                        <tbody>
                        @foreach(\App\Services\Recording\RecordingPolicy::MODES as $n => $m)
                            @php($c = $recCfg[$n])
                            <tr class="border-t border-border">
                                <td class="py-2 pr-3 font-medium text-ink whitespace-nowrap">{{ $n }} · {{ __($m['name']) }}</td>
                                <td class="py-2 pr-3">
                                    <select name="rec[{{ $n }}][button]" class="input">
                                        @foreach(\App\Services\Recording\RecordingPolicy::ALLOWED_BUTTONS[$n] as $b)<option value="{{ $b }}" @selected($c['button'] === $b)>{{ __(\App\Services\Recording\RecordingPolicy::BUTTONS[$b]) }}</option>@endforeach
                                    </select>
                                </td>
                                <td class="py-2 pr-3"><input type="checkbox" name="rec[{{ $n }}][icon]" value="1" @checked($c['icon'])></td>
                                <td class="py-2 pr-3"><input type="checkbox" name="rec[{{ $n }}][banner]" value="1" @checked($c['banner'])></td>
                                <td class="py-2"><input type="checkbox" name="rec[{{ $n }}][chime]" value="1" @checked($c['chime'])></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-muted">{{ __('If both the icon and the banner are switched off, the banner is kept on. Mode 1 has no record button (it starts by itself); in modes 4 only the host or the initiator can have the button.') }}</p>

                <div class="flex flex-wrap items-center gap-4 text-sm text-muted">
                    <label class="flex items-center gap-2">{{ __('Consent request expires after') }} <input type="number" name="rec_consent_seconds" min="10" max="120" value="{{ old('rec_consent_seconds', $recSeconds) }}" class="input w-20"> {{ __('seconds') }}</label>
                    <label class="flex items-center gap-2">{{ __('Largest recording') }} <input type="number" name="rec_max_mb" min="5" max="500" value="{{ old('rec_max_mb', $recMb) }}" class="input w-24"> MB</label>
                </div>
                <p class="text-xs text-muted">{{ __('Today the audio / video is captured by a device that can do it – the website in a browser. Phone apps show the button, the consent question and the REC sign, but cannot capture yet; if nobody in the call can capture, the person who started the recording is told. Raise upload_max_filesize and post_max_size in PHP to match the size above.') }}</p>
            </div>

            <div class="panel p-6 space-y-3">
                <h3 class="text-base font-semibold text-ink">{{ __('Call servers') }}</h3>
                <p class="text-xs text-muted">{{ __('Calls connect the two devices directly. A STUN server helps them find each other; a TURN server relays the audio when a direct connection is impossible (strict networks, many mobile carriers). Without TURN some calls will not connect.') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="block text-xs text-muted mb-1">{{ __('STUN server') }}</label><input type="text" name="friends_stun_url" value="{{ old('friends_stun_url', $stun) }}" class="input w-full"></div>
                    <div class="sm:col-span-2"><label class="block text-xs text-muted mb-1">{{ __('TURN server address(es), comma separated (optional)') }}</label><input type="text" name="friends_turn_url" value="{{ old('friends_turn_url', $turnUrl) }}" class="input w-full" placeholder="turn:turn.your-domain.com:3478"></div>
                    <div><label class="block text-xs text-muted mb-1">{{ __('TURN username') }}</label><input type="text" name="friends_turn_user" value="{{ old('friends_turn_user', $turnUser) }}" class="input w-full" autocomplete="off"></div>
                    <div><label class="block text-xs text-muted mb-1">{{ __('TURN password') }} @if($turnPassSet)<span class="text-green-700">({{ __('saved') }})</span>@endif</label><input type="password" name="friends_turn_pass" class="input w-full" autocomplete="new-password" placeholder="{{ $turnPassSet ? __('leave empty to keep') : '' }}"></div>
                    @if($turnPassSet)<label class="sm:col-span-2 flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="friends_turn_pass_clear" value="1"> {{ __('Remove the saved TURN password') }}</label>@endif
                </div>
            </div>

            <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        </form>
    </div>
</x-layouts.admin>
