<?php

declare(strict_types=1);

/** Bump when upload handler changes — shown in admin errors for deploy verification. */
if (!defined('UPLOADS_HANDLER_VERSION')) {
    define('UPLOADS_HANDLER_VERSION', '20250609c');
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

admin_require_setup_complete();

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_csrf_verify();
        uploads_assert_post_accepted();

        if (empty($_FILES['test_image'])) {
            throw new RuntimeException('Choose an image file first.');
        }

        $path = uploads_save_image($_FILES['test_image']);
        if ($path === null) {
            throw new RuntimeException('No file received.');
        }

        $full = uploads_base_dir() . '/' . $path;
        $result = [
            'path' => $path,
            'url' => upload_url($path),
            'bytes' => is_file($full) ? filesize($full) : 0,
        ];
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$status = uploads_status();
$logFile = uploads_base_dir() . '/upload-error.log';
$logTail = '';
if (is_file($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) {
        $logTail = implode("\n", array_slice($lines, -8));
    }
}

admin_render_header('Upload check', 'items');
?>
<section class="admin-panel admin-panel--wide">
    <h2>Upload check</h2>
    <p class="admin-field-note">Handler version: <strong><?php echo htmlspecialchars(UPLOADS_HANDLER_VERSION); ?></strong>
        — if uploads fail elsewhere but this page shows an older version, deploy the latest code and restart PHP/Apache.</p>

    <dl class="admin-dl">
        <dt>Folder in use</dt>
        <dd><code><?php echo htmlspecialchars($status['dir']); ?></code></dd>
        <dt>Configured path</dt>
        <dd><code><?php echo htmlspecialchars((string) ($status['using_path'] ?? uploads_base_dir())); ?></code></dd>
        <?php if (!empty($status['project_link'])): ?>
        <dt>assets/uploads link</dt>
        <dd><code><?php echo htmlspecialchars((string) $status['link_target']); ?></code></dd>
        <?php endif; ?>
        <dt>Writable</dt>
        <dd><?php echo $status['writable'] ? 'Yes' : 'No'; ?></dd>
        <dt>upload_max_filesize</dt>
        <dd><?php echo htmlspecialchars(ini_get('upload_max_filesize') ?: '?'); ?></dd>
        <dt>post_max_size</dt>
        <dd><?php echo htmlspecialchars(ini_get('post_max_size') ?: '?'); ?></dd>
        <dt>upload_tmp_dir</dt>
        <dd><?php echo htmlspecialchars(ini_get('upload_tmp_dir') ?: '(default)'); ?></dd>
    </dl>

    <?php if (!$status['ok']): ?>
    <div class="admin-alert admin-alert--error"><?php echo htmlspecialchars($status['message']); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert--error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($result): ?>
    <div class="admin-alert admin-alert--success">
        Upload OK (<?php echo (int) $result['bytes']; ?> bytes).
        Path: <code><?php echo htmlspecialchars($result['path']); ?></code>
    </div>
    <p><img class="admin-preview" src="<?php echo htmlspecialchars($result['url']); ?>" alt="Test upload"></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="admin-form">
        <?php admin_csrf_field(); ?>
        <div class="admin-field">
            <label for="test_image">Test image</label>
            <input type="file" id="test_image" name="test_image" accept="image/jpeg,image/png,image/webp,image/gif" required>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Upload test file</button>
    </form>

    <?php if ($logTail !== ''): ?>
    <h3>Recent upload log</h3>
    <pre class="admin-code"><?php echo htmlspecialchars($logTail); ?></pre>
    <?php endif; ?>
</section>
<?php
admin_render_footer();
