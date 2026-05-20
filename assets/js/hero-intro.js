/**
 * Intro overlay — chroma-keyed video; UI fades out in sync with playback on Enter.
 */
(function () {
    'use strict';

    const INTRO_SEEN_KEY = 'smartistore_intro_seen';

    function markIntroSeen() {
        try {
            sessionStorage.setItem(INTRO_SEEN_KEY, '1');
        } catch (e) { /* private mode */ }
    }

    const REVEAL_START = 0.78;
    const PLAYBACK_RATE = 2;
    const experience = document.getElementById('intro-experience');
    const heroBannerEarly = document.querySelector('.hero-banner--after-intro');

    if (document.documentElement.classList.contains('skip-intro-on-reload')) {
        markIntroSeen();
        document.body.classList.add('is-intro-complete');
        if (experience && experience.parentNode) experience.remove();
        if (heroBannerEarly) heroBannerEarly.classList.add('is-revealed');
        window.dispatchEvent(new CustomEvent('hero:revealed'));
        return;
    }

    if (!experience) return;

    const video = document.getElementById('hero-intro-video');
    const canvas = document.getElementById('hero-intro-canvas');
    const stage = document.getElementById('hero-intro-stage');
    const introLayer = document.getElementById('hero-intro');
    const heroBanner = document.querySelector('.hero-banner--after-intro');
    const enterBtn = document.getElementById('hero-intro-enter');
    const statusEl = document.getElementById('hero-intro-status');

    if (!video || !canvas || !stage || !enterBtn) return;

    let trimStart = parseFloat(experience.getAttribute('data-trim')) || 2;

    const ctx = canvas.getContext('2d', { alpha: true, willReadFrequently: true });
    if (!ctx) return;

    const buffer = document.createElement('canvas');
    const bctx = buffer.getContext('2d', { willReadFrequently: true });

    let duration = 0;
    let playable = 0;
    let heroRevealed = false;
    let heroPlaybackStarted = false;
    let isPlaying = false;
    let initialized = false;
    let chromaReady = false;
    let layoutCache = null;
    let rafId = null;

    video.muted = true;
    video.defaultMuted = true;
    video.volume = 0;
    video.setAttribute('muted', '');
    video.playsInline = true;
    video.preload = 'auto';
    video.pause();

    canvas.classList.remove('is-ready');

    document.body.classList.add('is-intro-active');
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);

    function setStatus(msg, show) {
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.hidden = !show;
    }

    function enableEnter() {
        enterBtn.disabled = false;
        enterBtn.setAttribute('aria-busy', 'false');
        setStatus('', false);
    }

    /** Same green-screen logic as main hero — never show raw green frames */
    function applyChromaKey(imageData) {
        const d = imageData.data;
        for (let i = 0; i < d.length; i += 4) {
            const r = d[i];
            const g = d[i + 1];
            const b = d[i + 2];
            const greenExcess = g - Math.max(r, b);

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

    function resizeCanvas() {
        const rect = stage.getBoundingClientRect();
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const w = Math.max(1, Math.floor((rect.width || window.innerWidth) * dpr));
        const h = Math.max(1, Math.floor((rect.height || window.innerHeight) * dpr));
        if (canvas.width !== w || canvas.height !== h) {
            canvas.width = w;
            canvas.height = h;
            layoutCache = null;
        }
    }

    function isMobileIntroView() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    /** Desktop / tablet intro — original layout (unchanged). */
    function getDesktopIntroLayout(vw, vh, cw, ch) {
        const videoAspect = vw / vh;
        const canvasAspect = cw / ch;
        const fit = 0.88;
        const leftBias = 0.34;
        let dw, dh, dx, dy;

        if (videoAspect > canvasAspect) {
            dh = Math.round(ch * fit);
            dw = Math.round(dh * videoAspect);
            dx = Math.round(cw * leftBias - dw / 2);
            dy = Math.round((ch - dh) / 2);
        } else {
            dw = Math.round(cw * fit);
            dh = Math.round(dw / videoAspect);
            dx = Math.round(cw * leftBias - dw / 2);
            dy = Math.round((ch - dh) / 2);
        }

        dx = Math.max(8, Math.min(cw - dw - 8, dx));

        return { dw, dh, dx, dy, useCoverCrop: false, sourceBiasX: 0 };
    }

    /** Mobile only — center video on screen, slight left nudge for the phone. */
    function getMobileIntroLayout(vw, vh, cw, ch) {
        const isSmallPhone = window.innerWidth <= 480;
        const panXContent = isSmallPhone ? -0.04 : -0.035;
        const sourceBiasX = isSmallPhone ? -0.14 : -0.12;
        const phoneAnchorY = 0.44;

        const scale = Math.max(cw / vw, ch / vh);
        const dw = Math.round(vw * scale);
        const dh = Math.round(vh * scale);
        const dx = Math.round((cw - dw) / 2 + panXContent * cw);
        const dy = Math.round(ch * phoneAnchorY - dh / 2);

        return { dw, dh, dx, dy, useCoverCrop: true, sourceBiasX };
    }

    function getLayout(vw, vh, cw, ch) {
        if (isMobileIntroView()) {
            return getMobileIntroLayout(vw, vh, cw, ch);
        }
        return getDesktopIntroLayout(vw, vh, cw, ch);
    }

    function drawVideoFrame(vw, vh, dw, dh, useCoverCrop, sourceBiasX) {
        bctx.clearRect(0, 0, dw, dh);

        if (!useCoverCrop) {
            bctx.drawImage(video, 0, 0, dw, dh);
            return;
        }

        const scale = Math.max(dw / vw, dh / vh);
        const sw = dw / scale;
        const sh = dh / scale;
        const maxSx = Math.max(0, vw - sw);
        const maxSy = Math.max(0, vh - sh);
        const sx = Math.max(0, Math.min(maxSx, maxSx * (0.5 + sourceBiasX)));
        const sy = Math.max(0, Math.min(maxSy, (vh - sh) / 2));
        bctx.drawImage(video, sx, sy, sw, sh, 0, 0, dw, dh);
    }

    function seekToTime(time, callback) {
        let done = false;
        function finish() {
            if (done) return;
            done = true;
            video.removeEventListener('seeked', onSeeked);
            callback();
        }
        function onSeeked() {
            finish();
        }
        video.addEventListener('seeked', onSeeked);
        video.currentTime = Math.min(time, Math.max(0, duration - 0.05));
        setTimeout(finish, 350);
    }

    function drawFrame() {
        const vw = video.videoWidth;
        const vh = video.videoHeight;
        if (!vw || !vh || video.readyState < 2) return false;

        resizeCanvas();
        const cw = canvas.width;
        const ch = canvas.height;
        if (cw < 2 || ch < 2) return false;

        const layoutKey = isMobileIntroView() ? 'mobile' : 'desktop';
        if (
            !layoutCache
            || layoutCache.cw !== cw
            || layoutCache.ch !== ch
            || layoutCache.layoutKey !== layoutKey
        ) {
            layoutCache = Object.assign({ cw, ch, layoutKey }, getLayout(vw, vh, cw, ch));
        }

        const { dw, dh, dx, dy, useCoverCrop, sourceBiasX } = layoutCache;

        if (buffer.width !== dw || buffer.height !== dh) {
            buffer.width = dw;
            buffer.height = dh;
        }

        drawVideoFrame(vw, vh, dw, dh, useCoverCrop, sourceBiasX || 0);

        try {
            const frame = bctx.getImageData(0, 0, dw, dh);
            bctx.putImageData(applyChromaKey(frame), 0, 0);
        } catch (e) {
            return false;
        }

        ctx.clearRect(0, 0, cw, ch);
        ctx.drawImage(buffer, dx, dy);

        if (!chromaReady) {
            chromaReady = true;
            canvas.classList.add('is-ready');
        }

        return true;
    }

    function getPlaybackProgress() {
        if (!playable) return 0;
        return Math.max(0, Math.min(1, (video.currentTime - trimStart) / playable));
    }

    function startHeroPlayback() {
        if (heroPlaybackStarted) return;
        heroPlaybackStarted = true;
        window.dispatchEvent(new CustomEvent('hero:revealed'));
    }

    function updateReveal(progress) {
        if (!heroBanner || !introLayer) return;

        if (progress < REVEAL_START) {
            heroBanner.classList.remove('is-transitioning');
            heroBanner.style.setProperty('--hero-opacity', '0');
            return;
        }

        startHeroPlayback();

        const t = (progress - REVEAL_START) / (1 - REVEAL_START);
        const ease = t * t * (3 - 2 * t);
        introLayer.style.opacity = String(1 - ease);
        heroBanner.classList.add('is-transitioning');
        heroBanner.style.setProperty('--hero-opacity', ease);
        heroBanner.style.setProperty('--hero-scale', 0.96 + ease * 0.04);
    }

    function playbackLoop() {
        drawFrame();
        updateReveal(getPlaybackProgress());

        if (!video.paused && !video.ended) {
            rafId = requestAnimationFrame(playbackLoop);
        }
    }

    function stopLoop() {
        if (rafId !== null) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
    }

    function beginVideoPlay() {
        video.playbackRate = PLAYBACK_RATE;

        const playPromise = video.play();
        if (playPromise && typeof playPromise.then === 'function') {
            playPromise
                .then(function () {
                    playbackLoop();
                })
                .catch(function () {
                    console.warn('Intro playback blocked');
                    isPlaying = false;
                    introLayer.classList.remove('is-playing');
                    enterBtn.disabled = false;
                    enterBtn.setAttribute('aria-busy', 'false');
                    setStatus('Tap Enter Store to continue', true);
                });
        } else {
            playbackLoop();
        }
    }

    function startPlayback() {
        if (isPlaying || heroRevealed) return;
        isPlaying = true;

        enterBtn.disabled = true;
        enterBtn.setAttribute('aria-busy', 'true');

        /* Fade text out while video plays — same moment, no delay */
        introLayer.classList.add('is-playing');
        beginVideoPlay();
    }

    function onVideoEnded() {
        stopLoop();
        video.pause();
        drawFrame();
        updateReveal(1);
        finishIntro();
    }

    function finishIntro() {
        if (heroRevealed) return;
        heroRevealed = true;
        isPlaying = false;
        stopLoop();

        markIntroSeen();
        startHeroPlayback();
        window.scrollTo(0, 0);

        if (heroBanner) {
            heroBanner.classList.remove('is-transitioning');
            heroBanner.classList.add('is-revealed');
            heroBanner.style.removeProperty('--hero-opacity');
            heroBanner.style.removeProperty('--hero-scale');
        }

        introLayer.classList.remove('is-playing');
        introLayer.style.opacity = '0';
        introLayer.style.pointerEvents = 'none';

        document.body.classList.remove('is-intro-active');
        document.body.classList.add('is-intro-complete');

        requestAnimationFrame(function () {
            window.scrollTo(0, 0);
            if (experience.parentNode) experience.remove();
            video.pause();
            video.removeAttribute('src');
            while (video.firstChild) video.removeChild(video.firstChild);
            video.load();
        });
    }

    function prepareIntro() {
        if (trimStart >= duration - 0.5) {
            trimStart = Math.min(1, duration * 0.15);
        }

        playable = Math.max(0.5, duration - trimStart);

        seekToTime(trimStart, function () {
            function tryDraw() {
                if (drawFrame()) {
                    enableEnter();
                    return;
                }
                requestAnimationFrame(tryDraw);
            }
            tryDraw();
        });
    }

    function beginIntro() {
        if (initialized) return;
        initialized = true;
        setStatus('Loading intro…', true);
        enterBtn.setAttribute('aria-busy', 'true');
        prepareIntro();
    }

    function onVideoReady() {
        if (initialized) return;
        duration = video.duration;
        if (!duration || !isFinite(duration) || duration <= 0) return;
        beginIntro();
    }

    enterBtn.addEventListener('click', startPlayback);

    video.addEventListener('ended', onVideoEnded);
    video.addEventListener('loadedmetadata', onVideoReady);
    video.addEventListener('canplay', onVideoReady);
    video.addEventListener('loadeddata', function () {
        if (!initialized && video.duration) onVideoReady();
    });

    video.addEventListener('error', function () {
        console.error('Intro video error:', video.currentSrc, video.error);
        setStatus('Video not found — add assets/videos/intro.webm', true);
        enableEnter();
    });

    window.addEventListener('resize', function () {
        if (!heroRevealed && chromaReady) {
            layoutCache = null;
            drawFrame();
        }
    });

    if (window.matchMedia) {
        const mobileIntroMq = window.matchMedia('(max-width: 768px)');
        const onBreakpointChange = function () {
            if (!heroRevealed && chromaReady) {
                layoutCache = null;
                drawFrame();
            }
        };
        if (typeof mobileIntroMq.addEventListener === 'function') {
            mobileIntroMq.addEventListener('change', onBreakpointChange);
        } else if (typeof mobileIntroMq.addListener === 'function') {
            mobileIntroMq.addListener(onBreakpointChange);
        }
    }

    video.load();
    if (video.readyState >= 1) onVideoReady();
})();
