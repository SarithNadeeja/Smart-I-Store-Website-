/**
 * Smart I Store — rules-based Q&A bot (no AI). Data from api/chatbot.php only.
 */
(function () {
    'use strict';

    var REPAIR_ISSUES = [
        { id: 'screen', label: 'Broken Screen' },
        { id: 'battery', label: 'Battery Issue' },
        { id: 'charging', label: 'Charging Issue' },
        { id: 'camera', label: 'Camera Issue' },
        { id: 'software', label: 'Software Issue' },
        { id: 'other', label: 'Other' },
    ];

    var root = document.getElementById('store-bot');
    if (!root) return;

    var apiBase = root.getAttribute('data-api') || '';
    var toggle = document.getElementById('store-bot-toggle');
    var panel = document.getElementById('store-bot-panel');
    var messagesEl = document.getElementById('store-bot-messages');
    var composer = document.getElementById('store-bot-composer');
    var bodyEl = document.getElementById('store-bot-body');
    var boot = null;
    var state = 'start';
    var ctx = { brandId: 0, categoryId: 0, repairBrand: '' };

    function formatPrice(n) {
        var x = Number(n);
        if (!isFinite(x)) return '0';
        return 'Rs. ' + Math.round(x).toLocaleString('en-LK');
    }

    function updateScrollState() {
        if (!bodyEl) return;
        var canScroll = bodyEl.scrollHeight > bodyEl.clientHeight + 6;
        var atBottom =
            bodyEl.scrollHeight - bodyEl.scrollTop - bodyEl.clientHeight < 12;
        bodyEl.classList.toggle('has-overflow', canScroll);
        bodyEl.classList.toggle('at-bottom', atBottom);
    }

    function refreshLayout(scrollToEnd) {
        requestAnimationFrame(function () {
            if (!bodyEl) return;
            if (scrollToEnd !== false) {
                bodyEl.scrollTop = bodyEl.scrollHeight;
            }
            updateScrollState();
        });
    }

    if (bodyEl) {
        bodyEl.addEventListener('scroll', updateScrollState, { passive: true });
        window.addEventListener(
            'resize',
            function () {
                if (!panel.hidden) updateScrollState();
            },
            { passive: true }
        );
    }

    function appendBubble(text, isBot) {
        var wrap = document.createElement('div');
        wrap.className = 'store-bot__msg store-bot__msg--' + (isBot ? 'bot' : 'user');
        var bubble = document.createElement('div');
        bubble.className = 'store-bot__bubble';
        String(text).split('\n').forEach(function (line, i) {
            if (i) bubble.appendChild(document.createElement('br'));
            bubble.appendChild(document.createTextNode(line));
        });
        wrap.appendChild(bubble);
        messagesEl.appendChild(wrap);
        refreshLayout();
    }

    function clearComposer() {
        composer.innerHTML = '';
    }

    function showOptions(buttons) {
        clearComposer();
        var row = document.createElement('div');
        row.className = 'store-bot__options';
        buttons.forEach(function (b) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'store-bot__opt';
            btn.textContent = b.label;
            btn.addEventListener('click', function () {
                appendBubble(b.label, false);
                b.onClick();
            });
            row.appendChild(btn);
        });
        composer.appendChild(row);
        refreshLayout();
    }

    function showBudgetForm(onSubmit) {
        clearComposer();
        var form = document.createElement('div');
        form.className = 'store-bot__budget';
        form.innerHTML =
            '<label class="store-bot__budget-label" for="store-bot-budget-input">Budget (Rs.)</label>' +
            '<input type="number" id="store-bot-budget-input" class="store-bot__input" min="1" step="1" placeholder="e.g. 100000">' +
            '<button type="button" class="btn btn-primary store-bot__send-budget">Search</button>';
        var input = form.querySelector('#store-bot-budget-input');
        var go = function () {
            var v = parseFloat(String(input.value).replace(/,/g, ''));
            if (!isFinite(v) || v <= 0) {
                appendBubble('Please enter a valid budget in Rs.', true);
                return;
            }
            appendBubble(String(Math.round(v)), false);
            onSubmit(v);
        };
        form.querySelector('.store-bot__send-budget').addEventListener('click', go);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') go();
        });
        composer.appendChild(form);
        input.focus();
        refreshLayout();
    }

    function contactActions() {
        if (!boot) return [];
        return [
            {
                label: 'WhatsApp Support',
                onClick: function () {
                    window.open(boot.whatsappUrl, '_blank', 'noopener,noreferrer');
                },
            },
            {
                label: 'Contact Smart I Store',
                onClick: function () {
                    window.location.href = boot.contactUrl;
                },
            },
        ];
    }

    function mergeContact(base) {
        return contactActions().concat(base);
    }

    function goStart() {
        messagesEl.innerHTML = '';
        clearComposer();
        state = 'start';
        ctx = { brandId: 0, categoryId: 0, repairBrand: '' };
        appendBubble(
            '👋 Welcome to Smart I Store!\nHow can I help you today?',
            true
        );
        showOptions([
            {
                label: '📱 Buy a Phone',
                onClick: function () {
                    state = 'phone_brand';
                    if (!boot.brands.length) {
                        appendBubble('No brands are available in our catalog yet. Please contact Smart I Store.', true);
                        showOptions(mergeContact([{ label: 'Back to Menu', onClick: goStart }]));
                        return;
                    }
                    appendBubble('What brand are you looking for?', true);
                    showOptions(
                        boot.brands.map(function (br) {
                            return {
                                label: br.name,
                                onClick: function () {
                                    ctx.brandId = br.id;
                                    state = 'phone_budget';
                                    appendBubble('What is your budget?', true);
                                    showBudgetForm(function (maxPrice) {
                                        state = 'phone_results';
                                        appendBubble('Searching available phones…', true);
                                        fetch(
                                            apiBase +
                                                '?action=search_phone&brand_id=' +
                                                encodeURIComponent(String(ctx.brandId)) +
                                                '&max_price=' +
                                                encodeURIComponent(String(maxPrice))
                                        )
                                            .then(function (r) {
                                                return r.json();
                                            })
                                            .then(function (data) {
                                                if (!data.ok || !data.products) {
                                                    fallbackUnknown();
                                                    return;
                                                }
                                                if (!data.products.length) {
                                                    appendBubble(
                                                        'No phones found for that budget. Try a higher budget or contact us.',
                                                        true
                                                    );
                                                    showProductFooterActions();
                                                    return;
                                                }
                                                appendBubble('Here is what we have:', true);
                                                data.products.forEach(function (p) {
                                                    appendProductCard(p);
                                                });
                                                showProductFooterActions();
                                            })
                                            .catch(function () {
                                                fallbackUnknown();
                                            });
                                    });
                                },
                            };
                        }).concat([
                            {
                                label: '◀ Back to Menu',
                                onClick: goStart,
                            },
                        ])
                    );
                },
            },
            {
                label: '🎧 Buy Accessories',
                onClick: function () {
                    state = 'acc_category';
                    if (!boot.accessoryCategories.length) {
                        appendBubble(
                            'Accessory categories are not set up yet. Please contact Smart I Store.',
                            true
                        );
                        showOptions(mergeContact([{ label: 'Back to Menu', onClick: goStart }]));
                        return;
                    }
                    appendBubble('What accessory are you looking for? (pick a category)', true);
                    showOptions(
                        boot.accessoryCategories
                            .map(function (cat) {
                                return {
                                    label: cat.title,
                                    onClick: function () {
                                        ctx.categoryId = cat.id;
                                        state = 'acc_brand';
                                        appendBubble('Which brand do you prefer?', true);
                                        if (!boot.brands.length) {
                                            fallbackUnknown();
                                            return;
                                        }
                                        showOptions(
                                            boot.brands
                                                .map(function (br) {
                                                    return {
                                                        label: br.name,
                                                        onClick: function () {
                                                            ctx.brandId = br.id;
                                                            state = 'acc_budget';
                                                            appendBubble('What is your budget?', true);
                                                            showBudgetForm(function (maxPrice) {
                                                                state = 'acc_results';
                                                                appendBubble('Searching accessories…', true);
                                                                fetch(
                                                                    apiBase +
                                                                        '?action=search_accessory&category_id=' +
                                                                        encodeURIComponent(
                                                                            String(ctx.categoryId)
                                                                        ) +
                                                                        '&brand_id=' +
                                                                        encodeURIComponent(
                                                                            String(ctx.brandId)
                                                                        ) +
                                                                        '&max_price=' +
                                                                        encodeURIComponent(String(maxPrice))
                                                                )
                                                                    .then(function (r) {
                                                                        return r.json();
                                                                    })
                                                                    .then(function (data) {
                                                                        if (!data.ok || !data.products) {
                                                                            fallbackUnknown();
                                                                            return;
                                                                        }
                                                                        if (!data.products.length) {
                                                                            appendBubble(
                                                                                'No matching accessories found. Try another budget or contact us.',
                                                                                true
                                                                            );
                                                                            showProductFooterActions();
                                                                            return;
                                                                        }
                                                                        appendBubble('Here is what we have:', true);
                                                                        data.products.forEach(function (p) {
                                                                            appendProductCard(p);
                                                                        });
                                                                        showProductFooterActions();
                                                                    })
                                                                    .catch(function () {
                                                                        fallbackUnknown();
                                                                    });
                                                            });
                                                        },
                                                    };
                                                })
                                                .concat([
                                                    {
                                                        label: '◀ Back',
                                                        onClick: goStart,
                                                    },
                                                ])
                                        );
                                    },
                                };
                            })
                            .concat([
                                {
                                    label: '◀ Back to Menu',
                                    onClick: goStart,
                                },
                            ])
                    );
                },
            },
            {
                label: '🔧 Repair a Phone',
                onClick: function () {
                    state = 'repair_brand';
                    if (!boot.brands.length) {
                        appendBubble('No brands loaded. Please contact Smart I Store.', true);
                        showOptions(mergeContact([{ label: 'Back to Menu', onClick: goStart }]));
                        return;
                    }
                    appendBubble('What is your phone brand?', true);
                    showOptions(
                        boot.brands
                            .map(function (br) {
                                return {
                                    label: br.name,
                                    onClick: function () {
                                        ctx.repairBrand = br.name;
                                        state = 'repair_issue';
                                        appendBubble('What issue are you facing?', true);
                                        showOptions(
                                            REPAIR_ISSUES.map(function (iss) {
                                                return {
                                                    label: iss.label,
                                                    onClick: function () {
                                                        state = 'repair_done';
                                                        appendBubble(
                                                            'Please contact Smart I Store for repair assistance.',
                                                            true
                                                        );
                                                        showOptions([
                                                            {
                                                                label: 'WhatsApp Support',
                                                                onClick: function () {
                                                                    var text =
                                                                        'Hello! I need a repair at Smart I Store:\n\nBrand: ' +
                                                                        ctx.repairBrand +
                                                                        '\nIssue: ' +
                                                                        iss.label;
                                                                    window.open(
                                                                        boot.whatsappUrl +
                                                                            '?text=' +
                                                                            encodeURIComponent(text),
                                                                        '_blank',
                                                                        'noopener,noreferrer'
                                                                    );
                                                                },
                                                            },
                                                            {
                                                                label: 'Call Now',
                                                                onClick: function () {
                                                                    window.location.href = boot.telUrl;
                                                                },
                                                            },
                                                            {
                                                                label: 'Contact Smart I Store',
                                                                onClick: function () {
                                                                    window.location.href = boot.contactUrl;
                                                                },
                                                            },
                                                            {
                                                                label: 'Back to Menu',
                                                                onClick: goStart,
                                                            },
                                                        ]);
                                                    },
                                                };
                                            }).concat([
                                                {
                                                    label: '◀ Back to Menu',
                                                    onClick: goStart,
                                                },
                                            ])
                                        );
                                    },
                                };
                            })
                            .concat([
                                {
                                    label: '◀ Back to Menu',
                                    onClick: goStart,
                                },
                            ])
                    );
                },
            },
            {
                label: '💬 Contact Smart I Store',
                onClick: function () {
                    appendBubble('How would you like to reach us?', true);
                    showOptions(
                        mergeContact([
                            {
                                label: 'Call Now',
                                onClick: function () {
                                    window.location.href = boot.telUrl;
                                },
                            },
                            { label: 'Back to Menu', onClick: goStart },
                        ])
                    );
                },
            },
        ]);
    }

    function whatsappInterest(product) {
        if (!boot) return;
        var text =
            'Hello! I am interested in: ' +
            product.name +
            ' — ' +
            formatPrice(product.price) +
            ' (Smart I Store bot)';
        window.open(
            boot.whatsappUrl + '?text=' + encodeURIComponent(text),
            '_blank',
            'noopener,noreferrer'
        );
    }

    function appendProductCard(p) {
        var wrap = document.createElement('div');
        wrap.className = 'store-bot__msg store-bot__msg--bot';
        var card = document.createElement('div');
        card.className = 'store-bot__card';
        var nameEl = document.createElement('div');
        nameEl.className = 'store-bot__card-name';
        nameEl.textContent = p.name;
        var priceEl = document.createElement('div');
        priceEl.className = 'store-bot__card-price';
        priceEl.textContent = formatPrice(p.price);
        var actions = document.createElement('div');
        actions.className = 'store-bot__card-actions';
        var view = document.createElement('a');
        view.className = 'store-bot__card-btn';
        view.href = p.url;
        view.target = '_blank';
        view.rel = 'noopener noreferrer';
        view.textContent = 'View Product';
        var cart = document.createElement('button');
        cart.type = 'button';
        cart.className = 'store-bot__card-btn store-bot__cart';
        cart.textContent = 'Add to Cart';
        cart.addEventListener('click', function () {
            whatsappInterest(p);
        });
        var cont = document.createElement('a');
        cont.className = 'store-bot__card-btn store-bot__card-btn--ghost';
        cont.href = boot.contactUrl;
        cont.textContent = 'Contact';
        actions.appendChild(view);
        actions.appendChild(cart);
        actions.appendChild(cont);
        card.appendChild(nameEl);
        card.appendChild(priceEl);
        card.appendChild(actions);
        wrap.appendChild(card);
        messagesEl.appendChild(wrap);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function showProductFooterActions() {
        showOptions(
            mergeContact([
                {
                    label: 'Back to Menu',
                    onClick: goStart,
                },
            ])
        );
    }

    function fallbackUnknown() {
        appendBubble('Sorry 😔\nPlease contact Smart I Store for assistance.', true);
        showOptions(
            mergeContact([
                {
                    label: 'Back to Menu',
                    onClick: goStart,
                },
            ])
        );
    }

    function openPanel() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('store-bot-open');
        refreshLayout(true);
        setTimeout(function () {
            refreshLayout(true);
        }, 80);
        if (!messagesEl.dataset.inited) {
            messagesEl.dataset.inited = '1';
            fetch(apiBase + '?action=bootstrap')
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (!data.ok) {
                        appendBubble('Could not load the assistant. Please try again later.', true);
                        return;
                    }
                    boot = data;
                    goStart();
                })
                .catch(function () {
                    appendBubble('Could not connect. Check your connection and try again.', true);
                });
        }
    }

    function closePanel() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('store-bot-open');
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        if (panel.hidden) openPanel();
        else closePanel();
    });

    document.getElementById('store-bot-close').addEventListener('click', function (e) {
        e.stopPropagation();
        closePanel();
    });

    root.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    document.addEventListener('click', function () {
        if (panel.hidden) return;
        closePanel();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) closePanel();
    });
})();
