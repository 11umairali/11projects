<div>
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-ink"><?php echo e(__('Quick Replies')); ?></h3>
        <button wire:click="$toggle('showForm')" class="text-xs text-brand hover:text-brand-strong font-medium">
            <?php echo e($showForm ? __('Cancel') : __('+ New')); ?>

        </button>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showForm): ?>
    <div class="bg-surface rounded-xl border border-border p-4 mb-4 space-y-3">
        <div>
            <label class="block text-xs font-medium text-muted mb-1"><?php echo e(__('Title')); ?> <span class="text-danger">*</span></label>
            <input type="text" wire:model="title" class="input w-full text-sm" placeholder="<?php echo e(__('e.g. Greeting')); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-danger mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div>
            <label class="block text-xs font-medium text-muted mb-1"><?php echo e(__('Shortcut')); ?></label>
            <div class="flex items-center gap-1">
                <span class="text-muted text-sm">/</span>
                <input type="text" wire:model="shortcut" class="input w-full text-sm" placeholder="<?php echo e(__('greeting')); ?>">
            </div>
            <p class="text-xs text-muted mt-1"><?php echo e(__('Type /shortcut in the composer to quickly insert')); ?></p>
        </div>
        <div>
            <label class="block text-xs font-medium text-muted mb-1"><?php echo e(__('Content')); ?> <span class="text-danger">*</span></label>
            <textarea wire:model="content" class="input w-full text-sm" rows="4" placeholder="<?php echo e(__('Type your quick reply...')); ?>"></textarea>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['content'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-danger mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div>
            <label class="block text-xs font-medium text-muted mb-1"><?php echo e(__('Visibility')); ?></label>
            <select wire:model="scope" class="input w-full text-sm">
                <option value="personal"><?php echo e(__('Only me')); ?></option>
                <option value="team"><?php echo e(__('Entire team')); ?></option>
            </select>
        </div>
        <button wire:click="save" class="btn-primary w-full text-sm">
            <?php echo e($editingId ? __('Update') : __('Save')); ?> <?php echo e(__('Quick Reply')); ?>

        </button>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="space-y-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $responses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $response): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
        <div class="group flex items-start gap-3 p-3 rounded-lg border border-border hover:border-brand/30 bg-surface-2 transition-colors cursor-pointer"
             wire:click="insert(<?php echo e($response->id); ?>)">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <p class="text-sm font-medium text-ink truncate"><?php echo e($response->title); ?></p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($response->shortcut): ?>
                    <span class="text-xs text-muted bg-surface px-1.5 py-0.5 rounded font-mono">/<?php echo e($response->shortcut); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($response->scope === 'team'): ?>
                    <span class="text-xs text-brand bg-brand/10 px-1.5 py-0.5 rounded"><?php echo e(__('Team')); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <p class="text-xs text-muted mt-0.5 line-clamp-2"><?php echo e($response->content); ?></p>
            </div>
            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                <button wire:click.stop="edit(<?php echo e($response->id); ?>)" class="p-1 text-muted hover:text-ink rounded" title="<?php echo e(__('Edit')); ?>"">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button wire:click.stop="delete(<?php echo e($response->id); ?>)" wire:confirm="Delete this quick reply? This action cannot be undone." class="p-1 text-muted hover:text-danger rounded" title="<?php echo e(__('Delete')); ?>"">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <div class="text-center py-6">
            <p class="text-sm text-muted"><?php echo e(__('No quick replies yet')); ?></p>
            <p class="text-xs text-muted mt-1"><?php echo e(__('Create one to speed up your responses')); ?></p>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH /home/dahimail.com/public_html/resources/views/livewire/inbox/canned-response-manager.blade.php ENDPATH**/ ?>