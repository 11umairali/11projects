<div class="workspace-switcher px-3 pb-4 pt-1" x-show="!sidebarCollapsed">
    <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false">
        <button x-on:click="open = !open"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border border-border bg-surface-2/80 shadow-sm text-sm text-ink hover:bg-surface hover:border-brand/30 transition-all group"
                aria-label="<?php echo e(__('Switch workspace')); ?>" aria-haspopup="listbox" :aria-expanded="open">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-brand text-white text-[11px] font-bold shadow-soft">
                <?php echo e(substr($activeWorkspace?->name ?? 'W', 0, 1)); ?>

            </span>
            <span class="flex-1 text-left text-[13px] font-semibold truncate"><?php echo e($activeWorkspace?->name ?? __('My Workspace')); ?></span>
            <svg viewBox="0 0 24 24" class="h-4 w-4 text-muted shrink-0 group-hover:text-ink transition-colors" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/></svg>
        </button>
        <div x-show="open" x-transition
             class="absolute left-0 right-0 mt-1.5 rounded-xl border border-border/70 bg-surface-2 shadow-soft p-2 z-50 overflow-hidden max-h-72 overflow-y-auto" role="listbox" style="display: none;">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $workspaces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <button wire:click="switchWorkspace(<?php echo e($ws->id); ?>)" @click="open = false"
                   class="w-full flex items-center gap-2 px-3 py-2 text-sm rounded-lg transition-colors <?php echo e($ws->id === auth()->user()->active_workspace_id ? 'bg-brand/10 text-brand font-medium' : 'text-muted hover:text-ink hover:bg-surface'); ?>"
                   role="option" aria-selected="<?php echo e($ws->id === auth()->user()->active_workspace_id ? 'true' : 'false'); ?>">
                    <span class="w-2 h-2 rounded-full shrink-0 <?php echo e($ws->id === auth()->user()->active_workspace_id ? 'bg-brand' : 'bg-muted/40'); ?>"></span>
                    <span class="truncate flex-1 text-left"><?php echo e($ws->name); ?></span>
                    <span class="text-[10px] text-muted/60 capitalize"><?php echo e($ws->pivot->role); ?></span>
                </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <hr class="my-1.5 border-border">
            
            <button wire:click="openCreateModal" @click="open = false"
                    class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-brand hover:bg-brand/5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <?php echo e(__('Create New Workspace')); ?>

            </button>
            <a href="<?php echo e(url('/settings/workspace')); ?>" wire:navigate @click="open = false"
               class="block rounded-lg px-3 py-2 text-xs font-medium text-muted hover:bg-brand/5 hover:text-brand transition-colors">
                <?php echo e(__('Manage workspace')); ?> &rarr;
            </a>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCreateModal): ?>
    <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-y-auto" aria-modal="true" role="dialog">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black/50" wire:click="closeCreateModal"></div>
                <div class="relative bg-surface-2 rounded-2xl shadow-xl max-w-md w-full p-6" x-trap="true">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h2 class="text-lg font-semibold text-ink"><?php echo e(__('Create New Workspace')); ?></h2>
                            <p class="text-sm text-muted mt-1"><?php echo e(__('Set up a new workspace for your team or project.')); ?></p>
                        </div>
                        <button wire:click="closeCreateModal" class="p-1.5 text-muted hover:text-ink rounded-lg hover:bg-surface">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form wire:submit="createWorkspace" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-ink/80 mb-1.5"><?php echo e(__('Workspace Name')); ?> <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="newWorkspaceName" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 bg-surface" placeholder="<?php echo e(__('My Company')); ?>" autofocus>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newWorkspaceName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-500 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-ink/80 mb-1.5"><?php echo e(__('Industry')); ?></label>
                            <select wire:model="newWorkspaceIndustry" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 bg-surface">
                                <option value=""><?php echo e(__('Select industry...')); ?></option>
                                <option value="saas"><?php echo e(__('SaaS / Software')); ?></option>
                                <option value="ecommerce"><?php echo e(__('E-commerce')); ?></option>
                                <option value="education"><?php echo e(__('Education')); ?></option>
                                <option value="healthcare"><?php echo e(__('Healthcare')); ?></option>
                                <option value="finance"><?php echo e(__('Finance')); ?></option>
                                <option value="consulting"><?php echo e(__('Consulting')); ?></option>
                                <option value="agency"><?php echo e(__('Agency')); ?></option>
                                <option value="real-estate"><?php echo e(__('Real Estate')); ?></option>
                                <option value="other"><?php echo e(__('Other')); ?></option>
                            </select>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" wire:click="closeCreateModal" class="btn-secondary text-sm"><?php echo e(__('Cancel')); ?></button>
                            <button type="submit" class="btn-primary px-5 py-2.5 text-sm">
                                <span wire:loading.remove wire:target="createWorkspace"><?php echo e(__('Create Workspace')); ?></span>
                                <span wire:loading wire:target="createWorkspace" class="inline-flex items-center gap-2">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <?php echo e(__('Creating...')); ?>

                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /home/dahimail.com/public_html/resources/views/livewire/workspace-switcher.blade.php ENDPATH**/ ?>