<?php
/**
 * Reusable catalog search field.
 *
 * @var string $site_search_id
 * @var string $site_search_scope     products | preowned | all
 * @var string $site_search_variant    hero | inline | compact
 * @var string $site_search_action
 * @var string $site_search_q
 * @var bool   $site_search_autocomplete
 * @var bool   $site_search_live_filter  Client-side filter on catalog cards (no API)
 */
$site_search_id = $site_search_id ?? 'site-search';
$scope = $site_search_scope ?? 'all';
$variant = $site_search_variant ?? 'inline';
$action = $site_search_action ?? page_url('products.php');
$q = trim($site_search_q ?? '');
$autocomplete = !empty($site_search_autocomplete);
$liveFilter = !empty($site_search_live_filter);

$placeholder = match ($scope) {
    'preowned' => 'Search pre-owned phones by brand or model…',
    'products' => 'Search products by name, brand, or model…',
    default => 'Search phones, accessories & pre-owned…',
};

$label = match ($scope) {
    'preowned' => 'Search pre-owned',
    'products' => 'Search products',
    default => 'Search store',
};

$inputId = $site_search_id . '-input';
$listId = $site_search_id . '-results';
$formClass = 'site-search site-search--' . htmlspecialchars($variant, ENT_QUOTES, 'UTF-8');
?>
<form class="<?php echo $formClass; ?>"
      id="<?php echo htmlspecialchars($site_search_id, ENT_QUOTES, 'UTF-8'); ?>"
      action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>"
      method="get"
      role="search"
      data-scope="<?php echo htmlspecialchars($scope, ENT_QUOTES, 'UTF-8'); ?>"
      <?php if ($autocomplete): ?>data-autocomplete="1"<?php endif; ?>
      <?php if ($liveFilter): ?>data-live-filter="1"<?php endif; ?>>
    <label class="visually-hidden" for="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($label); ?>
    </label>
    <div class="site-search__field">
        <span class="site-search__icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </span>
        <input type="search"
               class="site-search__input"
               id="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>"
               name="q"
               value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
               placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>"
               autocomplete="off"
               autocapitalize="off"
               spellcheck="false"
               <?php if ($autocomplete): ?>
               role="combobox"
               aria-expanded="false"
               aria-controls="<?php echo htmlspecialchars($listId, ENT_QUOTES, 'UTF-8'); ?>"
               aria-autocomplete="list"
               <?php endif; ?>>
        <?php if ($q !== '' && $liveFilter): ?>
        <button type="button" class="site-search__clear" aria-label="Clear search">&times;</button>
        <?php elseif ($q !== ''): ?>
        <a href="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" class="site-search__clear" aria-label="Clear search">&times;</a>
        <?php else: ?>
        <button type="button" class="site-search__clear" hidden aria-label="Clear search">&times;</button>
        <?php endif; ?>
        <button type="submit" class="site-search__submit btn btn-primary btn-sm">Search</button>
    </div>
    <?php if ($autocomplete): ?>
    <div class="site-search__dropdown" id="<?php echo htmlspecialchars($listId, ENT_QUOTES, 'UTF-8'); ?>" role="listbox" hidden></div>
    <?php endif; ?>
</form>
