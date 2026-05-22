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

$current_cat = get_queried_object();
$shop_url    = get_permalink(wc_get_page_id('shop'));

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

function bryson_pagination(int $total_pages, int $current_page): string
{
    if ($total_pages <= 1) return '';

    $params = $_GET;
    unset($params['paged']);
    $base = strtok($_SERVER['REQUEST_URI'], '?');

    $out = '<nav class="bryson-pagination">';

    if ($current_page > 1) {
        $params['paged'] = $current_page - 1;
        $out .= '<a href="' . esc_url($base . '?' . http_build_query($params)) . '" class="page-link">‹ Prev</a>';
    }

    $window = 2;
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i === 1 || $i === $total_pages || abs($i - $current_page) <= $window) {
            if ($i == $current_page) {
                $out .= '<span class="page-current">' . $i . '</span>';
            } else {
                $params['paged'] = $i;
                $out .= '<a href="' . esc_url($base . '?' . http_build_query($params)) . '" class="page-link">' . $i . '</a>';
            }
        } elseif (abs($i - $current_page) === $window + 1) {
            $out .= '<span class="page-ellipsis">…</span>';
        }
    }

    if ($current_page < $total_pages) {
        $params['paged'] = $current_page + 1;
        $out .= '<a href="' . esc_url($base . '?' . http_build_query($params)) . '" class="page-link">Next ›</a>';
    }

    $out .= '</nav>';
    return $out;
}
?>

<div class="tree-path">
    <?php echo build_breadcrumb($current_cat, $shop_url); ?>
</div>
<div class="d-flex flex-column flex-md-row-reverse gap-b w-100">
    <?php get_template_part('bryson-sidebar', null, ['current_cat' => $current_cat]); ?>

    <div class="catalog-wrap">

        <?php
        $search   = isset($_GET['sq'])      ? sanitize_text_field($_GET['sq'])    : '';
        $brand    = isset($_GET['brand'])    ? sanitize_text_field($_GET['brand']) : '';
        $instock  = isset($_GET['instock'])  ? '1'                                : '';
        $sort     = isset($_GET['sort']) && $_GET['sort'] === 'sku' ? 'sku' : 'name';
        $paged    = max(1, get_query_var('paged') ?: intval($_GET['paged'] ?? 1));
        $per_page = 30;
        ?>

        <?php if ($has_subcats && empty($search)) : ?>

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

            $query_args = [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'paged'          => $paged,
            ];

            // Sorting
            if ($sort === 'sku') {
                $sku_meta = ['sku_order' => ['key' => '_sku', 'compare' => 'EXISTS']];
                $query_args['orderby'] = 'sku_order';
                $query_args['order']   = 'ASC';
            } else {
                $query_args['orderby'] = 'title';
                $query_args['order']   = 'ASC';
            }

            $tax_query = [[
                'taxonomy'         => 'product_cat',
                'field'            => 'slug',
                'terms'            => $current_cat->slug,
                'include_children' => true,
            ]];

            if (!empty($brand)) {
                $tax_query[] = [
                    'taxonomy' => 'product_brand',
                    'field'    => 'slug',
                    'terms'    => $brand,
                ];
            }

            $query_args['tax_query'] = count($tax_query) > 1
                ? array_merge(['relation' => 'AND'], $tax_query)
                : $tax_query;

            // Meta query — combine instock + SKU ordering if needed
            $meta_query = [];
            if ($instock === '1') {
                $meta_query['stock_clause'] = ['key' => '_stock_status', 'value' => 'instock'];
            }
            if ($sort === 'sku') {
                $meta_query = array_merge($meta_query, $sku_meta);
            }
            if (!empty($meta_query)) {
                if (count($meta_query) > 1) $meta_query['relation'] = 'AND';
                $query_args['meta_query'] = $meta_query;
            }

            if (!empty($search)) {
                $search_where_filter = function ($where) use ($search) {
                    global $wpdb;
                    $like = '%' . $wpdb->esc_like($search) . '%';
                    $where .= $wpdb->prepare(
                        " AND ({$wpdb->posts}.post_title LIKE %s
                               OR EXISTS (
                                   SELECT 1 FROM {$wpdb->postmeta}
                                   WHERE post_id = {$wpdb->posts}.ID
                                     AND meta_key = '_sku'
                                     AND meta_value LIKE %s
                               ))",
                        $like,
                        $like
                    );
                    return $where;
                };
                add_filter('posts_where', $search_where_filter);
            }

            $wp_query = new WP_Query($query_args);

            if (!empty($search)) {
                remove_filter('posts_where', $search_where_filter);
            }

            $total_pages   = (int) $wp_query->max_num_pages;
            $total_results = (int) $wp_query->found_posts;

            $products = [];
            foreach ($wp_query->posts as $post) {
                $wc_product = wc_get_product($post->ID);
                if ($wc_product) $products[] = $wc_product;
            }

            $cart_quantities = [];
            foreach (WC()->cart->get_cart() as $cart_item) {
                $cart_quantities[$cart_item['product_id']] = $cart_item['quantity'];
            }

            // Build sort URL helpers
            $sort_params_name = array_merge($_GET, ['sort' => 'name', 'paged' => 1]);
            $sort_params_sku  = array_merge($_GET, ['sort' => 'sku',  'paged' => 1]);
            $base_url = strtok($_SERVER['REQUEST_URI'], '?');
            ?>

            <!-- Toolbar -->
            <div class="d-flex flex-column">
                <div class="catalog-toolbar">
                    <div class="toolbar-left">
                        <span class="toolbar-count">
                            <?php echo number_format($total_results); ?> item<?php echo $total_results !== 1 ? 's' : ''; ?>
                        </span>
                        <span class="toolbar-sep">·</span>
                        <span class="toolbar-sort-label">Sort:</span>
                        <a href="<?php echo esc_url($base_url . '?' . http_build_query($sort_params_name)); ?>"
                            class="sort-btn <?php echo $sort === 'name' ? 'active' : ''; ?>">Name</a>
                        <a href="<?php echo esc_url($base_url . '?' . http_build_query($sort_params_sku)); ?>"
                            class="sort-btn <?php echo $sort === 'sku' ? 'active' : ''; ?>">SKU</a>
                    </div>
                    <?php echo bryson_pagination($total_pages, $paged); ?>
                    <?php if (is_user_logged_in()) : ?>
                        <div class="toolbar-right">
                            <button id="bulk-add-to-cart-btn" type="button" class="bulk-add-btn">
                                Add to Cart
                            </button>
                        </div>
                    <?php endif; ?>
                </div>


                <div class="product-tree">
                    <div class="tree-content">
                        <form id="bulk-order-form" class="d-flex flex-wrap gap-3">
                            <?php if (empty($products)) : ?>
                                <div class="col-12">
                                    <p class="no-results">No products found.</p>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($products as $product) :
                                $pid             = $product->get_id();
                                $min_qty         = has_term('patterns', 'product_cat', $pid) ? 3 : 0;
                                $wholesale_price = get_post_meta($pid, 'wholesale_customer_wholesale_price', true);
                                $formatter       = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
                                $price           = is_user_logged_in()
                                    ? $formatter->formatCurrency((float)$wholesale_price, 'USD')
                                    : null;
                            ?>
                                <div class="col-3 col-md-2">
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
                        </form>

                        <?php echo bryson_pagination($total_pages, $paged); ?>

                    <?php endif; ?>
                    </div>
                </div>


            </div>

    </div><!-- /.catalog-wrap -->
</div>

<?php get_footer('shop'); ?>