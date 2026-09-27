<script>
    // Apply the theme before first paint to avoid a flash of the wrong theme.
    (function () {
        var t = localStorage.getItem('theme') || 'system';
        var dark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>
