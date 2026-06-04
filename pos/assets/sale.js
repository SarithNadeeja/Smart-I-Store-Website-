(function () {
    'use strict';

    var cfg = window.POS_SALE || {};
    var searchApi = cfg.searchApi || '';
    var cart = [];
    var selectedCustomer = null;

    var productSearch = document.getElementById('pos-product-search');
    var productResults = document.getElementById('pos-product-results');
    var customerSearch = document.getElementById('pos-customer-search');
    var customerResults = document.getElementById('pos-customer-results');
    var customerSelected = document.getElementById('pos-customer-selected');
    var customerClear = document.getElementById('pos-customer-clear');
    var customerIdInput = document.getElementById('customer_id');
    var cartBody = document.getElementById('pos-cart-body');
    var cartEmpty = document.getElementById('pos-cart-empty');
    var cartJson = document.getElementById('cart_json');
    var invoiceDiscount = document.getElementById('invoice_discount');
    var paymentMethod = document.getElementById('payment_method');
    var paidWrap = document.getElementById('pos-paid-wrap');
    var paidAmount = document.getElementById('paid_amount');
    var saleForm = document.getElementById('pos-sale-form');

    function money(n) {
        return 'Rs. ' + Number(n || 0).toFixed(2);
    }

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, ms);
        };
    }

    function fetchSearch(type, q) {
        if (!searchApi) return Promise.resolve([]);
        return fetch(searchApi + '?type=' + encodeURIComponent(type) + '&q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) { return data.ok ? (data.items || []) : []; })
            .catch(function () { return []; });
    }

    function renderProductResults(items) {
        if (!items.length) {
            productResults.innerHTML = '<p class="admin-hint">No products found.</p>';
            return;
        }
        productResults.innerHTML = items.map(function (p) {
            var label = p.variant_label ? ' · ' + escapeHtml(p.variant_label) : '';
            return '<button type="button" class="pos-search-item" data-id="' + p.id + '" data-name="' + escapeAttr(p.name) + '" data-price="' + p.price + '" data-is-phone="' + (p.is_phone ? '1' : '0') + '" data-stock="' + (p.stock_quantity || 0) + '">' +
                '<strong>' + escapeHtml(p.name) + '</strong>' +
                '<span>' + money(p.price) + label + '</span>' +
                '</button>';
        }).join('');
    }

    function renderCustomerResults(items) {
        if (!items.length) {
            customerResults.innerHTML = '<p class="admin-hint">No customers found.</p>';
            return;
        }
        customerResults.innerHTML = items.map(function (c) {
            return '<button type="button" class="pos-search-item" data-id="' + c.id + '" data-name="' + escapeAttr(c.name) + '" data-phone="' + escapeAttr(c.phone || '') + '">' +
                '<strong>' + escapeHtml(c.name) + '</strong>' +
                '<span>' + escapeHtml(c.phone || c.email || '') + '</span>' +
                '</button>';
        }).join('');
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function escapeAttr(s) {
        return String(s || '').replace(/"/g, '&quot;');
    }

    function findCartIndex(productId) {
        for (var i = 0; i < cart.length; i++) {
            if (cart[i].product_id === productId) return i;
        }
        return -1;
    }

    function addToCart(product) {
        var id = parseInt(product.id, 10);
        var isPhone = product.is_phone === '1' || product.is_phone === true;
        var idx = findCartIndex(id);
        if (idx >= 0) {
            if (isPhone) {
                alert('This unit is already in the cart.');
                return;
            }
            if (cart[idx].quantity >= (parseInt(product.stock, 10) || 0)) {
                alert('Not enough stock.');
                return;
            }
            cart[idx].quantity += 1;
            renderCart();
            productSearch.value = '';
            productResults.innerHTML = '';
            return;
        }
        cart.push({
            product_id: id,
            name: product.name,
            unit_price: parseFloat(product.price),
            quantity: 1,
            discount: 0,
            is_phone: isPhone,
            stock: parseInt(product.stock, 10) || 0
        });
        renderCart();
        productSearch.value = '';
        productResults.innerHTML = '';
    }

    function lineTotal(line) {
        return Math.max(0, (line.unit_price * line.quantity) - line.discount);
    }

    function cartSubtotal() {
        return cart.reduce(function (sum, line) { return sum + lineTotal(line); }, 0);
    }

    function renderCart() {
        if (!cart.length) {
            cartEmpty.hidden = false;
            cartBody.querySelectorAll('tr:not(#pos-cart-empty)').forEach(function (r) { r.remove(); });
        } else {
            cartEmpty.hidden = true;
            cartBody.querySelectorAll('tr:not(#pos-cart-empty)').forEach(function (r) { r.remove(); });
            cart.forEach(function (line, idx) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + escapeHtml(line.name) + '</td>' +
                    '<td><input type="number" min="0" step="0.01" class="pos-cart-input" data-field="unit_price" data-idx="' + idx + '" value="' + line.unit_price.toFixed(2) + '"></td>' +
                    '<td>' + (line.is_phone ? '1' : '<input type="number" min="1" max="' + line.stock + '" step="1" class="pos-cart-input" data-field="quantity" data-idx="' + idx + '" value="' + line.quantity + '">') + '</td>' +
                    '<td><input type="number" min="0" step="0.01" class="pos-cart-input" data-field="discount" data-idx="' + idx + '" value="' + line.discount.toFixed(2) + '"></td>' +
                    '<td>' + money(lineTotal(line)) + '</td>' +
                    '<td><button type="button" class="admin-link-btn admin-link-btn--danger" data-remove="' + idx + '">Remove</button></td>';
                cartBody.appendChild(tr);
            });
        }

        cartJson.value = JSON.stringify(cart.map(function (l) {
            return {
                product_id: l.product_id,
                quantity: l.is_phone ? 1 : l.quantity,
                unit_price: l.unit_price,
                discount: l.discount
            };
        }));

        var disc = parseFloat(invoiceDiscount.value) || 0;
        var sub = cartSubtotal();
        var grand = Math.max(0, sub - disc);
        document.getElementById('pos-subtotal').textContent = money(sub);
        document.getElementById('pos-discount-display').textContent = money(disc);
        document.getElementById('pos-grand-total').textContent = money(grand);
        updatePaidField(grand);
    }

    function updatePaidField(grand) {
        var method = paymentMethod.value;
        if (method === 'partial') {
            paidWrap.hidden = false;
        } else {
            paidWrap.hidden = true;
            paidAmount.value = method === 'credit' ? '0' : grand.toFixed(2);
        }
    }

    productSearch.addEventListener('input', debounce(function () {
        var q = productSearch.value.trim();
        if (q.length < 1) {
            productResults.innerHTML = '';
            return;
        }
        fetchSearch('products', q).then(renderProductResults);
    }, 250));

    productResults.addEventListener('click', function (e) {
        var btn = e.target.closest('.pos-search-item');
        if (!btn) return;
        addToCart({
            id: btn.dataset.id,
            name: btn.dataset.name,
            price: btn.dataset.price,
            is_phone: btn.dataset.isPhone === '1',
            stock: btn.dataset.stock
        });
    });

    customerSearch.addEventListener('input', debounce(function () {
        var q = customerSearch.value.trim();
        if (q.length < 1) {
            customerResults.innerHTML = '';
            return;
        }
        fetchSearch('customers', q).then(renderCustomerResults);
    }, 250));

    customerResults.addEventListener('click', function (e) {
        var btn = e.target.closest('.pos-search-item');
        if (!btn) return;
        selectedCustomer = { id: parseInt(btn.dataset.id, 10), name: btn.dataset.name, phone: btn.dataset.phone };
        customerIdInput.value = String(selectedCustomer.id);
        customerSelected.hidden = false;
        customerSelected.textContent = 'Selected: ' + selectedCustomer.name + (selectedCustomer.phone ? ' (' + selectedCustomer.phone + ')' : '');
        customerClear.hidden = false;
        customerResults.innerHTML = '';
        customerSearch.value = '';
    });

    customerClear.addEventListener('click', function () {
        selectedCustomer = null;
        customerIdInput.value = '';
        customerSelected.hidden = true;
        customerClear.hidden = true;
    });

    cartBody.addEventListener('input', function (e) {
        var input = e.target.closest('.pos-cart-input');
        if (!input) return;
        var idx = parseInt(input.dataset.idx, 10);
        var field = input.dataset.field;
        var line = cart[idx];
        if (!line) return;
        var val = parseFloat(input.value) || 0;
        if (field === 'quantity') {
            if (line.is_phone) return;
            val = Math.max(1, Math.min(line.stock, Math.round(val)));
        } else if (field === 'discount') {
            val = Math.max(0, val);
        } else {
            val = Math.max(0, val);
        }
        line[field] = val;
        renderCart();
    });

    cartBody.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-remove]');
        if (!btn) return;
        cart.splice(parseInt(btn.dataset.remove, 10), 1);
        renderCart();
    });

    invoiceDiscount.addEventListener('input', renderCart);
    paymentMethod.addEventListener('change', function () { renderCart(); });

    saleForm.addEventListener('submit', function (e) {
        if (!cart.length) {
            e.preventDefault();
            alert('Add at least one product to the cart.');
        }
    });

    renderCart();
})();
