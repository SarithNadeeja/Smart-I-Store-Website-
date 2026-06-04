(function () {
    'use strict';

    var cfg = window.POS_REPAIR || {};
    var searchApi = cfg.searchApi || '';
    var customerSearch = document.getElementById('pos-customer-search');
    var customerResults = document.getElementById('pos-customer-results');
    var customerIdInput = document.getElementById('customer_id');
    var customerPhoneInput = document.getElementById('customer_phone');
    var form = document.getElementById('pos-repair-form');

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, ms);
        };
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function escapeAttr(s) {
        return String(s || '').replace(/"/g, '&quot;');
    }

    customerSearch.addEventListener('input', debounce(function () {
        var q = customerSearch.value.trim();
        if (q.length < 1) {
            customerResults.innerHTML = '';
            return;
        }
        fetch(searchApi + '?type=customers&q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var items = data.ok ? (data.items || []) : [];
                if (!items.length) {
                    customerResults.innerHTML = '<p class="admin-hint">No customers found.</p>';
                    return;
                }
                customerResults.innerHTML = items.map(function (c) {
                    return '<button type="button" class="pos-search-item" data-id="' + c.id + '" data-name="' + escapeAttr(c.name) + '" data-phone="' + escapeAttr(c.phone || '') + '">' +
                        '<strong>' + escapeHtml(c.name) + '</strong><span>' + escapeHtml(c.phone || c.email || '') + '</span></button>';
                }).join('');
            })
            .catch(function () { customerResults.innerHTML = ''; });
    }, 250));

    customerResults.addEventListener('click', function (e) {
        var btn = e.target.closest('.pos-search-item');
        if (!btn) return;
        customerIdInput.value = btn.dataset.id;
        customerResults.innerHTML = '';
        customerSearch.value = btn.dataset.name;
        if (customerPhoneInput && btn.dataset.phone) {
            customerPhoneInput.value = btn.dataset.phone;
        }
    });

    form.addEventListener('submit', function (e) {
        if (!parseInt(customerIdInput.value, 10)) {
            e.preventDefault();
            alert('Select a customer.');
        }
    });
})();
