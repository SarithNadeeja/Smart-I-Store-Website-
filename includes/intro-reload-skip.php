<?php
/** Inline head: skip intro if already seen this session or on refresh (no flash) */
?>
<style id="intro-reload-skip-css">
html.skip-intro-on-reload #intro-experience { display: none !important; }
html.skip-intro-on-reload .hero-banner--after-intro {
    opacity: 1 !important;
    visibility: visible !important;
    transform: none !important;
    pointer-events: auto !important;
}
html.skip-intro-on-reload body {
    overflow: auto !important;
    height: auto !important;
}
</style>
<script id="intro-reload-skip-js">
(function () {
    var SEEN_KEY = 'smartistore_intro_seen';

    try {
        if (sessionStorage.getItem(SEEN_KEY)) {
            document.documentElement.classList.add('skip-intro-on-reload');
            return;
        }
    } catch (e) { /* private mode */ }

    var nav = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
    var isReload = nav ? nav.type === 'reload' : (performance.navigation && performance.navigation.type === 1);
    if (isReload) {
        document.documentElement.classList.add('skip-intro-on-reload');
    }
})();
</script>
