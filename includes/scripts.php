<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<?php
if (!empty($extra_js)) {
    foreach ($extra_js as $js) {
        echo '<script src="' . htmlspecialchars($js) . '"></script>' . "\n";
    }
}
?>
<script>window.SITE_SEARCH_API = <?php echo json_encode(base_url('api/search.php'), JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?php echo asset_url('js/site-search.js'); ?>"></script>
<script src="<?php echo asset_url('js/main.js'); ?>"></script>
<script src="<?php echo asset_url('js/store-bot.js'); ?>"></script>
