<?php
defined('ABSPATH') || exit;

$brands = get_terms(array(
    'taxonomy'   => 'product_brand',
    'hide_empty' => true,
    'orderby'    => 'name',
    'order'      => 'ASC',
));

$shop_url       = get_permalink(wc_get_page_id('shop'));
$search_page    = get_page_by_path('product-search');
$search_page_url = $search_page ? get_permalink($search_page->ID) : home_url('/product-search/');

$in_stock       = isset($_GET['instock']) ? $_GET['instock'] : '';
$global_search  = isset($_GET['q'])       ? $_GET['q']       : '';
$section_search = isset($_GET['sq'])      ? $_GET['sq']      : '';

$current_brand = isset($args['current_brand']) && ($args['current_brand'] instanceof WP_Term)
    ? $args['current_brand']
    : null;

$current_cat = isset($args['current_cat']) && ($args['current_cat'] instanceof WP_Term)
    ? $args['current_cat']
    : null;

$base_url    = $current_brand ? get_term_link($current_brand)
    : ($current_cat  ? get_term_link($current_cat)
        : $shop_url);

$current_path = strtok($_SERVER['REQUEST_URI'], '?');
?>

<!-- Mobile filter bar -->
<div class="mobile-filter-bar">
    <form method="GET" action="<?php echo esc_url($current_path); ?>" class="mobile-filter-body">
        <div class="mobile-filter-row">
            <input
                type="text"
                name="sq"
                class="sidebar-search-input"
                placeholder="Search products..."
                value="<?php echo esc_attr($section_search); ?>" />
            <button type="submit" class="sidebar-search-btn">Go</button>
            <a href="<?php echo esc_url($current_path); ?>" class="sidebar-clear-btn">Clear</a>
            <label class="sidebar-toggle-label" style="margin-left:auto;white-space:nowrap;">
                <input
                    type="checkbox"
                    name="instock"
                    value="1"
                    class="sidebar-toggle"
                    <?php checked($in_stock, '1'); ?>
                    onchange="this.form.submit()" />
                <span class="sidebar-toggle-text">In stock</span>
            </label>
        </div>
    </form>
</div>

<aside class="catalog-sidebar">

    <!-- Global search -->
    <form method="GET" action="<?php echo esc_url($search_page_url); ?>">
        <div class="sidebar-section shadow">
            <div class="sidebar-heading">Search</div>
            <div class="sidebar-search-wrap mb-2">
                <input
                    type="text"
                    name="q"
                    class="sidebar-search-input"
                    placeholder="Search all products..."
                    value="<?php echo esc_attr($global_search); ?>" />
                <input type="hidden" name="sq" value="<?php echo esc_attr($section_search); ?>" />
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="sidebar-search-btn">Go</button>
                <a href="<?php echo esc_url($search_page_url); ?>" class="sidebar-clear-btn">Clear</a>
            </div>
        </div>
    </form>

    <!-- Contextual search + filters -->
    <form method="GET" action="<?php echo esc_url($current_path); ?>">
        <div class="sidebar-section shadow">
            <div class="sidebar-heading">Search This Section</div>
            <div class="sidebar-search-wrap mb-2">
                <input
                    type="text"
                    name="sq"
                    class="sidebar-search-input sidebar-search-contextual"
                    placeholder="Search products..."
                    value="<?php echo esc_attr($section_search); ?>" />
                <input type="hidden" name="q" value="<?php echo esc_attr($global_search); ?>" />
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="sidebar-search-btn">Go</button>
                <a href="<?php echo esc_url($current_path); ?>" class="sidebar-clear-btn">Clear</a>
            </div>
        </div>

        <!-- In Stock Toggle -->
        <div class="sidebar-section shadow">
            <div class="sidebar-heading">Availability</div>
            <div class="sidebar-search-wrap">
                <label class="sidebar-toggle-label">
                    <input
                        type="checkbox"
                        name="instock"
                        value="1"
                        class="sidebar-toggle"
                        <?php checked($in_stock, '1'); ?> />
                    <span class="sidebar-toggle-text">In stock only</span>
                </label>
            </div>
        </div>
    </form>
</aside>

<script>
    (function() {
        var stockToggle = document.querySelector('.sidebar-toggle');

        if (stockToggle) {
            stockToggle.addEventListener('change', function() {
                var url = new URL(window.location.href);
                if (this.checked) {
                    url.searchParams.set('instock', '1');
                } else {
                    url.searchParams.delete('instock');
                }
                window.location.href = url.toString();
            });
        }
    })();
</script>