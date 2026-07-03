(function () {
    var form = document.getElementById('preowned-filters');
    var grid = document.getElementById('preowned-grid');
    var emptyMsg = document.getElementById('preowned-empty-filter');
    var searchForm = document.getElementById('preowned-search');

    if (form) {
        form.querySelectorAll('select').forEach(function (el) {
            el.addEventListener('change', function () {
                syncSearchToForm();
                form.submit();
            });
        });

        form.addEventListener('submit', function () {
            syncSearchToForm();
        });
    }

    function syncSearchToForm() {
        if (!form) {
            return;
        }
        var hidden = form.querySelector('#preowned-filter-q');
        if (hidden) {
            hidden.value = getSearchQuery();
        }
    }

    if (!grid) {
        return;
    }

    var cards = Array.prototype.slice.call(grid.querySelectorAll('.product-card'));
    if (!cards.length) {
        return;
    }

    function getSearchQuery() {
        if (!searchForm) {
            return '';
        }
        var input = searchForm.querySelector('.site-search__input');
        return input ? input.value.trim() : '';
    }

    function getFilterState() {
        var brandEl = form ? form.querySelector('#filter-brand') : null;
        var conditionEl = form ? form.querySelector('#filter-condition') : null;
        return {
            brand: brandEl ? brandEl.value : '',
            condition: conditionEl ? conditionEl.value : '',
            q: getSearchQuery().toLowerCase(),
        };
    }

    function cardMatches(card, state) {
        if (state.brand && (card.getAttribute('data-brand') || '') !== state.brand) {
            return false;
        }
        if (state.condition && (card.getAttribute('data-preowned-condition') || '') !== state.condition) {
            return false;
        }
        if (state.q) {
            var blob = (card.getAttribute('data-search') || '').toLowerCase();
            if (blob.indexOf(state.q) === -1) {
                return false;
            }
        }
        return true;
    }

    function applyFilters() {
        var state = getFilterState();
        var visible = 0;

        cards.forEach(function (card) {
            var show = cardMatches(card, state);
            card.classList.toggle('is-hidden', !show);
            if (show) {
                visible += 1;
            }
        });

        if (emptyMsg) {
            emptyMsg.hidden = visible > 0;
        }
    }

    document.addEventListener('site-search:filter', function () {
        applyFilters();
    });

    applyFilters();
})();
