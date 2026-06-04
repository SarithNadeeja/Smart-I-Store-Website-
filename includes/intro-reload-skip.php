<?php
/**
 * Skip intro overlay only after the user has completed it this browser tab (Enter Store).
 * Does not skip on refresh — the intro video plays again on reload until completed.
 */
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
        if (/[?&]replay_intro=1/.test(location.search)) {
            sessionStorage.removeItem(SEEN_KEY);
            return;
        }
        if (sessionStorage.getItem(SEEN_KEY)) {
            document.documentElement.classList.add('skip-intro-on-reload');
        }
    } catch (e) { /* private mode */ }
})();
</script>
