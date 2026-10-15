<?php $__env->startSection('title', 'Contact Us — ' . config('app.name')); ?>

<?php
    $c = $content ?? [];
    $supportEmail = \App\Models\SystemSetting::get('support_email', 'support@example.com');
?>

<?php $__env->startSection('content'); ?>
<main class="flex-1 pt-32 pb-20 overflow-hidden relative">
    
    <div class="absolute top-0 right-0 w-[50%] h-[50%] bg-brand/5 blur-[120px] rounded-full -z-10"></div>
    <div class="absolute bottom-0 left-0 w-[50%] h-[50%] bg-accent/5 blur-[120px] rounded-full -z-10"></div>

    <div class="container mx-auto px-4">
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-20 items-start">
                
                <div class="space-y-12">
                    <div class="space-y-6">
                        <div x-data="{ shown: false }" x-init="setTimeout(() => shown = true, 0)"
                             class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand/10 border border-brand/20 text-brand text-[10px] font-bold uppercase tracking-widest transition-all duration-700 transform"
                             :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
                            24/7 Support Available
                        </div>
                        <h1 x-data="{ shown: false }" x-init="setTimeout(() => shown = true, 100)"
                            class="text-3xl sm:text-5xl md:text-7xl font-bold tracking-tight text-ink transition-all duration-700 transform"
                            :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'">
                            Let's start a <br>
                            <span class="gradient-text italic"><?php echo e(__("Conversation.")); ?></span>
                        </h1>
                        <p x-data="{ shown: false }" x-init="setTimeout(() => shown = true, 200)"
                           class="text-xl text-muted leading-relaxed max-w-sm transition-all duration-700 transform"
                           :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'">
                            <?php echo e($c['subtitle']); ?>

                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-4 p-6 rounded-3xl bg-surface-2/30 border border-border/50 hover:bg-surface-2/50 transition-colors group">
                            <div class="h-10 w-10 rounded-xl bg-brand/10 flex items-center justify-center text-brand group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm uppercase tracking-widest text-muted mb-1"><?php echo e($c['support_email_label']); ?></h3>
                                <a href="mailto:<?php echo e($supportEmail); ?>" class="font-semibold text-brand hover:underline"><?php echo e($supportEmail); ?></a>
                            </div>
                        </div>
                        <div class="space-y-4 p-6 rounded-3xl bg-surface-2/30 border border-border/50 hover:bg-surface-2/50 transition-colors group">
                            <div class="h-10 w-10 rounded-xl bg-brand/10 flex items-center justify-center text-brand group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm uppercase tracking-widest text-muted mb-1">Avg. Response</h3>
                                <p class="font-semibold text-2xl font-mono"><?php echo e($c['response_time']); ?></p>
                            </div>
                        </div>
                    </div>

                    <p class="text-sm text-muted leading-relaxed"><?php echo e($c['support_channels'] ?? ''); ?></p>
                </div>

                
                <div x-data="{ shown: false, sent: false }" x-init="setTimeout(() => shown = true, 300)"
                     class="p-6 sm:p-8 md:p-12 rounded-[2rem] sm:rounded-[3rem] bg-surface-2 border border-border shadow-2xl relative transition-all duration-700 transform"
                     :class="shown ? 'opacity-100 scale-100' : 'opacity-0 scale-95'">

                    <div x-show="sent" x-cloak class="flex flex-col items-center justify-center py-12 space-y-4 text-center">
                        <div class="h-16 w-16 rounded-full bg-success/10 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-8 w-8 text-success"><path d="M20 6 9 17l-5-5"/></svg>
                        </div>
                        <h3 class="text-xl font-bold"><?php echo e(__("Message Sent!")); ?></h3>
                        <p class="text-sm text-muted">We'll get back to you as soon as possible.</p>
                    </div>

                    <form x-show="!sent" @submit.prevent="sent = true" class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-muted ml-1"><?php echo e(__("First Name")); ?></label>
                                <input type="text" required placeholder="Alex" class="flex h-14 w-full rounded-2xl border border-border bg-surface/50 px-6 py-1 text-base shadow-sm transition-colors placeholder:text-muted focus:outline-none focus:ring-1 focus:ring-brand">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-muted ml-1"><?php echo e(__("Last Name")); ?></label>
                                <input type="text" required placeholder="Rivera" class="flex h-14 w-full rounded-2xl border border-border bg-surface/50 px-6 py-1 text-base shadow-sm transition-colors placeholder:text-muted focus:outline-none focus:ring-1 focus:ring-brand">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-muted ml-1"><?php echo e(__("Email")); ?></label>
                            <input type="email" required placeholder="alex@company.com" class="flex h-14 w-full rounded-2xl border border-border bg-surface/50 px-6 py-1 text-base shadow-sm transition-colors placeholder:text-muted focus:outline-none focus:ring-1 focus:ring-brand">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-muted ml-1"><?php echo e(__("Your Message")); ?></label>
                            <textarea required rows="5" placeholder="How can we help you today?" class="w-full min-h-[160px] rounded-[2rem] bg-surface/50 border border-border focus:ring-1 focus:ring-brand transition-all p-6 text-sm resize-none placeholder:text-muted focus:outline-none"></textarea>
                        </div>
                        <button type="submit" class="w-full h-16 rounded-[2rem] bg-brand hover:bg-brand-strong text-lg font-bold shadow-xl shadow-brand/20 text-white flex items-center justify-center transition-all">
                            Send Message
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-5 w-5"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('frontend.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/dahimail.com/public_html/resources/views/frontend/legal/contact-page.blade.php ENDPATH**/ ?>