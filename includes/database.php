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

    return $pdo;
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
