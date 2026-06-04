<?php

/** Per-file limit enforced in the app (keep below server upload_max_filesize) */
define('UPLOAD_MAX_FILE_BYTES', 15 * 1024 * 1024);

/** Max combined upload size for one item form submit (main + sub images) */
define('UPLOAD_MAX_TOTAL_BYTES', 48 * 1024 * 1024);

function uploads_ini_size_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;

    return match ($unit) {
        'g' => (int) ($number * 1024 * 1024 * 1024),
        'm' => (int) ($number * 1024 * 1024),
        'k' => (int) ($number * 1024),
        default => (int) $number,
    };
}

function uploads_post_payload_lost(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }

    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    return $contentLength > 0 && $_POST === [] && $_FILES === [];
}

function uploads_post_limit_message(): string
{
    $postMax = ini_get('post_max_size') ?: 'unknown';
    $uploadMax = ini_get('upload_max_filesize') ?: 'unknown';

    return 'Upload or form data was too large for the server (limit post_max_size='
        . $postMax
        . ', upload_max_filesize='
        . $uploadMax
        . '). Use smaller images (under 10 MB each), fewer files at once, or increase those limits in php.ini / .htaccess.';
}

function uploads_assert_post_accepted(): void
{
    if (uploads_post_payload_lost()) {
        throw new RuntimeException(uploads_post_limit_message());
    }
}

function uploads_dir(): string
{
    $dir = dirname(__DIR__) . '/assets/uploads/items';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function uploads_save_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException(
            'Image is too large. Server limit is '
            . (ini_get('upload_max_filesize') ?: 'unknown')
            . ' per file. Use a smaller image or increase upload_max_filesize in PHP.'
        );
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed (error code ' . $error . ').');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size > UPLOAD_MAX_FILE_BYTES) {
        throw new RuntimeException(
            'Image is too large (max '
            . (int) (UPLOAD_MAX_FILE_BYTES / 1024 / 1024)
            . ' MB per file). Resize or compress before uploading.'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WebP, and GIF images are allowed.');
    }

    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $dest = uploads_dir() . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    return 'items/' . $name;
}

/**
 * Normalize $_FILES entry for a multi-file input (handles one or many uploads).
 *
 * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
 */
function uploads_collect_files(array $files): array
{
    if (!isset($files['name']) || $files['name'] === '' || ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [];
    }

    if (!is_array($files['name'])) {
        return [$files];
    }

    $out = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (($files['name'][$i] ?? '') === '') {
            continue;
        }
        $out[] = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
    }

    return $out;
}

function uploads_delete_file(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '') {
        return;
    }

    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $full = dirname(__DIR__) . '/assets/uploads/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * @return list<string> Relative paths under assets/uploads/ (e.g. items/abc.jpg)
 */
function uploads_item_image_paths(int $itemId): array
{
    if ($itemId <= 0 || !db_available()) {
        return [];
    }

    $paths = [];
    $pdo = db();

    $stmt = $pdo->prepare('SELECT main_image FROM items WHERE id = :id');
    $stmt->execute(['id' => $itemId]);
    $row = $stmt->fetch();
    if ($row && trim((string) ($row['main_image'] ?? '')) !== '') {
        $paths[] = trim((string) $row['main_image']);
    }

    $imgStmt = $pdo->prepare('SELECT image_path FROM item_images WHERE item_id = :id');
    $imgStmt->execute(['id' => $itemId]);
    foreach ($imgStmt->fetchAll() as $img) {
        $path = trim((string) ($img['image_path'] ?? ''));
        if ($path !== '') {
            $paths[] = $path;
        }
    }

    return array_values(array_unique($paths));
}

function uploads_delete_item_images(int $itemId): void
{
    foreach (uploads_item_image_paths($itemId) as $path) {
        uploads_delete_file($path);
    }
}

/**
 * @param list<string> $relativePaths
 */
function uploads_delete_files(array $relativePaths): void
{
    foreach ($relativePaths as $path) {
        uploads_delete_file($path);
    }
}
