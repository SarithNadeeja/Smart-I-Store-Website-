/**
 * Products page — category subnav + brand / model / stock dropdown filters + sort
 */
(function () {
    'use strict';

    var categorySubnav = document.getElementById('category-subnav');
    var productGrid = document.getElementById('product-grid');
    var emptyFilterMsg = document.getElementById('products-empty-filter');
    var filterClearBtn = document.getElementById('filter-clear');
    var brandSelect = document.getElementById('filter-brand');
    var modelSelect = document.getElementById('filter-model');
    var stockSelect = document.getElementById('filter-stock');
    var sortSelect = document.getElementById('filter-sort');
    var brandGroup = document.getElementById('filter-group-brand');
    var modelGroup = document.getElementById('filter-group-model');
    var stockGroup = document.getElementById('filter-group-stock');

    if (!productGrid) {
        return;
    }

    var productCards = Array.prototype.slice.call(productGrid.querySelectorAll('.product-card'));
    if (!productCards.length) {
        return;
    }

    var stockLabels = { in_stock: 'In stock', out_of_stock: 'Out of stock', pre_order: 'Pre order' };
    var labelsEl = document.getElementById('product-stock-labels');
    if (labelsEl) {
        try {
            stockLabels = JSON.parse(labelsEl.textContent || '{}');
        } catch (e) { /* keep defaults */ }
    }

    var state = {
        category: 'all',
        brand: '',
        model: '',
        stock: '',
        sort: '',
    };

    function readUrlState() {
        var params = new URLSearchParams(window.location.search);
        var cat = params.get('category');
        state.category = cat && categorySubnav && categorySubnav.querySelector('[data-filter="' + cat + '"]')
            ? cat
            : (categorySubnav && categorySubnav.querySelector('.category-subnav__btn.is-active')
                ? categorySubnav.querySelector('.category-subnav__btn.is-active').getAttribute('data-filter') || 'all'
                : 'all');
        state.brand = params.get('brand') || '';
        state.model = params.get('model') || '';
        state.stock = params.get('stock') || '';
        state.sort = params.get('sort') || (sortSelect ? sortSelect.value : '');
    }

    function cardsInScope(category, brand) {
        return productCards.filter(function (card) {
            var catId = card.getAttribute('data-category-id') || '0';
            var cardBrand = card.getAttribute('data-brand') || '';
            if (category !== 'all' && catId !== String(category)) {
                return false;
            }
            if (brand && cardBrand !== brand) {
                return false;
            }
            return true;
        });
    }

    function collectFacets(category, brand) {
        var cards = cardsInScope(category, brand);
        var brands = {};
        var models = {};
        var stocks = {};

        cards.forEach(function (card) {
            var cardBrand = card.getAttribute('data-brand') || '';
            var model = card.getAttribute('data-model') || '';
            var stock = card.getAttribute('data-stock') || '';

            if (cardBrand) {
                brands[cardBrand] = true;
            }
            if (model) {
                models[model] = true;
            }
            if (stock) {
                stocks[stock] = true;
            }
        });

        return {
            brands: Object.keys(brands).sort(function (a, b) { return a.localeCompare(b); }),
            models: Object.keys(models).sort(function (a, b) { return a.localeCompare(b); }),
            stocks: Object.keys(stocks).sort(),
        };
    }

    function populateSelect(selectEl, items, allLabel, currentValue, labelsMap) {
        if (!selectEl) {
            return;
        }

        var previous = currentValue || selectEl.value;
        selectEl.innerHTML = '';

        var allOpt = document.createElement('option');
        allOpt.value = '';
        allOpt.textContent = allLabel;
        selectEl.appendChild(allOpt);

        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item;
            opt.textContent = labelsMap && labelsMap[item] ? labelsMap[item] : item;
            if (item === previous) {
                opt.selected = true;
            }
            selectEl.appendChild(opt);
        });

        if (previous && items.indexOf(previous) === -1) {
            selectEl.value = '';
            return '';
        }

        selectEl.value = previous && items.indexOf(previous) !== -1 ? previous : '';
        return selectEl.value;
    }

    function rebuildFacetFilters() {
        var facets = collectFacets(state.category, '');

        if (brandGroup && brandSelect) {
            if (facets.brands.length) {
                brandGroup.hidden = false;
                state.brand = populateSelect(
                    brandSelect,
                    facets.brands,
                    'All brands',
                    state.brand
                ) || '';
            } else {
                brandGroup.hidden = true;
                state.brand = '';
                brandSelect.value = '';
            }
        }

        var modelFacets = collectFacets(state.category, state.brand);
        if (modelGroup && modelSelect) {
            if (modelFacets.models.length) {
                modelGroup.hidden = false;
                state.model = populateSelect(
                    modelSelect,
                    modelFacets.models,
                    'All models',
                    state.model
                ) || '';
            } else {
                modelGroup.hidden = true;
                state.model = '';
                modelSelect.value = '';
            }
        }

        if (stockGroup && stockSelect) {
            if (facets.stocks.length) {
                stockGroup.hidden = false;
                state.stock = populateSelect(
                    stockSelect,
                    facets.stocks,
                    'All stock',
                    state.stock,
                    stockLabels
                ) || '';
            } else {
                stockGroup.hidden = true;
                state.stock = '';
                stockSelect.value = '';
            }
        }
    }

    function cardMatches(card) {
        var catId = card.getAttribute('data-category-id') || '0';
        var brand = card.getAttribute('data-brand') || '';
        var model = card.getAttribute('data-model') || '';
        var stock = card.getAttribute('data-stock') || '';

        if (state.category !== 'all' && catId !== String(state.category)) {
            return false;
        }
        if (state.brand && brand !== state.brand) {
            return false;
        }
        if (state.model && model !== state.model) {
            return false;
        }
        if (state.stock && stock !== state.stock) {
            return false;
        }
        return true;
    }

    function sortCards() {
        var sort = state.sort || (sortSelect ? sortSelect.value : '');
        var visible = productCards.filter(function (card) {
            return !card.classList.contains('is-hidden');
        });

        visible.sort(function (a, b) {
            if (sort === 'price-asc') {
                return parseFloat(a.getAttribute('data-price') || 0) - parseFloat(b.getAttribute('data-price') || 0);
            }
            if (sort === 'price-desc') {
                return parseFloat(b.getAttribute('data-price') || 0) - parseFloat(a.getAttribute('data-price') || 0);
            }
            if (sort === 'name-asc') {
                var nameA = (a.querySelector('.product-name') || {}).textContent || '';
                var nameB = (b.querySelector('.product-name') || {}).textContent || '';
                return nameA.localeCompare(nameB);
            }
            return 0;
        });

        visible.forEach(function (card) {
            productGrid.appendChild(card);
        });
    }

    function updateClearButton() {
        if (!filterClearBtn) {
            return;
        }
        var hasSecondary = state.brand || state.model || state.stock || state.sort;
        filterClearBtn.hidden = !hasSecondary;
    }

    function syncUrl() {
        if (!window.history || !window.history.replaceState) {
            return;
        }
        var url = new URL(window.location.href);
        if (state.category === 'all') {
            url.searchParams.delete('category');
        } else {
            url.searchParams.set('category', String(state.category));
        }
        if (state.brand) {
            url.searchParams.set('brand', state.brand);
        } else {
            url.searchParams.delete('brand');
        }
        if (state.model) {
            url.searchParams.set('model', state.model);
        } else {
            url.searchParams.delete('model');
        }
        if (state.stock) {
            url.searchParams.set('stock', state.stock);
        } else {
            url.searchParams.delete('stock');
        }
        if (state.sort) {
            url.searchParams.set('sort', state.sort);
        } else {
            url.searchParams.delete('sort');
        }
        window.history.replaceState({}, '', url.pathname + url.search + url.hash);
    }

    function applyFilters(updateUrl) {
        var visible = 0;

        if (categorySubnav) {
            var categoryBtns = categorySubnav.querySelectorAll('.category-subnav__btn');
            categoryBtns.forEach(function (btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-filter') === String(state.category));
            });
        }

        productCards.forEach(function (card) {
            var show = cardMatches(card);
            card.classList.toggle('is-hidden', !show);
            if (show) {
                visible += 1;
            }
        });

        if (emptyFilterMsg) {
            emptyFilterMsg.hidden = visible > 0;
        }

        rebuildFacetFilters();
        sortCards();
        updateClearButton();

        if (updateUrl) {
            syncUrl();
        }
    }

    if (categorySubnav) {
        categorySubnav.addEventListener('click', function (e) {
            var btn = e.target.closest('.category-subnav__btn');
            if (!btn) {
                return;
            }
            e.preventDefault();
            state.category = btn.getAttribute('data-filter') || 'all';
            state.brand = '';
            state.model = '';
            state.stock = '';
            applyFilters(true);
        });
    }

    if (brandSelect) {
        brandSelect.addEventListener('change', function () {
            state.brand = brandSelect.value;
            state.model = '';
            applyFilters(true);
        });
    }

    if (modelSelect) {
        modelSelect.addEventListener('change', function () {
            state.model = modelSelect.value;
            applyFilters(true);
        });
    }

    if (stockSelect) {
        stockSelect.addEventListener('change', function () {
            state.stock = stockSelect.value;
            applyFilters(true);
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            state.sort = sortSelect.value;
            applyFilters(true);
        });
    }

    if (filterClearBtn) {
        filterClearBtn.addEventListener('click', function () {
            state.brand = '';
            state.model = '';
            state.stock = '';
            state.sort = '';
            if (sortSelect) {
                sortSelect.value = '';
            }
            applyFilters(true);
        });
    }

    readUrlState();
    if (sortSelect && state.sort) {
        sortSelect.value = state.sort;
    }
    if (brandSelect && state.brand) {
        brandSelect.value = state.brand;
    }
    if (modelSelect && state.model) {
        modelSelect.value = state.model;
    }
    if (stockSelect && state.stock) {
        stockSelect.value = state.stock;
    }
    applyFilters(false);
})();
