<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e(__('Page Not Found')); ?> — <?php echo e(config('app.name')); ?></title>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css']); ?>
</head>
<body class="bg-gradient-to-br from-gray-50 via-white to-primary-50/30 dark:from-[#0f1117] dark:via-[#1a1d27] dark:to-[#1a1530] antialiased min-h-screen flex items-center justify-center px-4" style="font-family: 'Outfit', sans-serif">

    <div class="max-w-lg w-full text-center">
        
        <div class="relative mx-auto w-40 h-40 mb-8">
            <div class="absolute inset-0 bg-primary-100/60 dark:bg-primary-900/20 rounded-full blur-2xl"></div>
            <div class="relative w-40 h-40 bg-gradient-to-br from-primary-50 to-primary-100 dark:from-primary-900/30 dark:to-primary-800/20 rounded-full flex items-center justify-center border-2 border-primary-200/50 dark:border-primary-700/30">
                <div class="text-center">
                    <span class="text-5xl font-black text-primary-600 dark:text-primary-400">404</span>
                </div>
            </div>
        </div>

        
        <h1 class="text-2xl sm:text-3xl font-bold text-ink dark:text-gray-100 mb-3"><?php echo e(__('Page not found')); ?></h1>
        <p class="text-muted  mb-8 max-w-sm mx-auto leading-relaxed">
            <?php echo e(__("The page you're looking for doesn't exist or has been moved. Let's get you back on track.")); ?>

        </p>

        
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="<?php echo e(url('/')); ?>" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white text-sm font-semibold rounded-xl hover:from-primary-700 hover:to-primary-800 shadow-lg shadow-primary-600/20 transition-all hover:-translate-y-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <?php echo e(__('Go Home')); ?>

            </a>
            <button onclick="history.back()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-surface-2 dark:bg-[#1a1d27] text-ink/80 /50 text-sm font-semibold rounded-xl border border-border dark:border-gray-700 hover:bg-surface dark:hover:bg-surface/10 hover:border-border dark:hover:border-gray-600 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?php echo e(__('Go Back')); ?>

            </button>
        </div>

        
        <div class="mt-12 flex items-center justify-center gap-2 text-muted ">
            <div class="w-6 h-6 bg-gradient-to-br from-primary-600 to-secondary-600 rounded-lg flex items-center justify-center">
                <span class="text-xs font-black text-white">M</span>
            </div>
            <span class="text-sm font-semibold"><?php echo e(config('app.name')); ?></span>
        </div>
    </div>

</body>
</html>
<?php /**PATH /home/dahimail.com/public_html/resources/views/errors/404.blade.php ENDPATH**/ ?>