(function () {
    'use strict';

    var STORAGE_KEY = 'admin-theme';
    var DEFAULT_THEME = 'dark';

    function normalizeTheme(value) {
        return value === 'light' ? 'light' : 'dark';
    }

    function getStoredTheme() {
        try {
            return normalizeTheme(localStorage.getItem(STORAGE_KEY) || DEFAULT_THEME);
        } catch (e) {
            return DEFAULT_THEME;
        }
    }

    function applyTheme(theme) {
        theme = normalizeTheme(theme);
        document.documentElement.setAttribute('data-admin-theme', theme);
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) {
            /* ignore */
        }
        document.querySelectorAll('.admin-theme-toggle__btn').forEach(function (btn) {
            var isActive = btn.getAttribute('data-theme') === theme;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function initToggle() {
        document.querySelectorAll('.admin-theme-toggle').forEach(function (wrap) {
            if (wrap.dataset.themeBound) {
                return;
            }
            wrap.dataset.themeBound = '1';
            wrap.querySelectorAll('.admin-theme-toggle__btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    applyTheme(btn.getAttribute('data-theme'));
                });
            });
        });
    }

    window.adminApplyTheme = applyTheme;
    window.adminGetTheme = getStoredTheme;

    function boot() {
        applyTheme(getStoredTheme());
        initToggle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
