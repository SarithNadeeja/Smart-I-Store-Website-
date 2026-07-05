<?php

/**
 * PostgreSQL connection, schema migration, default admin seed.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    db_migrate($pdo);
    db_seed_admin($pdo);
    db_seed_pos($pdo);

    return $pdo;
}

/**
 * PostgreSQL-safe boolean for PDO execute() arrays.
 * PHP false alone is often bound as "" which causes SQLSTATE[22P02].
 */
function db_bool(mixed $value): string
{
    return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
}

function db_migrate(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(64) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            must_change_credentials BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS categories (
            id SERIAL PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            icon VARCHAR(40) NOT NULL DEFAULT 'smartphone',
            count_label VARCHAR(60) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS phone_brands (
            id SERIAL PRIMARY KEY,
            name VARCHAR(80) NOT NULL UNIQUE,
            sort_order INT NOT NULL DEFAULT 0,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS items (
            id SERIAL PRIMARY KEY,
            category_id INT REFERENCES categories(id) ON DELETE SET NULL,
            brand_id INT REFERENCES phone_brands(id) ON DELETE SET NULL,
            name VARCHAR(200) NOT NULL,
            price NUMERIC(12, 2) NOT NULL DEFAULT 0,
            tag VARCHAR(60) NOT NULL DEFAULT '',
            color VARCHAR(20) NOT NULL DEFAULT '#333333',
            is_phone BOOLEAN NOT NULL DEFAULT FALSE,
            is_featured BOOLEAN NOT NULL DEFAULT FALSE,
            main_image VARCHAR(255) NOT NULL DEFAULT '',
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            sort_order INT NOT NULL DEFAULT 0,
            stock_status VARCHAR(20) NOT NULL DEFAULT 'in_stock',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS item_images (
            id SERIAL PRIMARY KEY,
            item_id INT NOT NULL REFERENCES items(id) ON DELETE CASCADE,
            image_path VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_items_category ON items(category_id);
        CREATE INDEX IF NOT EXISTS idx_items_brand ON items(brand_id);
        CREATE INDEX IF NOT EXISTS idx_items_active ON items(is_active);
        CREATE INDEX IF NOT EXISTS idx_item_images_item ON item_images(item_id);

        CREATE TABLE IF NOT EXISTS product_models (
            id SERIAL PRIMARY KEY,
            brand_id INT NOT NULL REFERENCES phone_brands(id) ON DELETE CASCADE,
            name VARCHAR(120) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (brand_id, name)
        );

        CREATE INDEX IF NOT EXISTS idx_product_models_brand ON product_models(brand_id);

        CREATE TABLE IF NOT EXISTS customer_reviews (
            id SERIAL PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            comment TEXT NOT NULL,
            rating SMALLINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_customer_reviews_list
            ON customer_reviews (is_active, created_at DESC);
    ");

    db_migrate_upgrade($pdo);
}

function db_migrate_upgrade(PDO $pdo): void
{
    $hasModelCol = $pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'items' AND column_name = 'model_id'"
    )->fetchColumn();

    if (!$hasModelCol) {
        $pdo->exec(
            'ALTER TABLE items ADD COLUMN model_id INT REFERENCES product_models(id) ON DELETE SET NULL'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_items_model ON items(model_id)');
    }

    $hasStockCol = $pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'items' AND column_name = 'stock_status'"
    )->fetchColumn();

    if (!$hasStockCol) {
        $pdo->exec(
            "ALTER TABLE items ADD COLUMN stock_status VARCHAR(20) NOT NULL DEFAULT 'in_stock'"
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_items_stock ON items(stock_status)');
    }

    $pdo->exec(
        "DELETE FROM customer_reviews WHERE name IN ('Sarah Mitchell', 'James Chen', 'Emily Rodriguez')"
    );

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS item_storage_variants (
            id SERIAL PRIMARY KEY,
            item_id INT NOT NULL REFERENCES items(id) ON DELETE CASCADE,
            ram VARCHAR(32) NOT NULL DEFAULT '',
            rom VARCHAR(32) NOT NULL DEFAULT '',
            price NUMERIC(12, 2),
            stock_status VARCHAR(20) NOT NULL DEFAULT 'in_stock',
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS item_system_specs (
            id SERIAL PRIMARY KEY,
            item_id INT NOT NULL REFERENCES items(id) ON DELETE CASCADE,
            spec_text TEXT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_item_storage_variants_item ON item_storage_variants(item_id);
        CREATE INDEX IF NOT EXISTS idx_item_system_specs_item ON item_system_specs(item_id);
    ");

    $hasVariantCost = $pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'item_storage_variants' AND column_name = 'cost_price'"
    )->fetchColumn();
    if (!$hasVariantCost) {
        $pdo->exec(
            'ALTER TABLE item_storage_variants ADD COLUMN cost_price NUMERIC(12, 2) NOT NULL DEFAULT 0'
        );
    }

    $hasSalePrice = $pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'items' AND column_name = 'sale_price'"
    )->fetchColumn();
    if (!$hasSalePrice) {
        $pdo->exec('ALTER TABLE items ADD COLUMN sale_price NUMERIC(12, 2)');
    }

    $hasQtyCol = $pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'items' AND column_name = 'stock_quantity'"
    )->fetchColumn();

    if (!$hasQtyCol) {
        $pdo->exec(
            'ALTER TABLE items ADD COLUMN stock_quantity INT NOT NULL DEFAULT 0 CHECK (stock_quantity >= 0)'
        );
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sales_ledger_entries (
            id SERIAL PRIMARY KEY,
            entry_date DATE NOT NULL DEFAULT CURRENT_DATE,
            direction VARCHAR(10) NOT NULL CHECK (direction IN ('income', 'expense')),
            income_kind VARCHAR(10) NULL CHECK (income_kind IN ('sale', 'other')),
            amount NUMERIC(12, 2) NOT NULL CHECK (amount >= 0),
            item_id INT REFERENCES items(id) ON DELETE SET NULL,
            sale_quantity INT NOT NULL DEFAULT 1 CHECK (sale_quantity >= 1),
            category_label VARCHAR(200) NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_sales_ledger_date ON sales_ledger_entries(entry_date);
        CREATE INDEX IF NOT EXISTS idx_sales_ledger_direction ON sales_ledger_entries(direction);
        CREATE INDEX IF NOT EXISTS idx_sales_ledger_label ON sales_ledger_entries(category_label);
        CREATE INDEX IF NOT EXISTS idx_sales_ledger_item ON sales_ledger_entries(item_id);
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pos_users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(64) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            must_change_credentials BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_customers (
            id SERIAL PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            phone VARCHAR(40) NOT NULL DEFAULT '',
            email VARCHAR(120) NOT NULL DEFAULT '',
            address TEXT NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT '',
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_suppliers (
            id SERIAL PRIMARY KEY,
            company_name VARCHAR(160) NOT NULL,
            contact_name VARCHAR(120) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            email VARCHAR(120) NOT NULL DEFAULT '',
            address TEXT NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT '',
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_ledger_entries (
            id SERIAL PRIMARY KEY,
            entry_date DATE NOT NULL DEFAULT CURRENT_DATE,
            direction VARCHAR(10) NOT NULL CHECK (direction IN ('income', 'expense')),
            income_kind VARCHAR(10) NULL CHECK (income_kind IN ('sale', 'other')),
            sale_channel VARCHAR(10) NULL CHECK (sale_channel IN ('physical', 'online')),
            amount NUMERIC(12, 2) NOT NULL CHECK (amount >= 0),
            item_id INT REFERENCES items(id) ON DELETE SET NULL,
            sale_quantity INT NOT NULL DEFAULT 1 CHECK (sale_quantity >= 1),
            category_label VARCHAR(200) NOT NULL DEFAULT '',
            customer_id INT REFERENCES pos_customers(id) ON DELETE SET NULL,
            supplier_id INT REFERENCES pos_suppliers(id) ON DELETE SET NULL,
            notes TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_pos_customers_name ON pos_customers(name);
        CREATE INDEX IF NOT EXISTS idx_pos_customers_phone ON pos_customers(phone);
        CREATE INDEX IF NOT EXISTS idx_pos_suppliers_company ON pos_suppliers(company_name);
        CREATE INDEX IF NOT EXISTS idx_pos_ledger_date ON pos_ledger_entries(entry_date);
        CREATE INDEX IF NOT EXISTS idx_pos_ledger_direction ON pos_ledger_entries(direction);
        CREATE INDEX IF NOT EXISTS idx_pos_ledger_channel ON pos_ledger_entries(sale_channel);
        CREATE INDEX IF NOT EXISTS idx_pos_ledger_customer ON pos_ledger_entries(customer_id);
        CREATE INDEX IF NOT EXISTS idx_pos_ledger_supplier ON pos_ledger_entries(supplier_id);
    ");

    db_migrate_pos_cloud($pdo);
    db_migrate_preowned($pdo);
    db_migrate_model_categories($pdo);
    db_migrate_site_advertisements($pdo);
}

function db_migrate_site_advertisements(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_advertisements (
            id SERIAL PRIMARY KEY,
            title VARCHAR(200) NOT NULL DEFAULT '',
            image_path VARCHAR(255) NOT NULL DEFAULT '',
            item_id INT REFERENCES items(id) ON DELETE SET NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_site_ads_active ON site_advertisements (is_active, sort_order, id);
    ");

    $pdo->exec('ALTER TABLE site_advertisements ALTER COLUMN item_id DROP NOT NULL');
}

/** Scope product models to a category so e.g. watch models don't show for phones. */
function db_migrate_model_categories(PDO $pdo): void
{
    $exists = $pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'product_models' AND column_name = 'category_id'"
    )->fetchColumn();

    if (!$exists) {
        $pdo->exec(
            'ALTER TABLE product_models ADD COLUMN category_id INT NULL REFERENCES categories(id) ON DELETE SET NULL'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_product_models_category ON product_models(category_id)');
    }
}

function db_migrate_preowned(PDO $pdo): void
{
    $cols = [
        'is_preowned' => 'BOOLEAN NOT NULL DEFAULT FALSE',
        'preowned_condition' => "VARCHAR(32) NOT NULL DEFAULT ''",
        'battery_health' => 'SMALLINT NULL CHECK (battery_health IS NULL OR (battery_health >= 0 AND battery_health <= 100))',
    ];
    foreach ($cols as $col => $def) {
        $exists = $pdo->query(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'items' AND column_name = " . $pdo->quote($col)
        )->fetchColumn();
        if (!$exists) {
            $pdo->exec("ALTER TABLE items ADD COLUMN {$col} {$def}");
        }
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_items_preowned ON items(is_preowned) WHERE is_preowned = TRUE');
}

function db_migrate_pos_cloud(PDO $pdo): void
{
    $itemCols = [
        'cost_price' => 'NUMERIC(12, 2) NOT NULL DEFAULT 0',
        'reorder_level' => 'INT NOT NULL DEFAULT 5',
        // Trade fields from the client's manual Excel sheet
        'product_code' => "VARCHAR(64) NOT NULL DEFAULT ''",
        'unit' => "VARCHAR(32) NOT NULL DEFAULT ''",
        'wholesale_price' => 'NUMERIC(12, 2) NULL',
        'min_price' => 'NUMERIC(12, 2) NULL',
        'note' => "TEXT NOT NULL DEFAULT ''",
    ];
    foreach ($itemCols as $col => $def) {
        $exists = $pdo->query(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'items' AND column_name = " . $pdo->quote($col)
        )->fetchColumn();
        if (!$exists) {
            $pdo->exec("ALTER TABLE items ADD COLUMN {$col} {$def}");
        }
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_items_product_code ON items(product_code)');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pos_staff (
            id SERIAL PRIMARY KEY,
            name VARCHAR(120) NOT NULL DEFAULT '',
            username VARCHAR(64) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'cashier'
                CHECK (role IN ('cashier', 'manager')),
            status VARCHAR(20) NOT NULL DEFAULT 'active'
                CHECK (status IN ('active', 'inactive')),
            must_change_credentials BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_invoices (
            id SERIAL PRIMARY KEY,
            invoice_no VARCHAR(32) NOT NULL UNIQUE,
            customer_id INT REFERENCES pos_customers(id) ON DELETE SET NULL,
            cashier_id INT NOT NULL REFERENCES pos_staff(id),
            subtotal NUMERIC(12, 2) NOT NULL DEFAULT 0,
            discount NUMERIC(12, 2) NOT NULL DEFAULT 0,
            total NUMERIC(12, 2) NOT NULL DEFAULT 0,
            paid_amount NUMERIC(12, 2) NOT NULL DEFAULT 0,
            balance NUMERIC(12, 2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
            payment_status VARCHAR(20) NOT NULL DEFAULT 'paid'
                CHECK (payment_status IN ('paid', 'partial', 'unpaid')),
            status VARCHAR(20) NOT NULL DEFAULT 'completed'
                CHECK (status IN ('completed', 'cancelled')),
            warranty_period_days INT NOT NULL DEFAULT 0,
            warranty_start_date DATE,
            warranty_end_date DATE,
            warranty_note TEXT NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_invoice_items (
            id SERIAL PRIMARY KEY,
            invoice_id INT NOT NULL REFERENCES pos_invoices(id) ON DELETE CASCADE,
            product_id INT REFERENCES items(id) ON DELETE SET NULL,
            product_name_snapshot VARCHAR(200) NOT NULL,
            unit_price NUMERIC(12, 2) NOT NULL DEFAULT 0,
            cost_price_snapshot NUMERIC(12, 2) NOT NULL DEFAULT 0,
            quantity INT NOT NULL DEFAULT 1 CHECK (quantity >= 1),
            discount NUMERIC(12, 2) NOT NULL DEFAULT 0,
            line_total NUMERIC(12, 2) NOT NULL DEFAULT 0,
            returned_quantity INT NOT NULL DEFAULT 0 CHECK (returned_quantity >= 0)
        );

        CREATE TABLE IF NOT EXISTS pos_invoice_payments (
            id SERIAL PRIMARY KEY,
            invoice_id INT NOT NULL REFERENCES pos_invoices(id) ON DELETE CASCADE,
            amount NUMERIC(12, 2) NOT NULL CHECK (amount > 0),
            payment_method VARCHAR(30) NOT NULL,
            note TEXT NOT NULL DEFAULT '',
            created_by INT NOT NULL REFERENCES pos_staff(id),
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_returns (
            id SERIAL PRIMARY KEY,
            return_no VARCHAR(32) NOT NULL UNIQUE,
            invoice_id INT NOT NULL REFERENCES pos_invoices(id),
            invoice_item_id INT NOT NULL REFERENCES pos_invoice_items(id),
            product_id INT REFERENCES items(id) ON DELETE SET NULL,
            quantity INT NOT NULL DEFAULT 1 CHECK (quantity >= 1),
            reason TEXT NOT NULL DEFAULT '',
            refund_amount NUMERIC(12, 2) NOT NULL DEFAULT 0,
            created_by INT NOT NULL REFERENCES pos_staff(id),
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_repair_jobs (
            id SERIAL PRIMARY KEY,
            job_no VARCHAR(32) NOT NULL UNIQUE,
            customer_id INT NOT NULL REFERENCES pos_customers(id),
            device_brand VARCHAR(80) NOT NULL DEFAULT '',
            device_model VARCHAR(120) NOT NULL DEFAULT '',
            imei_serial VARCHAR(80) NOT NULL DEFAULT '',
            issue_description TEXT NOT NULL,
            estimated_cost NUMERIC(12, 2) NOT NULL DEFAULT 0,
            advance_payment NUMERIC(12, 2) NOT NULL DEFAULT 0,
            final_cost NUMERIC(12, 2) NOT NULL DEFAULT 0,
            parts_cost NUMERIC(12, 2) NOT NULL DEFAULT 0,
            payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid'
                CHECK (payment_status IN ('paid', 'partial', 'unpaid')),
            status VARCHAR(30) NOT NULL DEFAULT 'Received',
            received_by INT NOT NULL REFERENCES pos_staff(id),
            technician_name VARCHAR(120) NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT '',
            repair_warranty_days INT NOT NULL DEFAULT 0,
            warranty_end_date DATE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP,
            delivered_at TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_repair_payments (
            id SERIAL PRIMARY KEY,
            repair_job_id INT NOT NULL REFERENCES pos_repair_jobs(id) ON DELETE CASCADE,
            amount NUMERIC(12, 2) NOT NULL CHECK (amount > 0),
            payment_method VARCHAR(30) NOT NULL,
            note TEXT NOT NULL DEFAULT '',
            created_by INT NOT NULL REFERENCES pos_staff(id),
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_repair_parts (
            id SERIAL PRIMARY KEY,
            repair_job_id INT NOT NULL REFERENCES pos_repair_jobs(id) ON DELETE CASCADE,
            part_description VARCHAR(200) NOT NULL,
            cost NUMERIC(12, 2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_expenses (
            id SERIAL PRIMARY KEY,
            expense_date DATE NOT NULL DEFAULT CURRENT_DATE,
            category VARCHAR(40) NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            amount NUMERIC(12, 2) NOT NULL CHECK (amount > 0),
            created_by INT NOT NULL REFERENCES pos_staff(id),
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_cash_drawer_days (
            id SERIAL PRIMARY KEY,
            business_date DATE NOT NULL UNIQUE,
            opening_cash NUMERIC(12, 2) NOT NULL DEFAULT 0,
            cash_sales NUMERIC(12, 2) NOT NULL DEFAULT 0,
            repair_cash_income NUMERIC(12, 2) NOT NULL DEFAULT 0,
            expenses NUMERIC(12, 2) NOT NULL DEFAULT 0,
            cash_out NUMERIC(12, 2) NOT NULL DEFAULT 0,
            expected_closing_cash NUMERIC(12, 2) NOT NULL DEFAULT 0,
            actual_closing_cash NUMERIC(12, 2),
            difference NUMERIC(12, 2),
            opened_by INT NOT NULL REFERENCES pos_staff(id),
            closed_by INT REFERENCES pos_staff(id),
            status VARCHAR(10) NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'closed')),
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            closed_at TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pos_audit_log (
            id SERIAL PRIMARY KEY,
            staff_id INT REFERENCES pos_staff(id) ON DELETE SET NULL,
            action VARCHAR(60) NOT NULL,
            entity_type VARCHAR(40) NOT NULL DEFAULT '',
            entity_id INT,
            details TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_pos_invoices_date ON pos_invoices(created_at);
        CREATE INDEX IF NOT EXISTS idx_pos_invoices_customer ON pos_invoices(customer_id);
        CREATE INDEX IF NOT EXISTS idx_pos_invoices_status ON pos_invoices(payment_status);
        CREATE INDEX IF NOT EXISTS idx_pos_invoice_items_invoice ON pos_invoice_items(invoice_id);
        CREATE INDEX IF NOT EXISTS idx_pos_repair_jobs_status ON pos_repair_jobs(status);
        CREATE INDEX IF NOT EXISTS idx_pos_repair_jobs_customer ON pos_repair_jobs(customer_id);
        CREATE INDEX IF NOT EXISTS idx_pos_expenses_date ON pos_expenses(expense_date);
        CREATE INDEX IF NOT EXISTS idx_pos_audit_created ON pos_audit_log(created_at);
    ");

    $repairProfitCol = $pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'pos_repair_jobs' AND column_name = 'repair_profit'"
    )->fetchColumn();
    if (!$repairProfitCol) {
        $pdo->exec(
            'ALTER TABLE pos_repair_jobs ADD COLUMN repair_profit NUMERIC(12, 2) NOT NULL DEFAULT 0'
        );
        $pdo->exec(
            'UPDATE pos_repair_jobs r SET parts_cost = COALESCE(x.expense, 0),
             repair_profit = CASE WHEN r.final_cost > 0 THEN GREATEST(0, r.final_cost - COALESCE(x.expense, 0)) ELSE 0 END
             FROM (
                 SELECT repair_job_id, SUM(cost) AS expense FROM pos_repair_parts GROUP BY repair_job_id
             ) x WHERE r.id = x.repair_job_id'
        );
    }

    $staffCount = (int) $pdo->query('SELECT COUNT(*) FROM pos_staff')->fetchColumn();
    if ($staffCount === 0) {
        $hasUsers = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = 'public' AND table_name = 'pos_users'"
        )->fetchColumn();
        if ($hasUsers) {
            $pdo->exec(
                "INSERT INTO pos_staff (name, username, password_hash, role, status, must_change_credentials)
                 SELECT COALESCE(NULLIF(username, ''), 'Staff'), username, password_hash, 'manager', 'active',
                        COALESCE(must_change_credentials, FALSE)
                 FROM pos_users
                 ON CONFLICT (username) DO NOTHING"
            );
        }
    }
}

function db_seed_admin(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO admin_users (username, password_hash, must_change_credentials)
         VALUES (:username, :hash, TRUE)'
    );
    $stmt->execute([
        'username' => 'admin',
        'hash' => password_hash('admin', PASSWORD_DEFAULT),
    ]);
}

function db_seed_pos(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM pos_staff')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO pos_staff (name, username, password_hash, role, status, must_change_credentials)
         VALUES (:name, :username, :hash, :role, :status, TRUE)'
    );
    $stmt->execute([
        'name' => 'Manager',
        'username' => 'admin',
        'hash' => password_hash('admin', PASSWORD_DEFAULT),
        'role' => 'manager',
        'status' => 'active',
    ]);
}

function db_available(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        db();
        $ok = true;
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}
