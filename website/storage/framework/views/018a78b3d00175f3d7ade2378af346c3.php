<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'buttonClass' => 'icon-button',
    'iconClass' => 'h-4 w-4',
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'buttonClass' => 'icon-button',
    'iconClass' => 'h-4 w-4',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<button
    type="button"
    x-on:click="toggleTheme()"
    <?php echo e($attributes->merge(['class' => $buttonClass])); ?>

    :aria-label="theme === 'dark' ? '<?php echo e(__('Switch to light mode')); ?>' : '<?php echo e(__('Switch to dark mode')); ?>'"
    :title="theme === 'dark' ? '<?php echo e(__('Switch to light mode')); ?>' : '<?php echo e(__('Switch to dark mode')); ?>'"
>
    <svg x-show="theme === 'dark'" x-cloak viewBox="0 0 24 24" class="<?php echo e($iconClass); ?>" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="5"></circle>
        <line x1="12" y1="1" x2="12" y2="3"></line>
        <line x1="12" y1="21" x2="12" y2="23"></line>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
        <line x1="1" y1="12" x2="3" y2="12"></line>
        <line x1="21" y1="12" x2="23" y2="12"></line>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
    </svg>
    <svg x-show="theme !== 'dark'" x-cloak viewBox="0 0 24 24" class="<?php echo e($iconClass); ?>" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
    </svg>
    <span class="sr-only" x-text="theme === 'dark' ? '<?php echo e(__('Switch to light mode')); ?>' : '<?php echo e(__('Switch to dark mode')); ?>'"></span>
</button>
<?php /**PATH /home/dahimail.com/public_html/resources/views/components/theme-toggle.blade.php ENDPATH**/ ?>