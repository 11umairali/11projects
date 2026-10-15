<?php if (isset($component)) { $__componentOriginal5863877a5171c196453bfa0bd807e410 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5863877a5171c196453bfa0bd807e410 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.app','data' => ['title' => __('Meetings')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.app'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Meetings'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="max-w-4xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-ink"><?php echo e(__('Meetings')); ?></h1>
            <p class="text-sm text-muted mt-1"><?php echo e(__('Video meetings built into :app. Share a link, let people in from the waiting room, share your screen and chat. Best with up to 6–8 people.', ['app' => config('app.name')])); ?></p>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?><div class="rounded-xl border border-green-300 bg-green-50 text-green-800 px-4 py-3 text-sm break-all"><?php echo e(session('status')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?><div class="rounded-xl border border-red-300 bg-red-50 text-red-800 px-4 py-3 text-sm"><?php echo e(session('error')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="rounded-xl border border-red-300 bg-red-50 text-red-800 px-4 py-3 text-sm"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="grid gap-4 sm:grid-cols-2">
            <form method="POST" action="<?php echo e(url('/meetings')); ?>" class="bg-surface-2 rounded-2xl border border-border p-5 space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="mode" value="now">
                <input type="hidden" name="waiting_room" value="1"><input type="hidden" name="allow_guests" value="1">
                <div class="font-medium text-ink"><?php echo e(__('Start a meeting now')); ?></div>
                <p class="text-sm text-muted"><?php echo e(__('Opens a room right away. Then share the link.')); ?></p>
                <button type="submit" class="btn-primary"><?php echo e(__('New meeting')); ?></button>
            </form>
            <div class="bg-surface-2 rounded-2xl border border-border p-5 space-y-3">
                <div class="font-medium text-ink"><?php echo e(__('Join with a code or link')); ?></div>
                <div class="flex gap-2">
                    <input id="mt-code" type="text" class="input flex-1" placeholder="abc-defg-hij" autocomplete="off">
                    <button type="button" class="btn-secondary" onclick="(function(){var v=document.getElementById('mt-code').value.trim();var m=v.match(/meet\/([a-z0-9\-]+)/i);var c=(m?m[1]:v).replace(/[^a-z0-9]/gi,'').toLowerCase();if(c)location.href='/meet/'+c;})()"><?php echo e(__('Join')); ?></button>
                </div>
            </div>
        </div>

        <details class="bg-surface-2 rounded-2xl border border-border p-5" <?php if($errors->any() || request()->filled('invite_user_id')): ?> open <?php endif; ?>>
            <summary class="font-medium text-ink cursor-pointer"><?php echo e(__('Schedule a meeting')); ?></summary>
            <form method="POST" action="<?php echo e(url('/meetings')); ?>" class="mt-4 space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="mode" value="schedule">
                <div><label class="block text-xs text-muted mb-1"><?php echo e(__('Title')); ?></label><input type="text" name="title" value="<?php echo e(old('title')); ?>" maxlength="150" class="input w-full" placeholder="<?php echo e(__('Weekly sync')); ?>"></div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div><label class="block text-xs text-muted mb-1"><?php echo e(__('Date')); ?></label><input type="date" name="date" value="<?php echo e(old('date', now()->format('Y-m-d'))); ?>" class="input w-full" required></div>
                    <div><label class="block text-xs text-muted mb-1"><?php echo e(__('Time')); ?></label><input type="time" name="time" value="<?php echo e(old('time', '10:00')); ?>" class="input w-full" required></div>
                    <div><label class="block text-xs text-muted mb-1"><?php echo e(__('Length (minutes)')); ?></label><input type="number" name="duration" value="<?php echo e(old('duration', 45)); ?>" min="15" max="480" step="5" class="input w-full"></div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($people)): ?>
                    <div>
                        <label class="block text-xs text-muted mb-1"><?php echo e(__('Invite friends and teammates')); ?></label>
                        <div class="max-h-44 overflow-y-auto rounded-xl border border-border divide-y divide-border">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $people; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <label class="flex items-center gap-3 px-3 py-2 cursor-pointer"><input type="checkbox" name="invitees[]" value="<?php echo e($p['id']); ?>" <?php if(in_array($p['id'], old('invitees', [(int) request('invite_user_id')])) ): echo 'checked'; endif; ?>><span class="text-sm text-ink"><?php echo e($p['name']); ?></span></label>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="space-y-2 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="waiting_room" value="1" checked> <?php echo e(__('Waiting room: I let people in')); ?></label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="allow_guests" value="1" checked> <?php echo e(__('Allow guests with the link (they always wait for me)')); ?></label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="mute_on_entry" value="1"> <?php echo e(__('Mute people when they join')); ?></label>
                </div>
                <button type="submit" class="btn-primary"><?php echo e(__('Schedule')); ?></button>
            </form>
        </details>

        <div id="meeting-lists" class="space-y-6">
            <?php echo $__env->make('meetings._list', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

    </div>
    <script src="<?php echo e(asset('js/meeting-list.js')); ?>?v=20261007"></script>
    <script>
        function shareMeeting(title, url, code, when) {
            var text = title + (when ? '\n' + when : '') + '\nJoin: ' + url + '\nCode: ' + code;
            if (navigator.share) { navigator.share({ title: title, text: text, url: url }).catch(function () {}); return; }
            (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { alert('<?php echo e(__('Invitation copied')); ?>'); }).catch(function () { window.prompt('<?php echo e(__('Copy the invitation:')); ?>', text); });
        }
        (function () {
            function f(s) { s = Math.abs(s); var d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60); return d ? d + 'd ' + h + 'h' : h ? h + 'h ' + m + 'min' : m + ' min'; }
            function tick() {
                document.querySelectorAll('[data-start]').forEach(function (e) {
                    if (e.dataset.live === '1') { e.textContent = '<?php echo e(__('In progress')); ?>'; return; }
                    if (!e.dataset.start) { e.textContent = ''; return; }
                    var s = Math.round((new Date(e.dataset.start).getTime() - Date.now()) / 1000);
                    e.textContent = s > 0 ? '<?php echo e(__('Starts in')); ?> ' + f(s) : '<?php echo e(__('Time to start')); ?>';
                });
            }
            tick(); setInterval(tick, 30000);
        })();
    </script>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5863877a5171c196453bfa0bd807e410)): ?>
<?php $attributes = $__attributesOriginal5863877a5171c196453bfa0bd807e410; ?>
<?php unset($__attributesOriginal5863877a5171c196453bfa0bd807e410); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5863877a5171c196453bfa0bd807e410)): ?>
<?php $component = $__componentOriginal5863877a5171c196453bfa0bd807e410; ?>
<?php unset($__componentOriginal5863877a5171c196453bfa0bd807e410); ?>
<?php endif; ?>
<?php /**PATH /home/dahimail.com/public_html/resources/views/meetings/index.blade.php ENDPATH**/ ?>