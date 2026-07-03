<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/store.php';

smartistore_session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'site-comment') {
    try {
        // Honeypot: bots fill the hidden field; pretend success.
        if (trim($_POST['website'] ?? '') !== '') {
            $_SESSION['site_comment_flash'] = ['type' => 'success', 'message' => 'Thank you! Your comment has been posted.'];
            header('Location: ' . page_url('index.php') . '#comments');
            exit;
        }

        $lastPosted = (int) ($_SESSION['site_comment_last'] ?? 0);
        if ($lastPosted > 0 && time() - $lastPosted < 60) {
            throw new RuntimeException('Please wait a moment before posting another comment.');
        }

        store_add_site_comment($_POST['name'] ?? '', $_POST['comment'] ?? '');
        $_SESSION['site_comment_last'] = time();
        $_SESSION['site_comment_flash'] = ['type' => 'success', 'message' => 'Thank you! Your comment has been posted.'];
    } catch (Throwable $e) {
        $_SESSION['site_comment_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
        $_SESSION['site_comment_old'] = [
            'name' => trim($_POST['name'] ?? ''),
            'comment' => trim($_POST['comment'] ?? ''),
        ];
    }
    header('Location: ' . page_url('index.php') . '#comments');
    exit;
}

$comment_flash = $_SESSION['site_comment_flash'] ?? null;
$comment_old = $_SESSION['site_comment_old'] ?? ['name' => '', 'comment' => ''];
unset($_SESSION['site_comment_flash'], $_SESSION['site_comment_old']);

$page_title = 'Home';
$body_class = 'page-home';
$extra_js = [
    asset_url('js/category-carousel.js'),
    asset_url('js/hero-mobile-slides.js'),
    asset_url('js/contact-whatsapp.js'),
];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/products-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main>
    <?php require_once __DIR__ . '/includes/hero-experience.php'; ?>

    <section class="home-search section section-white" id="search" aria-label="Search products">
        <div class="container">
            <div class="home-search__inner reveal-up">
                <div class="home-search__copy">
                    <span class="section-label">Find it fast</span>
                    <h2 class="section-title">Search our catalog</h2>
                    <p class="section-desc">Look up new products and pre-owned phones — suggestions appear as you type.</p>
                </div>
                <?php
                $site_search_id = 'home-search';
                $site_search_scope = 'all';
                $site_search_variant = 'hero';
                $site_search_action = page_url('products.php');
                $site_search_q = trim($_GET['q'] ?? '');
                $site_search_autocomplete = true;
                require __DIR__ . '/includes/site-search.php';
                ?>
            </div>
        </div>
    </section>

    <?php if (!empty($flagship_offers)): ?>
    <!-- Flagship offers (items with active promotional pricing) -->
    <section class="section section-white" id="featured">
        <div class="container">
            <div class="section-header reveal-up">
                <span class="section-label">Special Offers</span>
                <h2 class="section-title">Flagship Selection</h2>
                <p class="section-desc">Hand-picked deals with limited-time pricing — right after our latest arrivals.</p>
            </div>
            <div class="product-grid">
                <?php foreach ($flagship_offers as $i => $phone): ?>
                <?php
                $product_card_offer = true;
                include __DIR__ . '/includes/product-card.php';
                unset($product_card_offer);
                ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($category_slides)): ?>
    <?php require __DIR__ . '/includes/category-carousels.php'; ?>
    <?php else: ?>
    <section class="section section-gray" id="categories">
        <div class="container">
            <div class="section-header reveal-up">
                <span class="section-label">Shop by Category</span>
                <h2 class="section-title">Find Your Perfect Device</h2>
            </div>
            <p class="section-desc">No categories with products yet. Add categories and items in the <a href="<?php echo base_url('admin/'); ?>">admin panel</a>.</p>
        </div>
    </section>
    <?php endif; ?>

    <?php
    $site_comments = store_get_site_comments(30);
    require __DIR__ . '/includes/comments-marquee.php';
    ?>

    <!-- Why Choose Us -->
    <section class="section section-yellow" id="why-us">
        <div class="container">
            <div class="section-header section-header-center reveal-up">
                <span class="section-label">Why SmartIStore</span>
                <h2 class="section-title">The Premium Difference</h2>
                <p class="section-desc">More than a store — a destination for discerning tech enthusiasts.</p>
            </div>
            <div class="features-grid">
                <?php foreach ($why_choose_us as $i => $feature): ?>
                <div class="feature-card reveal-up" data-delay="<?php echo $i * 0.1; ?>">
                    <span class="feature-icon"><?php echo icon($feature['icon']); ?></span>
                    <h3 class="feature-title"><?php echo htmlspecialchars($feature['title']); ?></h3>
                    <p class="feature-desc"><?php echo htmlspecialchars($feature['desc']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- About preview -->
    <section class="section section-gray" id="about">
        <div class="container">
            <div class="about-preview reveal-up">
                <div class="about-preview__content">
                    <span class="section-label">About Us</span>
                    <h2 class="section-title">Your trusted mobile partner in Bandarawela</h2>
                    <p class="section-desc"><?php echo htmlspecialchars(SITE_TAGLINE); ?>. Visit our store for repairs, new devices, and accessories — with honest advice and genuine products.</p>
                    <a href="<?php echo page_url('about.php'); ?>" class="btn btn-primary">Learn more about us</a>
                </div>
                <div class="about-preview__card glass-card" aria-hidden="true">
                    <p class="about-preview__address"><a href="<?php echo htmlspecialchars(SITE_MAP_URL); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(SITE_ADDRESS); ?></a></p>
                    <p class="about-preview__phone"><?php echo htmlspecialchars(SITE_PHONE); ?></p>
                </div>
            </div>
            <?php
            $marquee_brands = store_get_brand_names();
            require __DIR__ . '/includes/brands-marquee.php';
            ?>
        </div>
    </section>

    <!-- Contact -->
    <section class="section section-gray" id="contact">
        <div class="container contact-grid">
            <div class="contact-info reveal-up">
                <span class="section-label">Get in Touch</span>
                <h2 class="section-title">Visit Our Store</h2>
                <p class="section-desc">Experience devices in person or reach out — we're here to help.</p>
                <ul class="contact-details">
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
                    <li>
                        <strong>Hours</strong>
                        <span>Mon – Sat: 10am – 8pm · Sun: 11am – 6pm</span>
                    </li>
                </ul>
            </div>
            <?php
            $contact_form_title = 'Send a Message';
            $contact_form_id_prefix = 'home-contact';
            require __DIR__ . '/includes/contact-whatsapp-form.php';
            ?>
            <?php require __DIR__ . '/includes/map-embed.php'; ?>

        </div>
    </section>

    <!-- Leave a comment -->
    <section class="section section-white" id="comments">
        <div class="container">
            <div class="section-header section-header-center reveal-up">
                <span class="section-label">Share Your Experience</span>
                <h2 class="section-title">Leave a Comment</h2>
                <p class="section-desc">Tell others about your experience with <?php echo htmlspecialchars(SITE_NAME); ?>.</p>
            </div>

            <?php if ($comment_flash): ?>
            <p class="comment-flash comment-flash--<?php echo htmlspecialchars($comment_flash['type']); ?>">
                <?php echo htmlspecialchars($comment_flash['message']); ?>
            </p>
            <?php endif; ?>

            <form class="comment-form glass-card reveal-up" method="post"
                  action="<?php echo page_url('index.php'); ?>#comments">
                <input type="hidden" name="form" value="site-comment">
                <input type="text" name="website" value="" class="comment-form__hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="form-group">
                    <label for="comment_name">Name</label>
                    <input type="text" id="comment_name" name="name" maxlength="60" placeholder="Your name"
                           value="<?php echo htmlspecialchars($comment_old['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="comment_text">Comment</label>
                    <textarea id="comment_text" name="comment" rows="4" maxlength="500"
                              placeholder="Share your experience…" required><?php echo htmlspecialchars($comment_old['comment']); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Post Comment</button>
            </form>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
