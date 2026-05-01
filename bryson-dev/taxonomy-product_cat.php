<?php
defined('ABSPATH') || exit;
get_header('shop');

function distinct_by(array $items, callable $key_fn): array
{
    $seen = [];
    return array_values(array_filter($items, function ($item) use ($key_fn, &$seen) {
        $key = $key_fn($item);
        return !isset($seen[$key]) && ($seen[$key] = true);
    }));
}

$current_cat    = get_queried_object();
$shop_url       = get_permalink(wc_get_page_id('shop'));

$subcategories = [];

if ($current_cat && $current_cat->taxonomy === 'product_cat') {
    $subcategories = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => $current_cat->term_id,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);
    $subcategories = distinct_by($subcategories, fn($term) => strtolower($term->name));
}

$has_subcats = !empty($subcategories) && !is_wp_error($subcategories);

function build_breadcrumb($cat, $shop_url)
{
    $crumbs  = [];
    $current = $cat;
    while ($current) {
        array_unshift($crumbs, [
            'name' => $current->name,
            'url'  => get_term_link($current),
        ]);
        $parent_id = $current->parent;
        $current   = $parent_id ? get_term($parent_id, 'product_cat') : null;
    }
    $output = '<a href="' . esc_url($shop_url) . '">shop</a>';
    foreach ($crumbs as $crumb) {
        $output .= '<span class="breadcrumb-separator"> ❯ </span>';
        $output .= '<a href="' . esc_url($crumb['url']) . '">' . esc_html($crumb['name']) . '</a>';
    }
    return $output;
}
?>

<div class="tree-path">
    <?php echo build_breadcrumb($current_cat, $shop_url); ?>
</div>

<div class="catalog-wrap">
    <div class="product-tree">
        <div class="tree-content">

            <?php if ($has_subcats) : ?>

                <?php
                $count = count($subcategories);
                foreach ($subcategories as $i => $cat) :
                    $is_last = ($i === $count - 1);
                    $prefix  = $is_last ? '└── ' : '├── ';
                ?>
                    <div class="tree-row">
                        <div style="display:flex; flex-direction:column;">
                            <div class="branch">|</div>
                            <div class="branch"><?php echo esc_html($prefix); ?></div>
                        </div>
                        <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="dir">
                            📁 <?php echo esc_html($cat->name); ?>
                        </a>
                    </div>
                <?php endforeach; ?>

            <?php else : ?>

                <?php
                // ── Read sidebar filter params ──
                $search  = isset($_GET['q'])       ? sanitize_text_field($_GET['q'])       : '';
                $brand   = isset($_GET['brand'])    ? sanitize_text_field($_GET['brand'])   : '';
                $instock = isset($_GET['instock'])  ? '1'                                   : '';

                // ── Build product query ──
                $query_args = [
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ];

                $tax_query = [];

                if (empty($search)) {
                    $tax_query[] = [
                        'taxonomy' => 'product_cat',
                        'field'    => 'slug',
                        'terms'    => $current_cat->slug,
                    ];
                }

                if (!empty($brand)) {
                    $tax_query[] = [
                        'taxonomy' => 'product_brand',
                        'field'    => 'slug',
                        'terms'    => $brand,
                    ];
                }

                if (!empty($tax_query)) {
                    $query_args['tax_query'] = count($tax_query) > 1
                        ? array_merge(['relation' => 'AND'], $tax_query)
                        : $tax_query;
                }

                if (!empty($search)) {
                    $query_args['s'] = $search;
                }

                if ($instock === '1') {
                    $query_args['meta_query'] = [[
                        'key'   => '_stock_status',
                        'value' => 'instock',
                    ]];
                }

                $wp_query = new WP_Query($query_args);
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

                <form id="bulk-order-form" class="row row-cols-1 row-cols-md-3 row-cols-xl-4 g-3">
                    <?php foreach ($products as $pi => $product) :
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

            <?php endif; ?>
        </div>
    </div>

    <?php get_template_part('bryson-sidebar', null, ['current_cat' => $current_cat]); ?>

</div>

<?php get_footer('shop'); ?>