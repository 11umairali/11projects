<?php
    $colors = \App\Support\UiTheme::variables(
        \App\Models\SystemSetting::get('primary_color'),
        \App\Models\SystemSetting::get('secondary_color'),
        \App\Models\SystemSetting::get('accent_color'),
    );
?>
<style id="brand-theme">
    :root {
        <?php $__currentLoopData = $colors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $token => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php echo e($token); ?>: <?php echo e($value); ?>;
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    }
</style>
<?php /**PATH /home/dahimail.com/public_html/resources/views/components/brand-theme.blade.php ENDPATH**/ ?>