<?php
    $__sn = \App\Models\SystemSetting::get('site_name', config('app.name'));
    $__fav = \App\Models\SystemSetting::get('favicon');
    $__logoL = \App\Models\SystemSetting::get('logo_light');
    $__logoD = \App\Models\SystemSetting::get('logo_dark');
?>
<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e($textDirection ?? 'ltr'); ?>"
      x-data="frontendLayout()"
      x-init="init()"
      @scroll.window="scrolled = window.scrollY > 20"
      :class="{ dark: theme === 'dark' }"
      :data-theme="theme"
      class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo $__env->yieldContent('title', $__sn . ' – AI-Powered Email Automation & CRM SaaS'); ?></title>
    <meta name="description" content="<?php echo $__env->yieldContent('meta_description', $__sn . ' – AI-Powered Email Automation & CRM SaaS. Manage inbox, contacts, campaigns, and workflows.'); ?>">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($__fav): ?>
        <link rel="icon" href="<?php echo e(asset('storage/' . $__fav)); ?>" type="image/png">
    <?php else: ?>
        <link rel="icon" href="<?php echo e(asset('favicon.ico')); ?>" sizes="any">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php $pwaEnabled = \App\Models\SystemSetting::get('pwa_enabled', 'false') === 'true'; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pwaEnabled): ?>
    <link rel="manifest" href="<?php echo e(route('pwa.manifest')); ?>">
    <meta name="theme-color" content="<?php echo e(\App\Models\SystemSetting::get('pwa_theme_color', '#6366f1')); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo e(\App\Models\SystemSetting::get('pwa_app_name', \App\Models\SystemSetting::get('site_name', 'MailTrixy'))); ?>">
    <?php $pwaIcon = \App\Models\SystemSetting::get('pwa_icon'); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pwaIcon): ?>
    <link rel="apple-touch-icon" href="<?php echo e(asset('storage/' . $pwaIcon)); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js']); ?>
    <?php if (isset($component)) { $__componentOriginal6328591391b02ca7d72027c0d6027f6b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6328591391b02ca7d72027c0d6027f6b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.brand-theme','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('brand-theme'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6328591391b02ca7d72027c0d6027f6b)): ?>
<?php $attributes = $__attributesOriginal6328591391b02ca7d72027c0d6027f6b; ?>
<?php unset($__attributesOriginal6328591391b02ca7d72027c0d6027f6b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6328591391b02ca7d72027c0d6027f6b)): ?>
<?php $component = $__componentOriginal6328591391b02ca7d72027c0d6027f6b; ?>
<?php unset($__componentOriginal6328591391b02ca7d72027c0d6027f6b); ?>
<?php endif; ?>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>


    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <?php echo $__env->yieldPushContent('styles'); ?>
    
    <?php $__customCss = \App\Models\SystemSetting::get('custom_css', ''); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(trim($__customCss) !== ''): ?>
        <style id="admin-custom-css"><?php echo $__customCss; ?></style>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    
    <?php echo \App\Models\SystemSetting::get('head_code', ''); ?>

</head>
<body class="bg-surface text-ink antialiased font-sans overflow-x-hidden">

    
    <nav class="fixed top-0 inset-x-0 z-50 transition-all duration-300 bg-surface-2/90 glass-nav border-b border-border/60"
         :class="scrolled ? 'shadow-soft' : ''"
         aria-label="Main navigation">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 lg:h-20">
                
                <a href="<?php echo e(url('/')); ?>" class="flex items-center gap-2.5 group" aria-label="<?php echo e($__sn); ?> home">
                    <?php if (isset($component)) { $__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.brand-logo','data' => ['name' => $__sn]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('brand-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($__sn)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3)): ?>
<?php $attributes = $__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3; ?>
<?php unset($__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3)): ?>
<?php $component = $__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3; ?>
<?php unset($__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3); ?>
<?php endif; ?>
                </a>

                
                <div class="hidden lg:flex items-center gap-1">
                    <a href="<?php echo e(url('/')); ?>" class="px-4 py-2 text-sm font-medium text-muted hover:text-ink rounded-lg hover:bg-surface-2/60 transition-colors"><?php echo e(__("Home")); ?></a>
                    <a href="#features" class="px-4 py-2 text-sm font-medium text-muted hover:text-ink rounded-lg hover:bg-surface-2/60 transition-colors"><?php echo e(__("Features")); ?></a>
                    <a href="#integrations" class="px-4 py-2 text-sm font-medium text-muted hover:text-ink rounded-lg hover:bg-surface-2/60 transition-colors"><?php echo e(__("Integrations")); ?></a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!\App\Support\BillingPolicy::isFree()): ?><a href="#pricing" class="px-4 py-2 text-sm font-medium text-muted hover:text-ink rounded-lg hover:bg-surface-2/60 transition-colors"><?php echo e(__("Pricing")); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <a href="#testimonials" class="px-4 py-2 text-sm font-medium text-muted hover:text-ink rounded-lg hover:bg-surface-2/60 transition-colors"><?php echo e(__("Testimonials")); ?></a>
                </div>

                
                <div class="hidden lg:flex items-center gap-3">
                    <?php if (isset($component)) { $__componentOriginal8d3bff7d7383a45350f7495fc470d934 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8d3bff7d7383a45350f7495fc470d934 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.language-switcher','data' => ['buttonClass' => 'flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-muted hover:text-ink rounded-xl hover:bg-surface-2/60 transition-colors']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('language-switcher'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['buttonClass' => 'flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-muted hover:text-ink rounded-xl hover:bg-surface-2/60 transition-colors']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8d3bff7d7383a45350f7495fc470d934)): ?>
<?php $attributes = $__attributesOriginal8d3bff7d7383a45350f7495fc470d934; ?>
<?php unset($__attributesOriginal8d3bff7d7383a45350f7495fc470d934); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8d3bff7d7383a45350f7495fc470d934)): ?>
<?php $component = $__componentOriginal8d3bff7d7383a45350f7495fc470d934; ?>
<?php unset($__componentOriginal8d3bff7d7383a45350f7495fc470d934); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginal2090438866f3dcdb76cd8b070bcc302d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2090438866f3dcdb76cd8b070bcc302d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.theme-toggle','data' => ['buttonClass' => 'inline-flex h-9 w-9 items-center justify-center rounded-xl border border-border bg-surface-2/80 text-ink/80 transition hover:text-ink hover:border-brand/40']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('theme-toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['buttonClass' => 'inline-flex h-9 w-9 items-center justify-center rounded-xl border border-border bg-surface-2/80 text-ink/80 transition hover:text-ink hover:border-brand/40']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2090438866f3dcdb76cd8b070bcc302d)): ?>
<?php $attributes = $__attributesOriginal2090438866f3dcdb76cd8b070bcc302d; ?>
<?php unset($__attributesOriginal2090438866f3dcdb76cd8b070bcc302d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2090438866f3dcdb76cd8b070bcc302d)): ?>
<?php $component = $__componentOriginal2090438866f3dcdb76cd8b070bcc302d; ?>
<?php unset($__componentOriginal2090438866f3dcdb76cd8b070bcc302d); ?>
<?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                    <a href="<?php echo e(url('/dashboard')); ?>" class="px-5 py-2 text-sm font-semibold text-white bg-gradient-to-r from-brand to-brand-strong rounded-xl shadow-lg shadow-brand/20 hover:shadow-brand/40 hover:brightness-110 transition-all"><?php echo e(__("Dashboard")); ?></a>
                    <?php else: ?>
                    <a href="<?php echo e(url('/login')); ?>" class="px-4 py-2 text-sm font-semibold text-ink border border-border rounded-xl hover:border-brand/40 hover:bg-surface-2/60 transition-all"><?php echo e(__("Login")); ?></a>
                    <a href="<?php echo e(url('/register')); ?>" class="px-5 py-2 text-sm font-semibold text-white bg-gradient-to-r from-brand to-brand-strong rounded-xl shadow-lg shadow-brand/20 hover:shadow-brand/40 hover:brightness-110 transition-all"><?php echo e(__("Get Started Free")); ?></a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <div class="flex items-center gap-2 lg:hidden">
                    <?php if (isset($component)) { $__componentOriginal2090438866f3dcdb76cd8b070bcc302d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2090438866f3dcdb76cd8b070bcc302d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.theme-toggle','data' => ['buttonClass' => 'inline-flex h-10 w-10 items-center justify-center rounded-xl text-muted hover:text-ink hover:bg-surface-2/60 transition-colors','iconClass' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('theme-toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['buttonClass' => 'inline-flex h-10 w-10 items-center justify-center rounded-xl text-muted hover:text-ink hover:bg-surface-2/60 transition-colors','iconClass' => 'h-5 w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2090438866f3dcdb76cd8b070bcc302d)): ?>
<?php $attributes = $__attributesOriginal2090438866f3dcdb76cd8b070bcc302d; ?>
<?php unset($__attributesOriginal2090438866f3dcdb76cd8b070bcc302d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2090438866f3dcdb76cd8b070bcc302d)): ?>
<?php $component = $__componentOriginal2090438866f3dcdb76cd8b070bcc302d; ?>
<?php unset($__componentOriginal2090438866f3dcdb76cd8b070bcc302d); ?>
<?php endif; ?>
                    <button @click="mobileMenu = !mobileMenu" class="p-2 rounded-xl text-muted hover:text-ink hover:bg-surface-2/60 transition-colors" aria-label="Toggle menu">
                        <svg x-show="!mobileMenu" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                        <svg x-show="mobileMenu" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>

        
        <div x-show="mobileMenu" x-cloak
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2"
             class="lg:hidden border-t border-border/50 bg-surface-2/95 glass-nav">
            <div class="max-w-7xl mx-auto px-4 py-4 space-y-1">
                <a href="<?php echo e(url('/')); ?>" @click="mobileMenu = false" class="block px-4 py-3 text-sm font-medium text-muted hover:text-ink rounded-xl hover:bg-surface-2/60 transition-colors"><?php echo e(__("Home")); ?></a>
                <a href="#features" @click="mobileMenu = false" class="block px-4 py-3 text-sm font-medium text-muted hover:text-ink rounded-xl hover:bg-surface-2/60 transition-colors"><?php echo e(__("Features")); ?></a>
                <a href="#integrations" @click="mobileMenu = false" class="block px-4 py-3 text-sm font-medium text-muted hover:text-ink rounded-xl hover:bg-surface-2/60 transition-colors"><?php echo e(__("Integrations")); ?></a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!\App\Support\BillingPolicy::isFree()): ?><a href="#pricing" @click="mobileMenu = false" class="block px-4 py-3 text-sm font-medium text-muted hover:text-ink rounded-xl hover:bg-surface-2/60 transition-colors"><?php echo e(__("Pricing")); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <a href="#testimonials" @click="mobileMenu = false" class="block px-4 py-3 text-sm font-medium text-muted hover:text-ink rounded-xl hover:bg-surface-2/60 transition-colors"><?php echo e(__("Testimonials")); ?></a>
                <div class="pt-3 border-t border-border/50 space-y-2">
                    <div class="px-4 py-2">
                        <?php if (isset($component)) { $__componentOriginal8d3bff7d7383a45350f7495fc470d934 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8d3bff7d7383a45350f7495fc470d934 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.language-switcher','data' => ['dropdownAlign' => 'left']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('language-switcher'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['dropdownAlign' => 'left']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8d3bff7d7383a45350f7495fc470d934)): ?>
<?php $attributes = $__attributesOriginal8d3bff7d7383a45350f7495fc470d934; ?>
<?php unset($__attributesOriginal8d3bff7d7383a45350f7495fc470d934); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8d3bff7d7383a45350f7495fc470d934)): ?>
<?php $component = $__componentOriginal8d3bff7d7383a45350f7495fc470d934; ?>
<?php unset($__componentOriginal8d3bff7d7383a45350f7495fc470d934); ?>
<?php endif; ?>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                    <a href="<?php echo e(url('/dashboard')); ?>" class="block text-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-brand to-brand-strong rounded-xl"><?php echo e(__("Dashboard")); ?></a>
                    <?php else: ?>
                    <a href="<?php echo e(url('/login')); ?>" class="block text-center px-4 py-2.5 text-sm font-semibold text-ink border border-border rounded-xl hover:border-brand/40 transition-colors"><?php echo e(__("Login")); ?></a>
                    <a href="<?php echo e(url('/register')); ?>" class="block text-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-brand to-brand-strong rounded-xl"><?php echo e(__("Get Started Free")); ?></a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    
    <main>
        <?php echo $__env->yieldContent('content'); ?>
    </main>

    
    <?php $__fc = \App\Models\Page::where('type', 'footer')->first()?->content ?? []; ?>
    <footer class="border-t border-border/50 bg-surface-2/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid grid-cols-1 gap-12 lg:grid-cols-12">

                
                <div class="lg:col-span-4 space-y-5">
                    <a href="<?php echo e(url('/')); ?>" class="inline-block">
                        <?php if (isset($component)) { $__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.brand-logo','data' => ['name' => $__sn]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('brand-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($__sn)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3)): ?>
<?php $attributes = $__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3; ?>
<?php unset($__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3)): ?>
<?php $component = $__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3; ?>
<?php unset($__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3); ?>
<?php endif; ?>
                    </a>
                    <p class="text-sm text-muted leading-relaxed max-w-xs"><?php echo e($__fc['description']); ?></p>

                    
                    <?php
                        $__socials = [
                            ['key' => 'twitter_url', 'label' => 'Twitter', 'path' => 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z'],
                            ['key' => 'github_url', 'label' => 'GitHub', 'path' => 'M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z'],
                            ['key' => 'linkedin_url', 'label' => 'LinkedIn', 'path' => 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z'],
                            ['key' => 'facebook_url', 'label' => 'Facebook', 'path' => 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z'],
                            ['key' => 'instagram_url', 'label' => 'Instagram', 'path' => 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z'],
                        ];
                    ?>
                    <div class="flex items-center gap-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $__socials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $social): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($__fc[$social['key']])): ?>
                            <a href="<?php echo e($__fc[$social['key']]); ?>" target="_blank" rel="noopener" class="w-9 h-9 rounded-xl bg-surface border border-border/50 flex items-center justify-center text-muted hover:text-brand hover:border-brand/30 transition-all" aria-label="<?php echo e($social['label']); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="<?php echo e($social['path']); ?>"/></svg>
                            </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>

                
                <div class="lg:col-span-4 grid grid-cols-2 gap-8">
                    
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-[0.2em] text-muted mb-4"><?php echo e(__("Links")); ?></h4>
                        <ul class="space-y-3">
                            <li><a href="#features" class="text-sm text-muted hover:text-ink transition-colors"><?php echo e(__("Features")); ?></a></li>
                            <li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!\App\Support\BillingPolicy::isFree()): ?><a href="#pricing" class="text-sm text-muted hover:text-ink transition-colors"><?php echo e(__("Pricing")); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></li>
                            <li><a href="#integrations" class="text-sm text-muted hover:text-ink transition-colors"><?php echo e(__("Integrations")); ?></a></li>
                            <li><a href="<?php echo e(route('about')); ?>" class="text-sm text-muted hover:text-ink transition-colors"><?php echo e(__("About Us")); ?></a></li>
                            <li><a href="<?php echo e(route('contact')); ?>" class="text-sm text-muted hover:text-ink transition-colors"><?php echo e(__("Contact")); ?></a></li>
                        </ul>
                    </div>

                    
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-[0.2em] text-muted mb-4"><?php echo e(__("Legal")); ?></h4>
                        <ul class="space-y-3">
                            <li><a href="<?php echo e(route('legal.terms')); ?>" class="text-sm text-muted hover:text-ink transition-colors"><?php echo e(__("Terms of Service")); ?></a></li>
                            <li><a href="<?php echo e(route('legal.privacy')); ?>" class="text-sm text-muted hover:text-ink transition-colors"><?php echo e(__("Privacy Policy")); ?></a></li>
                            <li><a href="<?php echo e(route('legal.refund')); ?>" class="text-sm text-muted hover:text-ink transition-colors"><?php echo e(__("Refund Policy")); ?></a></li>
                            <li><a href="<?php echo e(route('legal.privacy')); ?>" class="text-sm text-muted hover:text-ink transition-colors"><?php echo e(__("Cookie Policy")); ?></a></li>
                            <li><a href="<?php echo e(route('why-us')); ?>" class="text-sm text-muted hover:text-ink transition-colors"><?php echo e(__("Why Us")); ?></a></li>
                        </ul>
                    </div>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($__fc['newsletter_enabled']??'true') === 'true'): ?>
                <div class="lg:col-span-4">
                    <h4 class="text-xs font-bold uppercase tracking-[0.2em] text-muted mb-4"><?php echo e($__fc['newsletter_title']); ?></h4>
                    <p class="text-sm text-muted mb-4"><?php echo e($__fc['newsletter_subtitle']); ?></p>
                    <form class="flex gap-2" x-data="{ email: '', sent: false }" @submit.prevent="sent = true">
                        <input type="email" x-model="email" placeholder="you@example.com" required
                               class="flex-1 h-11 px-4 rounded-xl border border-border bg-surface text-sm text-ink placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition-all">
                        <button type="submit" x-show="!sent"
                                class="h-11 px-5 rounded-xl bg-brand text-white text-sm font-bold shadow-lg shadow-brand/20 hover:bg-brand-strong transition-colors">
                            Subscribe
                        </button>
                        <span x-show="sent" x-cloak class="h-11 px-5 rounded-xl bg-success/10 text-success text-sm font-bold flex items-center"><?php echo e(__("Subscribed!")); ?></span>
                    </form>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="mt-12 pt-6 border-t border-border/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-sm text-muted"><?php echo e($__fc['copyright'] ?? ''); ?></p>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1.5 text-xs text-muted">
                        <span class="w-2 h-2 bg-success rounded-full animate-pulse"></span>
                        All Systems Operational
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <?php if (isset($component)) { $__componentOriginal929715dcacade4e957f0bc5aff0c8a6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal929715dcacade4e957f0bc5aff0c8a6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.cookie-consent','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('cookie-consent'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal929715dcacade4e957f0bc5aff0c8a6d)): ?>
<?php $attributes = $__attributesOriginal929715dcacade4e957f0bc5aff0c8a6d; ?>
<?php unset($__attributesOriginal929715dcacade4e957f0bc5aff0c8a6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal929715dcacade4e957f0bc5aff0c8a6d)): ?>
<?php $component = $__componentOriginal929715dcacade4e957f0bc5aff0c8a6d; ?>
<?php unset($__componentOriginal929715dcacade4e957f0bc5aff0c8a6d); ?>
<?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pwaEnabled ?? false): ?>
    <?php echo $__env->make('partials.pwa-install-prompt', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?php echo e(asset("sw.js")); ?>').then(function(reg) {
            reg.addEventListener('updatefound', function() {
                var w = reg.installing;
                if (w) w.addEventListener('statechange', function() {
                    if (w.state === 'activated' && navigator.serviceWorker.controller) {
                        console.log('[PWA] New version available');
                    }
                });
            });
        }).catch(function(e) { console.warn('[PWA] SW registration failed:', e); });
    }
    </script>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <script>
    function frontendLayout() {
        return {
            mobileMenu: false,
            scrolled: window.scrollY > 20,
            theme: localStorage.getItem('theme') || 'light',

            init() {
                document.documentElement.setAttribute('data-theme', this.theme);
                document.documentElement.classList.toggle('dark', this.theme === 'dark');

                document.addEventListener('keydown', (e) => {
                    const tag = document.activeElement?.tagName;
                    const isInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || document.activeElement?.isContentEditable;
                    if (!isInput && (e.ctrlKey || e.metaKey) && e.key === '.') {
                        e.preventDefault();
                        this.toggleTheme();
                    }
                });
            },

            toggleTheme() {
                this.theme = this.theme === 'light' ? 'dark' : 'light';
                localStorage.setItem('theme', this.theme);
                document.documentElement.setAttribute('data-theme', this.theme);
                document.documentElement.classList.toggle('dark', this.theme === 'dark');
            },
        };
    }
    </script>

    <?php echo \App\Models\SystemSetting::get('footer_code', ''); ?>

    <?php echo $__env->yieldPushContent('scripts'); ?>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

 </body>
</html><?php /**PATH /home/dahimail.com/public_html/resources/views/frontend/layout.blade.php ENDPATH**/ ?>