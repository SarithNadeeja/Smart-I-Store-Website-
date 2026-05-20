<?php
require_once __DIR__ . '/config.php';

$page_title = $page_title ?? SITE_NAME;
$page_description = $page_description ?? 'Premium mobile phones, accessories, and expert service. Discover the latest smartphones from top brands.';
$body_class = $body_class ?? '';
$extra_css = $extra_css ?? [];
$extra_js = $extra_js ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($intro_reload_skip)): ?>
    <?php require __DIR__ . '/intro-reload-skip.php'; ?>
    <?php endif; ?>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <title><?php echo htmlspecialchars($page_title); ?> | <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Outfit:wght@200;300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css'); ?>">
    <?php foreach ($extra_css as $css): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
    <?php endforeach; ?>
</head>
<body class="<?php echo htmlspecialchars($body_class); ?>">
