<?php
/**
 * Vertically scrolling customer comments (like the brands marquee, but vertical).
 * Expects $site_comments (list of rows with name + comment). Hidden when empty.
 */
$site_comments = $site_comments ?? [];
if (!$site_comments) {
    return;
}

$commentsAnimate = count($site_comments) > 2;
$commentsDuration = max(18, count($site_comments) * 6);
?>
<section class="section section-white section-comments" id="customer-comments">
    <div class="container">
        <div class="section-header section-header-center reveal-up">
            <span class="section-label">Customer Comments</span>
            <h2 class="section-title">What People Say About Us</h2>
        </div>
        <div class="comments-marquee reveal-up<?php echo $commentsAnimate ? ' comments-marquee--animated' : ''; ?>"
             style="--comments-duration: <?php echo (int) $commentsDuration; ?>s"
             aria-label="Customer comments">
            <div class="comments-marquee__viewport">
                <div class="comments-marquee__track">
                    <div class="comments-marquee__group">
                        <?php foreach ($site_comments as $row): ?>
                        <figure class="comment-card glass-card">
                            <blockquote class="comment-card__text"><?php echo nl2br(htmlspecialchars($row['comment'])); ?></blockquote>
                            <figcaption class="comment-card__name">— <?php echo htmlspecialchars($row['name']); ?></figcaption>
                        </figure>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($commentsAnimate): ?>
                    <div class="comments-marquee__group" aria-hidden="true">
                        <?php foreach ($site_comments as $row): ?>
                        <figure class="comment-card glass-card">
                            <blockquote class="comment-card__text"><?php echo nl2br(htmlspecialchars($row['comment'])); ?></blockquote>
                            <figcaption class="comment-card__name">— <?php echo htmlspecialchars($row['name']); ?></figcaption>
                        </figure>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <p class="comments-marquee__cta reveal-up">
            <a href="#comments" class="btn btn-ghost btn-sm">Leave a comment</a>
        </p>
    </div>
</section>
