<?php

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

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
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

function uploads_delete_file(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '') {
        return;
    }

    $full = dirname(__DIR__) . '/assets/uploads/' . ltrim($relativePath, '/');
    if (is_file($full)) {
        unlink($full);
    }
}
