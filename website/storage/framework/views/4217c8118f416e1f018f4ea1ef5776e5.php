<!DOCTYPE html>
<html
    lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>"
    dir="<?php echo e($textDirection ?? 'ltr'); ?>"
    x-data="appLayout()"
    x-init="init()"
    :class="{ dark: theme === 'dark' }"
    :data-theme="theme"
    class="h-full overflow-hidden"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
    <meta name="dahi-realtime" content="<?php echo e(json_encode([
        'user' => auth()->id(), 'workspace' => auth()->user()->active_workspace_id,
        'key' => config('broadcasting.default') === 'pusher' ? config('broadcasting.connections.pusher.key') : null,
        'cluster' => config('broadcasting.connections.pusher.options.cluster', 'mt1'),
        'host' => config('services.realtime.ws_host'), 'port' => config('services.realtime.ws_port', 443),
        'tls' => config('services.realtime.ws_scheme', 'https') === 'https', 'auth' => url('/broadcasting/auth'),
    ])); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title><?php echo e($title ?? 'Dashboard'); ?> — <?php echo e(\App\Models\SystemSetting::get('site_name', config('app.name', 'MailTrixy'))); ?></title>



    
    <script>
    // Will be initialized after Livewire loads (see bottom of page)
    window.__mbPendingConfirm = null;
    </script>

    
    <?php $customFavicon = \App\Models\SystemSetting::get('favicon'); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($customFavicon): ?>
    <link rel="icon" href="<?php echo e(asset('storage/' . $customFavicon)); ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo e(asset('storage/' . $customFavicon)); ?>">
    <?php else: ?>
    <link rel="icon" href="<?php echo e(asset('favicon.ico')); ?>" sizes="any">
    <link rel="icon" href="<?php echo e(asset('images/dahimail-logo.png')); ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo e(asset('apple-touch-icon.png')); ?>">
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
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
        if (localStorage.getItem('sidebar-collapsed') === 'true') document.documentElement.setAttribute('data-sidebar-collapsed', 'true');
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    
    <?php $__customCss = \App\Models\SystemSetting::get('custom_css', ''); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(trim($__customCss) !== ''): ?>
        <style id="admin-custom-css"><?php echo $__customCss; ?></style>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php echo \App\Models\SystemSetting::get('head_code', ''); ?>

<style>.profile-initial,.mail-avatar-initial{background:#dbeafe!important;color:#1e3a8a!important;font-weight:700!important;font-size:18px!important}</style>
</head>
<body class="h-full overflow-hidden font-sans text-ink bg-surface antialiased">
<noscript>
    <div style="padding: 2rem; text-align: center; font-family: 'Inter', system-ui, sans-serif; background: #F8F9FC; min-height: 100vh; display: flex; align-items: center; justify-content: center;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #1E293B; margin-bottom: 0.5rem;"><?php echo e(__('JavaScript Required')); ?></h1>
            <p style="color: #9CA3AF; font-size: 0.875rem;"><?php echo e(__('This application requires JavaScript to function. Please enable JavaScript in your browser settings.')); ?></p>
        </div>
    </div>
</noscript>


<a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-primary-600 focus:text-white focus:rounded-lg focus:text-sm focus:font-medium focus:shadow-lg">
    Skip to main content
</a>


<div x-data="{ online: navigator.onLine }"
     x-init="window.addEventListener('online', () => online = true); window.addEventListener('offline', () => online = false)"
     x-show="!online"
     x-transition
     class="fixed top-0 left-0 right-0 z-[9999] bg-amber-500 text-white text-center py-1.5 text-sm font-medium"
     style="display: none;">
    You're offline. Changes will sync when you reconnect.
</div>


<div id="mb-progress-bar"></div>

<div class="relative flex h-full min-h-0" dir="ltr">

    
    <?php app("livewire")->forceAssetInjection(); ?><div x-persist="<?php echo e('sidebar'); ?>">
    
    <aside
        dir="<?php echo e($textDirection ?? 'ltr'); ?>"
        class="admin-sidebar app-sidebar fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform duration-300 lg:sticky lg:top-0 lg:translate-x-0"
        :class="{
            'sidebar-collapsed': sidebarCollapsed,
            '!translate-x-0': sidebarOpen
        }"
        :style="{ width: sidebarCollapsed ? '5rem' : '240px' }"
        style="width: 240px;"
    >
        
        <?php
            $logoLight = \App\Models\SystemSetting::get('logo_light');
            $logoDark = \App\Models\SystemSetting::get('logo_dark');
            $siteName = \App\Models\SystemSetting::get('site_name', config('app.name', 'MailTrixy'));
        ?>
        <div class="px-3 pt-4 pb-2 sidebar-brand flex items-center justify-between gap-2">
            <a href="<?php echo e(url('/dashboard')); ?>" class="flex items-center min-w-0" wire:navigate>
                <?php if (isset($component)) { $__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.brand-logo','data' => ['name' => $siteName]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('brand-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($siteName)]); ?>
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
            
            <button type="button"
                    class="lg:hidden shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg text-muted hover:text-ink hover:bg-surface-2 transition-colors"
                    x-on:click="sidebarOpen = false"
                    aria-label="Close sidebar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        
        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('workspace-switcher', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-137067261-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

        
        <nav id="app-sidebar-nav" class="flex-1 overflow-y-auto overflow-x-hidden px-3 pb-3" aria-label="Main navigation">
            <?php
            $navItems = [
                ['label' => __('Dashboard'), 'href' => url('/dashboard'), 'icon' => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect>'],
                ['label' => __('Friends'), 'pages' => [['label' => __('Chats'), 'href' => url('/friends')], ['label' => __('Friends'), 'href' => url('/people')]], 'href' => url('/people'), 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'],
                ['label' => __('Meetings'), 'href' => url('/meetings'), 'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18M8 14h3M8 17h6"></path>'],
                ['label' => __('Inbox'), 'href' => url('/inbox'), 'icon' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>'],
                ['label' => __('Contacts'), 'href' => url('/contacts'), 'icon' => '<circle cx="9" cy="8" r="3"></circle><path d="M3 19c1.8-3 4.8-4.5 6-4.5s4.2 1.5 6 4.5"></path><path d="M15.5 11a3 3 0 1 0 0-6"></path><path d="M18 19c.6-1.1 1.6-2.2 3-3"></path>'],
                ['label' => __('Deals'), 'href' => url('/deals'), 'icon' => '<path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0l-.77.78-.77-.78a5.4 5.4 0 0 0-7.65 0C1.46 6.7 1.33 10.28 4 13l8 8 8-8c2.67-2.72 2.54-6.3.42-8.42z"></path>'],
                ['label' => __('Knowledge Base'), 'href' => url('/knowledge-base'), 'icon' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>'],
                ['label' => 'Temp Mail', 'href' => url('/temp-mail'), 'icon' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline><line x1="2" y1="20" x2="8" y2="14"></line>'],
                ['label' => __('Workflows'), 'href' => url('/workflows'), 'icon' => '<polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline>'],
                ['label' => __('Campaigns'), 'href' => url('/campaigns'), 'icon' => '<line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>'],
                ['label' => __('Analytics'), 'href' => url('/analytics'), 'icon' => '<path d="M3 3v18h18"></path><path d="M7 14l4-4 3 3 5-6"></path>'],
                ['label' => 'Activity', 'href' => url('/activity'), 'icon' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>'],
                ['label' => 'Help Center', 'href' => url('/help'), 'icon' => '<circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line>'],

                ['section' => __('Settings')],
                ['label' => __('Settings'), 'href' => url('/settings'), 'icon' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>'],
            ];
            ?>

            <ul class="menu menu-sm w-full p-0 gap-0.5" x-data="{ currentPath: window.location.pathname }" @popstate.window="currentPath = window.location.pathname" x-init="document.addEventListener('livewire:navigated', () => { currentPath = window.location.pathname })">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $navItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($item['section'])): ?>
                        <li class="menu-title mt-2.5" x-show="!sidebarCollapsed"><span><?php echo e($item['section']); ?></span></li>
                    <?php elseif(isset($item['pages'])): ?>
                        <li>
                            <details <?php if(request()->is('friends', 'friends/*', 'people', 'people/*')): ?> open <?php endif; ?>>
                                <summary class="nav-item" aria-label="<?php echo e(__('Friends menu')); ?>">
                                    <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo \App\Helpers\SvgSanitizer::sanitize($item['icon']); ?></svg>
                                    <span><?php echo e($item['label']); ?></span>
                                </summary>
                                <ul>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $item['pages']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <?php $pagePath = parse_url($page['href'], PHP_URL_PATH); ?>
                                        <li><a href="<?php echo e($page['href']); ?>" wire:navigate class="nav-item" :class="(currentPath === '<?php echo e($pagePath); ?>' || currentPath.startsWith('<?php echo e($pagePath); ?>/')) ? 'nav-item-active' : ''"><?php echo e($page['label']); ?></a></li>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </ul>
                            </details>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="<?php echo e($item['href']); ?>" wire:navigate.hover
                               class="nav-item"
                               <?php $itemPath = parse_url($item['href'], PHP_URL_PATH); ?>
                               :class="(currentPath === '<?php echo e($itemPath); ?>' || (currentPath.startsWith('<?php echo e($itemPath); ?>/') && '<?php echo e($itemPath); ?>' !== '/')) ? 'nav-item-active' : ''"
                               aria-label="<?php echo e($item['label']); ?>" :aria-current="(currentPath === '<?php echo e($itemPath); ?>' || currentPath.startsWith('<?php echo e($itemPath); ?>/')) ? 'page' : 'false'">
                                <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo \App\Helpers\SvgSanitizer::sanitize($item['icon']); ?></svg>
                                <span><?php echo e($item['label']); ?></span>
                            </a>
                        </li>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['label'] === __('Contacts')): ?>
                        <li x-cloak x-show="!sidebarCollapsed && currentPath.startsWith('<?php echo e(parse_url(url('/contacts'), PHP_URL_PATH)); ?>')">
                            <a href="<?php echo e(url('/contacts/groups')); ?>" wire:navigate
                               class="nav-item pl-10 py-1.5 text-xs"
                               :class="currentPath === '<?php echo e(parse_url(url('/contacts/groups'), PHP_URL_PATH)); ?>' ? 'nav-item-active' : ''">
                                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197"/></svg>
                                <span><?php echo e(__('Groups')); ?></span>
                            </a>
                        </li>
                        <li x-cloak x-show="!sidebarCollapsed && currentPath.startsWith('<?php echo e(parse_url(url('/contacts'), PHP_URL_PATH)); ?>')">
                            <a href="<?php echo e(url('/contacts/merge')); ?>" wire:navigate
                               class="nav-item pl-10 py-1.5 text-xs"
                               :class="currentPath === '<?php echo e(parse_url(url('/contacts/merge'), PHP_URL_PATH)); ?>' ? 'nav-item-active' : ''">
                                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                <span><?php echo e(__('Combine Duplicates')); ?></span>
                            </a>
                        </li>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->is_admin): ?>
                <li class="menu-title mt-2.5" x-show="!sidebarCollapsed"><span><?php echo e(__('Admin Panel')); ?></span></li>
                <li>
                    <a href="<?php echo e(url('/admin/dashboard')); ?>" wire:navigate
                       class="nav-item"
                       :class="currentPath.startsWith('<?php echo e(parse_url(url('/admin'), PHP_URL_PATH)); ?>') ? 'nav-item-active' : ''"
                       aria-label="<?php echo e(__('Admin Panel')); ?>" :aria-current="currentPath.startsWith('<?php echo e(parse_url(url('/admin'), PHP_URL_PATH)); ?>') ? 'page' : 'false'">
                        <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <span><?php echo e(__('Admin Panel')); ?></span>
                    </a>
                </li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
        </nav>

        
        <div
            x-show="!sidebarCollapsed"
            x-transition:enter="transition-opacity duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="border-t border-border/40 px-3 py-2 shrink-0 sidebar-footer"
        >
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand/20 text-brand text-xs font-semibold">
                    <?php echo e(strtoupper(substr(auth()->user()->name ?? 'U', 0, 1))); ?>

                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold truncate leading-tight text-ink"><?php echo e(auth()->user()->name ?? 'User'); ?></p>
                    <p class="text-[10px] text-muted truncate leading-tight"><?php echo e(auth()->user()->email ?? ''); ?></p>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-2 text-[10px] text-muted">
                <span>&copy; <?php echo e(date('Y')); ?> <?php echo e(\App\Models\SystemSetting::get('site_name', config('app.name', 'MailTrixy'))); ?></span>
                <span>&middot;</span>
                <a href="<?php echo e(route('legal.terms')); ?>" class="hover:text-ink" target="_blank"><?php echo e(__('Terms')); ?></a>
                <span>&middot;</span>
                <a href="<?php echo e(route('legal.privacy')); ?>" class="hover:text-ink" target="_blank"><?php echo e(__('Privacy')); ?></a>
            </div>
        </div>

        
        <div class="sidebar-brand-icon-bottom" style="display:none">
            <a href="<?php echo e(url('/dashboard')); ?>" wire:navigate>
                <img src="<?php echo e(asset('images/dahimail-logo.png')); ?>" alt="<?php echo e($siteName); ?>" class="h-9 w-9 rounded-lg" width="36" height="36">
            </a>
        </div>
    </aside>
    </div>

    
    <div
        class="sidebar-overlay lg:hidden"
        x-show="sidebarOpen"
        x-on:click="sidebarOpen = false"
        x-transition:enter="transition-opacity duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;"
    ></div>

    
    <div class="app-content" dir="<?php echo e($textDirection ?? 'ltr'); ?>">

        
        <header class="app-header">
            <div class="flex items-center justify-between gap-4 px-6 py-3">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    
                    <button type="button" class="shrink-0 lg:hidden icon-button" x-on:click="sidebarOpen = !sidebarOpen" aria-label="Toggle sidebar">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    
                    <button type="button" class="hidden lg:flex icon-button" x-on:click="sidebarCollapsed = !sidebarCollapsed" :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                        <svg x-show="!sidebarCollapsed" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                        <svg x-show="sidebarCollapsed" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                    </button>

                    
                    <button @click="$dispatch('open-search'); document.querySelector('[aria-label=\'Global search\']')?.focus()" class="sm:hidden icon-button" aria-label="Search">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </button>

                    
                    <div class="hidden sm:block relative flex-1 max-w-md" x-data="{
                        query: '',
                        results: { contacts: [], conversations: [] },
                        loading: false,
                        open: false,
                        debounceTimer: null,
                        async search() {
                            if (this.query.trim().length < 2) { this.results = { contacts: [], conversations: [] }; this.open = false; return; }
                            this.loading = true;
                            this.open = true;
                            clearTimeout(this.debounceTimer);
                            this.debounceTimer = setTimeout(async () => {
                                try {
                                    const q = encodeURIComponent(this.query.trim());
                                    const res = await fetch('<?php echo e(url('/search/quick')); ?>?q=' + q, {
                                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                                    });
                                    if (res.ok) { this.results = await res.json(); }
                                } catch(e) { console.error(e); }
                                this.loading = false;
                            }, 300);
                        },
                        clear() { this.query = ''; this.open = false; this.results = { contacts: [], conversations: [] }; }
                    }" @click.outside="open = false"
                       x-init="document.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); $refs.searchInput.focus(); } })">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted pointer-events-none z-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
                            <input type="text" x-model="query" @input="search()" @focus="if(query.length >= 2) open = true"
                                   x-ref="searchInput"
                                   @keydown.enter.prevent="if(query.trim()) window.location.href='<?php echo e(url('/search')); ?>?q=' + encodeURIComponent(query.trim())"
                                   @keydown.escape="clear()"
                                   placeholder="Search... (Ctrl+K)"
                                   aria-label="Global search"
                                   class="input pl-9 pr-16 w-full">
                            <kbd x-show="!query" class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none hidden sm:inline-flex items-center gap-0.5 rounded-lg border border-border bg-surface-2 px-1.5 py-0.5 text-[10px] font-medium text-muted">
                                Ctrl K
                            </kbd>
                            <button x-show="query.length > 0" x-cloak @click="clear()" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        
                        <div x-show="open" x-transition class="absolute left-0 right-0 mt-2 rounded-2xl border border-border bg-surface-2 shadow-xl z-50 overflow-hidden" style="display:none">
                            <div x-show="loading" class="px-4 py-3 text-center">
                                <svg class="animate-spin h-4 w-4 text-brand mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </div>

                            
                            <template x-if="results.contacts.length > 0">
                                <div>
                                    <p class="px-4 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted"><?php echo e(__('Contacts')); ?></p>
                                    <template x-for="c in results.contacts" :key="c.id">
                                        <a :href="c.url" class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface transition-colors">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-xs font-bold" x-text="c.initials"></span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-ink truncate" x-text="c.name"></p>
                                                <p class="text-xs text-muted truncate" x-text="c.email"></p>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            
                            <template x-if="results.conversations.length > 0">
                                <div>
                                    <p class="px-4 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted"><?php echo e(__('Conversations')); ?></p>
                                    <template x-for="c in results.conversations" :key="c.id">
                                        <a :href="c.url" class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface transition-colors">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-info/10 text-info text-xs font-bold">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-ink truncate" x-text="c.subject"></p>
                                                <p class="text-xs text-muted truncate" x-text="c.contact"></p>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            
                            <div x-show="!loading && results.contacts.length === 0 && results.conversations.length === 0 && query.length >= 2" class="px-4 py-4 text-center">
                                <p class="text-sm text-muted"><?php echo e(__('No results for')); ?> "<span class="font-medium text-ink" x-text="query"></span>"</p>
                            </div>

                            
                            <a x-show="query.length >= 2" :href="'<?php echo e(url('/search')); ?>?q=' + encodeURIComponent(query)" class="flex items-center justify-center gap-2 px-4 py-2.5 border-t border-border/60 text-xs font-semibold text-brand hover:bg-brand/5 transition-colors">
                                <?php echo e(__('View all results')); ?>

                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    
                    <?php if (isset($component)) { $__componentOriginal8d3bff7d7383a45350f7495fc470d934 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8d3bff7d7383a45350f7495fc470d934 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.language-switcher','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('language-switcher'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.theme-toggle','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('theme-toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
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

                    
                    <div class="relative"
                         x-data="{
                            open: false,
                            unreadCount: <?php echo e(auth()->user()?->unreadNotifications()?->count() ?? 0); ?>,
                            notifications: [],
                            loaded: false,
                            polling: false, refreshQueued: false, timer: null, destroyed: false,
                            destroy() { this.destroyed = true; clearInterval(this.timer); },
                            csrf: '<?php echo e(csrf_token()); ?>',
                            async fetchNotifications() { await this.pollUnreadCount(); },
                            async markAllRead() {
                                try {
                                    await fetch('<?php echo e(url('/notifications/read')); ?>', {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }
                                    });
                                    this.unreadCount = 0;
                                    this.notifications = this.notifications.map(n => ({...n, read: true}));
                                } catch (e) {}
                            },
                            openNotification(n) {
                                if (window.DahiMessages?.target(n)) { window.DahiMessages.view(n); return; }
                                if (n.action_url) {
                                    window.location.href = '<?php echo e(url('/notifications')); ?>/' + n.id + '/open';
                                } else {
                                    fetch('<?php echo e(url('/notifications')); ?>/' + n.id + '/read', {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }
                                    });
                                    n.read = true;
                                    if (this.unreadCount > 0) this.unreadCount--;
                                }
                            },
                            async pollUnreadCount() {
                                if (this.destroyed) return;
                                if (this.polling) { this.refreshQueued = true; return; }
                                this.polling = true;
                                try {
                                    const r = await fetch('<?php echo e(url('/notifications')); ?>', { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                                    if (!r.ok) return;
                                    const j = await r.json();
                                    if (this.destroyed) return;
                                    const known = new Set(this.notifications.map(n => n.id));
                                    if (this.loaded) for (const n of (j.notifications || [])) {
                                        if (!n.read && !known.has(n.id) && ['friend_request', 'friend_accepted', 'email_received', 'email_failed', 'email_bounced'].includes(n.type)) {
                                            if (!window.DahiMessages?.show(n)) window.dispatchEvent(new CustomEvent('toast', {detail: {type: 'info', message: n.title}}));
                                        }
                                    }
                                    this.notifications = j.notifications || [];
                                    this.loaded = true;
                                    this.unreadCount = j.unread_count || 0;
                                } catch (e) {} finally {
                                    this.polling = false;
                                    if (this.refreshQueued && !this.destroyed) { this.refreshQueued = false; this.pollUnreadCount(); }
                                }
                            }
                         }"
                         x-init="pollUnreadCount(); timer = setInterval(() => pollUnreadCount(), 10000)"
                         x-on:dahi:realtime.window="pollUnreadCount()"
                         x-on:click.outside="open = false">
                        <button x-on:click="open = !open; if (open && !loaded) fetchNotifications()" class="icon-button relative" aria-label="Notifications">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
                            <span x-show="unreadCount > 0" x-cloak
                                  x-text="unreadCount > 9 ? '9+' : unreadCount"
                                  class="absolute -top-0.5 -right-0.5 min-w-[16px] h-[16px] px-1 bg-danger text-white text-[10px] font-bold rounded-full ring-2 ring-surface-2 flex items-center justify-center"></span>
                        </button>
                        <div x-show="open" x-transition
                             class="absolute right-0 mt-2 w-80 rounded-2xl border border-border/70 bg-surface-2 shadow-soft z-50 overflow-hidden" style="display: none;">
                            <div class="px-4 py-3 border-b border-border/50 flex items-center justify-between">
                                <p class="text-sm font-semibold text-ink"><?php echo e(__('Notifications')); ?></p>
                                <button x-show="unreadCount > 0" @click="markAllRead()" class="text-xs text-brand font-medium hover:text-brand-strong"><?php echo e(__('Mark all read')); ?></button>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                
                                <div x-show="!loaded" class="p-6 text-center">
                                    <div class="inline-block w-5 h-5 border-2 border-brand border-t-transparent rounded-full animate-spin"></div>
                                    <p class="text-xs text-muted mt-2"><?php echo e(__('Loading...')); ?></p>
                                </div>

                                
                                <div x-show="loaded && notifications.length === 0" x-cloak class="p-6 text-center">
                                    <svg class="w-10 h-10 mx-auto text-muted/40 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                    <p class="text-sm font-medium text-ink/70"><?php echo e(__('All caught up!')); ?></p>
                                    <p class="text-xs text-muted mt-1"><?php echo e(__("We'll notify you about new emails, campaigns, and team activity.")); ?></p>
                                </div>

                                
                                <template x-for="n in notifications" :key="n.id">
                                    <button type="button" @click="openNotification(n)"
                                            :class="n.read ? 'opacity-60' : 'bg-brand/5'"
                                            class="w-full text-left px-4 py-3 border-b border-border/30 hover:bg-surface transition-colors flex gap-3">
                                        <span class="message-identity">
                                            <span class="message-avatar">
                                                <span x-text="(n.sender_name || n.title || 'D').trim().split(/\s+/).slice(0,2).map(word => word[0]).join('').toUpperCase()"></span>
                                                <img x-show="window.DahiNotification?.avatar(n)" :src="window.DahiNotification?.avatar(n) || null" alt="" referrerpolicy="no-referrer" x-on:error="$el.style.display = 'none'" x-on:load="$el.style.display = ''">
                                            </span>
                                            <span class="message-category" aria-hidden="true" x-html="window.DahiNotification?.categoryIcon(n) || ''"></span>
                                        </span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-ink" x-text="n.sender_name || n.title"></p>
                                            <p class="text-xs text-muted line-clamp-2 mt-1" x-text="n.body"></p>
                                        </div>
                                        <div class="flex flex-col items-end gap-2 shrink-0">
                                            <time class="text-[10px] text-muted whitespace-nowrap" x-text="window.DahiNotification?.relativeTime(n) || n.created_at"></time>
                                            <span x-show="!n.read" class="w-1.5 h-1.5 bg-brand rounded-full" aria-label="Unread"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                            <div class="px-4 py-2.5 border-t border-border/50">
                                <a href="<?php echo e(url('/settings/notifications')); ?>" wire:navigate class="text-xs font-medium text-brand hover:text-brand-strong"><?php echo e(__('Notification settings')); ?> &rarr;</a>
                            </div>
                        </div>
                    </div>

                    
                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                        <button x-on:click="open = !open" class="flex items-center gap-3 transition" aria-label="User menu" aria-haspopup="true" :aria-expanded="open">
                            <span class="hidden text-right md:block">
                                <span class="block text-sm font-semibold text-ink"><?php echo e(auth()->user()->name ?? 'User'); ?></span>
                                <span class="block text-xs text-muted"><?php echo e(auth()->user()->email ?? ''); ?></span>
                            </span>
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand/15 text-brand text-sm font-bold">
                                <?php echo e(strtoupper(substr(auth()->user()->name ?? 'U', 0, 1))); ?><?php echo e(strtoupper(substr(auth()->user()->name ?? 'U', strpos(auth()->user()->name ?? 'U', ' ') + 1, 1))); ?>

                            </span>
                        </button>
                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-56 rounded-2xl border border-border/70 bg-surface-2 p-2 shadow-soft z-50"
                            style="display: none;"
                        >
                            <div class="px-3 py-2">
                                <p class="text-xs font-semibold uppercase tracking-widest text-muted"><?php echo e(__('Account')); ?></p>
                            </div>
                            <a href="<?php echo e(url('/settings/profile')); ?>" wire:navigate class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-ink transition hover:bg-muted/10">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 text-ink/60" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"></circle><path d="M5 21v-1a7 7 0 0 1 14 0v1"></path></svg>
                                <?php echo e(__('Profile')); ?>

                            </a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!\App\Support\BillingPolicy::isFree()): ?>
                            <a href="<?php echo e(url('/settings/billing')); ?>" wire:navigate class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-ink transition hover:bg-muted/10">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 text-ink/60" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20"></path></svg>
                                <?php echo e(__('Billing')); ?>

                            </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="my-2 border-t border-border/60"></div>
                            <form method="POST" action="<?php echo e(route('logout')); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-danger transition hover:bg-danger/10">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                    <?php echo e(__('Sign Out')); ?>

                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        
        <link rel="stylesheet" href="<?php echo e(asset('css/message-notifications.css')); ?>?v=20261015.1">
        <script src="<?php echo e(asset('js/message-notifications.js')); ?>?v=20261015.1"></script>
        <?php if (isset($component)) { $__componentOriginalbcd757c7bb20b76cf9bcd607adaf8c39 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbcd757c7bb20b76cf9bcd607adaf8c39 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.toast-container','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toast-container'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbcd757c7bb20b76cf9bcd607adaf8c39)): ?>
<?php $attributes = $__attributesOriginalbcd757c7bb20b76cf9bcd607adaf8c39; ?>
<?php unset($__attributesOriginalbcd757c7bb20b76cf9bcd607adaf8c39); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbcd757c7bb20b76cf9bcd607adaf8c39)): ?>
<?php $component = $__componentOriginalbcd757c7bb20b76cf9bcd607adaf8c39; ?>
<?php unset($__componentOriginalbcd757c7bb20b76cf9bcd607adaf8c39); ?>
<?php endif; ?>

        
        <main class="app-main">
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($breadcrumb)): ?>
            <div class="mb-2">
                <?php echo e($breadcrumb); ?>

            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
                <?php if (isset($component)) { $__componentOriginal7cfab914afdd05940201ca0b2cbc009b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.toast','data' => ['type' => 'success','message' => session('success')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toast'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'success','message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(session('success'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $attributes = $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $component = $__componentOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
                <?php if (isset($component)) { $__componentOriginal7cfab914afdd05940201ca0b2cbc009b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.toast','data' => ['type' => 'error','message' => session('error'),'duration' => 0]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toast'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'error','message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(session('error')),'duration' => 0]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $attributes = $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $component = $__componentOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('warning')): ?>
                <?php if (isset($component)) { $__componentOriginal7cfab914afdd05940201ca0b2cbc009b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.toast','data' => ['type' => 'warning','message' => session('warning'),'duration' => 8000]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toast'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'warning','message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(session('warning')),'duration' => 8000]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $attributes = $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $component = $__componentOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('info')): ?>
                <?php if (isset($component)) { $__componentOriginal7cfab914afdd05940201ca0b2cbc009b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.toast','data' => ['type' => 'info','message' => session('info'),'duration' => 8000]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toast'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'info','message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(session('info')),'duration' => 8000]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $attributes = $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $component = $__componentOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('admin_impersonating')): ?>
            <div class="mb-4 alert alert-error">
                <span class="flex-1 text-sm font-semibold"><?php echo e(__('Admin impersonation active (by')); ?> <?php echo e(session('admin_impersonating_name', 'Admin')); ?>). <?php echo e(__('This session is time-limited.')); ?></span>
                <form method="POST" action="<?php echo e(route('admin.impersonation.stop')); ?>">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn-primary text-xs px-3 py-1"><?php echo e(__('Return to Admin')); ?></button>
                </form>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div id="main-content" class="flex-1">
            <?php echo e($slot); ?>

            </div>

            
            <div id="live-announcements" aria-live="polite" aria-atomic="true" class="sr-only"></div>

            
        </main>
    </div>
</div>


<?php if (isset($component)) { $__componentOriginal762a64ed224c416affc34a6e8d8f0936 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal762a64ed224c416affc34a6e8d8f0936 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.keyboard-shortcuts','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('keyboard-shortcuts'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal762a64ed224c416affc34a6e8d8f0936)): ?>
<?php $attributes = $__attributesOriginal762a64ed224c416affc34a6e8d8f0936; ?>
<?php unset($__attributesOriginal762a64ed224c416affc34a6e8d8f0936); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal762a64ed224c416affc34a6e8d8f0936)): ?>
<?php $component = $__componentOriginal762a64ed224c416affc34a6e8d8f0936; ?>
<?php unset($__componentOriginal762a64ed224c416affc34a6e8d8f0936); ?>
<?php endif; ?>


<div x-data="{ helpOpen: false }" @keydown.escape.window="helpOpen = false">
    <button @click="helpOpen = !helpOpen" :aria-expanded="helpOpen" aria-controls="help-panel" aria-label="Open help center"
            class="fixed bottom-6 right-6 z-50 h-12 w-12 rounded-full bg-brand text-white shadow-lg hover:bg-brand-strong focus:outline-none focus:ring-2 focus:ring-brand/40 focus:ring-offset-2 transition-all duration-200 flex items-center justify-center text-xl font-bold">?</button>
    <div x-show="helpOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="helpOpen = false" class="fixed inset-0 z-50 bg-ink/20" x-cloak></div>
    <aside id="help-panel" x-show="helpOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="fixed top-0 right-0 z-50 h-full w-80 max-w-[90vw] bg-surface-2 shadow-2xl flex flex-col border-l border-border/50" x-cloak>
        <div class="flex items-center justify-between px-5 py-4 border-b border-border/50">
            <h2 class="text-lg font-semibold text-ink"><?php echo e(__('Help Center')); ?></h2>
            <button @click="helpOpen = false" aria-label="Close help panel" class="icon-button">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="px-5 py-3 border-b border-border/50">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" placeholder="<?php echo e(__('Search help topics...')); ?>" aria-label="<?php echo e(__('Search help topics')); ?>" class="input pl-9">
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto px-5 py-4" aria-label="Help topics">
            <h3 class="panel-heading mb-3"><?php echo e(__('Quick Links')); ?></h3>
            <ul class="space-y-1">
                <?php
                $helpLinks = [
                    ['label' => __('Getting Started'), 'href' => url('/onboarding/step-1'), 'icon' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>'],
                    ['label' => __('Connect Email Account'), 'href' => url('/settings/email'), 'icon' => '<rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>'],
                    ['label' => __('Set Up AI Replies'), 'href' => url('/settings/ai'), 'icon' => '<path d="M12 2a7 7 0 0 1 7 7c0 2.4-1.2 4.5-3 5.7V17a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-2.3C6.2 13.5 5 11.4 5 9a7 7 0 0 1 7-7z"></path><path d="M9 22h6"></path>'],
                    ['label' => __('Import Contacts'), 'href' => url('/contacts'), 'icon' => '<circle cx="9" cy="8" r="3"></circle><path d="M3 19c1.8-3 4.8-4.5 6-4.5s4.2 1.5 6 4.5"></path><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line>'],
                    ['label' => __('Create Campaign'), 'href' => url('/campaigns/create'), 'icon' => '<line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>'],
                    ['label' => __('Need Help?'), 'href' => url('/contact'), 'icon' => '<circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line>'],
                ];
                ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $helpLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <?php if($link['href'] === url('/onboarding/step-1') && !\App\Support\AccountFeatures::onboarding()) continue; ?>
                <li>
                    <a href="<?php echo e(url($link['href'])); ?>" wire:navigate class="nav-item text-sm">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 text-brand shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo \App\Helpers\SvgSanitizer::sanitize($link['icon']); ?></svg>
                        <?php echo e($link['label']); ?>

                    </a>
                </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Support\AccountFeatures::tour()): ?>
            
            <div class="mt-5 pt-4 border-t border-border/50">
                <h3 class="panel-heading mb-3"><?php echo e(__('Tools')); ?></h3>
                <button @click="localStorage.removeItem('tour_completed'); localStorage.removeItem('onboarding_tour_completed'); localStorage.removeItem('onboarding_checklist_dismissed'); $dispatch('restart-tour'); helpOpen = false;"
                        class="flex items-center gap-2.5 w-full px-3 py-2 text-sm font-medium text-brand bg-brand/10 rounded-xl hover:bg-brand/15 transition-colors">
                    <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    <?php echo e(__('Restart Product Tour')); ?>

                </button>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </nav>
    </aside>
</div>

<?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>


<script>
function appLayout() {
    return {
        theme: localStorage.getItem('theme') || 'light',
        sidebarOpen: false,
        sidebarCollapsed: localStorage.getItem('sidebar-collapsed') === 'true',

        init() {
            document.documentElement.setAttribute('data-theme', this.theme);
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
            document.documentElement.toggleAttribute('data-sidebar-collapsed', this.sidebarCollapsed);
            this.$watch('sidebarCollapsed', v => {
                localStorage.setItem('sidebar-collapsed', v);
                document.documentElement.toggleAttribute('data-sidebar-collapsed', v);
            });

            // Global keyboard shortcuts (/ to focus search, Ctrl+. to toggle theme)
            document.addEventListener('keydown', (e) => {
                const tag = document.activeElement?.tagName;
                const isInput = ['INPUT','TEXTAREA','SELECT'].includes(tag) || document.activeElement?.isContentEditable;

                // "/" to focus the global search input (outside inputs)
                if (e.key === '/' && !isInput) {
                    e.preventDefault();
                    const searchInput = document.querySelector('[aria-label="Global search"]');
                    if (searchInput) searchInput.focus();
                }

                // "Ctrl + ." to toggle theme
                if ((e.ctrlKey || e.metaKey) && e.key === '.') {
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

// wire:navigate progress bar
(function () {
    const bar = document.getElementById('mb-progress-bar');
    let timer = null;

    function start() {
        if (!bar) return;
        clearTimeout(timer);
        bar.style.transition = 'none';
        bar.style.width = '0%';
        bar.classList.add('active');
        bar.offsetHeight;
        bar.style.transition = 'width 300ms ease';
        bar.style.width = '60%';
        timer = setTimeout(() => { bar.style.width = '80%'; }, 400);
    }

    function finish() {
        if (!bar) return;
        clearTimeout(timer);
        bar.style.transition = 'width 200ms ease';
        bar.style.width = '100%';
        setTimeout(() => {
            bar.style.transition = 'opacity 300ms ease';
            bar.classList.remove('active');
            setTimeout(() => { bar.style.width = '0%'; }, 350);
        }, 200);
    }

    document.addEventListener('livewire:navigating', start);
    document.addEventListener('livewire:navigated', finish);

    document.addEventListener('livewire:navigated', () => {
        const main = document.querySelector('.app-main');
        if (!main || !main.firstElementChild) return;
        const el = main.firstElementChild;
        el.style.animation = 'none';
        el.offsetHeight;
        el.style.animation = '';
    });

    // Re-initialize Lucide icons on SPA navigation (handled by app.js bundle)

    // Fix dark mode persistence on wire:navigate SPA transitions
    document.addEventListener('livewire:navigated', function() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.classList.toggle('dark', savedTheme === 'dark');
        document.documentElement.setAttribute('data-theme', savedTheme);
    });
})();
</script>


<?php if (isset($component)) { $__componentOriginalf67cf4cedaef540152c3148bec35c9aa = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf67cf4cedaef540152c3148bec35c9aa = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.product-tour','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('product-tour'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf67cf4cedaef540152c3148bec35c9aa)): ?>
<?php $attributes = $__attributesOriginalf67cf4cedaef540152c3148bec35c9aa; ?>
<?php unset($__attributesOriginalf67cf4cedaef540152c3148bec35c9aa); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf67cf4cedaef540152c3148bec35c9aa)): ?>
<?php $component = $__componentOriginalf67cf4cedaef540152c3148bec35c9aa; ?>
<?php unset($__componentOriginalf67cf4cedaef540152c3148bec35c9aa); ?>
<?php endif; ?>


<?php if (isset($component)) { $__componentOriginal7668fad7795586306e1972f2f11338ca = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7668fad7795586306e1972f2f11338ca = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.onboarding-tour','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('onboarding-tour'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7668fad7795586306e1972f2f11338ca)): ?>
<?php $attributes = $__attributesOriginal7668fad7795586306e1972f2f11338ca; ?>
<?php unset($__attributesOriginal7668fad7795586306e1972f2f11338ca); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7668fad7795586306e1972f2f11338ca)): ?>
<?php $component = $__componentOriginal7668fad7795586306e1972f2f11338ca; ?>
<?php unset($__componentOriginal7668fad7795586306e1972f2f11338ca); ?>
<?php endif; ?>


<?php if (isset($component)) { $__componentOriginal1825343ee730bf5fafe5d8dd28124880 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1825343ee730bf5fafe5d8dd28124880 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.onboarding-checklist','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('onboarding-checklist'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1825343ee730bf5fafe5d8dd28124880)): ?>
<?php $attributes = $__attributesOriginal1825343ee730bf5fafe5d8dd28124880; ?>
<?php unset($__attributesOriginal1825343ee730bf5fafe5d8dd28124880); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1825343ee730bf5fafe5d8dd28124880)): ?>
<?php $component = $__componentOriginal1825343ee730bf5fafe5d8dd28124880; ?>
<?php unset($__componentOriginal1825343ee730bf5fafe5d8dd28124880); ?>
<?php endif; ?>




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


<script>
(function() {
    window.addEventListener('keydown', function(event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA' || event.target.isContentEditable) return;
        if (event.key === '/' && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            document.querySelector('[aria-label="Global search"], input[placeholder*="Search"]')?.focus();
        }
        if (event.key === '?' && event.shiftKey) {
            event.preventDefault();
            document.getElementById('keyboard-shortcuts-modal')?.classList.toggle('hidden');
        }
    });
})();
</script>


<div id="keyboard-shortcuts-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="this.closest('#keyboard-shortcuts-modal').classList.add('hidden')">
    <div class="bg-surface rounded-2xl shadow-2xl border border-border p-6 w-full max-w-md mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-ink"><?php echo e(__('Keyboard Shortcuts')); ?></h3>
            <button type="button" onclick="this.closest('#keyboard-shortcuts-modal').classList.add('hidden')" class="p-1 rounded-lg text-muted hover:bg-surface-2 transition-colors" aria-label="Close">
                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-muted"><?php echo e(__('Compose new email')); ?></span><kbd class="px-2 py-0.5 bg-surface-2 rounded text-xs font-mono text-ink">c</kbd></div>
            <div class="flex justify-between"><span class="text-muted"><?php echo e(__('Focus search')); ?></span><kbd class="px-2 py-0.5 bg-surface-2 rounded text-xs font-mono text-ink">/</kbd></div>
            <div class="flex justify-between"><span class="text-muted"><?php echo e(__('Go to Inbox')); ?></span><div><kbd class="px-2 py-0.5 bg-surface-2 rounded text-xs font-mono text-ink">g</kbd> <?php echo e(__('then')); ?> <kbd class="px-2 py-0.5 bg-surface-2 rounded text-xs font-mono text-ink">i</kbd></div></div>
            <div class="flex justify-between"><span class="text-muted"><?php echo e(__('Go to Dashboard')); ?></span><div><kbd class="px-2 py-0.5 bg-surface-2 rounded text-xs font-mono text-ink">g</kbd> <?php echo e(__('then')); ?> <kbd class="px-2 py-0.5 bg-surface-2 rounded text-xs font-mono text-ink">d</kbd></div></div>
            <div class="flex justify-between"><span class="text-muted"><?php echo e(__('Show this help')); ?></span><kbd class="px-2 py-0.5 bg-surface-2 rounded text-xs font-mono text-ink">?</kbd></div>
        </div>
    </div>
</div>


<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?php echo e(asset("sw.js")); ?>').catch(() => {});
}
</script>


<div id="mb-confirm-modal" x-data="{ show: false, message: '' }" x-cloak
     @mb-confirm.window="message = $event.detail.message; show = true;"
     @keydown.escape.window="if(show) { show = false; }">
    <template x-teleport="body">
        <div x-show="show" x-transition.opacity.duration.200ms class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="show = false"></div>
            <div x-show="show"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-surface-2 rounded-2xl shadow-xl border border-border w-full max-w-sm p-6 z-10">
                <div class="flex items-start gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl bg-warning/10 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-ink">Confirm</h3>
                        <p class="text-sm text-muted mt-1 leading-relaxed" x-text="message"></p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button @click="show = false"
                            class="px-4 py-2 text-sm font-medium text-muted hover:text-ink rounded-xl hover:bg-surface transition-colors">
                        Cancel
                    </button>
                    <button @click="show = false; window.dispatchEvent(new CustomEvent('mb-confirmed'));"
                            class="px-4 py-2 text-sm font-semibold text-white bg-brand rounded-xl hover:bg-brand-strong transition-colors shadow-sm">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
<script>
(function() {
    var pendingEl = null;

    window.confirm = function(msg) {
        // Capture the button NOW — activeElement is still the clicked button
        pendingEl = document.activeElement;
        // Show custom modal
        window.dispatchEvent(new CustomEvent('mb-confirm', { detail: { message: msg } }));
        return false;
    };

    window.addEventListener('mb-confirmed', function() {
        if (!pendingEl) return;
        var el = pendingEl;
        pendingEl = null;

        // Temporarily bypass confirm
        window.confirm = function() { return true; };
        el.click();

        // Restore override
        setTimeout(function() {
            window.confirm = function(msg) {
                pendingEl = document.activeElement;
                window.dispatchEvent(new CustomEvent('mb-confirm', { detail: { message: msg } }));
                return false;
            };
        }, 300);
    });
})();
</script>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pwaEnabled): ?>
<?php echo $__env->make('partials.pwa-install-prompt', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script>
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
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

<?php echo \App\Models\SystemSetting::get('footer_code', ''); ?>

<script>
(function() {
    var nav = document.getElementById('app-sidebar-nav');
    if (!nav) return;
    var saved = sessionStorage.getItem('app-sidebar-scroll');
    if (saved) nav.scrollTop = parseInt(saved, 10);
    nav.addEventListener('scroll', function() { sessionStorage.setItem('app-sidebar-scroll', nav.scrollTop); });
    document.addEventListener('livewire:navigating', function() { sessionStorage.setItem('app-sidebar-scroll', nav.scrollTop); });
})();
</script>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Helpers\WhiteLabel::shouldShowPoweredBy()): ?>
<div class="fixed bottom-2 right-3 z-30 text-[10px] text-muted/60 pointer-events-none select-none">
    <?php echo e(__('Powered by')); ?> <span class="font-medium"><?php echo e(\App\Helpers\WhiteLabel::brandName()); ?></span>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<?php echo \App\Models\SystemSetting::get('footer_code', ''); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
    <?php ($__fcm = config('services.fcm')); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($__fcm['web_vapid_key']) && !empty($__fcm['web_config']['apiKey'])): ?>
        <script>window.DAHI_PUSH = <?php echo json_encode(['vapid' => $__fcm['web_vapid_key'], 'config' => $__fcm['web_config']], 512) ?>;</script>
        <script src="<?php echo e(asset('js/dahi-push.js')); ?>?v=20261007" defer></script>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <script src="<?php echo e(asset('js/call-tones.js')); ?>?v=20261014" defer></script>
    <script src="<?php echo e(asset('js/friend-call.js')); ?>?v=20261014-media" defer></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php echo $__env->make('partials.timezone-sync', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /home/dahimail.com/public_html/resources/views/components/layouts/app.blade.php ENDPATH**/ ?>