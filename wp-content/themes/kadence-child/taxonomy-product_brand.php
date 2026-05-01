<?php
defined('ABSPATH') || exit;
get_header('shop');

$current_brand = get_queried_object();
$shop_url      = get_permalink(wc_get_page_id('shop'));

function build_breadcrumb($brand, $shop_url)
{
    $output = '<a href="' . esc_url($shop_url) . '">shop</a>';
    if ($brand instanceof WP_Term) {
        $output .= '<span class="breadcrumb-separator"> ❯ </span>';
        $output .= '<a href="' . esc_url(get_term_link($brand)) . '">' . esc_html($brand->name) . '</a>';
    }
    return $output;
}

$search  = isset($_GET['q'])       ? sanitize_text_field($_GET['q']) : '';
$instock = isset($_GET['instock']) ? '1'                              : '';

$query_args = [
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
];

if (empty($search)) {
    $query_args['tax_query'] = [[
        'taxonomy' => 'product_brand',
        'field'    => 'slug',
        'terms'    => $current_brand->slug,
    ]];
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

if ($instock === '1') {
    $products = array_values(array_filter($products, fn($p) => $p->is_in_stock()));
}

$pcount = count($products);

$cart_quantities = [];
foreach (WC()->cart->get_cart() as $cart_item) {
    $cart_quantities[$cart_item['product_id']] = $cart_item['quantity'];
}
?>

<div class="tree-path">
    <?php echo build_breadcrumb($current_brand, $shop_url); ?>
</div>

<div class="catalog-wrap">
    <div class="product-tree">
        <div class="tree-content">

            <form id="bulk-order-form" class="row row-cols-1 row-cols-md-3 row-cols-xl-4 g-3">
                <?php if ($pcount === 0) : ?>
                    <p>No products found.</p>
                <?php endif; ?>
                <?php foreach ($products as $product) :
                    $pid             = $product->get_id();
                    $wholesale_price = get_post_meta($pid, '_wholesale_price', true);
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
                                        <input
                                            type="number"
                                            class="order-qty"
                                            data-product-id="<?php echo esc_attr($pid); ?>"
                                            value="<?php echo esc_attr($cart_quantities[$pid] ?? 0); ?>"
                                            min="0">
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
            </form>

        </div>
    </div>

    <?php get_template_part('bryson-sidebar', null, ['current_cat' => null, 'current_brand' => $current_brand]); ?>

</div>

<?php get_footer('shop'); ?>