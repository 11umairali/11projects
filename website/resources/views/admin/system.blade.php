<x-layouts.admin :title="__('System Health')" :subtitle="__('Monitor infrastructure, services, and performance metrics.')">
    <div class="space-y-6">
        {{-- Status Badge --}}
        <div class="flex items-center justify-end">
            <div class="flex items-center gap-2">
                @php
                    $hasIssues = $failedJobs > 0 || ($diskUsage['used_percent'] !== null && $diskUsage['used_percent'] > 90);
                @endphp
                <span class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium {{ $hasIssues ? 'bg-warning/15 text-warning' : 'bg-success/15 text-success' }} rounded-full">
                    <span class="w-2 h-2 {{ $hasIssues ? 'bg-warning/100' : 'bg-success/100' }} rounded-full animate-pulse"></span>
                    {{ $hasIssues ? __('Needs Attention') : __('All Systems Operational') }}
                </span>
                <a href="{{ route('admin.system') }}" class="px-4 py-2 text-sm font-medium border border-border rounded-xl hover:bg-surface transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    {{ __('Refresh') }}
                </a>
            </div>
        </div>

        {{-- Health cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @php
            $queueStatus = $pendingJobs > 100 ? 'critical' : ($pendingJobs > 50 ? 'warning' : 'healthy');
            $failedStatus = $failedJobs > 10 ? 'critical' : ($failedJobs > 0 ? 'warning' : 'healthy');
            $diskStatus = ($diskUsage['used_percent'] ?? 0) > 90 ? 'critical' : (($diskUsage['used_percent'] ?? 0) > 75 ? 'warning' : 'healthy');
            $dbSizeStatus = ($dbSize ?? 0) > 5000 ? 'warning' : 'healthy';

            $healthMetrics = [
                [
                    'label' => __('Queue Depth'),
                    'value' => number_format($pendingJobs),
                    'unit' => __('jobs'),
                    'status' => $queueStatus,
                    'detail' => !empty($queueDepths)
                        ? collect($queueDepths)->map(fn($c, $q) => "$q: $c")->implode(', ')
                        : __('No pending jobs'),
                    'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
                ],
                [
                    'label' => __('Failed Jobs'),
                    'value' => number_format($failedJobs),
                    'unit' => __('jobs'),
                    'status' => $failedStatus,
                    'detail' => $failedJobs > 0 && count($recentFailedJobs) > 0
                        ? __('Latest:') . ' ' . ($recentFailedJobs[0]->job_class ?? __('Unknown'))
                        : __('No recent failures'),
                    'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                ],
                [
                    'label' => __('Database Size'),
                    'value' => $dbSize !== null ? $dbSize : __('N/A'),
                    'unit' => $dbSize !== null ? 'MB' : '',
                    'status' => $dbSizeStatus,
                    'detail' => $dbSize !== null ? __('MySQL database size on disk') : __('Could not read database size'),
                    'icon' => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
                ],
                [
                    'label' => __('Disk Usage'),
                    'value' => $diskUsage['used_percent'] !== null ? $diskUsage['used_percent'] : __('N/A'),
                    'unit' => $diskUsage['used_percent'] !== null ? '%' : '',
                    'status' => $diskStatus,
                    'detail' => $diskUsage['total_gb'] !== null
                        ? number_format($diskUsage['total_gb'] - $diskUsage['free_gb'], 1) . ' ' . __('GB used') . ' / ' . number_format($diskUsage['total_gb'], 1) . ' ' . __('GB total')
                        : __('Could not read disk info'),
                    'icon' => 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01',
                ],
                [
                    'label' => __('Log Errors'),
                    'value' => count($recentErrors),
                    'unit' => __('recent'),
                    'status' => count($recentErrors) > 10 ? 'critical' : (count($recentErrors) > 0 ? 'warning' : 'healthy'),
                    'detail' => count($recentErrors) > 0
                        ? __('Found in last 5KB of log file')
                        : __('No recent errors in log'),
                    'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
                ],
                [
                    'label' => __('Environment'),
                    'value' => ucfirst($appInfo['environment']),
                    'unit' => '',
                    'status' => $appInfo['debug_mode'] && $appInfo['environment'] === 'production' ? 'critical' : 'healthy',
                    'detail' => __('Debug:') . ' ' . ($appInfo['debug_mode'] ? __('ON') : __('OFF')) . ' | ' . __('Cache:') . ' ' . $appInfo['cache_driver'],
                    'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
                ],
            ];
            @endphp

            @foreach($healthMetrics as $metric)
            @php
                $statusColors = [
                    'healthy' => ['bg' => 'bg-success/10', 'border' => 'border-success/20', 'badge' => 'bg-success/15 text-success', 'dot' => 'bg-success/100'],
                    'warning' => ['bg' => 'bg-warning/10', 'border' => 'border-warning/20', 'badge' => 'bg-warning/15 text-warning', 'dot' => 'bg-warning/100'],
                    'critical' => ['bg' => 'bg-danger/10', 'border' => 'border-danger/20', 'badge' => 'bg-danger/15 text-danger', 'dot' => 'bg-danger/100'],
                ];
                $sc = $statusColors[$metric['status']];
            @endphp
            <div class="bg-surface-2 rounded-2xl border border-border p-5 hover:shadow-sm transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 {{ $sc['bg'] }} rounded-xl flex items-center justify-center border {{ $sc['border'] }}">
                            <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $metric['icon'] }}"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-muted">{{ $metric['label'] }}</span>
                    </div>
                    <span class="flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full {{ $sc['badge'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }}"></span>
                        {{ ucfirst($metric['status']) }}
                    </span>
                </div>
                <div class="mt-2">
                    <span class="text-3xl font-bold text-ink">{{ $metric['value'] }}</span>
                    <span class="text-sm text-muted ml-1">{{ $metric['unit'] }}</span>
                </div>
                <p class="text-xs text-muted mt-2">{{ $metric['detail'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Table row counts --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6">
            <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Database Table Counts') }}</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($tableCounts as $table => $count)
                <div class="flex items-center justify-between p-3 bg-surface rounded-xl">
                    <span class="text-sm font-medium text-muted">{{ $table }}</span>
                    <span class="text-sm font-bold text-ink">{{ is_numeric($count) ? number_format($count) : $count }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Server info --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-surface-2 rounded-2xl border border-border p-6">
                <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Application Information') }}</h2>
                <div class="space-y-3">
                    @php
                    $serverInfo = [
                        ['key' => __('PHP Version'), 'value' => $appInfo['php_version']],
                        ['key' => __('Laravel Version'), 'value' => $appInfo['laravel_version']],
                        ['key' => __('Environment'), 'value' => ucfirst($appInfo['environment'])],
                        ['key' => __('Debug Mode'), 'value' => $appInfo['debug_mode'] ? __('Enabled') : __('Disabled')],
                        ['key' => __('Cache Driver'), 'value' => $appInfo['cache_driver']],
                        ['key' => __('Queue Driver'), 'value' => $appInfo['queue_driver']],
                        ['key' => __('Session Driver'), 'value' => $appInfo['session_driver']],
                    ];
                    @endphp

                    @foreach($serverInfo as $info)
                    <div class="flex items-center justify-between py-2 border-b border-border last:border-0">
                        <span class="text-sm text-muted">{{ $info['key'] }}</span>
                        <span class="text-sm font-mono font-medium text-ink">{{ $info['value'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-surface-2 rounded-2xl border border-border p-6">
                <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Storage Usage') }}</h2>
                <div class="space-y-4">
                    {{-- Disk usage bar --}}
                    @if($diskUsage['used_percent'] !== null)
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-sm font-medium text-muted">{{ __('Disk Usage') }}</span>
                            <span class="text-sm font-semibold text-ink">{{ $diskUsage['used_percent'] }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all {{ $diskUsage['used_percent'] > 90 ? 'bg-danger/100' : ($diskUsage['used_percent'] > 75 ? 'bg-warning/100' : 'bg-success/100') }}"
                                 style="width: {{ $diskUsage['used_percent'] }}%"></div>
                        </div>
                        <p class="text-xs text-muted mt-1">{{ number_format($diskUsage['free_gb'], 1) }} {{ __('GB free of') }} {{ number_format($diskUsage['total_gb'], 1) }} GB</p>
                    </div>
                    @else
                    <p class="text-sm text-muted">{{ __('Disk usage info unavailable.') }}</p>
                    @endif

                    {{-- Storage directory sizes --}}
                    @if(!empty($storageSizes))
                    <div class="pt-2 border-t border-border">
                        <p class="text-xs font-bold text-muted uppercase tracking-wider mb-2">{{ __('Storage Directories') }}</p>
                        @foreach($storageSizes as $dir => $sizeMb)
                        <div class="flex items-center justify-between py-1.5">
                            <span class="text-sm text-muted font-mono">storage/{{ $dir }}</span>
                            <span class="text-sm font-semibold text-ink">{{ $sizeMb }} MB</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Recent failed jobs --}}
        @if(count($recentFailedJobs) > 0)
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-6 py-4 border-b border-border bg-danger/10/50 dark:bg-red-950/20">
                <h2 class="text-lg font-semibold text-ink">{{ __('Recent Failed Jobs') }}</h2>
                <p class="text-xs text-muted mt-0.5">{{ __('Last') }} {{ count($recentFailedJobs) }} {{ __('failed jobs') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface border-b border-border">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Job') }}</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Queue') }}</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Error') }}</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Failed At') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($recentFailedJobs as $job)
                        <tr class="hover:bg-surface transition-colors">
                            <td class="px-4 py-2.5 text-xs font-mono text-muted">{{ $job->job_class }}</td>
                            <td class="px-4 py-2.5 text-xs text-muted">{{ $job->queue }}</td>
                            <td class="px-4 py-2.5 text-xs text-danger dark:text-red-400 max-w-xs truncate" title="{{ $job->exception_summary }}">{{ Str::limit($job->exception_summary, 100) }}</td>
                            <td class="px-4 py-2.5 text-xs text-muted">{{ $job->failed_at }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Recent errors from log --}}
        @if(count($recentErrors) > 0)
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-6 py-4 border-b border-border bg-warning/10/50 dark:bg-yellow-950/20">
                <h2 class="text-lg font-semibold text-ink">{{ __('Recent Log Errors') }}</h2>
                <p class="text-xs text-muted mt-0.5">{{ __('Extracted from laravel.log (last 5KB)') }}</p>
            </div>
            <div class="p-4 space-y-2 max-h-96 overflow-y-auto">
                @foreach($recentErrors as $error)
                <div class="p-3 bg-danger/10 border border-red-100 rounded-lg">
                    <code class="text-[11px] text-danger break-all leading-relaxed">{{ Str::limit($error, 300) }}</code>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</x-layouts.admin>
