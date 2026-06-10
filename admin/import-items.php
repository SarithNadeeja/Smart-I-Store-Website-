<?php

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

admin_require_setup_complete();
$pdo = db();

/**
 * Map a normalized CSV header to an internal field name.
 */
function admin_import_match_column(string $header): ?string
{
    $header = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
    $map = [
        'name' => 'name', 'item' => 'name', 'item name' => 'name', 'product name' => 'name', 'product' => 'name',
        'code' => 'product_code', 'product code' => 'product_code', 'item code' => 'product_code',
        'cost' => 'cost_price', 'cost price' => 'cost_price', 'purchase cost' => 'cost_price',
        'category' => 'category',
        'unit' => 'unit',
        'retail price' => 'price', 'price' => 'price', 'sell price' => 'price', 'selling price' => 'price',
        'wholesale price' => 'wholesale_price', 'wholesale' => 'wholesale_price',
        'min price' => 'min_price', 'minimum price' => 'min_price',
        'brand' => 'brand',
        'stock alert' => 'reorder_level', 'reorder level' => 'reorder_level', 'alert' => 'reorder_level',
        'note' => 'note', 'notes' => 'note',
        'quantity' => 'stock_quantity', 'qty' => 'stock_quantity', 'stock' => 'stock_quantity', 'stock quantity' => 'stock_quantity',
    ];
    return $map[$header] ?? null;
}

function admin_import_parse_number(?string $raw): ?float
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    $raw = str_ireplace(['rs.', 'rs', ','], '', $raw);
    $raw = trim($raw);
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }
    return max(0, (float) $raw);
}

/**
 * Guess a sensible icon for an auto-created category.
 */
function admin_import_guess_icon(string $name): string
{
    $label = strtolower($name);
    if (str_contains($label, 'headphone') || str_contains($label, 'earphone') || str_contains($label, 'earpod') || str_contains($label, 'earbud')) {
        return 'headphones';
    }
    if (preg_match('/\btablets?\b/', $label)) {
        return 'tablet';
    }
    if (preg_match('/\b(smart\s*phones?|phones?)\b/', $label)) {
        return 'smartphone';
    }
    if (str_contains($label, 'watch')) {
        return 'watch';
    }
    return 'accessories';
}

$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_csrf_verify();

        $dryRun = isset($_POST['dry_run']);
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Choose a CSV file to upload.');
        }

        $fh = fopen($file['tmp_name'], 'r');
        if (!$fh) {
            throw new RuntimeException('Could not read the uploaded file.');
        }

        // Sniff delimiter from the header line (Excel sometimes exports with ";").
        $firstLine = (string) fgets($fh);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($fh);

        $headerRow = fgetcsv($fh, 0, $delimiter);
        if (!$headerRow) {
            throw new RuntimeException('The CSV file is empty.');
        }
        // Strip UTF-8 BOM from the first header cell.
        $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headerRow[0]);

        $columns = [];
        foreach ($headerRow as $i => $h) {
            $field = admin_import_match_column((string) $h);
            if ($field !== null && !in_array($field, $columns, true)) {
                $columns[$i] = $field;
            }
        }
        if (!in_array('name', $columns, true)) {
            throw new RuntimeException('Could not find a "name" column in the CSV header.');
        }

        $report = [
            'dry_run' => $dryRun,
            'imported' => 0,
            'categories_created' => [],
            'brands_created' => [],
            'skipped' => [],
            'total_rows' => 0,
        ];

        // Lookups (case-insensitive, by name).
        $categoriesByName = [];
        foreach ($pdo->query('SELECT id, title, description FROM categories')->fetchAll() as $cat) {
            $label = trim($cat['description'] !== '' ? $cat['description'] : $cat['title']);
            $categoriesByName[mb_strtolower($label)] = (int) $cat['id'];
            $categoriesByName[mb_strtolower(trim($cat['title']))] = (int) $cat['id'];
        }
        $brandsByName = [];
        foreach ($pdo->query('SELECT id, name FROM phone_brands')->fetchAll() as $brand) {
            $brandsByName[mb_strtolower(trim($brand['name']))] = (int) $brand['id'];
        }
        // Existing items keyed by name + product code (codes alone are reused across
        // different products in trade sheets, so they can't act as a unique key).
        $existingKeys = [];
        foreach ($pdo->query("SELECT LOWER(name) || '|' || LOWER(COALESCE(product_code, '')) FROM items")->fetchAll(PDO::FETCH_COLUMN) as $key) {
            $existingKeys[$key] = true;
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO items
                (category_id, brand_id, model_id, name, price, tag, color, is_phone, is_featured,
                 main_image, is_active, sort_order, stock_status, stock_quantity, cost_price,
                 product_code, unit, wholesale_price, min_price, reorder_level, note)
             VALUES
                (:cat, :brand, NULL, :name, :price, \'\', \'#333333\', :phone, :featured,
                 \'\', :active, 0, :stock_status, :qty, :cost,
                 :code, :unit, :wholesale, :min_price, :reorder, :note)'
        );

        $pdo->beginTransaction();
        try {
            $rowNum = 1;
            while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
                $rowNum++;
                if ($row === [null] || $row === false) {
                    continue;
                }

                $data = [];
                foreach ($columns as $i => $field) {
                    $data[$field] = trim((string) ($row[$i] ?? ''));
                }

                // Skip fully empty lines (e.g. blank row 11 in the sheet).
                if (implode('', $data) === '') {
                    continue;
                }
                $report['total_rows']++;

                if ($report['total_rows'] > 5000) {
                    throw new RuntimeException('Too many rows (max 5000 per import). Split the file and try again.');
                }

                $name = $data['name'] ?? '';
                if ($name === '') {
                    $report['skipped'][] = 'Row ' . $rowNum . ': missing item name.';
                    continue;
                }
                if (mb_strlen($name) > 200) {
                    $report['skipped'][] = 'Row ' . $rowNum . ' (' . mb_substr($name, 0, 40) . '…): name too long (max 200).';
                    continue;
                }

                $categoryName = $data['category'] ?? '';
                if ($categoryName === '') {
                    $report['skipped'][] = 'Row ' . $rowNum . ' (' . $name . '): missing category.';
                    continue;
                }

                $code = $data['product_code'] ?? '';
                if (mb_strlen($code) > 64) {
                    $report['skipped'][] = 'Row ' . $rowNum . ' (' . $name . '): product code too long (max 64).';
                    continue;
                }

                $price = admin_import_parse_number($data['price'] ?? '');
                if ($price === null) {
                    $report['skipped'][] = 'Row ' . $rowNum . ' (' . $name . '): missing or invalid retail price.';
                    continue;
                }

                // Category: find or create.
                $catKey = mb_strtolower($categoryName);
                if (isset($categoriesByName[$catKey])) {
                    $categoryId = $categoriesByName[$catKey];
                } else {
                    $icon = admin_import_guess_icon($categoryName);
                    $catStmt = $pdo->prepare(
                        'INSERT INTO categories (title, description, icon, is_active) VALUES (:t, :d, :i, TRUE) RETURNING id'
                    );
                    $catStmt->execute(['t' => $categoryName, 'd' => $categoryName, 'i' => $icon]);
                    $categoryId = (int) $catStmt->fetchColumn();
                    $categoriesByName[$catKey] = $categoryId;
                    $report['categories_created'][] = $categoryName;
                }

                // Brand: find or create. Empty / "none" stays unassigned.
                $brandName = $data['brand'] ?? '';
                $brandId = null;
                if ($brandName !== '' && !in_array(mb_strtolower($brandName), ['none', '-', 'n/a', 'na'], true)) {
                    $brandKey = mb_strtolower($brandName);
                    if (isset($brandsByName[$brandKey])) {
                        $brandId = $brandsByName[$brandKey];
                    } else {
                        $brandStmt = $pdo->prepare(
                            'INSERT INTO phone_brands (name, is_active) VALUES (:n, TRUE) RETURNING id'
                        );
                        $brandStmt->execute(['n' => $brandName]);
                        $brandId = (int) $brandStmt->fetchColumn();
                        $brandsByName[$brandKey] = $brandId;
                        $report['brands_created'][] = $brandName;
                    }
                }

                // Duplicate check: same item name + product code already in the system.
                $dupeKey = mb_strtolower($name) . '|' . mb_strtolower($code);
                if (isset($existingKeys[$dupeKey])) {
                    $report['skipped'][] = 'Row ' . $rowNum . ' (' . $name . '): this item already exists (same name and code).';
                    continue;
                }

                $quantity = (int) (admin_import_parse_number($data['stock_quantity'] ?? '') ?? 0);
                $reorder = (int) (admin_import_parse_number($data['reorder_level'] ?? '') ?? 5);
                $isPhone = store_category_is_phone($categoryId);

                $insertStmt->execute([
                    'cat' => $categoryId,
                    'brand' => $brandId,
                    'name' => $name,
                    'price' => $price,
                    'phone' => db_bool($isPhone),
                    'featured' => db_bool(false),
                    'active' => db_bool(true),
                    'stock_status' => 'in_stock',
                    'qty' => $quantity,
                    'cost' => admin_import_parse_number($data['cost_price'] ?? '') ?? 0,
                    'code' => $code,
                    'unit' => $data['unit'] ?? '',
                    'wholesale' => admin_import_parse_number($data['wholesale_price'] ?? ''),
                    'min_price' => admin_import_parse_number($data['min_price'] ?? ''),
                    'reorder' => $reorder,
                    'note' => $data['note'] ?? '',
                ]);

                $existingKeys[$dupeKey] = true;
                $report['imported']++;
            }

            if ($dryRun) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            fclose($fh);
        }
    } catch (Throwable $e) {
        $report = null;
        admin_flash('error', 'Import failed: ' . $e->getMessage());
    }
}

admin_render_header('Import Items (CSV)', 'import');
?>
<div class="admin-grid-2">
    <section class="admin-panel">
        <h2>Upload CSV</h2>
        <p class="admin-field-note">
            Upload your item sheet as a CSV file (in Excel: File &rarr; Save As &rarr; CSV).
            Categories and brands are matched by name and created automatically when missing.
            Images can be added afterwards by editing each item.
        </p>
        <form method="post" enctype="multipart/form-data" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(admin_csrf_token()); ?>">
            <div class="admin-field">
                <label for="csv_file">CSV file</label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
            </div>
            <label class="admin-check">
                <input type="checkbox" name="dry_run" checked>
                Test run first (check the file, but don't save anything)
            </label>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary">Upload &amp; import</button>
            </div>
        </form>
    </section>

    <section class="admin-panel">
        <h2>Supported columns</h2>
        <p class="admin-field-note">The first row must be a header. Column order does not matter; extra columns are ignored.</p>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Column header</th><th>Used for</th></tr></thead>
                <tbody>
                    <tr><td><code>name</code></td><td>Item name (required)</td></tr>
                    <tr><td><code>category</code></td><td>Category — created if it doesn't exist (required)</td></tr>
                    <tr><td><code>Retail price</code></td><td>Selling price shown on the website (required)</td></tr>
                    <tr><td><code>brand</code></td><td>Brand — created if it doesn't exist ("None" = no brand)</td></tr>
                    <tr><td><code>code</code></td><td>Product code (rows matching an existing item's name + code are skipped)</td></tr>
                    <tr><td><code>cost</code></td><td>Purchase cost (internal)</td></tr>
                    <tr><td><code>unit</code></td><td>Unit, e.g. 2 PCS</td></tr>
                    <tr><td><code>Wholesale price</code></td><td>Wholesale price (internal)</td></tr>
                    <tr><td><code>Min price</code></td><td>Lowest allowed selling price (internal)</td></tr>
                    <tr><td><code>Stock alert</code></td><td>Low-stock alert level</td></tr>
                    <tr><td><code>quantity</code></td><td>Stock quantity (defaults to 0 if the column is missing)</td></tr>
                    <tr><td><code>note</code></td><td>Internal note</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php if ($report): ?>
<section class="admin-panel" style="margin-top: 24px;">
    <h2><?php echo $report['dry_run'] ? 'Test run result (nothing was saved)' : 'Import result'; ?></h2>
    <div class="admin-alert admin-alert--<?php echo $report['skipped'] ? 'error' : 'success'; ?>">
        <?php echo (int) $report['imported']; ?> of <?php echo (int) $report['total_rows']; ?> rows
        <?php echo $report['dry_run'] ? 'would be imported' : 'imported'; ?>,
        <?php echo count($report['skipped']); ?> skipped,
        <?php echo count(array_unique($report['categories_created'])); ?> new categories,
        <?php echo count(array_unique($report['brands_created'])); ?> new brands.
    </div>

    <?php if ($report['categories_created']): ?>
    <p><strong>New categories<?php echo $report['dry_run'] ? ' (would be created)' : ''; ?>:</strong>
        <?php echo htmlspecialchars(implode(', ', array_unique($report['categories_created']))); ?></p>
    <?php endif; ?>

    <?php if ($report['brands_created']): ?>
    <p><strong>New brands<?php echo $report['dry_run'] ? ' (would be created)' : ''; ?>:</strong>
        <?php echo htmlspecialchars(implode(', ', array_unique($report['brands_created']))); ?></p>
    <?php endif; ?>

    <?php if ($report['skipped']): ?>
    <h3 style="margin-top: 16px;">Skipped rows</h3>
    <ul class="admin-import-skips">
        <?php foreach ($report['skipped'] as $msg): ?>
        <li><?php echo htmlspecialchars($msg); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($report['dry_run'] && $report['imported'] > 0): ?>
    <p class="admin-field-note">Everything look good? Upload the same file again with "Test run" unticked to save the items.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php
admin_render_footer();
