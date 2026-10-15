<?php ($__phoneMode = \App\Support\PhoneSettings::registrationMode()); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($__phoneMode !== 'off'): ?>
    
    <div>
        <label for="phone_national" class="block text-sm font-medium text-muted mb-1">
            <?php echo e(__('Phone number')); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($__phoneMode !== 'required'): ?><span class="text-xs">(<?php echo e(__('optional')); ?>)</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </label>
        <div class="flex gap-2">
            <select name="phone_country" aria-label="<?php echo e(__('Country code')); ?>" class="input w-40 flex-shrink-0"
                    x-data x-init="if (!$el.value) { const r = ((navigator.language || '').split('-')[1] || '').toUpperCase(); if (r && [...$el.options].some(o => o.value === r)) $el.value = r; }">
                <option value=""><?php echo e(__('Country code')); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = config('phone_countries', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $__c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <option value="<?php echo e($__c['iso']); ?>" <?php if(old('phone_country') === $__c['iso']): echo 'selected'; endif; ?>><?php echo e($__c['flag']); ?> <?php echo e($__c['name']); ?> (<?php echo e($__c['dial']); ?>)</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
            <input id="phone_national" type="tel" name="phone_national" value="<?php echo e(old('phone_national')); ?>" inputmode="tel" autocomplete="tel-national"
                   placeholder="<?php echo e(__('Phone number')); ?>" <?php if($__phoneMode === 'required'): ?> required <?php endif; ?>
                   class="input flex-1 min-w-0 <?php $__errorArgs = ['phone_national'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> !border-danger <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['phone_national'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-danger"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <p class="mt-1 text-xs text-muted"><?php echo e(__('Choose your country, then type your number without the country code.')); ?></p>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /home/dahimail.com/public_html/resources/views/auth/partials/phone-field.blade.php ENDPATH**/ ?>