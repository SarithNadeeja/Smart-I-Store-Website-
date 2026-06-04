<?php

require_once __DIR__ . '/database.php';

function pos_get_items_for_select(): array
{
    if (!db_available()) {
        return [];
    }

    $sql = "
        SELECT i.id, i.name, i.price, i.stock_quantity,
               b.name AS brand_name,
               m.name AS model_name
        FROM items i
        LEFT JOIN phone_brands b ON b.id = i.brand_id
        LEFT JOIN product_models m ON m.id = i.model_id
        WHERE i.is_active = TRUE
        ORDER BY i.name ASC, i.id ASC
    ";

    return db()->query($sql)->fetchAll();
}

function pos_search_customers(string $query, int $limit = 15): array
{
    if (!db_available()) {
        return [];
    }

    $limit = max(1, min(30, $limit));
    $query = trim($query);

    $sql = 'SELECT id, name, phone, email FROM pos_customers WHERE is_active = TRUE';
    $params = [];

    if ($query !== '') {
        $sql .= ' AND (name ILIKE :q OR phone ILIKE :q OR email ILIKE :q)';
        $params['q'] = '%' . $query . '%';
    }

    $sql .= ' ORDER BY name ASC LIMIT :lim';
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function pos_search_suppliers(string $query, int $limit = 15): array
{
    if (!db_available()) {
        return [];
    }

    $limit = max(1, min(30, $limit));
    $query = trim($query);

    $sql = 'SELECT id, company_name, contact_name, phone FROM pos_suppliers WHERE is_active = TRUE';
    $params = [];

    if ($query !== '') {
        $sql .= ' AND (company_name ILIKE :q OR contact_name ILIKE :q OR phone ILIKE :q)';
        $params['q'] = '%' . $query . '%';
    }

    $sql .= ' ORDER BY company_name ASC LIMIT :lim';
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function pos_search_labels(string $query, string $direction, ?string $incomeKind = null, int $limit = 12): array
{
    if (!db_available()) {
        return [];
    }

    $direction = $direction === 'expense' ? 'expense' : 'income';
    $limit = max(1, min(25, $limit));
    $query = trim($query);

    $sql = "
        SELECT category_label AS label, COUNT(*) AS used_count
        FROM pos_ledger_entries
        WHERE direction = :dir AND category_label <> ''
    ";
    $params = ['dir' => $direction];

    if ($direction === 'income' && $incomeKind === 'other') {
        $sql .= " AND income_kind = 'other'";
    } elseif ($direction === 'expense') {
        $sql .= " AND (income_kind IS NULL OR income_kind <> 'sale')";
    }

    if ($query !== '') {
        $sql .= ' AND category_label ILIKE :q';
        $params['q'] = '%' . $query . '%';
    }

    $sql .= ' GROUP BY category_label ORDER BY used_count DESC, category_label ASC LIMIT :lim';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return array_column($stmt->fetchAll(), 'label');
}

function pos_add_ledger_entry(
    string $entryDate,
    string $direction,
    float $amount,
    ?string $incomeKind = null,
    ?string $saleChannel = null,
    ?int $itemId = null,
    int $saleQuantity = 1,
    string $categoryLabel = '',
    ?int $customerId = null,
    ?int $supplierId = null,
    string $notes = '',
    bool $reduceStock = false
): int {
    $pdo = db();
    $direction = $direction === 'expense' ? 'expense' : 'income';
    $amount = max(0, round($amount, 2));
    $categoryLabel = trim($categoryLabel);
    $notes = trim($notes);
    $saleQuantity = max(1, $saleQuantity);

    if ($amount <= 0) {
        throw new RuntimeException('Amount must be greater than zero.');
    }

    $date = DateTime::createFromFormat('Y-m-d', $entryDate);
    if (!$date || $date->format('Y-m-d') !== $entryDate) {
        throw new RuntimeException('Invalid entry date.');
    }

    $saleChannel = in_array($saleChannel, ['physical', 'online'], true) ? $saleChannel : null;

    if ($direction === 'income') {
        $incomeKind = $incomeKind === 'sale' ? 'sale' : 'other';
        if ($incomeKind === 'sale') {
            if ($itemId === null || $itemId <= 0) {
                throw new RuntimeException('Select a product for a sale.');
            }
            if ($saleChannel === null) {
                throw new RuntimeException('Select whether this sale was in-store or online.');
            }
            $itemStmt = $pdo->prepare('SELECT id, name FROM items WHERE id = :id AND is_active = TRUE');
            $itemStmt->execute(['id' => $itemId]);
            $itemRow = $itemStmt->fetch();
            if (!$itemRow) {
                throw new RuntimeException('Selected product was not found.');
            }
            if ($categoryLabel === '') {
                $categoryLabel = (string) $itemRow['name'];
            }
            if ($customerId !== null && $customerId > 0) {
                pos_require_customer($customerId);
            } else {
                $customerId = null;
            }
            $supplierId = null;
        } else {
            $itemId = null;
            $saleQuantity = 1;
            $saleChannel = null;
            $customerId = null;
            $supplierId = null;
            if ($categoryLabel === '') {
                throw new RuntimeException('Describe what this income is for.');
            }
        }
    } else {
        $incomeKind = null;
        $itemId = null;
        $saleQuantity = 1;
        $saleChannel = null;
        $customerId = null;
        if ($supplierId !== null && $supplierId > 0) {
            pos_require_supplier($supplierId);
        } else {
            $supplierId = null;
        }
        if ($categoryLabel === '') {
            throw new RuntimeException('Describe what this expense is for.');
        }
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO pos_ledger_entries
             (entry_date, direction, income_kind, sale_channel, amount, item_id, sale_quantity,
              category_label, customer_id, supplier_id, notes)
             VALUES (:d, :dir, :ik, :ch, :amt, :item, :sq, :lbl, :cust, :sup, :notes)
             RETURNING id'
        );
        $stmt->execute([
            'd' => $entryDate,
            'dir' => $direction,
            'ik' => $incomeKind,
            'ch' => $saleChannel,
            'amt' => $amount,
            'item' => $itemId,
            'sq' => $saleQuantity,
            'lbl' => $categoryLabel,
            'cust' => $customerId,
            'sup' => $supplierId,
            'notes' => $notes,
        ]);
        $entryId = (int) $stmt->fetchColumn();

        if ($reduceStock && $direction === 'income' && $incomeKind === 'sale' && $itemId) {
            $upd = $pdo->prepare(
                'UPDATE items SET stock_quantity = GREATEST(0, stock_quantity - :q) WHERE id = :id'
            );
            $upd->execute(['q' => $saleQuantity, 'id' => $itemId]);
        }

        $pdo->commit();
        return $entryId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function pos_delete_ledger_entry(int $id): void
{
    if ($id <= 0) {
        return;
    }
    db()->prepare('DELETE FROM pos_ledger_entries WHERE id = :id')->execute(['id' => $id]);
}

function pos_get_ledger_recent(int $limit = 50): array
{
    if (!db_available()) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $sql = "
        SELECT e.*, i.name AS item_name,
               c.name AS customer_name, c.phone AS customer_phone,
               s.company_name AS supplier_name
        FROM pos_ledger_entries e
        LEFT JOIN items i ON i.id = e.item_id
        LEFT JOIN pos_customers c ON c.id = e.customer_id
        LEFT JOIN pos_suppliers s ON s.id = e.supplier_id
        ORDER BY e.entry_date DESC, e.id DESC
        LIMIT :lim
    ";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function pos_get_report(string $dateFrom, string $dateTo): array
{
    if (!db_available()) {
        return [];
    }

    $from = DateTime::createFromFormat('Y-m-d', $dateFrom);
    $to = DateTime::createFromFormat('Y-m-d', $dateTo);
    if (!$from || !$to || $from->format('Y-m-d') !== $dateFrom || $to->format('Y-m-d') !== $dateTo) {
        throw new RuntimeException('Invalid date range.');
    }
    if ($from > $to) {
        throw new RuntimeException('Start date must be on or before end date.');
    }

    $pdo = db();
    $params = ['from' => $dateFrom, 'to' => $dateTo];

    $totalsStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN direction = 'income' THEN amount ELSE 0 END), 0) AS total_income,
            COALESCE(SUM(CASE WHEN direction = 'expense' THEN amount ELSE 0 END), 0) AS total_expense,
            COALESCE(SUM(CASE WHEN direction = 'income' AND income_kind = 'sale' THEN amount ELSE 0 END), 0) AS sales_income,
            COALESCE(SUM(CASE WHEN direction = 'income' AND income_kind = 'other' THEN amount ELSE 0 END), 0) AS other_income,
            COALESCE(SUM(CASE WHEN direction = 'income' AND income_kind = 'sale' AND sale_channel = 'physical' THEN amount ELSE 0 END), 0) AS physical_sales,
            COALESCE(SUM(CASE WHEN direction = 'income' AND income_kind = 'sale' AND sale_channel = 'online' THEN amount ELSE 0 END), 0) AS online_sales,
            COUNT(*) FILTER (WHERE direction = 'income') AS income_count,
            COUNT(*) FILTER (WHERE direction = 'expense') AS expense_count
        FROM pos_ledger_entries
        WHERE entry_date BETWEEN :from AND :to
    ");
    $totalsStmt->execute($params);
    $totals = $totalsStmt->fetch() ?: [];

    $totalIncome = (float) ($totals['total_income'] ?? 0);
    $totalExpense = (float) ($totals['total_expense'] ?? 0);

    return [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'total_income' => $totalIncome,
        'total_expense' => $totalExpense,
        'net_profit' => $totalIncome - $totalExpense,
        'sales_income' => (float) ($totals['sales_income'] ?? 0),
        'other_income' => (float) ($totals['other_income'] ?? 0),
        'physical_sales' => (float) ($totals['physical_sales'] ?? 0),
        'online_sales' => (float) ($totals['online_sales'] ?? 0),
        'income_count' => (int) ($totals['income_count'] ?? 0),
        'expense_count' => (int) ($totals['expense_count'] ?? 0),
    ];
}

function pos_format_entry_type(array $row): string
{
    if (($row['direction'] ?? '') === 'expense') {
        return 'Expense';
    }
    if (($row['income_kind'] ?? '') === 'sale') {
        $ch = ($row['sale_channel'] ?? '') === 'online' ? 'Online sale' : 'In-store sale';
        return $ch;
    }
    return 'Other income';
}

function pos_format_channel(?string $channel): string
{
    return match ($channel) {
        'physical' => 'In-store',
        'online' => 'Online',
        default => '—',
    };
}

function pos_dashboard_stats(): array
{
    if (!db_available()) {
        return [
            'customers' => 0,
            'suppliers' => 0,
            'today_income' => 0,
            'today_expense' => 0,
            'today_profit' => 0,
            'month_income' => 0,
            'month_expense' => 0,
            'month_profit' => 0,
            'physical_month' => 0,
            'online_month' => 0,
        ];
    }

    $pdo = db();
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');

    $customers = (int) $pdo->query('SELECT COUNT(*) FROM pos_customers WHERE is_active = TRUE')->fetchColumn();
    $suppliers = (int) $pdo->query('SELECT COUNT(*) FROM pos_suppliers WHERE is_active = TRUE')->fetchColumn();

    $todayStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN direction = 'income' THEN amount ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN direction = 'expense' THEN amount ELSE 0 END), 0) AS expense
        FROM pos_ledger_entries WHERE entry_date = :d
    ");
    $todayStmt->execute(['d' => $today]);
    $todayRow = $todayStmt->fetch() ?: [];

    $monthStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN direction = 'income' THEN amount ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN direction = 'expense' THEN amount ELSE 0 END), 0) AS expense,
            COALESCE(SUM(CASE WHEN income_kind = 'sale' AND sale_channel = 'physical' THEN amount ELSE 0 END), 0) AS physical,
            COALESCE(SUM(CASE WHEN income_kind = 'sale' AND sale_channel = 'online' THEN amount ELSE 0 END), 0) AS online
        FROM pos_ledger_entries WHERE entry_date BETWEEN :from AND :to
    ");
    $monthStmt->execute(['from' => $monthStart, 'to' => $today]);
    $monthRow = $monthStmt->fetch() ?: [];

    $todayIncome = (float) ($todayRow['income'] ?? 0);
    $todayExpense = (float) ($todayRow['expense'] ?? 0);
    $monthIncome = (float) ($monthRow['income'] ?? 0);
    $monthExpense = (float) ($monthRow['expense'] ?? 0);

    return [
        'customers' => $customers,
        'suppliers' => $suppliers,
        'today_income' => $todayIncome,
        'today_expense' => $todayExpense,
        'today_profit' => $todayIncome - $todayExpense,
        'month_income' => $monthIncome,
        'month_expense' => $monthExpense,
        'month_profit' => $monthIncome - $monthExpense,
        'physical_month' => (float) ($monthRow['physical'] ?? 0),
        'online_month' => (float) ($monthRow['online'] ?? 0),
    ];
}

function pos_require_customer(int $id): void
{
    $stmt = db()->prepare('SELECT id FROM pos_customers WHERE id = :id AND is_active = TRUE');
    $stmt->execute(['id' => $id]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Customer not found.');
    }
}

function pos_require_supplier(int $id): void
{
    $stmt = db()->prepare('SELECT id FROM pos_suppliers WHERE id = :id AND is_active = TRUE');
    $stmt->execute(['id' => $id]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Supplier not found.');
    }
}

function pos_save_customer(
    int $id,
    string $name,
    string $phone,
    string $email,
    string $address,
    string $notes,
    bool $isActive
): int {
    $name = trim($name);
    if ($name === '') {
        throw new RuntimeException('Customer name is required.');
    }

    $pdo = db();
    $data = [
        'name' => $name,
        'phone' => trim($phone),
        'email' => trim($email),
        'address' => trim($address),
        'notes' => trim($notes),
        'active' => $isActive,
    ];

    if ($id > 0) {
        $data['id'] = $id;
        $stmt = $pdo->prepare(
            'UPDATE pos_customers SET name = :name, phone = :phone, email = :email,
             address = :address, notes = :notes, is_active = :active WHERE id = :id'
        );
        $stmt->execute($data);
        return $id;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO pos_customers (name, phone, email, address, notes, is_active)
         VALUES (:name, :phone, :email, :address, :notes, :active) RETURNING id'
    );
    $stmt->execute($data);
    return (int) $stmt->fetchColumn();
}

function pos_delete_customer(int $id): void
{
    if ($id <= 0) {
        return;
    }
    db()->prepare('DELETE FROM pos_customers WHERE id = :id')->execute(['id' => $id]);
}

function pos_list_customers(): array
{
    if (!db_available()) {
        return [];
    }
    return db()->query(
        'SELECT * FROM pos_customers ORDER BY is_active DESC, name ASC'
    )->fetchAll();
}

function pos_get_customer(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM pos_customers WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pos_save_supplier(
    int $id,
    string $companyName,
    string $contactName,
    string $phone,
    string $email,
    string $address,
    string $notes,
    bool $isActive
): int {
    $companyName = trim($companyName);
    if ($companyName === '') {
        throw new RuntimeException('Supplier / company name is required.');
    }

    $pdo = db();
    $data = [
        'company' => $companyName,
        'contact' => trim($contactName),
        'phone' => trim($phone),
        'email' => trim($email),
        'address' => trim($address),
        'notes' => trim($notes),
        'active' => $isActive,
    ];

    if ($id > 0) {
        $data['id'] = $id;
        $stmt = $pdo->prepare(
            'UPDATE pos_suppliers SET company_name = :company, contact_name = :contact,
             phone = :phone, email = :email, address = :address, notes = :notes,
             is_active = :active WHERE id = :id'
        );
        $stmt->execute($data);
        return $id;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO pos_suppliers (company_name, contact_name, phone, email, address, notes, is_active)
         VALUES (:company, :contact, :phone, :email, :address, :notes, :active) RETURNING id'
    );
    $stmt->execute($data);
    return (int) $stmt->fetchColumn();
}

function pos_delete_supplier(int $id): void
{
    if ($id <= 0) {
        return;
    }
    db()->prepare('DELETE FROM pos_suppliers WHERE id = :id')->execute(['id' => $id]);
}

function pos_list_suppliers(): array
{
    if (!db_available()) {
        return [];
    }
    return db()->query(
        'SELECT * FROM pos_suppliers ORDER BY is_active DESC, company_name ASC'
    )->fetchAll();
}

function pos_get_supplier(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM pos_suppliers WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pos_update_user_credentials(int $userId, string $username, string $password, bool $clearMustChange = true): void
{
    $sql = 'UPDATE pos_users SET username = :u, password_hash = :h';
    if ($clearMustChange) {
        $sql .= ', must_change_credentials = FALSE';
    }
    $sql .= ' WHERE id = :id';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        'u' => $username,
        'h' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);

    if ($userId === (int) ($_SESSION['pos_user_id'] ?? 0)) {
        $_SESSION['pos_username'] = $username;
    }
}
