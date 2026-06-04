<?php
/** Floating Q&A bot — public site only (included from footer). */
$store_bot_api = base_url('api/chatbot.php');
$store_bot_logo = asset_url('images/botlogo.png');
?>
<div
    id="store-bot"
    class="store-bot"
    data-api="<?php echo htmlspecialchars($store_bot_api); ?>"
>
    <button
        type="button"
        id="store-bot-toggle"
        class="store-bot__toggle"
        aria-expanded="false"
        aria-controls="store-bot-panel"
        aria-label="Open Smart I Store assistant"
    >
        <img src="<?php echo htmlspecialchars($store_bot_logo); ?>" alt="" width="76" height="76" loading="lazy" decoding="async">
    </button>
    <div id="store-bot-panel" class="store-bot__panel" hidden role="dialog" aria-label="Smart I Store assistant" aria-modal="true">
        <header class="store-bot__head">
            <div class="store-bot__head-title">
                <img src="<?php echo htmlspecialchars($store_bot_logo); ?>" alt="" width="32" height="32" loading="lazy">
                <span>Smart I Store</span>
            </div>
            <button type="button" id="store-bot-close" class="store-bot__close" aria-label="Close">&times;</button>
        </header>
        <div id="store-bot-body" class="store-bot__body">
            <div id="store-bot-messages" class="store-bot__messages" aria-live="polite"></div>
            <div id="store-bot-composer" class="store-bot__composer"></div>
        </div>
    </div>
</div>
