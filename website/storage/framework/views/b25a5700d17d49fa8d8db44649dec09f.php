<div class="max-w-4xl mx-auto" x-data="{ showSchedule: <?php if ((object) ('showSchedule') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showSchedule'->value()); ?>')<?php echo e('showSchedule'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showSchedule'); ?>')<?php endif; ?> }">

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('services.channel_test_mode') && $channel !== 'email'): ?>
    <div class="mb-4 p-3 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded-xl text-sm flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
        <span><strong>Test Mode Active</strong> — <?php echo e(ucfirst($channel)); ?> messages will be saved locally but NOT actually delivered. Disable <code>CHANNEL_TEST_MODE</code> in .env for real delivery.</span>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('compose-success')): ?>
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="mb-4 p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <?php echo e(session('compose-success')); ?>

    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('compose-error')): ?>
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
         class="mb-4 p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <?php echo e(session('compose-error')); ?>

    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any() || session('error')): ?>
    <div class="mb-4 p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?><p><?php echo e(session('error')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $compose_validation_error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
            <p><?php echo e($compose_validation_error); ?></p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


    
    <div class="bg-surface-2 rounded-2xl border border-border shadow-sm overflow-hidden">

        
        <div class="px-6 py-3 border-b border-border bg-surface">
            <div class="flex items-center gap-4">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider"><?php echo e(__('Channel')); ?></span>
                <div class="flex items-center gap-1.5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $lockChannel || $channel === 'email'): ?>
                    <button type="button" wire:click="$set('channel', 'email')"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors <?php echo e($channel === 'email' ? 'bg-brand/15 text-brand' : 'text-muted hover:bg-surface '); ?>">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <?php echo e(__('Email')); ?>

                        </span>
                    </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((! $lockChannel || $channel === 'sms') && in_array('sms', $connectedChannels)): ?>
                    <button type="button" wire:click="$set('channel', 'sms')"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors <?php echo e($channel === 'sms' ? 'bg-info/15 text-info' : 'text-muted hover:bg-surface '); ?>">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <?php echo e(__('SMS')); ?>

                        </span>
                    </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((! $lockChannel || $channel === 'whatsapp') && in_array('whatsapp', $connectedChannels)): ?>
                    <button type="button" wire:click="$set('channel', 'whatsapp')"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors <?php echo e($channel === 'whatsapp' ? 'bg-success/15 text-success' : 'text-muted hover:bg-surface '); ?>">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            <?php echo e(__('WhatsApp')); ?>

                        </span>
                    </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((! $lockChannel || $channel === 'telegram') && in_array('telegram', $connectedChannels)): ?>
                    <button type="button" wire:click="$set('channel', 'telegram')"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors <?php echo e($channel === 'telegram' ? 'bg-sky-100 text-sky-700' : 'text-muted hover:bg-surface '); ?>">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <?php echo e(__('Telegram')); ?>

                        </span>
                    </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((! $lockChannel || $channel === 'slack') && in_array('slack', $connectedChannels)): ?>
                    <button type="button" wire:click="switchToSlack"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors <?php echo e($channel === 'slack' ? 'bg-purple-100 text-purple-700' : 'text-muted hover:bg-surface '); ?>">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            <?php echo e(__('Slack')); ?>

                        </span>
                    </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($channel === 'email'): ?>
        
        <div class="px-6 py-3 border-b border-border flex items-center gap-3">
            <label class="text-sm font-medium text-muted w-12 flex-shrink-0"><?php echo e(__('From')); ?></label>
            <select wire:model="fromAccountId"
                    class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $emailAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <option value="<?php echo e($account->id); ?>">
                    <?php echo e($account->display_name ? "{$account->display_name} <{$account->email}>" : $account->email); ?>

                </option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($emailAccounts->isEmpty()): ?>
                <option value="" disabled><?php echo e(__('No connected email accounts')); ?></option>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </div>

        
        <div class="px-6 py-3 border-b border-border" x-data="{ focused: false }">
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-muted w-12 shrink-0"><?php echo e(__('To')); ?></label>
                <div class="flex-1 relative">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedGroupId): ?>
                    
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-50 text-green-800 text-sm font-medium rounded-lg border border-green-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584M15 6.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <?php echo e($selectedGroupName); ?>

                            <span class="text-green-600 text-xs">(<?php echo e($selectedGroupCount); ?> <?php echo e(__('contacts')); ?>)</span>
                            <button type="button" wire:click="clearGroup" class="ml-1 text-green-500 hover:text-green-800 text-lg leading-none">&times;</button>
                        </span>
                    </div>
                    <?php else: ?>
                    <input type="text"
                           wire:model.live.debounce.300ms="toSearch"
                           wire:blur="dismissSuggestions"
                           @focus="focused = true"
                           placeholder="<?php echo e(__('Search contacts, groups, or type email...')); ?>"
                           class="w-full text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 pr-8 text-ink placeholder-gray-400">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <div wire:loading wire:target="toSearch" class="absolute right-3 top-1/2 -translate-y-1/2">
                        <svg class="w-4 h-4 animate-spin text-muted" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </div>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$selectedGroupId && $showContactSuggestions && (!empty($contactSuggestions) || !empty($groupSuggestions))): ?>
                    <div class="absolute top-full left-0 right-0 mt-1 bg-surface-2 rounded-xl border border-border shadow-lg z-50 max-h-64 overflow-y-auto">
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($groupSuggestions)): ?>
                        <div class="px-3 pt-2 pb-1"><span class="text-[10px] font-semibold uppercase tracking-wider text-muted"><?php echo e(__('Groups')); ?></span></div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $groupSuggestions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <button type="button"
                                wire:mousedown.prevent="selectGroup(<?php echo e($group['id']); ?>)"
                                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-surface transition-colors">
                            <span class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center text-xs font-semibold text-green-700 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584M15 6.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-ink truncate"><?php echo e($group['name']); ?></p>
                                <p class="text-xs text-muted"><?php echo e($group['count']); ?> <?php echo e(__('contacts')); ?></p>
                            </div>
                            <span class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-medium"><?php echo e(__('GROUP')); ?></span>
                        </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($contactSuggestions)): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($groupSuggestions)): ?>
                        <div class="px-3 pt-2 pb-1 border-t border-border mt-1"><span class="text-[10px] font-semibold uppercase tracking-wider text-muted"><?php echo e(__('Contacts')); ?></span></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $contactSuggestions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $suggestion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <button type="button"
                                wire:mousedown.prevent="selectContact('<?php echo e($suggestion['email']); ?>', '<?php echo e(addslashes($suggestion['name'])); ?>')"
                                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-surface transition-colors">
                            <span class="w-8 h-8 bg-primary-100 dark:bg-primary-900/30 rounded-full flex items-center justify-center text-xs font-semibold text-primary-700 dark:text-primary-300 flex-shrink-0">
                                <?php echo e($suggestion['initials']); ?>

                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-ink truncate"><?php echo e($suggestion['name']); ?></p>
                                <p class="text-xs text-muted truncate"><?php echo e($suggestion['email']); ?><?php echo e($suggestion['company'] ? " - {$suggestion['company']}" : ''); ?></p>
                            </div>
                        </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                </div>

                
                <button type="button" wire:click="$toggle('showCcBcc')"
                        class="text-xs text-muted hover:text-muted  font-medium flex-shrink-0">
                    <?php echo e($showCcBcc ? __('Hide') : __('Cc/Bcc')); ?>

                </button>
            </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCcBcc): ?>
        <div class="px-6 py-3 border-b border-border flex items-center gap-3" wire:transition>
            <label class="text-sm font-medium text-muted w-12 flex-shrink-0"><?php echo e(__('Cc')); ?></label>
            <input type="text" wire:model="ccEmails"
                   placeholder="<?php echo e(__('Comma-separated emails')); ?>"
                   class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink placeholder-gray-400">
        </div>
        <div class="px-6 py-3 border-b border-border flex items-center gap-3" wire:transition>
            <label class="text-sm font-medium text-muted w-12 flex-shrink-0"><?php echo e(__('Bcc')); ?></label>
            <input type="text" wire:model="bccEmails"
                   placeholder="<?php echo e(__('Comma-separated emails')); ?>"
                   class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink placeholder-gray-400">
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="px-6 py-3 border-b border-border flex items-center gap-3">
            <label class="text-sm font-medium text-muted w-12 flex-shrink-0"><?php echo e(__('Subject')); ?></label>
            <input type="text" wire:model="subject"
                   placeholder="<?php echo e(__('Email subject')); ?>"
                   class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink placeholder-gray-400 font-medium">
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCannedResponses && $cannedResponses->isNotEmpty()): ?>
        <div class="mx-2 mt-2 bg-[#1a1d27] border border-[#2d3039] rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] max-h-48 overflow-y-auto">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $cannedResponses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $canned): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
            <button wire:click="insertCannedResponseById(<?php echo e($canned->id); ?>)"
                    class="w-full text-left px-4 py-2.5 hover:bg-white/5 border-b border-[#2d3039] last:border-0 transition-colors">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-200"><?php echo e($canned->title); ?></span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canned->shortcut): ?>
                    <span class="text-xs text-gray-500 font-mono">/<?php echo e($canned->shortcut); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <p class="text-xs text-gray-500 truncate mt-0.5"><?php echo e(\Illuminate\Support\Str::limit($canned->content, 80)); ?></p>
            </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="px-2 py-2">
            <div x-data="quillEditor('body')" wire:ignore class="quill-wrapper">
                <div x-ref="toolbar">
                    <span class="ql-formats">
                        <select class="ql-font">
                            <option value="">Sans Serif</option>
                            <option value="serif">Serif</option>
                            <option value="monospace">Monospace</option>
                        </select>
                        <select class="ql-size">
                            <option value="small">Small</option>
                            <option selected>Normal</option>
                            <option value="large">Large</option>
                        </select>
                    </span>
                    <span class="ql-formats">
                        <button class="ql-bold"></button>
                        <button class="ql-italic"></button>
                        <button class="ql-underline"></button>
                    </span>
                    <span class="ql-formats">
                        <button class="ql-align" value=""></button>
                        <button class="ql-align" value="center"></button>
                        <button class="ql-align" value="right"></button>
                    </span>
                    <span class="ql-formats">
                        <button class="ql-list" value="bullet"></button>
                        <button class="ql-link"></button>
                    </span>
                </div>
                <div x-ref="editor"
                     data-placeholder="<?php echo e(__('Write your message here...')); ?>"
                     style="min-height: 300px;"></div>
            </div>
        </div>
        <?php else: ?>
        
        <div class="px-6 py-3 border-b border-border flex items-center gap-3">
            <label class="text-sm font-medium text-muted w-12 flex-shrink-0"><?php echo e(__('To')); ?></label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($channel === 'slack'): ?>
                
                <select wire:model.live="toPhone"
                        class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink">
                    <option value=""><?php echo e(__('Select a Slack channel...')); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ($slackChannelsList ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <option value="<?php echo e($ch['id']); ?>"><?php echo e($ch['is_private'] ? '🔒' : '#'); ?> <?php echo e($ch['name']); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            <?php else: ?>
                <input type="text" wire:model="toPhone"
                       placeholder="<?php echo e($channel === 'sms' ? '+1234567890' : ($channel === 'whatsapp' ? __( '+1234567890 (with country code)') : __('@username or chat ID'))); ?>"
                       class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink placeholder-gray-400">
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['toPhone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="px-6 py-1"><p class="text-xs text-red-500"><?php echo e($message); ?></p></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="px-6 py-4">
            <textarea wire:model="messageBody" rows="12"
                      placeholder="<?php echo e(__('Type your')); ?> <?php echo e($channel === 'sms' ? __('SMS') : ($channel === 'whatsapp' ? __('WhatsApp') : ($channel === 'slack' ? __('Slack') : __('Telegram')))); ?> <?php echo e(__('message...')); ?>"
                      class="w-full text-sm border-0 bg-transparent focus:ring-0 focus:outline-none resize-none text-ink placeholder-gray-400 leading-relaxed"></textarea>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['messageBody'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-500 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($channel === 'sms'): ?>
            <p class="text-xs text-muted mt-2"><?php echo e(strlen($messageBody)); ?>/160 <?php echo e(__('characters')); ?> (<?php echo e(max(1, ceil(strlen($messageBody) / 160))); ?> <?php echo e(__('SMS segment')); ?><?php echo e(max(1, ceil(strlen($messageBody) / 160)) > 1 ? 's' : ''); ?>)</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($channel === 'email' && $showAiWrite): ?>
        <div class="mx-6 mb-4 bg-[#1a1d27] rounded-xl border border-[#2d3039] p-5" wire:transition>
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#3b82f6]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <h3 class="text-sm font-semibold text-gray-200"><?php echo e(__('AI Email Writer')); ?></h3>
                </div>
                <button type="button" wire:click="toggleAiWrite" class="text-gray-500 hover:text-gray-300 transition" aria-label="Close AI writer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="space-y-3">
                
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1"><?php echo e(__('What would you like to write about?')); ?></label>
                    <textarea wire:model="aiPrompt" rows="3"
                              class="w-full px-3 py-2 text-sm bg-[#0c0d12] text-gray-200 border border-[#2d3039] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3b82f6]/40 focus:border-[#3b82f6]/50 resize-none placeholder-gray-600"
                              placeholder="<?php echo e(__('e.g., Follow up on our meeting last week about the Q1 marketing budget. Mention the 15% increase we discussed and ask them to confirm the timeline.')); ?>"></textarea>
                </div>

                
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5"><?php echo e(__('Tone')); ?></label>
                    <div class="flex flex-wrap gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['professional' => __('Professional'), 'friendly' => __('Friendly'), 'casual' => __('Casual'), 'persuasive' => __('Persuasive')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $toneKey => $toneLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <button type="button" wire:click="$set('aiTone', '<?php echo e($toneKey); ?>')"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors <?php echo e($aiTone === $toneKey ? 'bg-[#3b82f6] text-white' : 'bg-[#0c0d12] text-gray-400 border border-[#2d3039] hover:bg-white/5 hover:text-gray-200'); ?>">
                            <?php echo e($toneLabel); ?>

                        </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>

                
                <button type="button" wire:click="aiWrite"
                        class="w-full px-4 py-2.5 bg-[#3b82f6] text-white text-sm font-medium rounded-xl hover:bg-[#2563eb] transition-colors flex items-center justify-center gap-2"
                        wire:loading.attr="disabled" wire:target="aiWrite">
                    <span wire:loading.remove wire:target="aiWrite" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <?php echo e(__('Generate Email')); ?>

                    </span>
                    <span wire:loading wire:target="aiWrite" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <?php echo e(__('Generating...')); ?>

                    </span>
                </button>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($aiGeneratedContent): ?>
                <div class="bg-[#0c0d12] rounded-xl border border-[#2d3039] p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-[#3b82f6] uppercase tracking-wider"><?php echo e(__('Generated Email')); ?></span>
                    </div>
                    <div class="text-sm text-gray-300 whitespace-pre-wrap leading-relaxed max-h-64 overflow-y-auto mb-3 hide-scroll"><?php echo e($aiGeneratedContent); ?></div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="acceptAiContent"
                                class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition-colors">
                            <?php echo e(__('Replace Body')); ?>

                        </button>
                        <button type="button" wire:click="insertAiContent"
                                class="px-3 py-1.5 bg-[#3b82f6] text-white text-xs font-medium rounded-lg hover:bg-[#2563eb] transition-colors">
                            <?php echo e(__('Append to Body')); ?>

                        </button>
                        <button type="button" wire:click="regenerateAi"
                                class="px-3 py-1.5 bg-white/5 text-gray-400 text-xs font-medium rounded-lg border border-[#2d3039] hover:bg-white/10 hover:text-gray-200 transition-colors">
                            <?php echo e(__('Regenerate')); ?>

                        </button>
                        <button type="button" wire:click="discardAiContent"
                                class="px-3 py-1.5 text-gray-500 text-xs font-medium hover:text-gray-300 transition-colors">
                            <?php echo e(__('Discard')); ?>

                        </button>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($channel === 'email' && !empty($attachments)): ?>
        <div class="mx-6 mb-4">
            <div class="flex flex-wrap gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($file && method_exists($file, 'getSize') && $file->exists()): ?>
                <div class="flex items-center gap-2 px-3 py-2 bg-surface rounded-lg border border-border text-sm">
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    <span class="text-ink/80 truncate max-w-[200px]"><?php echo e($file->getClientOriginalName()); ?></span>
                    <span class="text-xs text-muted">(<?php echo e(number_format(($file->getSize() ?: 0) / 1024, 0)); ?>KB)</span>
                    <button type="button" wire:click="removeAttachment(<?php echo e($index); ?>)" class="text-muted hover:text-red-500" aria-label="Remove attachment">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($channel === 'email' && $showSchedule): ?>
        <div class="mx-6 mb-4 bg-warning/10 rounded-xl border border-warning/20 p-4" wire:transition>
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-sm font-semibold text-amber-900"><?php echo e(__('Schedule Send')); ?></span>
                </div>
                <button type="button" wire:click="$set('showSchedule', false)"
                        class="p-1 text-muted hover:text-ink/80 rounded-lg hover:bg-warning/10 transition-colors" aria-label="Close schedule panel">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            
            <div class="flex flex-wrap gap-2 mb-3">
                <button type="button" wire:click="setSchedulePreset('in_2_hours')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg border border-warning/20 bg-surface-2 text-amber-800 hover:bg-warning/15 transition-colors">
                    <?php echo e(__('In 2 hours')); ?>

                </button>
                <button type="button" wire:click="setSchedulePreset('in_4_hours')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg border border-warning/20 bg-surface-2 text-amber-800 hover:bg-warning/15 transition-colors">
                    <?php echo e(__('In 4 hours')); ?>

                </button>
                <button type="button" wire:click="setSchedulePreset('tomorrow_9am')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg border border-warning/20 bg-surface-2 text-amber-800 hover:bg-warning/15 transition-colors">
                    <?php echo e(__('Tomorrow 9 AM')); ?>

                </button>
                <button type="button" wire:click="setSchedulePreset('monday_9am')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg border border-warning/20 bg-surface-2 text-amber-800 hover:bg-warning/15 transition-colors">
                    <?php echo e(__('Monday 9 AM')); ?>

                </button>
            </div>

            
            <div class="flex items-center gap-3">
                <input type="datetime-local" wire:model="scheduledAt"
                       min="<?php echo e(now()->addMinutes(5)->format('Y-m-d\TH:i')); ?>"
                       class="flex-1 px-3 py-2 text-sm bg-surface-2 border border-warning/20 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent">
                <button type="button" wire:click="scheduleSend"
                        class="px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-xl hover:bg-amber-700 transition-colors flex items-center gap-2"
                        wire:loading.attr="disabled" wire:target="scheduleSend">
                    <span wire:loading.remove wire:target="scheduleSend"><?php echo e(__('Schedule')); ?></span>
                    <span wire:loading wire:target="scheduleSend" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <?php echo e(__('Scheduling...')); ?>

                    </span>
                </button>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['scheduledAt'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-xs text-danger mt-1.5"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <p class="text-xs text-muted mt-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scheduledAt): ?>
                    <?php echo e(__('Scheduled for')); ?> <span class="font-medium text-amber-700"><?php echo e(\Carbon\Carbon::parse($scheduledAt)->format('M j, Y \a\t g:i A')); ?></span>
                <?php else: ?>
                    <?php echo e(__('Pick a time or use a preset above')); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </p>

            
            <p class="text-[11px] text-muted/80 mt-1 flex items-start gap-1">
                <svg class="w-3 h-3 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><?php echo e(__('Scheduled messages may be delayed by 1-2 minutes.')); ?></span>
            </p>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
        <div class="mx-6 mb-3">
            <div class="p-3 bg-danger/10 rounded-xl border border-danger/20">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <p class="text-xs text-danger"><?php echo e($error); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="px-6 py-4 border-t border-border bg-surface flex items-center justify-between">
            <div class="flex items-center gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($channel === 'email'): ?>
                
                <label class="p-2 text-muted hover:text-muted  hover:bg-surface  rounded-lg cursor-pointer transition-colors" title="<?php echo e(__('Attach file')); ?>"" aria-label="Attach file">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    <input type="file" wire:model="attachments" multiple class="hidden" accept="*/*">
                </label>

                
                <button type="button" wire:click="toggleAiWrite"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg transition-colors <?php echo e($showAiWrite ? 'bg-brand/15 text-brand' : 'text-muted hover:text-purple-600 hover:bg-brand/10'); ?>"
                        title="<?php echo e(__('AI Write')); ?>"">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <?php echo e(__('AI Write')); ?>

                </button>

                
                <button type="button" wire:click="$toggle('showSchedule')"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg transition-colors <?php echo e($showSchedule ? 'bg-warning/15 text-warning' : 'text-muted hover:text-amber-600 hover:bg-warning/10'); ?>"
                        title="<?php echo e(__('Schedule send')); ?>"">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?php echo e(__('Schedule')); ?>

                </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="flex items-center gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($channel === 'email'): ?>
                
                <button type="button" wire:click="saveDraft"
                        class="px-4 py-2 text-sm font-medium text-muted  bg-surface  rounded-xl hover:bg-gray-200 dark:bg-gray-700 transition-colors"
                        wire:loading.attr="disabled" wire:target="saveDraft">
                    <span wire:loading.remove wire:target="saveDraft"><?php echo e(__('Save Draft')); ?></span>
                    <span wire:loading wire:target="saveDraft" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <?php echo e(__('Saving...')); ?>

                    </span>
                </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <button type="button" wire:click="sendMessage"
                        class="px-5 py-2 text-sm font-medium text-white bg-primary-600 rounded-xl hover:bg-primary-700 transition-colors flex items-center gap-2"
                        wire:loading.attr="disabled" wire:target="sendMessage">
                    <span wire:loading.remove wire:target="sendMessage" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                        <?php echo e($channel === 'email' ? __('Send Email') : ($channel === 'sms' ? __('Send SMS') : ($channel === 'whatsapp' ? __('Send WhatsApp') : __('Send Message')))); ?>

                    </span>
                    <span wire:loading wire:target="sendMessage" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <?php echo e(__('Sending...')); ?>

                    </span>
                </button>
            </div>
        </div>
    </div>

    
    <div x-data="{ showWarning: false }"
         x-init="setTimeout(() => showWarning = true, <?php echo e((config('session.lifetime', 120) - 5) * 60 * 1000); ?>)"
         x-show="showWarning" x-transition
         class="fixed bottom-4 right-4 z-50 bg-warning/10 border border-warning/30 rounded-xl p-4 shadow-lg max-w-sm"
         x-cloak>
        <p class="text-sm font-medium text-ink"><?php echo e(__('Session expiring soon')); ?></p>
        <p class="text-xs text-muted mt-1"><?php echo e(__('Your session is about to expire. Click anywhere to stay logged in. Your draft is saved.')); ?></p>
        <button type="button" @click="fetch('/sanctum/csrf-cookie'); showWarning = false" class="mt-2 px-3 py-1 bg-brand text-white text-xs font-medium rounded-lg hover:bg-brand-strong transition-colors">
            <?php echo e(__('Stay Logged In')); ?>

        </button>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showUndoBar): ?>
    <div class="fixed top-4 right-4 z-50"
         x-data="{ countdown: <?php if ((object) ('undoCountdown') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('undoCountdown'->value()); ?>')<?php echo e('undoCountdown'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('undoCountdown'); ?>')<?php endif; ?>, timer: null }"
         x-init="timer = setInterval(() => { countdown--; if (countdown <= 0) { clearInterval(timer); $wire.confirmSend(); } }, 1000)"
         x-on:undo-cancelled.window="clearInterval(timer)">
        <div class="bg-[#1a1d27] text-gray-200 rounded-xl px-5 py-3 shadow-[0_10px_30px_rgba(0,0,0,0.5)] border border-white/10 flex items-center gap-4">
            <span class="text-sm"><?php echo e(__('Email sending in')); ?> <strong x-text="countdown"></strong><?php echo e(__('s...')); ?></span>
            <button wire:click="undoSend" @click="clearInterval(timer); $dispatch('undo-cancelled')" class="text-sm font-semibold text-yellow-400 hover:text-yellow-300 underline">
                <?php echo e(__('Undo')); ?>

            </button>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH /home/dahimail.com/public_html/resources/views/livewire/inbox/compose-email.blade.php ENDPATH**/ ?>