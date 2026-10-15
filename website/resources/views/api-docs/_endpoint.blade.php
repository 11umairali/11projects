@php
    $methodColors = [
        'GET' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'POST' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'PUT' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'PATCH' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'DELETE' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    ];
    $color = $methodColors[$method] ?? 'bg-gray-100 text-gray-700';
    $uid = 'ep-' . md5($method . $path);
@endphp

<div x-data="{ open: false }" class="mb-6 border border-border rounded-xl overflow-hidden hover:border-brand/30 transition-colors">
    {{-- Header --}}
    <button @click="open = !open" class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-surface-3/50 transition-colors">
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $color }} min-w-[52px] justify-center">{{ $method }}</span>
        <code class="text-sm font-mono text-ink flex-1 truncate">{{ $path }}</code>
        <span class="text-xs text-muted hidden sm:inline flex-shrink-0">{{ Str::limit($description, 50) }}</span>
        <svg class="w-4 h-4 text-muted flex-shrink-0 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
    </button>

    {{-- Expanded content --}}
    <div x-show="open" x-collapse x-cloak class="border-t border-border px-4 py-4 space-y-4 bg-surface">
        <p class="text-sm text-muted">{{ $description }}</p>

        {{-- Parameters table --}}
        @if(!empty($params))
            <div>
                <h4 class="text-xs font-semibold text-ink mb-2 uppercase tracking-wider">Parameters</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border border-border rounded-lg overflow-hidden">
                        <thead>
                            <tr class="bg-surface-3 text-left">
                                <th class="px-3 py-1.5 text-xs font-medium text-muted">Name</th>
                                <th class="px-3 py-1.5 text-xs font-medium text-muted">Type</th>
                                <th class="px-3 py-1.5 text-xs font-medium text-muted w-16">Required</th>
                                <th class="px-3 py-1.5 text-xs font-medium text-muted">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($params as $param)
                                <tr>
                                    <td class="px-3 py-1.5 font-mono text-xs text-ink">{{ $param['name'] }}</td>
                                    <td class="px-3 py-1.5 text-xs text-muted">{{ $param['type'] }}</td>
                                    <td class="px-3 py-1.5 text-xs">
                                        @if($param['required'])
                                            <span class="text-red-500 font-medium">Yes</span>
                                        @else
                                            <span class="text-muted">No</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-1.5 text-xs text-muted">{{ $param['desc'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Example request --}}
        <div>
            <h4 class="text-xs font-semibold text-ink mb-2 uppercase tracking-wider">Example Request</h4>
            <div x-data="{ copied: false }" class="relative">
                <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-xs font-mono overflow-x-auto">{{ $curl }}</pre>
                <button @click="navigator.clipboard.writeText(@js($curl)); copied = true; setTimeout(() => copied = false, 2000)"
                        class="absolute top-2 right-2 p-1.5 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 transition-colors" :title="copied ? 'Copied!' : 'Copy'">
                    <svg x-show="!copied" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <svg x-show="copied" x-cloak class="w-3.5 h-3.5 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Example response --}}
        <div>
            <h4 class="text-xs font-semibold text-ink mb-2 uppercase tracking-wider">Example Response</h4>
            <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-xs font-mono overflow-x-auto max-h-60">{{ $response }}</pre>
        </div>
    </div>
</div>
