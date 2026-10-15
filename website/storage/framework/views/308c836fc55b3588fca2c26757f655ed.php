<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name' => config('app.name', 'Dahimail')]));

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

foreach (array_filter((['name' => config('app.name', 'Dahimail')]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<span <?php echo e($attributes->class(['inline-flex items-center gap-3'])); ?>>
    <img src="<?php echo e(asset('images/dahimail-logo.png')); ?>" alt="<?php echo e($name); ?>" width="40" height="40" class="h-10 w-10 shrink-0 rounded-lg">
    <span class="text-xl font-semibold leading-none"><?php echo e($name); ?></span>
</span>
<?php /**PATH /home/dahimail.com/public_html/resources/views/components/brand-logo.blade.php ENDPATH**/ ?>