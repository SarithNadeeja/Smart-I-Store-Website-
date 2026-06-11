<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once dirname(__DIR__, 2) . '/includes/store.php';

function pos_payment_methods(): array
{
    return [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'card_manual' => 'Card Manual',
        'credit' => 'Credit',
        'partial' => 'Partial Payment',
    ];
}

function pos_expense_categories(): array
{
    return [
        'Transport', 'Food', 'Electricity', 'Rent', 'Salary', 'Repair Parts', 'Other',
    ];
}

function pos_repair_statuses(): array
{
    return ['Received', 'Completed', 'Delivered', 'Cancelled'];
}

/** Payment methods shown when collecting balance at delivery */
function pos_repair_collect_methods(): array
{
    return [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'card_manual' => 'Card Manual',
    ];
}

function pos_format_money(float $n): string
{
    return 'Rs. ' . number_format($n, 2);
}

function pos_audit(int $staffId, string $action, string $entityType = '', ?int $entityId = null, string $details = ''): void
{
    db()->prepare(
        'INSERT INTO pos_audit_log (staff_id, action, entity_type, entity_id, details)
         VALUES (:s, :a, :t, :e, :d)'
    )->execute([
        's' => $staffId,
        'a' => $action,
        't' => $entityType,
        'e' => $entityId,
        'd' => $details,
    ]);
}

function pos_next_number(string $prefix): string
{
    $pdo = db();
    $date = date('Ymd');
    $like = $prefix . '-' . $date . '-%';
    $table = match ($prefix) {
        'INV' => 'pos_invoices',
        'RET' => 'pos_returns',
        'JOB' => 'pos_repair_jobs',
        default => 'pos_invoices',
    };
    $col = match ($prefix) {
        'INV' => 'invoice_no',
        'RET' => 'return_no',
        'JOB' => 'job_no',
        default => 'invoice_no',
    };
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$col} LIKE :p");
    $stmt->execute(['p' => $like]);
    $seq = (int) $stmt->fetchColumn() + 1;
    return $prefix . '-' . $date . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

function pos_search_products(string $q, int $limit = 20): array
{
    $q = trim($q);
    $sql = "
        SELECT i.id, i.name, i.price, i.cost_price, i.stock_quantity, i.is_phone,
               i.product_code,
               c.title AS category_name, b.name AS brand_name
        FROM items i
        LEFT JOIN categories c ON c.id = i.category_id
        LEFT JOIN phone_brands b ON b.id = i.brand_id
        WHERE i.is_active = TRUE
    ";
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (i.name ILIKE :q OR i.product_code ILIKE :q OR b.name ILIKE :q OR c.title ILIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY i.name ASC LIMIT :lim';
    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':lim', max(1, min(50, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return array_map(static function (array $row): array {
        $row = pos_enrich_product_for_sale($row);
        $priced = store_apply_item_pricing($row, $row);
        $row['price'] = $priced['current_price'];
        $row['list_price'] = $priced['list_price'];

        return $row;
    }, $rows);
}

function pos_enrich_product_for_sale(array $row): array
{
    $id = (int) ($row['id'] ?? 0);
    $row['is_phone'] = !empty($row['is_phone']);
    $row['variant_label'] = '';
    if ($row['is_phone'] && $id > 0) {
        $variants = store_get_item_storage_variants($id);
        if ($variants) {
            $row['variant_label'] = $variants[0]['label'] ?? '';
        }
    }

    return $row;
}

function pos_get_storage_variant(int $variantId): ?array
{
    if ($variantId <= 0) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT sv.id, sv.item_id, sv.ram, sv.rom, sv.price, sv.cost_price, sv.stock_status,
                i.name AS item_name, i.price AS item_price, i.cost_price AS item_cost, i.is_phone
         FROM item_storage_variants sv
         INNER JOIN items i ON i.id = sv.item_id
         WHERE sv.id = :id AND i.is_active = TRUE'
    );
    $stmt->execute(['id' => $variantId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'item_id' => (int) $row['item_id'],
        'ram' => trim($row['ram'] ?? ''),
        'rom' => trim($row['rom'] ?? ''),
        'price' => $row['price'] !== null && $row['price'] !== '' ? (float) $row['price'] : null,
        'cost_price' => (float) ($row['cost_price'] ?? 0),
        'stock_status' => store_normalize_stock_status($row['stock_status'] ?? 'in_stock'),
        'item_name' => $row['item_name'],
        'item_price' => (float) $row['item_price'],
        'item_cost' => (float) $row['item_cost'],
        'label' => store_format_storage_variant_label(trim($row['ram'] ?? ''), trim($row['rom'] ?? '')),
    ];
}

function pos_get_product(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, name, price, cost_price, stock_quantity, is_phone FROM items WHERE id = :id AND is_active = TRUE'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $row = pos_enrich_product_for_sale($row);

    return store_apply_item_pricing($row, $row);
}

function pos_search_customers(string $q, int $limit = 20): array
{
    $q = trim($q);
    $sql = 'SELECT * FROM pos_customers WHERE is_active = TRUE';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (name ILIKE :q OR phone ILIKE :q OR email ILIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY name ASC LIMIT :lim';
    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':lim', max(1, min(50, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function pos_save_customer(int $id, string $name, string $phone, string $email, string $address, string $notes): int
{
    $name = trim($name);
    if ($name === '') {
        throw new RuntimeException('Customer name is required.');
    }
    $data = [
        'name' => $name,
        'phone' => trim($phone),
        'email' => trim($email),
        'address' => trim($address),
        'notes' => trim($notes),
        'active' => true,
    ];
    if ($id > 0) {
        $data['id'] = $id;
        db()->prepare(
            'UPDATE pos_customers SET name=:name, phone=:phone, email=:email, address=:address, notes=:notes WHERE id=:id'
        )->execute($data);
        return $id;
    }
    $stmt = db()->prepare(
        'INSERT INTO pos_customers (name, phone, email, address, notes, is_active)
         VALUES (:name,:phone,:email,:address,:notes,:active) RETURNING id'
    );
    $stmt->execute($data);
    return (int) $stmt->fetchColumn();
}

function pos_get_customer(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM pos_customers WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function pos_list_customers(): array
{
    return db()->query(
        'SELECT * FROM pos_customers WHERE is_active = TRUE ORDER BY name ASC'
    )->fetchAll();
}

function pos_repair_total_paid(array $job): float
{
    $paid = 0.0;
    foreach ($job['payments'] ?? [] as $payment) {
        $paid += (float) $payment['amount'];
    }
    return $paid;
}

function pos_repair_balance_due(array $job): float
{
    $final = (float) ($job['final_cost'] ?? 0);
    if ($final <= 0) {
        return 0.0;
    }
    return max(0, round($final - pos_repair_total_paid($job), 2));
}

function pos_repair_amount_due(array $job): float
{
    $final = (float) ($job['final_cost'] ?? 0);
    if ($final > 0) {
        return pos_repair_balance_due($job);
    }
    return max(0, (float) ($job['estimated_cost'] ?? 0) - pos_repair_total_paid($job));
}

function pos_repair_sync_payment_status(int $jobId): void
{
    $job = pos_get_repair_job($jobId);
    if (!$job) {
        return;
    }
    $final = (float) $job['final_cost'];
    $paid = pos_repair_total_paid($job);
    $status = $final > 0
        ? pos_resolve_payment_status($final, $paid)
        : ($paid > 0 ? 'partial' : 'unpaid');
    db()->prepare('UPDATE pos_repair_jobs SET payment_status = :ps WHERE id = :id')
        ->execute(['ps' => $status, 'id' => $jobId]);
}

/** Sum of repair expenses (uses pos_repair_parts; stored in parts_cost). */
function pos_repair_expense_total(array $job): float
{
    return (float) ($job['parts_cost'] ?? 0);
}

function pos_repair_profit(array $job): float
{
    $stored = (float) ($job['repair_profit'] ?? 0);
    $final = (float) ($job['final_cost'] ?? 0);
    if ($final <= 0) {
        return 0.0;
    }
    if ($stored !== 0.0 || pos_repair_expense_total($job) > 0) {
        return $stored;
    }
    return max(0, round($final - pos_repair_expense_total($job), 2));
}

function pos_repair_sync_profit(int $jobId): void
{
    $pdo = db();
    $sumStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(cost), 0) FROM pos_repair_parts WHERE repair_job_id = :id'
    );
    $sumStmt->execute(['id' => $jobId]);
    $expenseTotal = (float) $sumStmt->fetchColumn();

    $jobStmt = $pdo->prepare('SELECT final_cost FROM pos_repair_jobs WHERE id = :id');
    $jobStmt->execute(['id' => $jobId]);
    $final = (float) ($jobStmt->fetchColumn() ?: 0);
    $profit = $final > 0 ? max(0, round($final - $expenseTotal, 2)) : 0;

    $pdo->prepare(
        'UPDATE pos_repair_jobs SET parts_cost = :exp, repair_profit = :profit WHERE id = :id'
    )->execute(['exp' => $expenseTotal, 'profit' => $profit, 'id' => $jobId]);
}

function pos_add_repair_expense(int $jobId, string $description, float $cost, int $staffId): void
{
    $job = pos_get_repair_job($jobId);
    if (!$job) {
        throw new RuntimeException('Repair job not found.');
    }
    if (in_array($job['status'], ['Delivered', 'Cancelled'], true)) {
        throw new RuntimeException('Cannot add expenses to a closed job.');
    }
    $description = trim($description);
    if ($description === '') {
        throw new RuntimeException('Expense description is required.');
    }
    if ($cost <= 0) {
        throw new RuntimeException('Expense cost must be greater than zero.');
    }
    db()->prepare(
        'INSERT INTO pos_repair_parts (repair_job_id, part_description, cost) VALUES (:j, :d, :c)'
    )->execute(['j' => $jobId, 'd' => $description, 'c' => $cost]);
    pos_repair_sync_profit($jobId);
    pos_audit($staffId, 'repair_expense_added', 'repair', $jobId, $description . ' ' . pos_format_money($cost));
}

function pos_remove_repair_expense(int $expenseId, int $jobId, int $staffId): void
{
    $job = pos_get_repair_job($jobId);
    if (!$job) {
        throw new RuntimeException('Repair job not found.');
    }
    if (in_array($job['status'], ['Delivered', 'Cancelled'], true)) {
        throw new RuntimeException('Cannot remove expenses from a closed job.');
    }
    $stmt = db()->prepare(
        'DELETE FROM pos_repair_parts WHERE id = :id AND repair_job_id = :j RETURNING part_description'
    );
    $stmt->execute(['id' => $expenseId, 'j' => $jobId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Expense not found.');
    }
    pos_repair_sync_profit($jobId);
    pos_audit($staffId, 'repair_expense_removed', 'repair', $jobId, 'Expense removed');
}

/** Repair revenue / expense / profit for a date range (completed or delivered jobs). */
function pos_repair_profit_totals(string $dateFrom, string $dateTo): array
{
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(final_cost), 0) AS revenue,
                COALESCE(SUM(parts_cost), 0) AS expense,
                COALESCE(SUM(repair_profit), 0) AS profit
         FROM pos_repair_jobs
         WHERE status IN ('Completed', 'Delivered')
           AND final_cost > 0
           AND DATE(COALESCE(delivered_at, completed_at, created_at)) BETWEEN :from AND :to"
    );
    $stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
    $row = $stmt->fetch() ?: ['revenue' => 0, 'expense' => 0, 'profit' => 0];
    return [
        'revenue' => (float) $row['revenue'],
        'expense' => (float) $row['expense'],
        'profit' => (float) $row['profit'],
    ];
}

function pos_resolve_payment_status(float $total, float $paid): string
{
    if ($paid <= 0) {
        return 'unpaid';
    }
    if ($paid + 0.009 >= $total) {
        return 'paid';
    }
    return 'partial';
}

function pos_create_invoice(array $header, array $lines, int $staffId): int
{
    if (!$lines) {
        throw new RuntimeException('Add at least one product to the cart.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $subtotal = 0.0;
        $preparedLines = [];
        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $product = pos_get_product($productId);
            if (!$product) {
                throw new RuntimeException('Product not found.');
            }
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            if ((int) $product['stock_quantity'] < $qty) {
                throw new RuntimeException('Insufficient stock for ' . $product['name']);
            }

            $unitPrice = isset($line['unit_price']) ? (float) $line['unit_price'] : (float) $product['price'];
            $costSnapshot = (float) $product['cost_price'];
            $nameSnapshot = $product['name'];
            $label = trim((string) ($product['variant_label'] ?? ''));
            if ($label !== '') {
                $nameSnapshot .= ' (' . $label . ')';
            }

            $lineDiscount = max(0, (float) ($line['discount'] ?? 0));
            $lineTotal = max(0, ($unitPrice * $qty) - $lineDiscount);
            $subtotal += $lineTotal;
            $preparedLines[] = [
                'product_id' => $productId,
                'product_name_snapshot' => $nameSnapshot,
                'unit_price' => $unitPrice,
                'cost_price_snapshot' => $costSnapshot,
                'quantity' => $qty,
                'discount' => $lineDiscount,
                'line_total' => $lineTotal,
            ];
        }

        $invoiceDiscount = max(0, (float) ($header['discount'] ?? 0));
        $total = max(0, $subtotal - $invoiceDiscount);
        $paid = max(0, (float) ($header['paid_amount'] ?? 0));
        if ($paid > $total) {
            $paid = $total;
        }
        $balance = max(0, $total - $paid);
        $paymentStatus = pos_resolve_payment_status($total, $paid);
        $warrantyDays = max(0, (int) ($header['warranty_period_days'] ?? 0));
        $warrantyStart = $warrantyDays > 0 ? date('Y-m-d') : null;
        $warrantyEnd = $warrantyDays > 0 ? date('Y-m-d', strtotime('+' . $warrantyDays . ' days')) : null;

        $stmt = $pdo->prepare(
            'INSERT INTO pos_invoices
             (invoice_no, customer_id, cashier_id, subtotal, discount, total, paid_amount, balance,
              payment_method, payment_status, warranty_period_days, warranty_start_date, warranty_end_date,
              warranty_note, notes)
             VALUES (:no,:cust,:cash,:sub,:disc,:tot,:paid,:bal,:pm,:ps,:wd,:ws,:we,:wn,:notes)
             RETURNING id'
        );
        $stmt->execute([
            'no' => pos_next_number('INV'),
            'cust' => !empty($header['customer_id']) ? (int) $header['customer_id'] : null,
            'cash' => $staffId,
            'sub' => $subtotal,
            'disc' => $invoiceDiscount,
            'tot' => $total,
            'paid' => $paid,
            'bal' => $balance,
            'pm' => $header['payment_method'] ?? 'cash',
            'ps' => $paymentStatus,
            'wd' => $warrantyDays,
            'ws' => $warrantyStart,
            'we' => $warrantyEnd,
            'wn' => trim($header['warranty_note'] ?? ''),
            'notes' => trim($header['notes'] ?? ''),
        ]);
        $invoiceId = (int) $stmt->fetchColumn();

        $itemStmt = $pdo->prepare(
            'INSERT INTO pos_invoice_items
             (invoice_id, product_id, product_name_snapshot, unit_price, cost_price_snapshot,
              quantity, discount, line_total)
             VALUES (:inv,:pid,:name,:up,:cp,:qty,:disc,:lt)'
        );
        foreach ($preparedLines as $pl) {
            $itemStmt->execute([
                'inv' => $invoiceId,
                'pid' => $pl['product_id'],
                'name' => $pl['product_name_snapshot'],
                'up' => $pl['unit_price'],
                'cp' => $pl['cost_price_snapshot'],
                'qty' => $pl['quantity'],
                'disc' => $pl['discount'],
                'lt' => $pl['line_total'],
            ]);
            $pdo->prepare('UPDATE items SET stock_quantity = GREATEST(0, stock_quantity - :q) WHERE id = :id')
                ->execute(['q' => $pl['quantity'], 'id' => $pl['product_id']]);
            // Last unit sold: show the item as out of stock on the website.
            // Phone listings represent physical units, so they are hidden entirely.
            $pdo->prepare(
                "UPDATE items
                 SET stock_status = 'out_of_stock',
                     is_active = CASE WHEN is_phone THEN FALSE ELSE is_active END
                 WHERE id = :id AND stock_quantity <= 0"
            )->execute(['id' => $pl['product_id']]);
        }

        if ($paid > 0) {
            $pdo->prepare(
                'INSERT INTO pos_invoice_payments (invoice_id, amount, payment_method, note, created_by)
                 VALUES (:i,:a,:m,:n,:s)'
            )->execute([
                'i' => $invoiceId,
                'a' => $paid,
                'm' => $header['payment_method'] ?? 'cash',
                'n' => 'Initial payment',
                's' => $staffId,
            ]);
        }

        $pdo->commit();
        pos_audit($staffId, 'invoice_created', 'invoice', $invoiceId, 'Invoice saved');
        return $invoiceId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function pos_get_invoice(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT i.*, c.name AS customer_name, c.phone AS customer_phone,
                s.name AS cashier_name
         FROM pos_invoices i
         LEFT JOIN pos_customers c ON c.id = i.customer_id
         LEFT JOIN pos_staff s ON s.id = i.cashier_id
         WHERE i.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $inv = $stmt->fetch();
    if (!$inv) {
        return null;
    }
    $itemStmt = db()->prepare('SELECT * FROM pos_invoice_items WHERE invoice_id = :id ORDER BY id');
    $itemStmt->execute(['id' => $id]);
    $inv['items'] = $itemStmt->fetchAll();
    $payStmt = db()->prepare(
        'SELECT p.*, s.name AS staff_name FROM pos_invoice_payments p
         LEFT JOIN pos_staff s ON s.id = p.created_by WHERE invoice_id = :id ORDER BY p.id'
    );
    $payStmt->execute(['id' => $id]);
    $inv['payments'] = $payStmt->fetchAll();
    return $inv;
}

/**
 * Spread the invoice-level discount across the line items, proportionally to
 * their totals (display helper — nothing is stored). Returns a map of
 * invoice_item id => allocated discount; rounding drift goes to the last line.
 */
function pos_allocate_invoice_discount(array $invoice): array
{
    $shares = [];
    $items = $invoice['items'] ?? [];
    foreach ($items as $item) {
        $shares[(int) $item['id']] = 0.0;
    }

    $discount = (float) ($invoice['discount'] ?? 0);
    if ($discount <= 0 || !$items) {
        return $shares;
    }

    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += (float) $item['line_total'];
    }
    if ($subtotal <= 0) {
        return $shares;
    }

    $allocated = 0.0;
    $lastId = null;
    foreach ($items as $item) {
        $id = (int) $item['id'];
        $share = round($discount * (float) $item['line_total'] / $subtotal, 2);
        $shares[$id] = $share;
        $allocated += $share;
        $lastId = $id;
    }
    $shares[$lastId] = round($shares[$lastId] + ($discount - $allocated), 2);

    return $shares;
}

function pos_add_invoice_payment(int $invoiceId, float $amount, string $method, string $note, int $staffId): void
{
    $inv = pos_get_invoice($invoiceId);
    if (!$inv || $inv['status'] === 'cancelled') {
        throw new RuntimeException('Invoice not found.');
    }
    if ($amount <= 0) {
        throw new RuntimeException('Invalid payment amount.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $newPaid = (float) $inv['paid_amount'] + $amount;
        $total = (float) $inv['total'];
        if ($newPaid > $total) {
            $amount = $total - (float) $inv['paid_amount'];
            $newPaid = $total;
        }
        $balance = max(0, $total - $newPaid);
        $pdo->prepare(
            'INSERT INTO pos_invoice_payments (invoice_id, amount, payment_method, note, created_by)
             VALUES (:i,:a,:m,:n,:s)'
        )->execute(['i' => $invoiceId, 'a' => $amount, 'm' => $method, 'n' => $note, 's' => $staffId]);
        $pdo->prepare(
            'UPDATE pos_invoices SET paid_amount=:p, balance=:b, payment_status=:ps WHERE id=:id'
        )->execute([
            'p' => $newPaid,
            'b' => $balance,
            'ps' => pos_resolve_payment_status($total, $newPaid),
            'id' => $invoiceId,
        ]);
        $pdo->commit();
        pos_audit($staffId, 'payment_added', 'invoice', $invoiceId, pos_format_money($amount));
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function pos_create_return(int $invoiceItemId, int $qty, string $reason, int $staffId): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT ii.*, i.invoice_no, i.status AS invoice_status
         FROM pos_invoice_items ii
         JOIN pos_invoices i ON i.id = ii.invoice_id
         WHERE ii.id = :id'
    );
    $stmt->execute(['id' => $invoiceItemId]);
    $line = $stmt->fetch();
    if (!$line || $line['invoice_status'] === 'cancelled') {
        throw new RuntimeException('Invoice line not found.');
    }
    $maxReturn = (int) $line['quantity'] - (int) $line['returned_quantity'];
    if ($qty < 1 || $qty > $maxReturn) {
        throw new RuntimeException('Invalid return quantity.');
    }
    $refund = ((float) $line['line_total'] / (int) $line['quantity']) * $qty;
    $pdo->beginTransaction();
    try {
        $retStmt = $pdo->prepare(
            'INSERT INTO pos_returns (return_no, invoice_id, invoice_item_id, product_id, quantity, reason, refund_amount, created_by)
             VALUES (:no,:inv,:ii,:pid,:q,:r,:amt,:s) RETURNING id'
        );
        $retStmt->execute([
            'no' => pos_next_number('RET'),
            'inv' => $line['invoice_id'],
            'ii' => $invoiceItemId,
            'pid' => $line['product_id'],
            'q' => $qty,
            'r' => trim($reason),
            'amt' => $refund,
            's' => $staffId,
        ]);
        $returnId = (int) $retStmt->fetchColumn();
        $pdo->prepare('UPDATE pos_invoice_items SET returned_quantity = returned_quantity + :q WHERE id = :id')
            ->execute(['q' => $qty, 'id' => $invoiceItemId]);
        if ($line['product_id']) {
            $pdo->prepare(
                "UPDATE items SET stock_quantity = stock_quantity + :q, stock_status = 'in_stock', is_active = TRUE WHERE id = :id"
            )->execute(['q' => $qty, 'id' => $line['product_id']]);
        }
        $pdo->commit();
        pos_audit($staffId, 'return_created', 'return', $returnId, $line['product_name_snapshot']);
        return $returnId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function pos_create_repair_job(array $data, int $staffId): int
{
    $customerId = (int) ($data['customer_id'] ?? 0);
    if ($customerId <= 0 || !pos_get_customer($customerId)) {
        throw new RuntimeException('Select a valid customer.');
    }
    $issue = trim($data['issue_description'] ?? '');
    if ($issue === '') {
        throw new RuntimeException('Issue description is required.');
    }
    $advance = max(0, (float) ($data['advance_payment'] ?? 0));
    $estimated = max(0, (float) ($data['estimated_cost'] ?? 0));
    $phone = trim($data['customer_phone'] ?? '');
    if ($phone !== '') {
        db()->prepare('UPDATE pos_customers SET phone = :p WHERE id = :id')
            ->execute(['p' => $phone, 'id' => $customerId]);
    }

    $device = trim($data['device'] ?? '');
    if ($device === '') {
        $device = trim(($data['device_brand'] ?? '') . ' ' . ($data['device_model'] ?? ''));
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO pos_repair_jobs
             (job_no, customer_id, device_brand, device_model, imei_serial, issue_description,
              estimated_cost, advance_payment, final_cost, parts_cost, repair_profit, payment_status,
              status, received_by, technician_name, notes, repair_warranty_days, warranty_end_date)
             VALUES (:no,:c,:b,:m,:imei,:issue,:est,:adv,0,0,0,:ps,:st,:rb,:tech,:notes,0,NULL)
             RETURNING id'
        );
        $paymentStatus = $advance > 0 ? ($advance >= $estimated && $estimated > 0 ? 'paid' : 'partial') : 'unpaid';
        $stmt->execute([
            'no' => pos_next_number('JOB'),
            'c' => $customerId,
            'b' => '',
            'm' => $device,
            'imei' => trim($data['imei_serial'] ?? ''),
            'issue' => $issue,
            'est' => $estimated,
            'adv' => $advance,
            'ps' => $paymentStatus,
            'st' => 'Received',
            'rb' => $staffId,
            'tech' => '',
            'notes' => '',
        ]);
        $jobId = (int) $stmt->fetchColumn();
        if ($advance > 0) {
            $pdo->prepare(
                'INSERT INTO pos_repair_payments (repair_job_id, amount, payment_method, note, created_by)
                 VALUES (:j,:a,:m,:n,:s)'
            )->execute([
                'j' => $jobId,
                'a' => $advance,
                'm' => $data['payment_method'] ?? 'cash',
                'n' => 'Advance payment',
                's' => $staffId,
            ]);
        }
        $pdo->commit();
        pos_audit($staffId, 'repair_created', 'repair', $jobId, 'Repair job opened');
        return $jobId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function pos_get_repair_job(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.*, c.name AS customer_name, c.phone AS customer_phone,
                s.name AS received_by_name
         FROM pos_repair_jobs r
         JOIN pos_customers c ON c.id = r.customer_id
         LEFT JOIN pos_staff s ON s.id = r.received_by
         WHERE r.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $job = $stmt->fetch();
    if (!$job) {
        return null;
    }
    $pay = db()->prepare(
        'SELECT p.*, st.name AS staff_name FROM pos_repair_payments p
         LEFT JOIN pos_staff st ON st.id = p.created_by WHERE repair_job_id = :id ORDER BY p.id'
    );
    $pay->execute(['id' => $id]);
    $job['payments'] = $pay->fetchAll();
    $parts = db()->prepare('SELECT * FROM pos_repair_parts WHERE repair_job_id = :id ORDER BY id');
    $parts->execute(['id' => $id]);
    $job['expenses'] = $parts->fetchAll();
    $job['parts'] = $job['expenses'];
    return $job;
}

function pos_save_repair_job_simple(int $id, float $finalCost, string $notes, int $staffId): void
{
    $job = pos_get_repair_job($id);
    if (!$job) {
        throw new RuntimeException('Repair job not found.');
    }
    if ($job['status'] === 'Cancelled') {
        throw new RuntimeException('Cancelled jobs cannot be edited.');
    }
    if ($job['status'] === 'Delivered') {
        throw new RuntimeException('Delivered jobs cannot be edited. Open print receipt if needed.');
    }

    $finalCost = max(0, $finalCost);
    db()->prepare(
        'UPDATE pos_repair_jobs SET final_cost = :fc, notes = :notes WHERE id = :id'
    )->execute([
        'fc' => $finalCost,
        'notes' => trim($notes),
        'id' => $id,
    ]);
    pos_repair_sync_payment_status($id);
    pos_repair_sync_profit($id);
    pos_audit($staffId, 'repair_updated', 'repair', $id, 'Cost/notes saved');
}

function pos_mark_repair_completed(int $id, int $staffId): void
{
    $job = pos_get_repair_job($id);
    if (!$job) {
        throw new RuntimeException('Repair job not found.');
    }
    if ($job['status'] === 'Cancelled') {
        throw new RuntimeException('Job is cancelled.');
    }
    if ($job['status'] === 'Delivered') {
        throw new RuntimeException('Job is already delivered.');
    }
    if ((float) $job['final_cost'] <= 0) {
        throw new RuntimeException('Enter the final repair cost before marking completed.');
    }

    $completedAt = $job['completed_at'] ?: date('Y-m-d H:i:s');
    db()->prepare(
        'UPDATE pos_repair_jobs SET status = :st, completed_at = :ca WHERE id = :id'
    )->execute(['st' => 'Completed', 'ca' => $completedAt, 'id' => $id]);
    pos_repair_sync_payment_status($id);
    pos_repair_sync_profit($id);
    pos_audit($staffId, 'repair_completed', 'repair', $id, 'Ready for pickup');
}

function pos_mark_repair_delivered(
    int $id,
    int $staffId,
    float $receivedAmount,
    string $paymentMethod,
    bool $allowDueBalance,
    bool $isManager
): void {
    $job = pos_get_repair_job($id);
    if (!$job) {
        throw new RuntimeException('Repair job not found.');
    }
    if ($job['status'] === 'Cancelled') {
        throw new RuntimeException('Job is cancelled.');
    }
    if ($job['status'] === 'Delivered') {
        throw new RuntimeException('Job is already delivered.');
    }

    $balance = pos_repair_balance_due($job);
    if ($balance > 0) {
        if ($allowDueBalance) {
            if (!$isManager) {
                throw new RuntimeException('Only a manager can deliver with an outstanding balance.');
            }
        } else {
            if ($receivedAmount <= 0) {
                throw new RuntimeException('Enter the amount received from the customer.');
            }
            $methods = pos_repair_collect_methods();
            if (!isset($methods[$paymentMethod])) {
                throw new RuntimeException('Invalid payment method.');
            }
            pos_add_repair_payment($id, $receivedAmount, $paymentMethod, 'Balance on delivery', $staffId);
            $job = pos_get_repair_job($id);
            $balance = pos_repair_balance_due($job);
            if ($balance > 0.009) {
                throw new RuntimeException(
                    'Received amount is not enough. Balance remaining: ' . pos_format_money($balance)
                );
            }
        }
    }

    $deliveredAt = $job['delivered_at'] ?: date('Y-m-d H:i:s');
    db()->prepare(
        'UPDATE pos_repair_jobs SET status = :st, delivered_at = :da, payment_status = :ps WHERE id = :id'
    )->execute([
        'st' => 'Delivered',
        'da' => $deliveredAt,
        'ps' => 'paid',
        'id' => $id,
    ]);
    pos_repair_sync_profit($id);
    pos_audit($staffId, 'repair_delivered', 'repair', $id, 'Device collected');
}

function pos_cancel_repair_job(int $id, int $staffId): void
{
    $job = pos_get_repair_job($id);
    if (!$job) {
        throw new RuntimeException('Repair job not found.');
    }
    if ($job['status'] === 'Delivered') {
        throw new RuntimeException('Delivered jobs cannot be cancelled.');
    }
    db()->prepare('UPDATE pos_repair_jobs SET status = :st WHERE id = :id')
        ->execute(['st' => 'Cancelled', 'id' => $id]);
    pos_audit($staffId, 'repair_cancelled', 'repair', $id, 'Job cancelled');
}

function pos_add_repair_payment(int $jobId, float $amount, string $method, string $note, int $staffId): void
{
    $job = pos_get_repair_job($jobId);
    if (!$job) {
        throw new RuntimeException('Repair job not found.');
    }
    if ($amount <= 0) {
        throw new RuntimeException('Invalid amount.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO pos_repair_payments (repair_job_id, amount, payment_method, note, created_by)
             VALUES (:j,:a,:m,:n,:s)'
        )->execute(['j' => $jobId, 'a' => $amount, 'm' => $method, 'n' => $note, 's' => $staffId]);
        $pdo->commit();
        pos_repair_sync_payment_status($jobId);
        pos_audit($staffId, 'repair_payment', 'repair', $jobId, pos_format_money($amount));
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function pos_add_expense(string $date, string $category, string $description, float $amount, int $staffId): void
{
    if ($amount <= 0) {
        throw new RuntimeException('Amount must be greater than zero.');
    }
    if (!in_array($category, pos_expense_categories(), true)) {
        throw new RuntimeException('Invalid expense category.');
    }
    db()->prepare(
        'INSERT INTO pos_expenses (expense_date, category, description, amount, created_by)
         VALUES (:d,:c,:desc,:a,:s)'
    )->execute([
        'd' => $date,
        'c' => $category,
        'desc' => trim($description),
        'a' => $amount,
        's' => $staffId,
    ]);
    pos_audit($staffId, 'expense_added', 'expense', null, $category . ' ' . pos_format_money($amount));
}

function pos_dashboard_stats(): array
{
    $pdo = db();
    $today = date('Y-m-d');
    $sales = (float) $pdo->query(
        "SELECT COALESCE(SUM(total),0) FROM pos_invoices
         WHERE DATE(created_at) = CURRENT_DATE AND status = 'completed'"
    )->fetchColumn();
    $shopExpenses = (float) $pdo->query(
        'SELECT COALESCE(SUM(amount),0) FROM pos_expenses WHERE expense_date = CURRENT_DATE'
    )->fetchColumn();
    $costStmt = $pdo->query(
        "SELECT COALESCE(SUM((ii.cost_price_snapshot * ii.quantity) - ii.discount),0)
         FROM pos_invoice_items ii
         JOIN pos_invoices i ON i.id = ii.invoice_id
         WHERE DATE(i.created_at) = CURRENT_DATE AND i.status = 'completed'"
    );
    $productCost = (float) $costStmt->fetchColumn();
    $repairToday = pos_repair_profit_totals($today, $today);
    $repairMonth = pos_repair_profit_totals(date('Y-m-01'), $today);
    $profitEstimate = $sales - $productCost - $shopExpenses + $repairToday['profit'];
    $pendingRepairs = (int) $pdo->query(
        "SELECT COUNT(*) FROM pos_repair_jobs WHERE status NOT IN ('Delivered','Cancelled')"
    )->fetchColumn();
    $lowStock = $pdo->query(
        "SELECT i.name, COALESCE(NULLIF(c.description, ''), c.title) AS category_name,
                i.stock_quantity
         FROM items i LEFT JOIN categories c ON c.id = i.category_id
         WHERE i.is_active = TRUE AND i.is_phone = FALSE
           AND i.stock_quantity <= GREATEST(COALESCE(i.reorder_level, 5), 0)
         ORDER BY i.stock_quantity ASC LIMIT 10"
    )->fetchAll();
    $recentInvoices = $pdo->query(
        'SELECT id, invoice_no, total, payment_status, created_at FROM pos_invoices
         ORDER BY created_at DESC LIMIT 8'
    )->fetchAll();
    $recentRepairs = $pdo->query(
        'SELECT id, job_no, status, device_brand, device_model, created_at FROM pos_repair_jobs
         ORDER BY created_at DESC LIMIT 8'
    )->fetchAll();
    return [
        'today_sales' => $sales,
        'today_expenses' => $shopExpenses,
        'today_profit' => $profitEstimate,
        'today_repair_revenue' => $repairToday['revenue'],
        'today_repair_expense' => $repairToday['expense'],
        'today_repair_profit' => $repairToday['profit'],
        'month_repair_revenue' => $repairMonth['revenue'],
        'month_repair_expense' => $repairMonth['expense'],
        'month_repair_profit' => $repairMonth['profit'],
        'pending_repairs' => $pendingRepairs,
        'low_stock' => $lowStock,
        'recent_invoices' => $recentInvoices,
        'recent_repairs' => $recentRepairs,
    ];
}

function pos_date_range(string $preset): array
{
    $today = date('Y-m-d');
    return match ($preset) {
        'yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
        'week' => [date('Y-m-d', strtotime('monday this week')), $today],
        'month' => [date('Y-m-01'), $today],
        default => [$today, $today],
    };
}

function pos_export_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function pos_cancel_invoice(int $id, int $staffId, bool $isManager): void
{
    if (!$isManager) {
        throw new RuntimeException('Only managers can cancel invoices.');
    }
    $inv = pos_get_invoice($id);
    if (!$inv || $inv['status'] === 'cancelled') {
        throw new RuntimeException('Invoice not found.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($inv['items'] as $item) {
            $restock = (int) $item['quantity'] - (int) $item['returned_quantity'];
            if ($restock > 0 && $item['product_id']) {
                $pdo->prepare(
                    "UPDATE items SET stock_quantity = stock_quantity + :q, stock_status = 'in_stock', is_active = TRUE WHERE id = :id"
                )->execute(['q' => $restock, 'id' => $item['product_id']]);
            }
        }
        $pdo->prepare("UPDATE pos_invoices SET status = 'cancelled' WHERE id = :id")->execute(['id' => $id]);
        $pdo->commit();
        pos_audit($staffId, 'invoice_cancelled', 'invoice', $id, '');
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function pos_search_warranty(string $q): array
{
    $q = trim($q);
    if ($q === '') {
        return ['invoices' => [], 'repairs' => []];
    }
    $like = '%' . $q . '%';
    $inv = db()->prepare(
        "SELECT i.id, i.invoice_no, i.warranty_end_date, c.name, c.phone
         FROM pos_invoices i
         LEFT JOIN pos_customers c ON c.id = i.customer_id
         WHERE i.warranty_period_days > 0 AND (
           i.invoice_no ILIKE :q OR c.phone ILIKE :q OR c.name ILIKE :q
         ) ORDER BY i.created_at DESC LIMIT 20"
    );
    $inv->execute(['q' => $like]);
    $rep = db()->prepare(
        "SELECT r.id, r.job_no, r.warranty_end_date, c.name, c.phone
         FROM pos_repair_jobs r
         JOIN pos_customers c ON c.id = r.customer_id
         WHERE r.repair_warranty_days > 0 AND (
           r.job_no ILIKE :q OR r.imei_serial ILIKE :q OR c.phone ILIKE :q
         ) ORDER BY r.created_at DESC LIMIT 20"
    );
    $rep->execute(['q' => $like]);
    return ['invoices' => $inv->fetchAll(), 'repairs' => $rep->fetchAll()];
}
