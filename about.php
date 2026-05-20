<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/store.php';

$page_title = 'About Us';
$body_class = 'page-about';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="section-label reveal-up">Our Story</span>
            <h1 class="page-hero-title reveal-up">About <em><?php echo htmlspecialchars(SITE_NAME); ?></em></h1>
            <p class="page-hero-desc reveal-up"><?php echo htmlspecialchars(SITE_TAGLINE); ?></p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            <div class="about-main reveal-up">
                <h2 class="section-title">Who we are</h2>
                <p class="about-main__lead">
                    Based in <strong>Bandarawela</strong>, <?php echo htmlspecialchars(SITE_NAME); ?> is your local destination for
                    <strong>brand-new and second-hand smartphones</strong>, a full range of <strong>mobile accessories</strong>,
                    and professional <strong>phone repair</strong> — including display replacement, battery replacement, and other services you need to stay connected.
                </p>
                <p class="about-main__text">
                    We focus on fair pricing, genuine parts where it matters, and work you can trust. Whether you are buying your next device,
                    picking up a case or charger, or fixing a cracked screen, our team is here to help with clear advice and no unnecessary upsell.
                </p>
            </div>
        </div>
    </section>

    <section class="section section-gray">
        <div class="container">
            <div class="about-pillars">
                <article class="about-pillar reveal-up">
                    <div class="about-pillar__media">
                        <img src="<?php echo htmlspecialchars(asset_url('images/phones.png')); ?>" alt="Smartphones for sale at Smart I Store" width="640" height="400" loading="lazy">
                    </div>
                    <div class="about-pillar__body">
                        <h3 class="about-pillar__title">Selling phones</h3>
                        <p class="about-pillar__desc">
                            Browse <strong>new and pre-owned</strong> smartphones to match your budget. We help you compare models, check condition on used devices,
                            and choose a handset with warranty or service options that suit how you use your phone every day.
                        </p>
                    </div>
                </article>
                <article class="about-pillar about-pillar--reverse reveal-up" data-delay="0.08">
                    <div class="about-pillar__media">
                        <img src="<?php echo htmlspecialchars(asset_url('images/assets.png')); ?>" alt="Mobile accessories" width="640" height="400" loading="lazy">
                    </div>
                    <div class="about-pillar__body">
                        <h3 class="about-pillar__title">Accessories</h3>
                        <p class="about-pillar__desc">
                            From chargers and cables to cases, screen protectors, and audio gear, we stock practical accessories that protect your device
                            and keep it powered. Ask us for recommendations that fit your exact model — we sell items we would use ourselves.
                        </p>
                    </div>
                </article>
                <article class="about-pillar reveal-up" data-delay="0.16">
                    <div class="about-pillar__media">
                        <img src="<?php echo htmlspecialchars(asset_url('images/repair.png')); ?>" alt="Phone repair service" width="640" height="400" loading="lazy">
                    </div>
                    <div class="about-pillar__body">
                        <h3 class="about-pillar__title">Repairing</h3>
                        <p class="about-pillar__desc">
                            Fast, careful repairs for displays, batteries, charging issues, and more. We explain what failed, what we will replace,
                            and turnaround time before we start — so you can decide with confidence.
                        </p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="section section-white section-brands">
        <div class="container">
            <?php
            $marquee_brands = store_get_brand_names();
            require __DIR__ . '/includes/brands-marquee.php';
            ?>
        </div>
    </section>

    <section class="section section-gray about-visit">
        <div class="container">
            <div class="section-header section-header-center reveal-up">
                <span class="section-label">Find us</span>
                <h2 class="section-title">Visit our store</h2>
                <p class="section-desc"><?php echo htmlspecialchars(SITE_ADDRESS); ?></p>
            </div>
            <?php $map_large = true; require __DIR__ . '/includes/map-embed.php'; ?>
        </div>
    </section>

    <section class="section section-white">
        <div class="container about-grid">
            <div class="about-content reveal-up">
                <h2 class="section-title">Why locals choose us</h2>
                <p>Walk-in service, honest quotes, and a team that knows the devices we sell and repair. We are proud to be part of the Bandarawela community.</p>
                <ul class="about-stats">
                    <li><strong>Repair</strong><span>Display, battery &amp; more</span></li>
                    <li><strong>Sales</strong><span>New &amp; second-hand</span></li>
                    <li><strong>Accessories</strong><span>Retail range</span></li>
                    <li><strong>Local</strong><span>Visit anytime</span></li>
                </ul>
            </div>
            <div class="about-visual glass-card reveal-up">
                <div class="about-visual-inner" aria-hidden="true">
                    <span class="about-visual-text">Smart I<br>Store</span>
                </div>
                <ul class="about-contact-list">
                    <li>
                        <strong>Address</strong>
                        <a href="<?php echo htmlspecialchars(SITE_MAP_URL); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(SITE_ADDRESS); ?></a>
                    </li>
                    <li>
                        <strong>Phone</strong>
                        <a href="tel:<?php echo preg_replace('/\D/', '', SITE_WHATSAPP_1); ?>"><?php echo htmlspecialchars(SITE_PHONE); ?></a>
                    </li>
                    <li>
                        <strong>Email</strong>
                        <a href="mailto:<?php echo SITE_EMAIL; ?>"><?php echo htmlspecialchars(SITE_EMAIL); ?></a>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section section-gray">
        <div class="container">
            <div class="section-header section-header-center reveal-up">
                <span class="section-label">Our Values</span>
                <h2 class="section-title">What We Stand For</h2>
            </div>
            <div class="values-grid">
                <div class="value-card reveal-up">
                    <span class="feature-icon"><?php echo icon('shield'); ?></span>
                    <h3>Genuine products</h3>
                    <p>Authentic devices and accessories with proper warranty support when available.</p>
                </div>
                <div class="value-card reveal-up" data-delay="0.1">
                    <span class="feature-icon"><?php echo icon('support'); ?></span>
                    <h3>Expert service</h3>
                    <p>Friendly staff who explain options clearly and help you choose what fits your budget.</p>
                </div>
                <div class="value-card reveal-up" data-delay="0.2">
                    <span class="feature-icon"><?php echo icon('truck'); ?></span>
                    <h3>Local &amp; reliable</h3>
                    <p>A store you can visit in person — repairs, purchases, and questions handled on the spot.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section cta-strip">
        <div class="container cta-strip-inner reveal-up">
            <h2>Ready to browse our catalog?</h2>
            <a href="<?php echo page_url('products.php'); ?>" class="btn btn-primary">View products</a>
            <a href="<?php echo page_url('contact.php'); ?>" class="btn btn-ghost btn-ghost--light">Contact us</a>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
