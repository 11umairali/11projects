{{--
    Toast Helper Component (Inline)
    --------------------------------
    A convenience wrapper to dispatch a toast from Blade.
    This is NOT the container -- use <x-toast-container /> in your layout.

    Usage:
        <x-toast type="success" message="Contact created!" />
        <x-toast type="error" message="Failed to save." :duration="8000" />
--}}

@props([
    'type' => 'info',
    'message' => '',
    'duration' => 5000,
])

<div
    x-data
    x-init="$dispatch('toast', { type: @js($type), message: @js($message), duration: @js($duration) }); $el.remove()"
></div>
