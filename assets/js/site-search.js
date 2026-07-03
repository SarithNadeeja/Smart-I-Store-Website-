/**
 * Site-wide product search — live suggestions + catalog card filtering.
 */
(function () {
    'use strict';

    var apiUrl = window.SITE_SEARCH_API || '';
    var debounceMs = 280;
    var debounceTimers = {};

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function debounce(key, fn) {
        if (debounceTimers[key]) {
            clearTimeout(debounceTimers[key]);
        }
        debounceTimers[key] = setTimeout(fn, debounceMs);
    }

    function getClearButton(form) {
        return form.querySelector('.site-search__clear');
    }

    function toggleClearButton(form, show) {
        var btn = getClearButton(form);
        if (!btn) {
            return;
        }
        btn.hidden = !show;
    }

    function closeDropdown(form) {
        var dropdown = form.querySelector('.site-search__dropdown');
        var input = form.querySelector('.site-search__input');
        if (!dropdown) {
            return;
        }
        dropdown.hidden = true;
        dropdown.innerHTML = '';
        if (input) {
            input.setAttribute('aria-expanded', 'false');
        }
    }

    function openDropdown(form) {
        var dropdown = form.querySelector('.site-search__dropdown');
        var input = form.querySelector('.site-search__input');
        if (!dropdown || !input) {
            return;
        }
        dropdown.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function renderSuggestions(form, results, q) {
        var dropdown = form.querySelector('.site-search__dropdown');
        if (!dropdown) {
            return;
        }

        if (!results.length) {
            dropdown.innerHTML = '<p class="site-search__empty">No matching items in stock. Try another name or brand.</p>';
            openDropdown(form);
            return;
        }

        var html = results.map(function (item) {
            var thumb = item.image
                ? '<img class="site-search__thumb" src="' + escapeHtml(item.image) + '" alt="" loading="lazy">'
                : '<span class="site-search__thumb site-search__thumb--empty" aria-hidden="true"></span>';
            var badgeClass = item.scope === 'preowned' ? 'site-search__badge--preowned' : 'site-search__badge--product';
            var meta = item.meta ? '<span class="site-search__meta">' + escapeHtml(item.meta) + '</span>' : '';

            return '<a class="site-search__option" role="option" href="' + escapeHtml(item.url) + '">'
                + thumb
                + '<span class="site-search__option-body">'
                + '<span class="site-search__option-top">'
                + '<span class="site-search__name">' + escapeHtml(item.name) + '</span>'
                + '<span class="site-search__badge ' + badgeClass + '">' + escapeHtml(item.scope_label) + '</span>'
                + '</span>'
                + meta
                + '<span class="site-search__price">' + escapeHtml(item.price_label) + '</span>'
                + '</span>'
                + '</a>';
        }).join('');

        dropdown.innerHTML = html;
        openDropdown(form);
    }

    function fetchSuggestions(form, q) {
        if (!apiUrl || q.length < 2) {
            closeDropdown(form);
            return;
        }

        var scope = form.getAttribute('data-scope') || 'all';
        var url = apiUrl + '?action=suggest&q=' + encodeURIComponent(q) + '&scope=' + encodeURIComponent(scope);

        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    closeDropdown(form);
                    return;
                }
                var input = form.querySelector('.site-search__input');
                if (!input || input.value.trim() !== q) {
                    return;
                }
                renderSuggestions(form, data.results || [], q);
            })
            .catch(function () {
                closeDropdown(form);
            });
    }

    function cardMatchesQuery(card, q) {
        if (!q) {
            return true;
        }
        var blob = (card.getAttribute('data-search') || '').toLowerCase();
        return blob.indexOf(q) !== -1;
    }

    function applyLiveFilter(form) {
        var input = form.querySelector('.site-search__input');
        if (!input) {
            return;
        }

        var q = input.value.trim().toLowerCase();
        var grid = document.getElementById('product-grid') || document.getElementById('preowned-grid');
        toggleClearButton(form, q !== '');

        if (grid && grid.id === 'product-grid') {
            document.dispatchEvent(new CustomEvent('site-search:filter', { detail: { q: q, form: form } }));
            return;
        }

        var emptyMsg = document.getElementById('products-empty-filter') || document.getElementById('preowned-empty-filter');
        if (!grid) {
            return;
        }

        var cards = grid.querySelectorAll('.product-card');
        var visible = 0;

        cards.forEach(function (card) {
            var show = cardMatchesQuery(card, q);
            card.classList.toggle('is-hidden', !show);
            if (show) {
                visible += 1;
            }
        });

        if (emptyMsg) {
            emptyMsg.hidden = visible > 0;
        }

        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            if (q) {
                url.searchParams.set('q', input.value.trim());
            } else {
                url.searchParams.delete('q');
            }
            window.history.replaceState({}, '', url.pathname + url.search + url.hash);
        }

        document.dispatchEvent(new CustomEvent('site-search:filter', { detail: { q: q, form: form } }));
    }

    function initAutocompleteForm(form) {
        var input = form.querySelector('.site-search__input');
        if (!input) {
            return;
        }

        input.addEventListener('input', function () {
            var q = input.value.trim();
            toggleClearButton(form, q !== '');
            debounce(form.id || 'search', function () {
                fetchSuggestions(form, q);
            });
        });

        input.addEventListener('focus', function () {
            var q = input.value.trim();
            if (q.length >= 2) {
                fetchSuggestions(form, q);
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDropdown(form);
                input.blur();
            }
        });
    }

    function initLiveFilterForm(form) {
        var input = form.querySelector('.site-search__input');
        if (!input) {
            return;
        }

        input.addEventListener('input', function () {
            debounce((form.id || 'filter') + '-live', function () {
                applyLiveFilter(form);
            });
        });

        var clearBtn = getClearButton(form);
        if (clearBtn && clearBtn.tagName === 'BUTTON') {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                applyLiveFilter(form);
                input.focus();
            });
        }
    }

    function initClearButtons() {
        document.querySelectorAll('.site-search').forEach(function (form) {
            if (form.getAttribute('data-live-filter') === '1') {
                return;
            }
            var clearBtn = getClearButton(form);
            var input = form.querySelector('.site-search__input');
            if (!clearBtn || !input || clearBtn.tagName !== 'BUTTON') {
                return;
            }
            clearBtn.addEventListener('click', function () {
                input.value = '';
                toggleClearButton(form, false);
                closeDropdown(form);
                input.focus();
            });
        });
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.site-search')) {
            return;
        }
        document.querySelectorAll('.site-search[data-autocomplete="1"]').forEach(closeDropdown);
    });

    document.querySelectorAll('.site-search').forEach(function (form) {
        if (form.getAttribute('data-autocomplete') === '1') {
            initAutocompleteForm(form);
        }
        if (form.getAttribute('data-live-filter') === '1') {
            initLiveFilterForm(form);
            if (form.querySelector('.site-search__input') && form.querySelector('.site-search__input').value.trim()) {
                applyLiveFilter(form);
            }
        }
    });

    initClearButtons();
})();
