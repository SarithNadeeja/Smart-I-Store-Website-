/**
 * Home page advertisement slideshow — 4 ads per row, auto-advance.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-home-ads-slideshow]');
    if (!root) {
        return;
    }

    var track = root.querySelector('.home-ads-slideshow__track');
    var pages = Array.prototype.slice.call(root.querySelectorAll('.home-ads-slideshow__page'));
    var dots = Array.prototype.slice.call(root.querySelectorAll('.home-ads-slideshow__dot'));
    var prevBtn = root.querySelector('.home-ads-slideshow__arrow--prev');
    var nextBtn = root.querySelector('.home-ads-slideshow__arrow--next');

    if (!track || pages.length <= 1) {
        return;
    }

    var index = 0;
    var timer = null;
    var intervalMs = 5500;

    function goTo(nextIndex) {
        index = (nextIndex + pages.length) % pages.length;
        track.style.transform = 'translateX(-' + (index * 100) + '%)';

        dots.forEach(function (dot, i) {
            var active = i === index;
            dot.classList.toggle('is-active', active);
            dot.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function next() {
        goTo(index + 1);
    }

    function prev() {
        goTo(index - 1);
    }

    function restartTimer() {
        if (timer) {
            clearInterval(timer);
        }
        timer = setInterval(next, intervalMs);
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            next();
            restartTimer();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            prev();
            restartTimer();
        });
    }

    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            var target = parseInt(dot.getAttribute('data-slide-to') || '0', 10);
            goTo(target);
            restartTimer();
        });
    });

    root.addEventListener('mouseenter', function () {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    });

    root.addEventListener('mouseleave', restartTimer);

    restartTimer();
})();
