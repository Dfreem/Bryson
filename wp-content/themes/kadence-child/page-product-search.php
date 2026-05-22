<?php

/**
 * Template Name: Product Search
 */
defined('ABSPATH') || exit;
get_header('shop');

$search  = isset($_GET['q'])      ? sanitize_text_field($_GET['q'])      : '';
$brand   = isset($_GET['brand'])  ? sanitize_text_field($_GET['brand'])  : '';
$instock = isset($_GET['instock']) ? '1'                                  : '';

// ── Build product query ──
$query_args = [
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
];

$tax_query = [];

if (!empty($brand)) {
    $tax_query[] = [
        'taxonomy' => 'product_brand',
        'field'    => 'slug',
        'terms'    => $brand,
    ];
}

if (!empty($tax_query)) {
    $query_args['tax_query'] = $tax_query;
}

if (!empty($search)) {
    $search_where_filter = function ($where) use ($search) {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(
            " AND ({$wpdb->posts}.post_title LIKE %s
               OR {$wpdb->posts}.post_excerpt LIKE %s
               OR {$wpdb->posts}.post_content LIKE %s
               OR EXISTS (
                   SELECT 1 FROM {$wpdb->postmeta}
                   WHERE post_id = {$wpdb->posts}.ID
                     AND meta_key = '_sku'
                     AND meta_value LIKE %s
               ))",
            $like,
            $like,
            $like,
            $like
        );
        return $where;
    };
    add_filter('posts_where', $search_where_filter);
}

if ($instock === '1') {
    $query_args['meta_query'] = [[
        'key'   => '_stock_status',
        'value' => 'instock',
    ]];
}

$wp_query = new WP_Query($query_args);

if (!empty($search)) {
    remove_filter('posts_where', $search_where_filter);
}

$products = [];
foreach ($wp_query->posts as $post) {
    $wc_product = wc_get_product($post->ID);
    if ($wc_product) {
        $products[] = $wc_product;
    }
}

$pcount = count($products);

$cart_quantities = [];
foreach (WC()->cart->get_cart() as $cart_item) {
    $cart_quantities[$cart_item['product_id']] = $cart_item['quantity'];
}
?>

<div class="tree-path">
    <?php echo '<a href="/shop/" style="margin-inline-end: 3em;"> < shop</a>'; ?>
    <?php if (!empty($search)) : ?>
        Search results for: "<?php echo esc_html($search); ?>"
        <span class="search-result-count">(<?php echo $pcount; ?> product<?php echo $pcount !== 1 ? 's' : ''; ?>)</span>
    <?php else : ?>
        All Products
    <?php endif; ?>
</div>

<div class="d-flex flex-column flex-md-row-reverse gap-b w-100">
    <div class="catalog-wrap">
        <div class="product-tree">
            <div class="tree-content">

                <form id="bulk-order-form" class="row row-cols-1 row-cols-md-3 row-cols-xl-4 g-3">
                    <?php if (!empty($products)) : ?>
                        <?php foreach ($products as $product) :
                            $pid             = $product->get_id();
                            $min_qty         = has_term('patterns', 'product_cat', $pid) ? 3 : 0;
                            $wholesale_price = get_post_meta($pid, '_regular_price', true);
                            $formatter       = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
                            $price           = is_user_logged_in()
                                ? $formatter->formatCurrency((float)$wholesale_price, 'USD')
                                : null;
                        ?>
                            <div class="col">
                                <div class="product-cell">
                                    <a href="<?php echo esc_url(get_permalink($pid)); ?>">
                                        <img
                                            src="<?php echo esc_url(wp_get_attachment_url($product->get_image_id())); ?>"
                                            alt="<?php echo esc_attr($product->get_name()); ?>"
                                            class="product-cell-img" />
                                    </a>
                                    <div class="product-cell-info">
                                        <a href="<?php echo esc_url(get_permalink($pid)); ?>" class="product-cell-name">
                                            <?php echo esc_html($product->get_name()); ?>
                                        </a>
                                        <span class="product-cell-sku"><?php echo esc_html($product->get_sku()); ?></span>
                                        <?php if (is_user_logged_in()) : ?>
                                            <span class="product-cell-price"><?php echo esc_html($price); ?></span>
                                            <?php if ($product->is_in_stock()) : ?>
                                                <div class="order-qty-wrap">
                                                    <input
                                                        type="number"
                                                        class="order-qty"
                                                        data-product-id="<?php echo esc_attr($pid); ?>"
                                                        data-min-qty="<?php echo esc_attr($min_qty); ?>"
                                                        value="<?php echo esc_attr($cart_quantities[$pid] ?? 0); ?>"
                                                        min="<?php echo esc_attr($min_qty); ?>" />
                                                    <button type="button" class="add-to-cart-btn btn" data-product-id="<?php echo esc_attr($pid); ?>">Add</button>
                                                </div>
                                            <?php else : ?>
                                                <span class="out-of-stock"><?php esc_html_e('Out of stock', 'woocommerce'); ?></span>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="product-cell-login">
                                                <a href="<?php echo esc_url(wp_login_url(get_permalink($pid))); ?>">Login</a>
                                                |
                                                <a href="<?php echo esc_url(wp_registration_url()); ?>">Register</a>
                                            </span>
                                            <a href="<?php echo esc_url(get_permalink($pid)); ?>" class="product-cell-view">Click to view</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="col-12">
                            <p class="no-results">
                                <?php if (!empty($search)) : ?>
                                    No products found for "<?php echo esc_html($search); ?>".
                                <?php else : ?>
                                    No products found.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </form>

            </div>
        </div>

        <?php get_template_part('bryson-sidebar'); ?>
    </div>

</div>

<?php get_footer('shop'); ?>