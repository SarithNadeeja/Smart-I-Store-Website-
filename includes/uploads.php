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

function uploads_base_dir(): string
{
    if (defined('UPLOADS_BASE_PATH') && UPLOADS_BASE_PATH !== '') {
        return rtrim(str_replace('\\', '/', (string) UPLOADS_BASE_PATH), '/');
    }

    return str_replace('\\', '/', dirname(__DIR__) . '/assets/uploads');
}

function uploads_normalize_path(string $path): string
{
    return str_replace('\\', '/', $path);
}

function uploads_ensure_base_dir(): void
{
    $base = uploads_base_dir();
    if (is_dir($base)) {
        return;
    }

    if (is_link($base) && !is_dir($base)) {
        throw new RuntimeException(
            'Upload storage link is broken (assets/uploads). '
            . 'Fix the symlink or set UPLOADS_BASE_PATH in includes/config.local.php.'
        );
    }

    if (!@mkdir($base, 0755, true) && !is_dir($base)) {
        throw new RuntimeException(
            'Upload folder could not be created (assets/uploads). '
            . 'Create it manually and make it writable by the web server.'
        );
    }
}

function uploads_dir(): string
{
    uploads_ensure_base_dir();
    $dir = uploads_base_dir() . '/items';

    if (is_dir($dir)) {
        if (!is_writable($dir)) {
            throw new RuntimeException(
                'Upload folder is not writable (assets/uploads/items). '
                . 'Allow the web server user to write to this folder.'
            );
        }

        return uploads_normalize_path($dir);
    }

    if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException(
            'Upload folder could not be created (assets/uploads/items). '
            . 'Create it manually and make it writable by the web server.'
        );
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        @chmod($dir, 0777);
    }

    if (!is_writable($dir)) {
        throw new RuntimeException(
            'Upload folder is not writable (assets/uploads/items). '
            . 'Allow the web server user to write to this folder.'
        );
    }

    return uploads_normalize_path($dir);
}

function uploads_allowed_image_types(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
}

function uploads_resolve_image_extension(array $file): string
{
    $allowed = uploads_allowed_image_types();
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        throw new RuntimeException('Upload temporary file is missing. Try again or use a smaller image.');
    }

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($tmp) ?: '');
    }
    if ($mime !== '' && isset($allowed[$mime])) {
        return $allowed[$mime];
    }

    $imageInfo = @getimagesize($tmp);
    if (is_array($imageInfo) && !empty($imageInfo['mime']) && isset($allowed[$imageInfo['mime']])) {
        return $allowed[$imageInfo['mime']];
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $byExt = [
        'jpg' => 'jpg',
        'jpeg' => 'jpg',
        'png' => 'png',
        'webp' => 'webp',
        'gif' => 'gif',
    ];
    if ($imageInfo !== false && isset($byExt[$ext])) {
        return $byExt[$ext];
    }

    $label = $mime !== '' ? $mime : 'unknown type';
    throw new RuntimeException('Only JPG, PNG, WebP, and GIF images are allowed (received ' . $label . ').');
}

function uploads_temp_is_trusted(string $tmp): bool
{
    if ($tmp === '' || !is_file($tmp)) {
        return false;
    }
    if (is_uploaded_file($tmp)) {
        return true;
    }

    $tmpReal = uploads_normalize_path((string) (realpath($tmp) ?: $tmp));
    $dirs = [sys_get_temp_dir()];
    $uploadTmp = ini_get('upload_tmp_dir');
    if (is_string($uploadTmp) && $uploadTmp !== '') {
        $dirs[] = $uploadTmp;
    }
    if (DIRECTORY_SEPARATOR === '\\') {
        $dirs[] = 'C:/xampp/tmp';
    }

    foreach ($dirs as $dir) {
        if ($dir === '') {
            continue;
        }
        $dirReal = uploads_normalize_path((string) (realpath($dir) ?: $dir));
        $dirReal = rtrim($dirReal, '/');
        if ($tmpReal === $dirReal || str_starts_with($tmpReal, $dirReal . '/')) {
            return true;
        }
    }

    return false;
}

function uploads_store_failure_detail(string $tmp, string $dest): string
{
    $parts = [];
    $parent = dirname($dest);

    if (!is_dir($parent)) {
        $parts[] = 'folder missing: assets/uploads/items';
    } elseif (!is_writable($parent)) {
        $parts[] = 'folder not writable: assets/uploads/items';
    }

    if ($tmp === '' || !is_file($tmp)) {
        $parts[] = 'temp file missing';
    } elseif (filesize($tmp) <= 0) {
        $parts[] = 'temp file empty';
    }

    $free = @disk_free_space($parent);
    if ($free !== false && $free < 1024 * 1024) {
        $parts[] = 'disk nearly full';
    }

    $last = error_get_last();
    if (is_array($last) && !empty($last['message'])) {
        $parts[] = trim((string) $last['message']);
    }

    return $parts !== [] ? ' ' . implode('; ', $parts) : '';
}

function uploads_store_temp_file(string $tmp, string $dest): void
{
    $parent = dirname($dest);
    if (!is_dir($parent)) {
        @mkdir($parent, 0755, true);
    }

    if (@move_uploaded_file($tmp, $dest) && is_file($dest) && filesize($dest) > 0) {
        return;
    }

    if (!uploads_temp_is_trusted($tmp)) {
        throw new RuntimeException(
            'Upload temporary file is missing. Try again, use a smaller image, or check PHP upload_tmp_dir.'
        );
    }

    if (@copy($tmp, $dest) && is_file($dest) && filesize($dest) > 0) {
        @unlink($tmp);
        return;
    }

    if (@rename($tmp, $dest) && is_file($dest) && filesize($dest) > 0) {
        return;
    }

    $bytes = @file_get_contents($tmp);
    if ($bytes !== false && $bytes !== '' && @file_put_contents($dest, $bytes) !== false) {
        @unlink($tmp);
        return;
    }

    $in = @fopen($tmp, 'rb');
    if ($in === false) {
        throw new RuntimeException(
            'Could not read the uploaded file from temp storage. Check PHP upload_tmp_dir ('
            . (ini_get('upload_tmp_dir') ?: 'system default') . ').'
        );
    }

    $out = @fopen($dest, 'wb');
    if ($out === false) {
        fclose($in);
        throw new RuntimeException(
            'Could not write to assets/uploads/items. Fix folder permissions for the web server user.'
            . uploads_store_failure_detail($tmp, $dest)
        );
    }

    $copied = stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);

    if ($copied === false || !is_file($dest) || filesize($dest) <= 0) {
        @unlink($dest);
        throw new RuntimeException(
            'Could not save uploaded image.'
            . uploads_store_failure_detail($tmp, $dest)
        );
    }

    @unlink($tmp);
}

/**
 * Validate combined upload payload size before writing any files.
 *
 * @param array<string, mixed> $filesMain $_FILES['main_image'] or similar
 * @param list<array<string, mixed>> $subFiles from uploads_collect_files()
 */
function uploads_assert_total_size(array $filesMain, array $subFiles): void
{
    $uploadBytes = 0;
    if (!empty($filesMain['name']) && (int) ($filesMain['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadBytes += (int) ($filesMain['size'] ?? 0);
    }
    foreach ($subFiles as $file) {
        $uploadBytes += (int) ($file['size'] ?? 0);
    }
    if ($uploadBytes > UPLOAD_MAX_TOTAL_BYTES) {
        throw new RuntimeException(
            'Total upload size is too large (max '
            . (int) (UPLOAD_MAX_TOTAL_BYTES / 1024 / 1024)
            . ' MB per save). Upload fewer images or use smaller files.'
        );
    }
}

function uploads_status(): array
{
    try {
        $dir = uploads_dir();
        $writable = is_writable($dir);
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'dir' => uploads_base_dir() . '/items',
            'writable' => false,
            'message' => $e->getMessage(),
        ];
    }

    return [
        'ok' => $writable,
        'dir' => $dir,
        'writable' => $writable,
        'message' => $writable ? 'Upload folder is writable.' : 'Upload folder is not writable.',
    ];
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

    if (trim((string) ($file['name'] ?? '')) === '') {
        return null;
    }

    $extension = uploads_resolve_image_extension($file);
    $name = bin2hex(random_bytes(8)) . '.' . $extension;
    $dir = uploads_dir();
    $dest = $dir . '/' . $name;
    $tmp = (string) ($file['tmp_name'] ?? '');

    uploads_store_temp_file($tmp, $dest);

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
    $full = uploads_base_dir() . '/' . $relativePath;
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
