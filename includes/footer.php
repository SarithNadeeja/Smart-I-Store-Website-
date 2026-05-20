<footer class="site-footer">
    <div class="footer-glow"></div>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="<?php echo page_url('index.php'); ?>" class="logo logo-footer">
                    <span class="logo-mark"></span>
                    <span class="logo-text"><?php echo htmlspecialchars(SITE_NAME); ?></span>
                </a>
                <p class="footer-tagline"><?php echo htmlspecialchars(SITE_TAGLINE); ?>. Curated devices, authentic warranties, and white-glove service.</p>
                <?php require __DIR__ . '/social-links.php'; ?>
            </div>

            <div class="footer-col">
                <h4 class="footer-heading">Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo page_url('index.php'); ?>">Home</a></li>
                    <li><a href="<?php echo page_url('products.php'); ?>">Products</a></li>
                    <li><a href="<?php echo page_url('about.php'); ?>">About Us</a></li>
                    <li><a href="<?php echo page_url('contact.php'); ?>">Contact</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4 class="footer-heading">Categories</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo page_url('products.php'); ?>">Smartphones</a></li>
                    <li><a href="<?php echo page_url('products.php'); ?>">Accessories</a></li>
                    <li><a href="<?php echo page_url('products.php'); ?>">Smart Watches</a></li>
                    <li><a href="<?php echo page_url('products.php'); ?>">Tablets</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4 class="footer-heading">Contact</h4>
                <ul class="footer-contact">
                    <li><a href="<?php echo htmlspecialchars(SITE_MAP_URL); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(SITE_ADDRESS); ?></a></li>
                    <li><a href="tel:<?php echo preg_replace('/\D/', '', SITE_WHATSAPP_1); ?>"><?php echo htmlspecialchars(SITE_PHONE); ?></a></li>
                    <li><a href="mailto:<?php echo SITE_EMAIL; ?>"><?php echo htmlspecialchars(SITE_EMAIL); ?></a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p class="footer-copy">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. All rights reserved.</p>
            <p class="footer-powered">
                Website powered by
                <a href="<?php echo htmlspecialchars(POWERED_BY_URL); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(POWERED_BY_LABEL); ?></a>
            </p>
            <div class="footer-legal">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

<?php require_once __DIR__ . '/store-bot-widget.php'; ?>
