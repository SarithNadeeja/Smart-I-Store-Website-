<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editRow = null;
$selectedPicker = null;

if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT a.*, i.name AS item_name, i.main_image AS item_image
         FROM site_advertisements a
         LEFT JOIN items i ON i.id = a.item_id
         WHERE a.id = :id'
    );
    $stmt->execute(['id' => $editId]);
    $editRow = $stmt->fetch() ?: null;

    if ($editRow) {
        $itemId = (int) ($editRow['item_id'] ?? 0);
        $matches = admin_search_offer_items($pdo, $editRow['item_name'] ?? '', 50);
        foreach ($matches as $row) {
            if ((int) $row['id'] === $itemId) {
                $selectedPicker = $row;
                break;
            }
        }
        if ($selectedPicker === null && $itemId > 0) {
            $selectedPicker = [
                'id' => $itemId,
                'label' => $editRow['item_name'] ?? ('Item #' . $itemId),
                'image' => !empty($editRow['item_image']) ? upload_url($editRow['item_image']) : '',
            ];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        uploads_assert_post_accepted();
        admin_csrf_verify();
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT image_path FROM site_advertisements WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if ($row) {
                uploads_delete_file($row['image_path'] ?? '');
                $pdo->prepare('DELETE FROM site_advertisements WHERE id = :id')->execute(['id' => $id]);
            }
            admin_flash('success', 'Advertisement deleted.');
        } elseif ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']);
            $linkedItemId = $itemId > 0 ? $itemId : null;

            if ($linkedItemId !== null) {
                $itemCheck = $pdo->prepare('SELECT id FROM items WHERE id = :id AND is_active = TRUE');
                $itemCheck->execute(['id' => $linkedItemId]);
                if (!$itemCheck->fetch()) {
                    throw new RuntimeException('Selected product is missing or inactive.');
                }
            }

            $imagePath = '';
            if ($id > 0) {
                $existing = $pdo->prepare('SELECT image_path FROM site_advertisements WHERE id = :id');
                $existing->execute(['id' => $id]);
                $imagePath = trim((string) ($existing->fetchColumn() ?: ''));
            }
            if (!empty($_FILES['ad_image']['name'])) {
                $newImage = uploads_save_ad_image($_FILES['ad_image']);
                if ($newImage) {
                    if ($imagePath !== '') {
                        uploads_delete_file($imagePath);
                    }
                    $imagePath = $newImage;
                }
            }

            if ($imagePath === '') {
                throw new RuntimeException('Advertisement image is required.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE site_advertisements
                     SET title = :t, image_path = :img, item_id = :iid, sort_order = :ord, is_active = :a
                     WHERE id = :id'
                );
                $stmt->execute([
                    't' => $title,
                    'img' => $imagePath,
                    'iid' => $linkedItemId,
                    'ord' => $sortOrder,
                    'a' => db_bool($isActive),
                    'id' => $id,
                ]);
                admin_flash('success', 'Advertisement updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO site_advertisements (title, image_path, item_id, sort_order, is_active)
                     VALUES (:t, :img, :iid, :ord, :a)'
                );
                $stmt->execute([
                    't' => $title,
                    'img' => $imagePath,
                    'iid' => $linkedItemId,
                    'ord' => $sortOrder,
                    'a' => db_bool($isActive),
                ]);
                admin_flash('success', 'Advertisement created. It will appear beside Search our catalog on the home page.');
            }
        }

        header('Location: ' . admin_url('advertisements.php'));
        exit;
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
        header('Location: ' . admin_url('advertisements.php' . ($editId ? '?edit=' . $editId : '')));
        exit;
    }
}

$ads = $pdo->query(
    'SELECT a.*, i.name AS item_name
     FROM site_advertisements a
     LEFT JOIN items i ON i.id = a.item_id
     ORDER BY a.sort_order ASC, a.id DESC'
)->fetchAll();

admin_render_header('Advertisements', 'advertisements');
?>
<div class="admin-grid-2">
    <section class="admin-panel">
        <h2><?php echo $editRow ? 'Edit advertisement' : 'Add advertisement'; ?></h2>
        <p class="admin-field-note">Upload a banner image. Optionally link it to a product — when linked, clicking the ad opens that product page.</p>

        <form method="post" class="admin-form" enctype="multipart/form-data" id="ad-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo (int) ($editRow['id'] ?? 0); ?>">

            <div class="admin-field">
                <label for="ad_title">Title (optional)</label>
                <input type="text" id="ad_title" name="title" maxlength="200"
                       placeholder="Short label for admin / screen readers"
                       value="<?php echo htmlspecialchars($editRow['title'] ?? ''); ?>">
            </div>

            <div class="admin-field">
                <label for="ad_image">Advertisement image<?php echo $editRow ? ' (leave empty to keep current)' : ''; ?></label>
                <?php if (!empty($editRow['image_path'])): ?>
                <img class="admin-preview" src="<?php echo htmlspecialchars(upload_url($editRow['image_path'])); ?>" alt="">
                <?php endif; ?>
                <input type="file" id="ad_image" name="ad_image" accept="image/*" data-upload-max-mb="15"
                       <?php echo $editRow ? '' : 'required'; ?>>
                <p class="admin-field-note">Portrait or square banners work best beside the home search (recommended ~600×800px or similar).</p>
            </div>

            <div class="admin-field admin-item-picker">
                <label for="ad-item-search">Linked product (optional)</label>
                <input type="search" id="ad-item-search" autocomplete="off"
                       placeholder="Type name, brand, model, or category…"
                       value="<?php echo $selectedPicker ? htmlspecialchars($selectedPicker['label']) : ''; ?>">
                <input type="hidden" id="item_id" name="item_id"
                       value="<?php echo (int) ($editRow['item_id'] ?? 0) ?: ''; ?>">
                <ul id="ad-item-results" class="admin-item-picker__results" hidden role="listbox" aria-label="Matching products"></ul>
                <p class="admin-field-note">Leave empty to show the image only, without opening a product page.</p>
            </div>

            <div class="admin-item-picker__selected" id="ad-item-selected"<?php echo $selectedPicker ? '' : ' hidden'; ?>>
                <?php if ($selectedPicker): ?>
                <?php if (!empty($selectedPicker['image'])): ?>
                <img class="admin-item-picker__thumb" src="<?php echo htmlspecialchars($selectedPicker['image']); ?>" alt="">
                <?php endif; ?>
                <div class="admin-item-picker__meta">
                    <strong><?php echo htmlspecialchars($selectedPicker['label']); ?></strong>
                    <button type="button" class="admin-link-btn" id="ad-item-clear">Change product</button>
                </div>
                <?php endif; ?>
            </div>

            <div class="admin-field">
                <label for="sort_order">Sort order</label>
                <input type="number" id="sort_order" name="sort_order" min="0" step="1"
                       value="<?php echo (int) ($editRow['sort_order'] ?? 0); ?>">
                <p class="admin-field-note">Lower numbers appear first.</p>
            </div>

            <label class="admin-check">
                <input type="checkbox" name="is_active"<?php echo ($editRow === null || !empty($editRow['is_active'])) ? ' checked' : ''; ?>>
                Active (visible on website)
            </label>

            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary" id="ad-submit">
                    <?php echo $editRow ? 'Update' : 'Create'; ?>
                </button>
                <?php if ($editRow): ?>
                <a href="<?php echo admin_url('advertisements.php'); ?>" class="btn btn-ghost">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="admin-panel">
        <h2>Home page ads</h2>
        <p class="admin-field-note">Shown below <strong>Search our catalog</strong> on the home page (4 ads per row, slideshow if you add more).</p>
        <?php if (!$ads): ?>
        <p class="admin-empty">No advertisements yet. Add one using the form.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>Product</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ads as $ad): ?>
                    <tr>
                        <td>
                            <?php if (!empty($ad['image_path'])): ?>
                            <img class="admin-preview admin-preview--sm" src="<?php echo htmlspecialchars(upload_url($ad['image_path'])); ?>" alt="">
                            <?php endif; ?>
                        </td>
                        <td><?php echo ($ad['item_name'] ?? '') !== '' ? htmlspecialchars($ad['item_name']) : '—'; ?></td>
                        <td><?php echo (int) ($ad['sort_order'] ?? 0); ?></td>
                        <td><?php echo !empty($ad['is_active']) ? 'Active' : 'Hidden'; ?></td>
                        <td class="admin-table__actions">
                            <a href="<?php echo admin_url('advertisements.php?edit=' . (int) $ad['id']); ?>" class="admin-link-btn">Edit</a>
                            <form method="post" class="admin-inline-form" onsubmit="return confirm('Delete this advertisement?');">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
                                <button type="submit" class="admin-link-btn admin-link-btn--danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    var searchUrl = <?php echo json_encode(admin_url('api/offer-item-search.php'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var searchInput = document.getElementById('ad-item-search');
    var itemIdInput = document.getElementById('item_id');
    var resultsEl = document.getElementById('ad-item-results');
    var selectedEl = document.getElementById('ad-item-selected');
    var clearBtn = document.getElementById('ad-item-clear');
    var debounceTimer = null;

    if (!searchInput || !itemIdInput || !resultsEl) {
        return;
    }

    function hideResults() {
        resultsEl.hidden = true;
        resultsEl.innerHTML = '';
    }

    function selectItem(item) {
        itemIdInput.value = String(item.id);
        searchInput.value = item.label;
        hideResults();
        selectedEl.hidden = false;
        selectedEl.innerHTML = '';
        if (item.image) {
            var img = document.createElement('img');
            img.className = 'admin-item-picker__thumb';
            img.src = item.image;
            img.alt = '';
            selectedEl.appendChild(img);
        }
        var meta = document.createElement('div');
        meta.className = 'admin-item-picker__meta';
        meta.innerHTML = '<strong></strong>';
        meta.querySelector('strong').textContent = item.label;
        var change = document.createElement('button');
        change.type = 'button';
        change.className = 'admin-link-btn';
        change.id = 'ad-item-clear';
        change.textContent = 'Change product';
        change.addEventListener('click', clearSelection);
        meta.appendChild(change);
        selectedEl.appendChild(meta);
    }

    function clearSelection() {
        itemIdInput.value = '';
        searchInput.value = '';
        selectedEl.hidden = true;
        selectedEl.innerHTML = '';
        searchInput.focus();
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', clearSelection);
    }

    searchInput.addEventListener('input', function () {
        var q = searchInput.value.trim();
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        if (q.length < 1) {
            hideResults();
            return;
        }
        debounceTimer = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.ok || !data.items || !data.items.length) {
                        resultsEl.innerHTML = '<li class="admin-item-picker__empty">No matching products</li>';
                        resultsEl.hidden = false;
                        return;
                    }
                    resultsEl.innerHTML = data.items.map(function (item) {
                        return '<li><button type="button" class="admin-item-picker__option" data-id="' + item.id + '">'
                            + (item.image ? '<img src="' + item.image + '" alt="" class="admin-item-picker__thumb">' : '')
                            + '<span>' + item.label + '</span></button></li>';
                    }).join('');
                    resultsEl.hidden = false;
                    resultsEl.querySelectorAll('.admin-item-picker__option').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var id = parseInt(btn.getAttribute('data-id'), 10);
                            var picked = data.items.find(function (it) { return it.id === id; });
                            if (picked) {
                                selectItem(picked);
                            }
                        });
                    });
                })
                .catch(function () {
                    hideResults();
                });
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.admin-item-picker')) {
            hideResults();
        }
    });
})();
</script>
<?php
admin_render_footer();
