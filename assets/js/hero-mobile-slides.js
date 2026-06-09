/**
 * Home hero — image slideshow (mobile + desktop banners)
 */
(function () {
    'use strict';

    var INTERVAL_MS = 4000;

    function initSlideshow(rootId, slideSelector, mq, mqMatches) {
        var root = document.getElementById(rootId);
        if (!root) return;

        var slides = Array.prototype.slice.call(root.querySelectorAll(slideSelector));
        if (!slides.length) return;

        var index = 0;
        var timer = null;

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
            if (!mqMatches()) return;
            setActive(index);
            if (slides.length > 1) {
                timer = setInterval(next, INTERVAL_MS);
            }
        }

        function onMqChange() {
            index = 0;
            start();
        }

        mq.addEventListener('change', onMqChange);

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stop();
            } else if (mqMatches()) {
                start();
            }
        });

        start();
    }

    var mobileMq = window.matchMedia('(max-width: 768px)');
    var desktopMq = window.matchMedia('(min-width: 769px)');

    initSlideshow('hero-mobile-slides', '.hero-banner__mobile-slide', mobileMq, function () {
        return mobileMq.matches;
    });

    initSlideshow('hero-desktop-slides', '.hero-banner__desktop-slide', desktopMq, function () {
        return desktopMq.matches;
    });
})();
