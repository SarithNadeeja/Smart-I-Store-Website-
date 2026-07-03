<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Contact';
$body_class = 'page-contact';
$extra_js = [asset_url('js/contact-whatsapp.js')];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main>
    <section class="page-hero page-hero-compact">
        <div class="container">
            <span class="section-label reveal-up">Contact</span>
            <h1 class="page-hero-title reveal-up">Get in <em>touch</em></h1>
            <p class="page-hero-desc reveal-up">Visit our showroom or message us on WhatsApp — we'd love to hear from you.</p>
        </div>
    </section>

    <section class="section section-gray">
        <div class="container contact-page-grid">
            <?php
            $contact_form_title = 'Send a Message';
            $contact_form_id_prefix = 'contact-page';
            require __DIR__ . '/includes/contact-whatsapp-form.php';
            ?>

            <aside class="contact-sidebar reveal-up">
                <div class="contact-card glass-card">
                    <h3>Store Details</h3>
                    <ul class="contact-details">
                        <li>
                            <strong>Address</strong>
                            <a href="<?php echo htmlspecialchars(SITE_MAP_URL); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(SITE_ADDRESS); ?></a>
                        </li>
                        <li>
                            <strong>WhatsApp</strong>
                            <a href="<?php echo htmlspecialchars(whatsapp_url(SITE_WHATSAPP_1)); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(SITE_WHATSAPP_1); ?></a>
                        </li>
                        <li>
                            <strong>Phone</strong>
                            <a href="tel:<?php echo preg_replace('/\D/', '', SITE_WHATSAPP_1); ?>"><?php echo htmlspecialchars(SITE_PHONE); ?></a>
                        </li>
                        <li>
                            <strong>Hours</strong>
                            <span>Mon – Sat: 10am – 8pm<br>Sun: 11am – 6pm</span>
                        </li>
                    </ul>
                </div>
                <?php $map_large = true; require __DIR__ . '/includes/map-embed.php'; ?>

            </aside>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
