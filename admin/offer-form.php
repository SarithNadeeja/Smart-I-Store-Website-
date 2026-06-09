<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$selectedItemId = (int) ($_GET['item_id'] ?? 0);
$selectedItem = null;
$selectedPicker = null;

if ($selectedItemId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id');
    $stmt->execute(['id' => $selectedItemId]);
    $selectedItem = $stmt->fetch() ?: null;
    if (!$selectedItem) {
        $selectedItemId = 0;
    } else {
        $matches = admin_search_offer_items($pdo, $selectedItem['name'], 50);
        foreach ($matches as $row) {
            if ((int) $row['id'] === $selectedItemId) {
                $selectedPicker = $row;
                break;
            }
        }
        if ($selectedPicker === null) {
            $label = $selectedItem['name'];
            $selectedPicker = [
                'id' => $selectedItemId,
                'label' => $label,
                'list_price' => (float) $selectedItem['price'],
                'offer_price' => $selectedItem['sale_price'] !== null && $selectedItem['sale_price'] !== ''
                    ? (float) $selectedItem['sale_price'] : null,
                'tag' => trim((string) ($selectedItem['tag'] ?? '')) ?: 'Sale',
                'image' => $selectedItem['main_image'] ?? '',
            ];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        uploads_assert_post_accepted();
        admin_csrf_verify();

        $itemId = (int) ($_POST['item_id'] ?? 0);
        if (!empty($_POST['remove_offer'])) {
            admin_remove_item_offer($pdo, $itemId);
            admin_flash('success', 'Offer removed.');
            header('Location: ' . admin_url('offers.php'));
            exit;
        }

        $listPrice = (float) ($_POST['list_price'] ?? 0);
        $offerPrice = (float) ($_POST['offer_price'] ?? 0);
        $tag = trim($_POST['offer_tag'] ?? 'Offer');
        admin_apply_offer_to_item($pdo, $itemId, $listPrice, $offerPrice, $tag);
        admin_flash('success', 'Offer saved. Item will appear in Flagship Selection on the homepage.');
        header('Location: ' . admin_url('offers.php'));
        exit;
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
        header('Location: ' . admin_url('offer-form.php' . ($selectedItemId ? '?item_id=' . $selectedItemId : '')));
        exit;
    }
}

$hasActiveOffer = $selectedItem && admin_item_has_active_offer($selectedItem);
$listPriceVal = $selectedItem ? (float) $selectedItem['price'] : 0;
$offerPriceVal = $selectedItem && $selectedItem['sale_price'] !== null && $selectedItem['sale_price'] !== ''
    ? (float) $selectedItem['sale_price'] : '';
$offerTagVal = trim($selectedItem['tag'] ?? '');
if ($offerTagVal === '' || is_numeric($offerTagVal)) {
    $offerTagVal = 'Offer';
}

admin_render_header('Add offer', 'offers');
?>
<section class="admin-panel admin-panel--wide">
    <div class="admin-panel-head">
        <h2>Add offer</h2>
        <a href="<?php echo admin_url('offers.php'); ?>" class="btn btn-ghost">Back to offers</a>
    </div>

    <p class="admin-field-note">Search for an existing catalog item or pre-owned phone, then set a retail price and a lower offer price. Add new products under <a href="<?php echo admin_url('item-form.php'); ?>">Add / Manage Items</a> or <a href="<?php echo admin_url('preowned-form.php'); ?>">Pre-Owned Phones</a> first.</p>

    <form method="post" class="admin-form" id="offer-form"
          action="<?php echo admin_url('offer-form.php' . ($selectedItemId ? '?item_id=' . $selectedItemId : '')); ?>">
        <?php admin_csrf_field(); ?>

        <div class="admin-field admin-item-picker">
            <label for="offer-item-search">Search item</label>
            <input type="search" id="offer-item-search" autocomplete="off"
                   placeholder="Type name, brand, model, or category…"
                   value="<?php echo $selectedPicker ? htmlspecialchars($selectedPicker['label']) : ''; ?>">
            <input type="hidden" id="item_id" name="item_id" value="<?php echo $selectedItemId > 0 ? (int) $selectedItemId : ''; ?>" required>
            <ul id="offer-item-results" class="admin-item-picker__results" hidden role="listbox" aria-label="Matching items"></ul>
            <p class="admin-field-note">Matching items appear as you type. Select one to fill in the prices below.</p>
        </div>

        <div class="admin-item-picker__selected" id="offer-item-selected"<?php echo $selectedPicker ? '' : ' hidden'; ?>>
            <?php if ($selectedPicker): ?>
            <?php if (!empty($selectedPicker['image'])): ?>
            <img class="admin-item-picker__thumb" src="<?php echo htmlspecialchars(upload_url($selectedPicker['image'])); ?>" alt="">
            <?php endif; ?>
            <div class="admin-item-picker__meta">
                <strong><?php echo htmlspecialchars($selectedPicker['label']); ?></strong>
                <button type="button" class="admin-link-btn" id="offer-item-clear">Change item</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="admin-field-row">
            <div class="admin-field">
                <label for="list_price">Retail price (Rs.)</label>
                <input type="number" id="list_price" name="list_price" min="0.01" step="0.01" required
                       value="<?php echo $listPriceVal > 0 ? htmlspecialchars((string) $listPriceVal) : ''; ?>">
            </div>
            <div class="admin-field">
                <label for="offer_price">Offer price (Rs.)</label>
                <input type="number" id="offer_price" name="offer_price" min="0.01" step="0.01" required
                       value="<?php echo $offerPriceVal !== '' ? htmlspecialchars((string) $offerPriceVal) : ''; ?>"
                       placeholder="Must be lower than retail price">
            </div>
            <div class="admin-field">
                <label for="offer_preview_discount">Discount preview</label>
                <output id="offer_preview_discount" class="admin-offer-preview" for="list_price offer_price">—</output>
                <p class="admin-field-note">Shown on the website (whole number, e.g. 67% OFF).</p>
            </div>
        </div>

        <?php if ($hasActiveOffer): ?>
        <label class="admin-check admin-check--danger">
            <input type="checkbox" name="remove_offer" value="1">
            Remove offer from this item
        </label>
        <?php endif; ?>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary" id="offer-submit"<?php echo $selectedItemId > 0 ? '' : ' disabled'; ?>>Save offer</button>
            <a href="<?php echo admin_url('offers.php'); ?>" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</section>
<script>
(function () {
    var searchUrl = <?php echo json_encode(admin_url('api/offer-item-search.php'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var searchInput = document.getElementById('offer-item-search');
    var itemIdInput = document.getElementById('item_id');
    var resultsEl = document.getElementById('offer-item-results');
    var selectedEl = document.getElementById('offer-item-selected');
    var clearBtn = document.getElementById('offer-item-clear');
    var listInput = document.getElementById('list_price');
    var offerInput = document.getElementById('offer_price');
    var discountOut = document.getElementById('offer_preview_discount');
    var submitBtn = document.getElementById('offer-submit');
    var debounceTimer = null;
    var activeIndex = -1;
    var lastItems = [];

    function discountPercent(list, offer) {
        list = parseFloat(list);
        offer = parseFloat(offer);
        if (!list || !offer || offer >= list) return null;
        return Math.round((1 - offer / list) * 100);
    }

    function updateDiscountPreview() {
        var pct = discountPercent(listInput.value, offerInput.value);
        discountOut.textContent = pct !== null ? pct + '% OFF' : '—';
    }

    function setSelected(item) {
        itemIdInput.value = String(item.id);
        searchInput.value = item.label;
        listInput.value = item.list_price || '';
        offerInput.value = item.offer_price || '';
        resultsEl.hidden = true;
        resultsEl.innerHTML = '';
        activeIndex = -1;
        submitBtn.disabled = false;

        var html = '';
        if (item.image) {
            html += '<img class="admin-item-picker__thumb" src="' + item.image + '" alt="">';
        }
        html += '<div class="admin-item-picker__meta"><strong>' + escapeHtml(item.label) + '</strong>';
        html += '<button type="button" class="admin-link-btn" id="offer-item-clear">Change item</button></div>';
        selectedEl.innerHTML = html;
        selectedEl.hidden = false;
        selectedEl.querySelector('#offer-item-clear').addEventListener('click', clearSelection);
        updateDiscountPreview();
    }

    function clearSelection() {
        itemIdInput.value = '';
        searchInput.value = '';
        listInput.value = '';
        offerInput.value = '';
        selectedEl.hidden = true;
        selectedEl.innerHTML = '';
        submitBtn.disabled = true;
        discountOut.textContent = '—';
        searchInput.focus();
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderResults(items) {
        lastItems = items;
        activeIndex = -1;
        resultsEl.innerHTML = '';
        if (!items.length) {
            resultsEl.innerHTML = '<li class="admin-item-picker__empty">No matching items</li>';
            resultsEl.hidden = false;
            return;
        }
        items.forEach(function (item, index) {
            var li = document.createElement('li');
            li.className = 'admin-item-picker__option';
            li.setAttribute('role', 'option');
            li.dataset.index = String(index);
            var meta = '';
            if (item.is_preowned) {
                meta += '<span class="admin-item-picker__cat">Pre-Owned</span>';
            }
            if (item.category_title) {
                meta += '<span class="admin-item-picker__cat">' + escapeHtml(item.category_title) + '</span>';
            }
            li.innerHTML = '<span class="admin-item-picker__label">' + escapeHtml(item.label) + '</span>' + meta
                + '<span class="admin-item-picker__price">Rs. ' + Number(item.list_price).toLocaleString() + '</span>';
            li.addEventListener('mousedown', function (e) {
                e.preventDefault();
                setSelected(item);
            });
            resultsEl.appendChild(li);
        });
        resultsEl.hidden = false;
    }

    function fetchItems(q) {
        fetch(searchUrl + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) renderResults(data.items || []);
            })
            .catch(function () {
                resultsEl.innerHTML = '<li class="admin-item-picker__empty">Search failed</li>';
                resultsEl.hidden = false;
            });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                fetchItems(searchInput.value.trim());
            }, 200);
        });
        searchInput.addEventListener('focus', function () {
            fetchItems(searchInput.value.trim());
        });
        searchInput.addEventListener('keydown', function (e) {
            if (resultsEl.hidden || !lastItems.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, lastItems.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
            } else if (e.key === 'Enter' && activeIndex >= 0) {
                e.preventDefault();
                setSelected(lastItems[activeIndex]);
                return;
            } else if (e.key === 'Escape') {
                resultsEl.hidden = true;
                return;
            } else {
                return;
            }
            Array.prototype.forEach.call(resultsEl.querySelectorAll('.admin-item-picker__option'), function (el, i) {
                el.classList.toggle('is-active', i === activeIndex);
            });
        });
        document.addEventListener('click', function (e) {
            if (!resultsEl.contains(e.target) && e.target !== searchInput) {
                resultsEl.hidden = true;
            }
        });
    }

    if (clearBtn) clearBtn.addEventListener('click', clearSelection);
    if (listInput) listInput.addEventListener('input', updateDiscountPreview);
    if (offerInput) offerInput.addEventListener('input', updateDiscountPreview);
    updateDiscountPreview();
})();
</script>
<?php
admin_render_footer();
