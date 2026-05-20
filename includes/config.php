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

/** Scroll-scrub intro video */
define('INTRO_VIDEO', 'videos/intro.webm');
define('INTRO_TRIM_START', 2); // seconds to skip from start

// Subdirectory path without trailing slash; empty string for root install
define('BASE_PATH', '/SmartIStore');

/** PostgreSQL */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '5432');
define('DB_NAME', 'smartistore');
define('DB_USER', 'postgres');
define('DB_PASS', '1234');

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
    $base = __DIR__ . '/../assets/videos/';
    return file_exists($base . 'intro.webm')
        || file_exists($base . 'intro.mp4');
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

