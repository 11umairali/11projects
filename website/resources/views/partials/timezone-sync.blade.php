{{-- Detects the browser's timezone and keeps it in the user_tz cookie; the server copies it to the signed-in account. --}}
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
        @auth
        if (cur !== tz && window.fetch) {
            var t = document.querySelector('meta[name="csrf-token"]');
            fetch('{{ url('/timezone/sync') }}', {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': t ? t.content : ''},
                body: JSON.stringify({timezone: tz})
            }).catch(function () {});
        }
        @endauth
    } catch (e) {}
})();
</script>
