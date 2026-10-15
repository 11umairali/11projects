
<script>
(function () {
    try {
        var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (!tz) return;
        var m = document.cookie.match(/(?:^|; )user_tz=([^;]*)/);
        var cur = m ? decodeURIComponent(m[1]) : null;
        if (cur !== tz) {
            document.cookie = 'user_tz=' + encodeURIComponent(tz) + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        }
        <?php if(auth()->guard()->check()): ?>
        if (cur !== tz && window.fetch) {
            var t = document.querySelector('meta[name="csrf-token"]');
            fetch('<?php echo e(url('/timezone/sync')); ?>', {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': t ? t.content : ''},
                body: JSON.stringify({timezone: tz})
            }).catch(function () {});
        }
        <?php endif; ?>
    } catch (e) {}
})();
</script>
<?php /**PATH /home/dahimail.com/public_html/resources/views/partials/timezone-sync.blade.php ENDPATH**/ ?>