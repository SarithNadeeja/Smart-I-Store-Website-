<?php
/**
 * Upload diagnostic — admin only. Remove from production when uploads are stable.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/admin/includes/init.php';

admin_require_login();

header('Content-Type: text/plain; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo "POST a file as field \"image\" to test uploads.\n";
    echo "uploads_dir: " . uploads_dir() . "\n";
    echo "writable: " . (is_writable(uploads_dir()) ? 'yes' : 'no') . "\n";
    echo "upload_max_filesize: " . (ini_get('upload_max_filesize') ?: '?') . "\n";
    echo "post_max_size: " . (ini_get('post_max_size') ?: '?') . "\n";
    echo "upload_tmp_dir: " . (ini_get('upload_tmp_dir') ?: '(default)') . "\n";
    exit;
}

try {
    uploads_assert_post_accepted();
    if (empty($_FILES['image'])) {
        throw new RuntimeException('No file field "image" in request.');
    }
    $f = $_FILES['image'];
    echo "error code: " . ($f['error'] ?? '?') . "\n";
    echo "tmp_name: " . ($f['tmp_name'] ?? '') . "\n";
    echo "is_uploaded_file: " . ((!empty($f['tmp_name']) && is_uploaded_file($f['tmp_name'])) ? 'yes' : 'no') . "\n";
    echo "size: " . ($f['size'] ?? 0) . "\n";

    $path = uploads_save_image($f);
    echo "SAVE OK: " . ($path ?? 'null') . "\n";
    if ($path) {
        $full = uploads_base_dir() . '/' . $path;
        echo "full: {$full}\n";
        echo "bytes: " . (is_file($full) ? filesize($full) : 0) . "\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "SAVE FAIL: " . $e->getMessage() . "\n";
}
