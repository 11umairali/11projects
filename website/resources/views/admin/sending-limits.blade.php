<x-layouts.admin :title="__('Sending limits')" :subtitle="__('How much e-mail people can send. 0 means no limit for that rule. Users only see “limit reached”, never these numbers.')">
    <div class="space-y-6 max-w-4xl">
        @if(session('success'))<div class="p-4 bg-success/10 border border-success/20 rounded-xl text-sm font-medium text-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="p-4 bg-danger/10 border border-danger/20 rounded-xl text-sm text-danger">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ url('/admin/sending-limits') }}" class="space-y-6">
            @csrf
            @php
            $groups = [
                __('Per account – per day, by account age (recipients per 24 hours)') => [
                    ['send_new_days', __('A “new account” is younger than (days)')], ['send_new_daily', __('New account: per day')],
                    ['send_established_days', __('An “established account” is younger than (days)')], ['send_established_daily', __('Established account: per day')],
                    ['send_trusted_daily', __('Older (trusted) account: per day')],
                ],
                __('Per account – other periods (all of them are checked together with the daily limit)') => [
                    ['send_hourly', __('Per hour')], ['send_weekly', __('Per week (7 days)')], ['send_yearly', __('Per year (365 days)')],
                ],
                __('Abuse protection – all accounts together') => [
                    ['send_ip_daily', __('Per IP address, per day')], ['send_device_daily', __('Per device, per day')],
                ],
            ];
            @endphp
            @foreach($groups as $title => $fields)
                <div class="panel p-6">
                    <h2 class="text-base font-semibold text-ink mb-4">{{ $title }}</h2>
                    <div class="grid sm:grid-cols-2 gap-4">
                        @foreach($fields as [$key, $label])
                            <label class="block"><span class="block text-sm text-muted mb-1">{{ $label }}</span>
                                <input type="number" min="0" name="{{ $key }}" value="{{ old($key, $v[$key]) }}" class="input" required></label>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <p class="text-xs text-muted">{{ __('A limit set on one user (Admin → Users) overrides the daily account limit for that user. A “device” is the app install (or browser) the mail was sent from.') }}</p>
            <button class="btn-primary" type="submit">{{ __('Save limits') }}</button>
        </form>
        <div class="panel p-6">
            <h2 class="text-base font-semibold text-ink mb-3">{{ __('Top senders in the last 24 hours') }}</h2>
            <table class="w-full text-sm"><thead><tr class="text-left text-muted"><th class="py-1">{{ __('Account') }}</th><th class="py-1 text-right">{{ __('Recipients') }}</th></tr></thead>
                <tbody>@forelse($top as $t)<tr class="border-t border-border"><td class="py-1.5">{{ $t->email ?? '—' }}</td><td class="py-1.5 text-right">{{ $t->n }}</td></tr>@empty<tr><td colspan="2" class="py-3 text-muted">{{ __('Nothing sent yet.') }}</td></tr>@endforelse</tbody></table>
        </div>
    </div>
</x-layouts.admin>
