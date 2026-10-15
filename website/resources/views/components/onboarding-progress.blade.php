@props(['currentStep' => 1])

@php
$steps = [
    1 => 'Workspace',
    2 => 'Email',
    3 => 'AI Training',
    4 => 'Auto-Reply',
    5 => 'Team',
];
@endphp

<div class="w-full max-w-3xl mx-auto mb-10">
    <div class="flex items-center justify-between">
        @foreach($steps as $number => $label)
            @php
                $isCompleted = $number < $currentStep;
                $isCurrent = $number === $currentStep;
                $isFuture = $number > $currentStep;
            @endphp

            {{-- Step circle + label --}}
            <div class="flex flex-col items-center relative z-10">
                <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 transition-all duration-300
                    @if($isCompleted) bg-success/100 border-green-500 text-white
                    @elseif($isCurrent) bg-primary-600 border-primary-600 text-white ring-4 ring-primary-100
                    @else bg-surface-2 border-border text-muted
                    @endif">
                    @if($isCompleted)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    @else
                        <span class="text-sm font-semibold">{{ $number }}</span>
                    @endif
                </div>
                <span class="mt-2 text-xs font-medium
                    @if($isCompleted) text-success
                    @elseif($isCurrent) text-primary-700
                    @else text-muted
                    @endif">{{ $label }}</span>
            </div>

            {{-- Connector line (not after last step) --}}
            @if($number < count($steps))
                <div class="flex-1 h-0.5 mx-2 -mt-5
                    @if($number < $currentStep) bg-success/100
                    @else bg-gray-200
                    @endif"></div>
            @endif
        @endforeach
    </div>
</div>
