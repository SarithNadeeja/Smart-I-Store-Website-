<?php
/** @var string $social_class CSS class for the wrapper */
$social_class = $social_class ?? 'social-links';
?>
<div class="<?php echo htmlspecialchars($social_class); ?>" aria-label="Social media">
    <a href="<?php echo htmlspecialchars(SOCIAL_TIKTOK); ?>" class="social-link" target="_blank" rel="noopener noreferrer" aria-label="TikTok">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
    </a>
    <a href="<?php echo htmlspecialchars(SOCIAL_FACEBOOK); ?>" class="social-link" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
    </a>
</div>
