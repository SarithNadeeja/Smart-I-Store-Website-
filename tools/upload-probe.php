<?php
/**
 * Upload diagnostics — admin only.
 * Open in browser while logged in to admin.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/admin/includes/init.php';

admin_require_login();

header('Content-Type: text/plain; charset=utf-8');

echo "=== SmartIStore upload diagnostics ===\n\n";

$status = uploads_status();
echo "uploads_status.ok: " . ($status['ok'] ? 'yes' : 'no') . "\n";
echo "uploads_status.dir: " . $status['dir'] . "\n";
echo "uploads_status.message: " . $status['message'] . "\n\n";

echo "uploads_base_dir: " . uploads_base_dir() . "\n";
echo "realpath: " . (realpath(uploads_base_dir() . '/items') ?: 'none') . "\n";
echo "is_writable: " . (is_writable(uploads_dir()) ? 'yes' : 'no') . "\n\n";

echo "PHP upload_max_filesize: " . (ini_get('upload_max_filesize') ?: '?') . "\n";
echo "PHP post_max_size: " . (ini_get('post_max_size') ?: '?') . "\n";
echo "PHP upload_tmp_dir: " . (ini_get('upload_tmp_dir') ?: '(default)') . "\n";
echo "PHP file_uploads: " . (ini_get('file_uploads') ? 'On' : 'Off') . "\n";
echo "PHP open_basedir: " . (ini_get('open_basedir') ?: '(none)') . "\n";
echo "SAPI: " . (PHP_SAPI ?: '?') . "\n\n";

$logFile = uploads_base_dir() . '/upload-error.log';
if (is_file($logFile)) {
    echo "=== Last 5 lines of upload-error.log ===\n";
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) {
        foreach (array_slice($lines, -5) as $line) {
            echo $line . "\n";
        }
    }
} else {
    echo "No upload-error.log yet (created on first failed upload).\n";
}

echo "\n=== POST test ===\n";
echo "POST a file as field \"image\" to this same URL to run a live save test.\n";

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    echo "\n--- POST result ---\n";
    try {
        uploads_assert_post_accepted();
        if (empty($_FILES['image'])) {
            throw new RuntimeException('No file field "image".');
        }
        $f = $_FILES['image'];
        echo "name: " . ($f['name'] ?? '') . "\n";
        echo "error: " . ($f['error'] ?? '?') . "\n";
        echo "size: " . ($f['size'] ?? 0) . "\n";
        echo "tmp_name: " . ($f['tmp_name'] ?? '') . "\n";
        echo "is_uploaded_file: " . ((!empty($f['tmp_name']) && is_uploaded_file($f['tmp_name'])) ? 'yes' : 'no') . "\n";

        $path = uploads_save_image($f);
        echo "RESULT: SAVE OK -> " . ($path ?? 'null') . "\n";
    } catch (Throwable $e) {
        echo "RESULT: SAVE FAIL\n";
        echo $e->getMessage() . "\n";
    }
}
