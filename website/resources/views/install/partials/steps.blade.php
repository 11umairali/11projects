@php
    $steps = [
        ['num' => 1, 'label' => __('Welcome')],
        ['num' => 2, 'label' => __('Requirements')],
        ['num' => 3, 'label' => __('Database')],
        ['num' => 4, 'label' => __('Application')],
        ['num' => 5, 'label' => __('Admin Account')],
        ['num' => 6, 'label' => __('Install')],
    ];
@endphp

<div class="space-y-1 mt-8">
    @foreach ($steps as $step)
        @php
            $isCompleted = $currentStep > $step['num'];
            $isActive = $currentStep === $step['num'];
            $isPending = $currentStep < $step['num'];
        @endphp
        <div class="flex items-center gap-4 py-2.5 px-3 rounded-xl transition-all {{ $isActive ? 'bg-primary/10' : '' }}">
            @if ($isCompleted)
                <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            @elseif ($isActive)
                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center shrink-0 shadow-lg shadow-primary/30">
                    <span class="text-xs font-bold text-white">{{ $step['num'] }}</span>
                </div>
            @else
                <div class="w-8 h-8 rounded-full bg-[#1a1d27] border border-[#2d3039] flex items-center justify-center shrink-0">
                    <span class="text-xs font-medium text-gray-500">{{ $step['num'] }}</span>
                </div>
            @endif

            <span class="text-[11px] font-bold uppercase tracking-widest {{ $isActive ? 'text-primary' : ($isCompleted ? 'text-gray-200' : 'text-gray-500') }}">
                {{ $step['label'] }}
            </span>
        </div>
    @endforeach
</div>
