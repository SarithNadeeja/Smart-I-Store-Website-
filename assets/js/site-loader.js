/**
 * Full-page loader — waits for window load, fonts, and hero banner video.
 */
(function () {
    'use strict';

    var loader = document.getElementById('site-loader');
    var label = document.getElementById('site-loader-label');
    if (!loader) return;

    var MIN_MS = 400;
    var VIDEO_TIMEOUT_MS = 60000;
    var started = Date.now();
    var finished = false;

    function parseVideos() {
        var raw = loader.getAttribute('data-videos');
        if (!raw) return [];
        try {
            var list = JSON.parse(raw);
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function setLabel(text) {
        if (label) label.textContent = text;
    }

    function waitWindowLoad() {
        return new Promise(function (resolve) {
            if (document.readyState === 'complete') {
                resolve();
                return;
            }
            window.addEventListener('load', resolve, { once: true });
        });
    }

    function waitFonts() {
        if (document.fonts && document.fonts.ready) {
            return document.fonts.ready.catch(function () {});
        }
        return Promise.resolve();
    }

    function preloadVideo(entry) {
        return new Promise(function (resolve) {
            var v = document.createElement('video');
            var settled = false;

            function done() {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                v.removeAttribute('src');
                while (v.firstChild) v.removeChild(v.firstChild);
                v.load();
                resolve();
            }

            var timer = setTimeout(done, VIDEO_TIMEOUT_MS);

            v.muted = true;
            v.defaultMuted = true;
            v.volume = 0;
            v.setAttribute('muted', '');
            v.playsInline = true;
            v.preload = 'auto';

            v.addEventListener('canplaythrough', done, { once: true });
            v.addEventListener('error', done, { once: true });
            v.addEventListener('loadeddata', function () {
                if (v.readyState >= 3) done();
            }, { once: true });

            if (entry.sources && entry.sources.length) {
                entry.sources.forEach(function (src) {
                    var s = document.createElement('source');
                    s.src = src.url;
                    s.type = src.type;
                    v.appendChild(s);
                });
            } else if (entry.url) {
                v.src = entry.url;
            } else {
                done();
                return;
            }

            v.style.cssText = 'position:absolute;width:1px;height:1px;opacity:0;pointer-events:none';
            document.body.appendChild(v);
            v.load();
        });
    }

    function buildVideoJobs(videos) {
        var jobs = [];

        videos.forEach(function (item) {
            if (!item || !item.url || item.role !== 'hero') return;
            var sources = [{ url: item.url, type: item.type || 'video/webm' }];
            if (item.fallback_url) {
                sources.push({
                    url: item.fallback_url,
                    type: item.fallback_type || 'video/mp4'
                });
            }
            jobs.push({ role: 'hero', sources: sources });
        });

        return jobs;
    }

    function warmHeroVideoElement() {
        return new Promise(function (resolve) {
            var hero = document.getElementById('hero-source-video');
            if (!hero) {
                resolve();
                return;
            }

            var settled = false;
            function done() {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                resolve();
            }

            var timer = setTimeout(done, VIDEO_TIMEOUT_MS);

            hero.muted = true;
            hero.defaultMuted = true;
            hero.volume = 0;
            hero.setAttribute('muted', '');
            hero.playsInline = true;
            hero.preload = 'auto';

            hero.addEventListener('canplaythrough', done, { once: true });
            hero.addEventListener('error', done, { once: true });
            hero.addEventListener('loadeddata', function () {
                if (hero.readyState >= 3) done();
            }, { once: true });

            if (hero.readyState >= 3) {
                done();
                return;
            }

            hero.load();
        });
    }

    function preloadAllVideos(videos) {
        var jobs = buildVideoJobs(videos);
        if (!jobs.length) return Promise.resolve();

        setLabel('Loading banner video…');

        return Promise.all(
            jobs.map(function (job) {
                if (job.sources) return preloadVideo(job);
                return preloadVideo({ url: job.url });
            })
        );
    }

    function finish() {
        if (finished) return;
        finished = true;

        var elapsed = Date.now() - started;
        var wait = Math.max(0, MIN_MS - elapsed);

        setTimeout(function () {
            loader.classList.add('is-hidden');
            loader.setAttribute('aria-busy', 'false');
            document.documentElement.classList.remove('is-site-loading');
            document.documentElement.classList.add('is-site-loaded');

            window.dispatchEvent(new CustomEvent('site:ready'));

            window.setTimeout(function () {
                if (loader.parentNode) loader.parentNode.removeChild(loader);
            }, 600);
        }, wait);
    }

    function run() {
        var videos = parseVideos();
        var hasVideos = videos.length > 0;

        if (hasVideos) setLabel('Loading…');

        Promise.all([
            waitWindowLoad(),
            waitFonts(),
            preloadAllVideos(videos),
            warmHeroVideoElement()
        ])
            .then(finish)
            .catch(finish);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
