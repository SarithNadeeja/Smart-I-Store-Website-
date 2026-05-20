<?php
$page_title = 'Contact';
$body_class = 'page-contact';
$submitted = isset($_GET['name']) && isset($_GET['email']);
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main>
    <section class="page-hero page-hero-compact">
        <div class="container">
            <span class="section-label reveal-up">Contact</span>
            <h1 class="page-hero-title reveal-up">Get in <em>touch</em></h1>
            <p class="page-hero-desc reveal-up">Visit our showroom or send us a message — we'd love to hear from you.</p>
        </div>
    </section>

    <section class="section section-gray">
        <div class="container contact-page-grid">
            <?php if ($submitted): ?>
            <div class="alert alert-success reveal-up" role="status">
                Thank you, <?php echo htmlspecialchars($_GET['name']); ?>! We'll reply to <?php echo htmlspecialchars($_GET['email']); ?> shortly.
            </div>
            <?php endif; ?>

            <form class="contact-form glass-card reveal-up" action="<?php echo page_url('contact.php'); ?>" method="get">
                <h2 class="form-title">Send a Message</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" placeholder="John Doe" value="<?php echo htmlspecialchars($_GET['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="you@example.com" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="phone">Phone (optional)</label>
                    <input type="tel" id="phone" name="phone" placeholder="+1 555 000 0000" value="<?php echo htmlspecialchars($_GET['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <select id="subject" name="subject">
                        <option value="general">General Inquiry</option>
                        <option value="product">Product Question</option>
                        <option value="support">Support</option>
                        <option value="tradein">Trade-In</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="5" placeholder="How can we help?" required><?php echo htmlspecialchars($_GET['message'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send Message</button>
            </form>

            <aside class="contact-sidebar reveal-up">
                <div class="contact-card glass-card">
                    <h3>Store Details</h3>
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
