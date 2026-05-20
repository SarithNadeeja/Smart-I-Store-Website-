<?php
/**
 * Inline SVG icons helper
 */
function icon(string $name): string
{
    $icons = [
        'smartphone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18.01"/></svg>',
        'headphones' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 14v3a3 3 0 0 0 3 3h1v-8H5a2 2 0 0 0-2 2zm18 0v3a3 3 0 0 1-3 3h-1v-8h1a2 2 0 0 1 2 2z"/><path d="M6 14V9a6 6 0 1 1 12 0v5"/></svg>',
        'watch' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="7"/><path d="M12 9v3l2 2"/><path d="M9 2h6M9 22h6"/></svg>',
        'tablet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="3" width="16" height="18" rx="2"/><line x1="12" y1="17" x2="12" y2="17.01"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l8 4v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4z"/><path d="M9 12l2 2 4-4"/></svg>',
        'support' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2a7 7 0 0 1 7 7c0 2.5-1.2 4.7-3 6.1V18a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-2.9A7 7 0 0 1 5 9a7 7 0 0 1 7-7z"/><path d="M9 22h6"/></svg>',
        'truck' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 6h15v9H1z"/><path d="M16 9h4l3 4v2h-7V9z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
        'refresh' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 12a8 8 0 0 1 14-5"/><path d="M20 12a8 8 0 0 1-14 5"/><path d="M18 3v5h-5M6 21v-5h5"/></svg>',
        'star' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.27 5.82 22 7 14.14l-5-4.87 6.91-1.01z"/></svg>',
        'chevron-down' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>',
        'arrow-right' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    ];
    return $icons[$name] ?? '';
}
