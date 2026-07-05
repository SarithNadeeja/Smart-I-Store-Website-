<?php

/** Bump when upload handler changes — visible in admin errors. */
define('UPLOADS_HANDLER_VERSION', '20250611b');

/** WebP output quality (0–100) for converted admin uploads */
define('UPLOADS_WEBP_QUALITY', 82);

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

function uploads_project_uploads_path(): string
{
    return uploads_normalize_path(dirname(__DIR__) . '/assets/uploads');
}

function uploads_base_dir(): string
{
    if (defined('UPLOADS_BASE_PATH') && UPLOADS_BASE_PATH !== '') {
        return rtrim(str_replace('\\', '/', (string) UPLOADS_BASE_PATH), '/');
    }

    return uploads_project_uploads_path();
}

function uploads_normalize_path(string $path): string
{
    return str_replace('\\', '/', $path);
}

function uploads_link_target(string $path): ?string
{
    if (!is_link($path)) {
        return null;
    }

    $target = readlink($path);
    if ($target === false || $target === '') {
        return null;
    }

    if ($target[0] !== '/' && $target[1] !== ':') {
        $target = uploads_normalize_path(dirname($path) . '/' . $target);
    }

    return uploads_normalize_path($target);
}

/**
 * Try to fix a broken uploads symlink (missing target, or replace with a real folder).
 */
function uploads_repair_broken_link(string $linkPath): bool
{
    if (!is_link($linkPath)) {
        return is_dir($linkPath);
    }

    $target = uploads_link_target($linkPath);
    if ($target !== null && !is_dir($target)) {
        @mkdir($target, 0775, true);
        @mkdir($target . '/items', 0775, true);
        if (is_dir($target)) {
            uploads_log_error('Created missing upload symlink target: ' . $target);
        }
    }

    if (is_dir($linkPath)) {
        return true;
    }

    // Last resort: replace broken assets/uploads symlink with a real folder in the project.
    if ($linkPath === uploads_project_uploads_path()) {
        $parent = dirname($linkPath);
        if (is_writable($parent)) {
            @unlink($linkPath);
            if (@mkdir($linkPath, 0775, true) || is_dir($linkPath)) {
                uploads_log_error('Replaced broken assets/uploads symlink with a real folder.');
                return true;
            }
        }
    }

    return is_dir($linkPath);
}

function uploads_ensure_base_dir(): void
{
    $base = uploads_base_dir();

    if (is_dir($base)) {
        return;
    }

    if (is_link($base)) {
        if (uploads_repair_broken_link($base)) {
            return;
        }

        $target = uploads_link_target($base);
        $detail = $target !== null
            ? 'Symlink points to missing folder: ' . $target . '. '
            : '';

        throw new RuntimeException(
            '[Upload ' . UPLOADS_HANDLER_VERSION . '] Upload storage link is broken (assets/uploads). '
            . $detail
            . 'On the server: rm assets/uploads && mkdir -p assets/uploads/items && chmod -R 775 assets/uploads '
            . '— or set UPLOADS_BASE_PATH in includes/config.local.php to a writable folder.'
        );
    }

    if (!@mkdir($base, 0775, true) && !is_dir($base)) {
        throw new RuntimeException(
            '[Upload ' . UPLOADS_HANDLER_VERSION . '] Upload folder could not be created ('
            . $base
            . '). Create it manually and make it writable by the web server.'
        );
    }
}

function uploads_dir(): string
{
    uploads_ensure_base_dir();
    $dir = uploads_base_dir() . '/items';

    if (is_dir($dir)) {
        if (!is_writable($dir)) {
            @chmod($dir, 0775);
        }
        if (!is_writable($dir)) {
            uploads_fail(
                'Upload folder is not writable.',
                'Fix permissions on assets/uploads/items for the web server user (e.g. chmod 775).'
            );
        }

        $real = realpath($dir);
        return uploads_normalize_path($real !== false ? $real : $dir);
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

function uploads_ads_dir(): string
{
    uploads_ensure_base_dir();
    $dir = uploads_base_dir() . '/ads';

    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException(
                'Advertisement upload folder could not be created (assets/uploads/ads).'
            );
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            @chmod($dir, 0777);
        }
    }

    if (!is_writable($dir)) {
        @chmod($dir, 0775);
    }

    if (!is_writable($dir)) {
        throw new RuntimeException(
            'Advertisement upload folder is not writable (assets/uploads/ads).'
        );
    }

    $real = realpath($dir);

    return uploads_normalize_path($real !== false ? $real : $dir);
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
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/heic-sequence' => 'heic',
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
        'heic' => 'heic',
        'heif' => 'heif',
    ];
    if ($imageInfo !== false && isset($byExt[$ext])) {
        return $byExt[$ext];
    }
    if (isset($byExt[$ext])) {
        return $byExt[$ext];
    }

    $label = $mime !== '' ? $mime : 'unknown type';
    throw new RuntimeException('Only JPG, PNG, WebP, GIF, and HEIC images are allowed (received ' . $label . ').');
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

function uploads_fail(string $summary, string $detail = ''): void
{
    $msg = '[Upload ' . UPLOADS_HANDLER_VERSION . '] ' . $summary;
    if ($detail !== '') {
        $msg .= ' — ' . $detail;
    }
    uploads_log_error($msg);
    throw new RuntimeException($msg);
}

function uploads_log_error(string $message): void
{
    $line = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
    $paths = [
        uploads_project_uploads_path() . '/upload-error.log',
        uploads_normalize_path(dirname(__DIR__) . '/storage/upload-error.log'),
    ];

    if (defined('UPLOADS_BASE_PATH') && UPLOADS_BASE_PATH !== '') {
        array_unshift($paths, rtrim(str_replace('\\', '/', (string) UPLOADS_BASE_PATH), '/') . '/upload-error.log');
    }

    foreach ($paths as $logFile) {
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) !== false) {
            return;
        }
    }
}

function uploads_store_failure_detail(string $tmp, string $dest, array $steps = []): string
{
    clearstatcache(true, $tmp);
    clearstatcache(true, $dest);

    $parent = dirname($dest);
    $parts = $steps;

    $parts[] = 'dest=' . $dest;
    $parts[] = 'dest_exists=' . (is_file($dest) ? 'yes' : 'no');
    $parts[] = 'dest_bytes=' . (is_file($dest) ? (string) filesize($dest) : '0');
    $parts[] = 'tmp=' . $tmp;
    $parts[] = 'tmp_exists=' . (is_file($tmp) ? 'yes' : 'no');
    $parts[] = 'tmp_bytes=' . (is_file($tmp) ? (string) filesize($tmp) : '0');
    $parts[] = 'is_uploaded_file=' . (is_uploaded_file($tmp) ? 'yes' : 'no');
    $parts[] = 'parent_writable=' . (is_dir($parent) && is_writable($parent) ? 'yes' : 'no');
    $parts[] = 'upload_tmp_dir=' . (ini_get('upload_tmp_dir') ?: '(default)');

    if (!is_dir($parent)) {
        $parts[] = 'reason=folder missing';
    } elseif (!is_writable($parent)) {
        $parts[] = 'reason=folder not writable';
    } elseif ($tmp === '' || !is_file($tmp)) {
        $parts[] = 'reason=temp file missing';
    } elseif (filesize($tmp) <= 0) {
        $parts[] = 'reason=temp file empty';
    }

    $free = @disk_free_space($parent);
    if ($free !== false && $free < 1024 * 1024) {
        $parts[] = 'reason=disk nearly full';
    }

    $last = error_get_last();
    if (is_array($last) && !empty($last['message'])) {
        $parts[] = 'php=' . trim((string) $last['message']);
    }

    return implode(' | ', $parts);
}

/**
 * Load an image resource with GD (mime, extension, then raw bytes).
 *
 * @return \GdImage|resource|false
 */
function uploads_gd_load_image(string $sourcePath, string $mime, string $ext)
{
    if (!function_exists('imagecreatefromjpeg')) {
        return false;
    }

    $resource = false;
    if ($mime === 'image/jpeg' || $mime === 'image/pjpeg' || $ext === 'jpg' || $ext === 'jpeg') {
        $resource = @imagecreatefromjpeg($sourcePath);
    } elseif ($mime === 'image/png' || $mime === 'image/x-png' || $ext === 'png') {
        $resource = @imagecreatefrompng($sourcePath);
    } elseif ($mime === 'image/gif' || $ext === 'gif') {
        $resource = @imagecreatefromgif($sourcePath);
    } elseif ($mime === 'image/webp' || $ext === 'webp') {
        $resource = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false;
    }

    if ($resource === false && function_exists('imagecreatefromstring')) {
        $data = @file_get_contents($sourcePath);
        if ($data !== false && $data !== '') {
            $resource = @imagecreatefromstring($data);
        }
    }

    return $resource !== false ? $resource : false;
}

/**
 * @param \GdImage|resource $resource
 * @return \GdImage|resource
 */
function uploads_gd_prepare_for_webp($resource)
{
    if (function_exists('imagepalettetotruecolor') && !imageistruecolor($resource)) {
        imagepalettetotruecolor($resource);
    }
    imagealphablending($resource, true);
    imagesavealpha($resource, true);

    return $resource;
}

/**
 * Convert a stored image (PNG, JPG, JPEG, HEIC, GIF) to WebP when supported.
 * Falls back to the original file for JPG/PNG/GIF if WebP is unavailable on the server.
 */
function uploads_convert_to_webp(string $sourcePath): string
{
    if (!is_file($sourcePath)) {
        throw new RuntimeException('Uploaded image file is missing.');
    }

    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    if ($ext === 'webp') {
        return $sourcePath;
    }

    $destPath = preg_replace('/\.[^.]+$/', '', $sourcePath) . '.webp';
    if (is_file($destPath)) {
        @unlink($destPath);
    }

    if (class_exists('Imagick')) {
        try {
            $image = new Imagick($sourcePath);
            $image->setIteratorIndex(0);
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(UPLOADS_WEBP_QUALITY);
            $image->writeImage($destPath);
            $image->clear();
            $image->destroy();
            clearstatcache(true, $destPath);
            if (is_file($destPath) && filesize($destPath) > 0) {
                if ($sourcePath !== $destPath) {
                    @unlink($sourcePath);
                }
                return $destPath;
            }
        } catch (Throwable $e) {
            uploads_log_error('Imagick WebP conversion failed: ' . $e->getMessage());
        }
    }

    if (function_exists('imagewebp')) {
        $imageInfo = @getimagesize($sourcePath);
        $mime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
        $resource = uploads_gd_load_image($sourcePath, $mime, $ext);

        if ($resource !== false) {
            $resource = uploads_gd_prepare_for_webp($resource);
            $saved = @imagewebp($resource, $destPath, UPLOADS_WEBP_QUALITY);
            imagedestroy($resource);
            clearstatcache(true, $destPath);
            if ($saved && is_file($destPath) && filesize($destPath) > 0) {
                if ($sourcePath !== $destPath) {
                    @unlink($sourcePath);
                }
                return $destPath;
            }
            uploads_log_error('GD imagewebp failed for ' . $sourcePath . ' mime=' . $mime);
        } else {
            uploads_log_error('GD could not load image for WebP: ' . $sourcePath . ' mime=' . $mime);
        }
    }

    // Server has no working WebP encoder — keep JPG/PNG/GIF so uploads still succeed.
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        uploads_log_error(
            'WebP conversion skipped (GD WebP or Imagick not available on server); kept original .'
            . $ext
            . ' file. Enable php_gd2 WebP in php.ini or install Imagick for automatic WebP.'
        );

        return $sourcePath;
    }

    uploads_fail(
        'Could not convert HEIC image to WebP.',
        'Install PHP Imagick with HEIC support on the server, or upload JPG/PNG instead.'
    );
}

function uploads_store_temp_file(string $tmp, string $dest): void
{
    $parent = dirname($dest);
    if (!is_dir($parent) && !@mkdir($parent, 0775, true) && !is_dir($parent)) {
        uploads_fail('Upload folder missing.', 'Create assets/uploads/items on the server.');
    }

    if (is_file($dest)) {
        @unlink($dest);
    }

    if ($tmp !== '' && is_uploaded_file($tmp) && @move_uploaded_file($tmp, $dest)) {
        clearstatcache(true, $dest);
        if (is_file($dest)) {
            return;
        }
    }

    if ($tmp === '' || !is_file($tmp)) {
        uploads_fail(
            'Temporary upload file missing.',
            'Try a smaller JPG/PNG, or increase post_max_size / upload_max_filesize in PHP.'
        );
    }

    if (@copy($tmp, $dest)) {
        clearstatcache(true, $dest);
        if (is_file($dest) && filesize($dest) > 0) {
            @unlink($tmp);
            return;
        }
    }

    if (@file_put_contents($dest, (string) file_get_contents($tmp)) !== false) {
        clearstatcache(true, $dest);
        if (is_file($dest) && filesize($dest) > 0) {
            @unlink($tmp);
            return;
        }
    }

    uploads_fail(
        'Could not save uploaded image.',
        uploads_store_failure_detail($tmp, $dest, ['move_and_copy_failed'])
    );
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
    $projectLink = uploads_project_uploads_path();
    $linkTarget = uploads_link_target($projectLink);
    $usingPath = uploads_base_dir();

    try {
        $dir = uploads_dir();
        $writable = is_writable($dir);
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'dir' => $usingPath . '/items',
            'writable' => false,
            'message' => $e->getMessage(),
            'project_link' => is_link($projectLink) ? $projectLink : null,
            'link_target' => $linkTarget,
            'using_path' => $usingPath,
        ];
    }

    $message = $writable ? 'Upload folder is writable.' : 'Upload folder is not writable.';
    if ($linkTarget !== null && !is_dir($linkTarget)) {
        $message = 'Broken symlink: assets/uploads -> ' . $linkTarget;
    }

    return [
        'ok' => $writable,
        'dir' => $dir,
        'writable' => $writable,
        'message' => $message,
        'project_link' => is_link($projectLink) ? $projectLink : null,
        'link_target' => $linkTarget,
        'using_path' => $usingPath,
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
        $labels = [
            UPLOAD_ERR_INI_SIZE => 'file exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'file exceeds MAX_FILE_SIZE in form',
            UPLOAD_ERR_PARTIAL => 'file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'no file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'missing temp folder on server',
            UPLOAD_ERR_CANT_WRITE => 'failed to write to disk',
            UPLOAD_ERR_EXTENSION => 'blocked by a PHP extension',
        ];
        $label = $labels[$error] ?? 'unknown error';
        throw new RuntimeException('Image upload failed: ' . $label . ' (code ' . $error . ').');
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
    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    $tmp = (string) ($file['tmp_name'] ?? '');

    uploads_log_error(
        'save attempt v=' . UPLOADS_HANDLER_VERSION
        . ' name=' . ($file['name'] ?? '')
        . ' ext=' . $extension
        . ' tmp=' . $tmp
        . ' size=' . ($file['size'] ?? 0)
    );

    uploads_store_temp_file($tmp, $dest);

    $finalDest = uploads_convert_to_webp($dest);

    return 'items/' . basename($finalDest);
}

/** Save an advertisement banner image under assets/uploads/ads/. */
function uploads_save_ad_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Advertisement image upload failed (code ' . $error . ').');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size > UPLOAD_MAX_FILE_BYTES) {
        throw new RuntimeException(
            'Advertisement image is too large (max '
            . (int) (UPLOAD_MAX_FILE_BYTES / 1024 / 1024)
            . ' MB).'
        );
    }

    if (trim((string) ($file['name'] ?? '')) === '') {
        return null;
    }

    $extension = uploads_resolve_image_extension($file);
    $name = bin2hex(random_bytes(8)) . '.' . $extension;
    $dir = uploads_ads_dir();
    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    $tmp = (string) ($file['tmp_name'] ?? '');

    uploads_store_temp_file($tmp, $dest);
    $finalDest = uploads_convert_to_webp($dest);

    return 'ads/' . basename($finalDest);
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
