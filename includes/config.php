<?php
/**
 * Smart I Store — Site configuration
 * Adjust BASE_PATH if the site lives in a subdirectory (e.g. '/SmartIStore' on XAMPP).
 * Use '' when deployed at domain root on cPanel.
 */
define('SITE_NAME', 'Smart I Store');
define('SITE_TAGLINE', 'Mobile Phone Repairing, Mobile Phones, Accessories Wholesale & Retail');
define('SITE_ABOUT', SITE_TAGLINE);
define('SITE_EMAIL', 'hello@smartistore.com');
define('SITE_PHONE', '0707391895 / 0760779621');
define('SITE_WHATSAPP_1', '0707391895');
define('SITE_WHATSAPP_2', '0760779621');
define('SITE_ADDRESS', '296, Badulla Road, Thanthiriya, Bandarawela');
define('SITE_MAP_URL', 'https://maps.app.goo.gl/hSg77ANnma2kShxh9');
define('SITE_LOGO', 'images/logo.jpg');

define('SOCIAL_TIKTOK', 'https://www.tiktok.com/@smart_i_store?_r=1&_t=ZS-96QsDvsFapY');
define('SOCIAL_FACEBOOK', 'https://www.facebook.com/share/193jGRq3pD/?mibextid=wwXIfr');
define('POWERED_BY_URL', 'https://infersioai.com');
define('POWERED_BY_LABEL', 'infersioai.com');

/** Main hero video (after intro scroll) */
define('HERO_VIDEO', 'videos/website.webm');

/** Intro overlay disabled — homepage goes straight to hero banner */
define('INTRO_ENABLED', false);
define('INTRO_VIDEO', 'videos/intro.webm');
define('INTRO_TRIM_START', 2);

/**
 * Derive /YourFolder from DOCUMENT_ROOT vs project root (works on XAMPP and cPanel).
 */
function smartistore_detect_base_path(): string
{
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $projectRoot = realpath(__DIR__ . '/..');

    if ($docRoot && $projectRoot && str_starts_with($projectRoot, $docRoot)) {
        $rel = substr($projectRoot, strlen($docRoot));
        $rel = str_replace('\\', '/', $rel);

        return $rel === '' ? '' : $rel;
    }

    return '';
}

/**
 * Subdirectory URL prefix (no trailing slash). Empty string = site at domain root.
 * Auto-detected from the project folder under the web root; override in config.local.php.
 */
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', smartistore_detect_base_path());
}
/** PostgreSQL — hosting */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '5432');
define('DB_NAME', 'smartistore_db');
define('DB_USER', 'smartistore_user');
define('DB_PASS', 'smartistorepwd');

function base_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = rtrim(BASE_PATH, '/');
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}

function asset_url(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function page_url(string $page): string
{
    return base_url($page);
}

function is_current_page(string $page): bool
{
    $current = basename($_SERVER['PHP_SELF'] ?? '');
    return $current === $page;
}

function intro_video_exists(): bool
{
    if (!INTRO_ENABLED) {
        return false;
    }
    $base = __DIR__ . '/../assets/videos/';

    return file_exists($base . 'intro.webm')
        || file_exists($base . 'intro.mp4');
}

function hero_video_exists(): bool
{
    $base = __DIR__ . '/../assets/videos/';
    return file_exists($base . 'website.webm');
}

/** Hero banner video for the site-wide preload loader */
function site_preload_videos(): array
{
    if (!hero_video_exists()) {
        return [];
    }

    $base = __DIR__ . '/../assets/videos/';
    $entry = [
        'role' => 'hero',
        'url' => asset_url(HERO_VIDEO),
        'type' => 'video/webm',
    ];

    if (file_exists($base . 'website.mp4')) {
        $entry['fallback_url'] = asset_url('videos/website.mp4');
        $entry['fallback_type'] = 'video/mp4';
    }

    return [$entry];
}

function site_logo_exists(): bool
{
    return file_exists(__DIR__ . '/../assets/' . SITE_LOGO);
}

function site_logo_url(): string
{
    return asset_url(SITE_LOGO);
}

/** WhatsApp link for Sri Lanka numbers (070… / 076…) */
function whatsapp_url(string $number): string
{
    $digits = preg_replace('/\D/', '', $number);
    if ($digits !== '' && $digits[0] === '0') {
        $digits = '94' . substr($digits, 1);
    }
    return 'https://wa.me/' . $digits;
}

function whatsapp_order_url(string $number, string $message): string
{
    return whatsapp_url($number) . '?text=' . rawurlencode($message);
}

