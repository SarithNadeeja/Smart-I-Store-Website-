(function () {
    'use strict';

    var shell = document.getElementById('admin-shell');
    var sidebar = document.getElementById('admin-sidebar');
    var backdrop = document.getElementById('admin-nav-backdrop');
    var menuBtn = document.getElementById('admin-menu-btn');

    if (!shell || !sidebar || !menuBtn) {
        return;
    }

    function isMobileNav() {
        return window.matchMedia('(max-width: 900px)').matches;
    }

    function setOpen(open) {
        shell.classList.toggle('is-nav-open', open);
        document.body.classList.toggle('admin-body--nav-open', open);
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        menuBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        if (backdrop) {
            backdrop.hidden = !open;
            backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
    }

    function closeNav() {
        setOpen(false);
    }

    function toggleNav() {
        setOpen(!shell.classList.contains('is-nav-open'));
    }

    menuBtn.addEventListener('click', toggleNav);

    if (backdrop) {
        backdrop.addEventListener('click', closeNav);
    }

    sidebar.querySelectorAll('.admin-nav__link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (isMobileNav()) {
                closeNav();
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && shell.classList.contains('is-nav-open')) {
            closeNav();
        }
    });

    window.addEventListener('resize', function () {
        if (!isMobileNav()) {
            closeNav();
        }
    });
})();
