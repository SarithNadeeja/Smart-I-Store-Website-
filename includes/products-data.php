<?php

require_once __DIR__ . '/store.php';

$featured_phones = store_get_featured_phones(4);
$categories = store_get_categories();
$category_slides = store_get_home_category_slides();

$why_choose_us = [
    [
        'title' => 'Genuine Products',
        'desc' => 'Every device is sourced from authorized distributors with full warranty.',
        'icon' => 'shield',
    ],
    [
        'title' => 'Expert Support',
        'desc' => 'Our specialists help you find the perfect phone for your needs.',
        'icon' => 'support',
    ],
    [
        'title' => 'Fast Delivery',
        'desc' => 'Same-day dispatch on in-stock items with secure packaging.',
        'icon' => 'truck',
    ],
    [
        'title' => 'Trade-In Program',
        'desc' => 'Upgrade seamlessly with competitive trade-in valuations.',
        'icon' => 'refresh',
    ],
];
