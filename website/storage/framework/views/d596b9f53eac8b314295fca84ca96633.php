<?php if (isset($component)) { $__componentOriginal1e6834b7596effc838ab3adb1475b477 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1e6834b7596effc838ab3adb1475b477 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.guest','data' => ['title' => __('Reset Password')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.guest'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Reset Password'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div>
        <h2 class="text-2xl font-bold text-ink"><?php echo e(__('Reset your password')); ?></h2>
        <p class="mt-2 text-sm text-muted"><?php echo e(__('Choose an available method to reset your password.')); ?></p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!\App\Support\AccountFeatures::emailRecovery() && !\App\Support\AccountFeatures::phraseRecovery()): ?>
            <p class="mt-2 text-sm text-muted"><?php echo e(__('Password recovery is currently disabled. Contact your administrator for help.')); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Support\AccountFeatures::phraseRecovery()): ?>
    <a href="<?php echo e(route('password.phrase')); ?>" class="mt-6 block rounded-xl border border-border bg-surface p-4 hover:border-brand transition-colors">
        <span class="block font-semibold text-brand"><?php echo e(__('Use recovery phrase')); ?></span>
        <span class="mt-1 block text-sm text-muted"><?php echo e(__('Have your saved passphrase? Reset your password without accessing your mailbox.')); ?></span>
    </a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?>
    <div class="mt-4 p-4 bg-success/10 border border-success/20 text-success rounded-xl text-sm">
        <?php echo e(session('status')); ?>

    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Support\AccountFeatures::emailRecovery()): ?>
    <form method="POST" action="<?php echo e(route('password.email')); ?>" class="mt-6 space-y-5"
          x-data="{ submitting: false }" @submit="submitting = true">
        <?php echo csrf_field(); ?>
        <h3 class="text-sm font-semibold text-ink"><?php echo e(__('Send an email reset link')); ?></h3>
        <div>
            <label for="email" class="block text-sm font-medium text-muted mb-1"><?php echo e(__('Email address')); ?></label>
            <input id="email" type="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus
                   class="input <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> !border-danger <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                   placeholder="<?php echo e(__('you@company.com')); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-danger"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <button type="submit" :disabled="submitting"
                class="btn-primary w-full py-3"
                :class="submitting && 'opacity-75 cursor-not-allowed'">
            <span x-show="!submitting"><?php echo e(__('Send Reset Link')); ?></span>
            <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <?php echo e(__('Sending reset link...')); ?>

            </span>
        </button>

        <p class="text-center text-sm text-muted">
            <a href="<?php echo e(route('login')); ?>" class="text-brand font-semibold hover:text-brand-strong"><?php echo e(__('Back to login')); ?></a>
        </p>
    </form>
    <?php else: ?>
        <p class="mt-5 text-center text-sm"><a href="<?php echo e(route('login')); ?>" class="text-brand font-semibold"><?php echo e(__('Back to login')); ?></a></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1e6834b7596effc838ab3adb1475b477)): ?>
<?php $attributes = $__attributesOriginal1e6834b7596effc838ab3adb1475b477; ?>
<?php unset($__attributesOriginal1e6834b7596effc838ab3adb1475b477); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1e6834b7596effc838ab3adb1475b477)): ?>
<?php $component = $__componentOriginal1e6834b7596effc838ab3adb1475b477; ?>
<?php unset($__componentOriginal1e6834b7596effc838ab3adb1475b477); ?>
<?php endif; ?>
<?php /**PATH /home/dahimail.com/public_html/resources/views/auth/forgot-password.blade.php ENDPATH**/ ?>