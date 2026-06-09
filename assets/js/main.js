/**
 * Smart I Store — Main JavaScript
 */
(function () {
    'use strict';

    const header = document.getElementById('site-header');
    const navToggle = document.getElementById('nav-toggle');
    const mainNav = document.getElementById('main-nav');
    const navOverlay = document.getElementById('nav-overlay');

    /* --------------------------------------------------------------------------
       Fullscreen hero — green screen removal on radial background
       -------------------------------------------------------------------------- */
    (function initHeroChroma() {
        if (document.querySelector('.hero-banner--has-mobile-slides')
            || document.querySelector('.hero-banner--has-desktop-slides')) {
            return;
        }

        const video = document.getElementById('hero-source-video');
        const canvas = document.getElementById('hero-chroma-canvas');
        const stage = document.getElementById('hero-stage');
        const heroEl = document.getElementById('hero');
        if (!video || !canvas || !stage) return;

        const ctx = canvas.getContext('2d', { alpha: true, willReadFrequently: true });
        if (!ctx) return;

        let rafId = null;
        let started = false;

        /* Offscreen buffer — chroma only the video frame, not the whole canvas */
        const buffer = document.createElement('canvas');
        const bctx = buffer.getContext('2d', { willReadFrequently: true });

        video.loop = true;
        video.muted = true;
        video.defaultMuted = true;
        video.volume = 0;
        video.setAttribute('muted', '');
        video.playsInline = true;

        function resize() {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = Math.max(1, Math.floor(stage.clientWidth * dpr));
            const h = Math.max(1, Math.floor(stage.clientHeight * dpr));
            canvas.width = w;
            canvas.height = h;
        }

        /**
         * Key only strong green pixels (avoids wiping phones / dark areas)
         */
        function applyChromaKey(imageData) {
            const d = imageData.data;
            const len = d.length;

            for (let i = 0; i < len; i += 4) {
                const r = d[i];
                const g = d[i + 1];
                const b = d[i + 2];
                const greenExcess = g - Math.max(r, b);

                /* Must be visibly green */
                if (g < 80 || greenExcess < 28) continue;

                const softness = Math.min(1, (greenExcess - 28) / 55);
                const alpha = 1 - softness;

                if (alpha < 0.92) {
                    const avg = (r + b) * 0.5;
                    d[i + 1] = Math.round(g * 0.45 + avg * 0.55);
                }

                d[i + 3] = Math.round(d[i + 3] * Math.max(0, Math.min(1, alpha)));
            }

            return imageData;
        }

        /** Scale video to cover the full hero (like object-fit: cover) */
        function getCoverRect(vw, vh, cw, ch) {
            const videoAspect = vw / vh;
            const canvasAspect = cw / ch;
            let dw, dh, dx, dy;

            if (videoAspect > canvasAspect) {
                dh = ch;
                dw = Math.round(ch * videoAspect);
                dx = Math.round((cw - dw) / 2);
                dy = 0;
            } else {
                dw = cw;
                dh = Math.round(cw / videoAspect);
                dx = 0;
                dy = Math.round((ch - dh) / 2);
            }

            return { dw, dh, dx, dy };
        }

        function drawFrame() {
            const vw = video.videoWidth;
            const vh = video.videoHeight;
            if (!vw || !vh || video.readyState < 2) return false;

            const cw = canvas.width;
            const ch = canvas.height;
            const { dw, dh, dx, dy } = getCoverRect(vw, vh, cw, ch);

            /* Transparent — CSS radial background shows through keyed areas */
            ctx.clearRect(0, 0, cw, ch);

            buffer.width = dw;
            buffer.height = dh;
            bctx.clearRect(0, 0, dw, dh);
            bctx.drawImage(video, 0, 0, dw, dh);

            try {
                const frame = bctx.getImageData(0, 0, dw, dh);
                bctx.putImageData(applyChromaKey(frame), 0, 0);
                ctx.drawImage(buffer, dx, dy);
            } catch (err) {
                console.warn('Hero chroma key failed:', err);
                /* Fallback: show raw video frame without keying */
                ctx.drawImage(video, dx, dy, dw, dh);
            }

            return true;
        }

        function loop() {
            drawFrame();
            if (!video.paused) {
                rafId = requestAnimationFrame(loop);
            }
        }

        function startPlayback() {
            if (started) return;
            started = true;
            resize();

            const tryPlay = video.play();
            if (tryPlay && typeof tryPlay.then === 'function') {
                tryPlay
                    .then(function () {
                        cancelAnimationFrame(rafId);
                        loop();
                    })
                    .catch(function () {
                        console.warn('Hero autoplay blocked — tap the page to play.');
                        drawFrame();
                    });
            } else {
                loop();
            }
        }

        function beginHero() {
            if (heroEl) heroEl.classList.add('is-ready');
            startPlayback();
        }

        function onReady() {
            resize();
            drawFrame();
            if (document.documentElement.classList.contains('is-site-loaded')) {
                beginHero();
            } else {
                window.addEventListener('site:ready', beginHero, { once: true });
            }
        }

        video.addEventListener('error', function () {
            console.error('Hero video error. URL:', video.currentSrc || video.src);
        });

        window.addEventListener('resize', function () {
            resize();
            drawFrame();
        });

        /* Handle late script load (cached video may already be ready) */
        if (video.readyState >= 2) {
            onReady();
        } else {
            video.addEventListener('loadeddata', onReady, { once: true });
            video.addEventListener('canplay', onReady, { once: true });
        }

        /* Extra kick if autoplay attribute alone started the video */
        video.addEventListener('playing', function () {
            if (!started) onReady();
            else if (!rafId && !video.paused) loop();
        });
    })();

    /* --------------------------------------------------------------------------
       Sticky header
       -------------------------------------------------------------------------- */
    function handleScroll() {
        if (!header) return;
        header.classList.toggle('is-scrolled', window.scrollY > 40);
    }

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();

    /* --------------------------------------------------------------------------
       Mobile navigation
       -------------------------------------------------------------------------- */
    function closeNav() {
        if (!mainNav || !navToggle) return;
        mainNav.classList.remove('is-open');
        navToggle.classList.remove('is-active');
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.setAttribute('aria-label', 'Open menu');
        navOverlay?.classList.remove('is-visible');
        document.body.style.overflow = '';
    }

    function openNav() {
        if (!mainNav || !navToggle) return;
        mainNav.classList.add('is-open');
        navToggle.classList.add('is-active');
        navToggle.setAttribute('aria-expanded', 'true');
        navToggle.setAttribute('aria-label', 'Close menu');
        navOverlay?.classList.add('is-visible');
        document.body.style.overflow = 'hidden';
    }

    navToggle?.addEventListener('click', function () {
        if (mainNav.classList.contains('is-open')) closeNav();
        else openNav();
    });

    navOverlay?.addEventListener('click', closeNav);
    mainNav?.querySelectorAll('.nav-link').forEach(function (link) {
        link.addEventListener('click', closeNav);
    });

    /* GSAP scroll reveals */
    if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);

        gsap.utils.toArray('.reveal-up').forEach(function (el) {
            const delay = parseFloat(el.getAttribute('data-delay')) || 0;
            gsap.to(el, {
                opacity: 1,
                y: 0,
                duration: 0.9,
                delay: delay,
                ease: 'power3.out',
                scrollTrigger: {
                    trigger: el,
                    start: 'top 88%',
                    toggleActions: 'play none none none',
                },
            });
        });
    } else {
        document.querySelectorAll('.reveal-up').forEach(function (el) {
            el.style.opacity = '1';
            el.style.transform = 'none';
        });
    }

    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            const id = this.getAttribute('href');
            if (!id || id === '#') return;
            const target = document.querySelector(id);
            if (!target) return;
            e.preventDefault();
            const offset = header ? header.offsetHeight : 0;
            const top = target.getBoundingClientRect().top + window.scrollY - offset;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });
})();
