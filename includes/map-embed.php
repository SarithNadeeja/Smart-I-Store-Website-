<?php
/** Google Maps embed + link to directions (set SITE_MAP_URL in config.php) */
$map_large = !empty($map_large);
$map_class = 'map-embed reveal-up' . ($map_large ? ' map-embed--lg' : '');
$embed_src = 'https://maps.google.com/maps?q=' . rawurlencode(SITE_ADDRESS) . '&output=embed';
?>
<div class="<?php echo htmlspecialchars($map_class); ?>" aria-label="Store location on Google Maps">
    <iframe
        class="map-embed__frame"
        title="<?php echo htmlspecialchars(SITE_NAME . ' — ' . SITE_ADDRESS); ?>"
        src="<?php echo htmlspecialchars($embed_src); ?>"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        allowfullscreen
    ></iframe>
    <a
        class="map-embed__open"
        href="<?php echo htmlspecialchars(SITE_MAP_URL); ?>"
        target="_blank"
        rel="noopener noreferrer"
    >Open in Google Maps</a>
</div>
