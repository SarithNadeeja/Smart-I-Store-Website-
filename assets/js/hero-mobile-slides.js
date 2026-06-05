/**
 * Mobile hero — banner slideshow (desktop keeps video hero)
 */
(function () {
    'use strict';

    var root = document.getElementById('hero-mobile-slides');
    if (!root) return;

    var mq = window.matchMedia('(max-width: 768px)');
    var slides = Array.prototype.slice.call(root.querySelectorAll('.hero-banner__mobile-slide'));
    if (!slides.length) return;

    var index = 0;
    var timer = null;
    var INTERVAL_MS = 4000;

    function setActive(i) {
        slides.forEach(function (slide, n) {
            slide.classList.toggle('is-active', n === i);
        });
    }

    function next() {
        index = (index + 1) % slides.length;
        setActive(index);
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function start() {
        stop();
        if (!mq.matches) return;
        setActive(index);
        if (slides.length > 1) {
            timer = setInterval(next, INTERVAL_MS);
        }
    }

    mq.addEventListener('change', function () {
        index = 0;
        start();
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stop();
        } else if (mq.matches) {
            start();
        }
    });

    start();
})();
