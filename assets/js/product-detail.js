/**
 * Product detail — image zoom, gallery thumbnails, storage variants, WhatsApp
 */
(function () {
    'use strict';

    var ZOOM_LEVEL = 2.5;
    var LENS_RATIO = 0.4;

    function initGalleryThumbs() {
        var gallery = document.getElementById('product-gallery');
        var mainImg = document.getElementById('product-detail-main');
        if (!gallery || !mainImg) {
            return;
        }

        var thumbs = gallery.querySelectorAll('.product-detail__thumb');
        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                var src = thumb.getAttribute('data-src');
                if (!src) {
                    return;
                }
                mainImg.src = src;
                thumbs.forEach(function (t) {
                    var active = t === thumb;
                    t.classList.toggle('is-active', active);
                    t.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                document.dispatchEvent(new CustomEvent('product:imagechange', {
                    detail: { src: src }
                }));
            });
        });
    }

    function initProductZoom() {
        var zoomRoot = document.getElementById('product-zoom');
        var stage = document.getElementById('product-zoom-stage');
        var img = document.getElementById('product-detail-main');
        var lens = document.getElementById('product-zoom-lens');
        var pane = document.getElementById('product-zoom-pane');

        if (!zoomRoot || !stage || !img || !lens || !pane) {
            return;
        }

        var canHoverZoom = window.matchMedia('(hover: hover) and (pointer: fine)').matches
            && window.matchMedia('(min-width: 992px)').matches;

        if (!canHoverZoom) {
            zoomRoot.classList.add('product-detail__zoom--no-hover');
            return;
        }

        zoomRoot.classList.add('product-detail__zoom--active');

        function syncPaneImage() {
            pane.style.backgroundImage = 'url("' + String(img.currentSrc || img.src).replace(/"/g, '%22') + '")';
        }

        function hideZoom() {
            lens.hidden = true;
            zoomRoot.classList.remove('is-zooming');
        }

        function showZoom() {
            syncPaneImage();
            lens.hidden = false;
            zoomRoot.classList.add('is-zooming');
        }

        function updateZoom(clientX, clientY) {
            var rect = stage.getBoundingClientRect();
            var x = clientX - rect.left;
            var y = clientY - rect.top;

            if (x < 0 || y < 0 || x > rect.width || y > rect.height) {
                hideZoom();
                return;
            }

            var lensW = rect.width * LENS_RATIO;
            var lensH = rect.height * LENS_RATIO;
            var maxLeft = Math.max(0, rect.width - lensW);
            var maxTop = Math.max(0, rect.height - lensH);
            var left = Math.max(0, Math.min(x - lensW / 2, maxLeft));
            var top = Math.max(0, Math.min(y - lensH / 2, maxTop));

            lens.style.width = lensW + 'px';
            lens.style.height = lensH + 'px';
            lens.style.transform = 'translate(' + left + 'px, ' + top + 'px)';

            var bgW = rect.width * ZOOM_LEVEL;
            var bgH = rect.height * ZOOM_LEVEL;
            var paneW = rect.width;
            var paneH = rect.height;

            pane.style.backgroundSize = bgW + 'px ' + bgH + 'px';

            var cx = left + lensW / 2;
            var cy = top + lensH / 2;
            var bgX = -(cx / rect.width * bgW - paneW / 2);
            var bgY = -(cy / rect.height * bgH - paneH / 2);

            pane.style.backgroundPosition = bgX + 'px ' + bgY + 'px';
        }

        stage.addEventListener('mouseenter', showZoom);
        stage.addEventListener('mouseleave', hideZoom);
        stage.addEventListener('mousemove', function (e) {
            if (!zoomRoot.classList.contains('is-zooming')) {
                showZoom();
            }
            updateZoom(e.clientX, e.clientY);
        });

        img.addEventListener('load', syncPaneImage);
        document.addEventListener('product:imagechange', syncPaneImage);

        if (img.complete) {
            syncPaneImage();
        }
    }

    function initWhatsAppOrder() {
        var orderDataEl = document.getElementById('product-order-data');
        var variantSelect = document.getElementById('product-variant');
        var priceEl = document.getElementById('product-detail-price');
        var whatsappBtn = document.getElementById('product-whatsapp-order');
        var stockEl = document.getElementById('product-detail-stock');

        if (!orderDataEl || !whatsappBtn) {
            return;
        }

        var orderData;
        try {
            orderData = JSON.parse(orderDataEl.textContent || '{}');
        } catch (e) {
            return;
        }

        function whatsappDigits(number) {
            return String(number || '').replace(/\D/g, '');
        }

        function buildWhatsAppUrl(message) {
            var digits = whatsappDigits(orderData.whatsappNumber);
            if (!digits) {
                return '#';
            }
            return 'https://wa.me/' + digits + '?text=' + encodeURIComponent(message);
        }

        function formatVariantLabel(variant) {
            if (variant.label) {
                return variant.label;
            }
            var ram = variant.ram || '';
            var rom = variant.rom || '';
            if (ram && rom) {
                return ram + ' RAM / ' + rom + ' ROM';
            }
            return ram || rom || 'Standard';
        }

        function effectivePrice(variant) {
            if (variant && variant.current_price != null) {
                return Number(variant.current_price);
            }
            if (variant && variant.effective_price != null) {
                return Number(variant.effective_price);
            }
            var product = orderData.product || {};
            if (variant && variant.price != null && variant.price !== '') {
                return Number(variant.price);
            }
            return Number(product.current_price || product.price || 0);
        }

        function listPriceFor(variant) {
            if (variant && variant.on_sale && variant.list_price != null) {
                return Number(variant.list_price);
            }
            var product = orderData.product || {};
            if (!variant && product.on_sale && product.list_price != null) {
                return Number(product.list_price);
            }
            return null;
        }

        function formatPriceHtml(current, list, prefix) {
            var html = '<span class="product-price-row">';
            if (prefix) {
                html += '<span class="product-price-prefix">' + prefix + '</span>';
            }
            if (list != null && list > current) {
                html += '<span class="product-price--was" aria-label="Original price">Rs. '
                    + Math.round(list).toLocaleString('en-LK') + '</span>';
                html += '<span class="product-price--now" aria-label="Sale price">Rs. '
                    + Math.round(current).toLocaleString('en-LK') + '</span>';
            } else {
                html += '<span class="product-price--now">Rs. '
                    + Math.round(current).toLocaleString('en-LK') + '</span>';
            }
            html += '</span>';
            return html;
        }

        function buildOrderMessage(variant) {
            var product = orderData.product || {};
            var price = effectivePrice(variant);
            var lines = [
                'Hello! I would like to order from ' + (orderData.siteName || 'SmartIStore') + ':',
                '',
                'Product: ' + (product.name || ''),
                'Price: Rs. ' + Math.round(price).toLocaleString('en-LK')
            ];

            if (variant) {
                var label = formatVariantLabel(variant);
                if (label) {
                    lines.push('Storage: ' + label);
                }
                if (variant.stock_label) {
                    lines.push('Variant stock: ' + variant.stock_label);
                }
            }

            if (product.meta) {
                lines.push('Details: ' + product.meta);
            }
            if (product.category_title) {
                lines.push('Category: ' + product.category_title);
            }
            if (!variant && product.stock_label) {
                lines.push('Stock: ' + product.stock_label);
            }

            lines.push('');
            lines.push('Please confirm availability. Thank you!');
            return lines.join('\n');
        }

        function stockClass(status) {
            var map = {
                in_stock: 'in_stock',
                low_stock: 'low_stock',
                out_of_stock: 'out_of_stock'
            };
            return map[status] || 'in_stock';
        }

        function applyVariant(variant) {
            if (priceEl) {
                var current = effectivePrice(variant);
                var list = listPriceFor(variant);
                priceEl.innerHTML = formatPriceHtml(current, list, '');
            }
            if (stockEl && variant && variant.stock_label) {
                stockEl.textContent = variant.stock_label;
                stockEl.className = 'product-stock product-stock--' + stockClass(variant.stock_status);
            }
            whatsappBtn.href = buildWhatsAppUrl(buildOrderMessage(variant));
        }

        var variants = orderData.variants || [];

        var currentProductId = (orderData.product && orderData.product.id) ? parseInt(orderData.product.id, 10) : 0;

        if (variantSelect && variants.length) {
            variantSelect.addEventListener('change', function () {
                var itemId = parseInt(variantSelect.value, 10);
                if (!isNaN(itemId) && itemId > 0 && itemId !== currentProductId) {
                    window.location.href = 'product.php?id=' + itemId;
                    return;
                }
                var chosen = variants.find(function (v) {
                    return parseInt(v.item_id, 10) === itemId;
                });
                if (chosen) {
                    applyVariant(chosen);
                }
            });
        }
    }

    initGalleryThumbs();
    initProductZoom();
    initWhatsAppOrder();
})();
