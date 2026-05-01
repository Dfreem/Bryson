<?php
defined('ABSPATH') || exit;
get_header('shop');

$current_cat   = get_queried_object();
// if (!($current_cat instanceof WP_Term)) {
//     $current_cat = null;
// }
// $new_stuff_slug = 'new-stuff';
// $new_stuff    = get_term_by('slug', $new_stuff_slug, 'product_cat');
// $new_stuff_id = ($new_stuff && !is_wp_error($new_stuff)) ? $new_stuff->term_id : 0;
$shop_url      = get_permalink(wc_get_page_id('shop'));

function distinct_by(array $items, callable $key_fn): array
{
    $seen = [];
    return array_values(array_filter($items, function ($item) use ($key_fn, &$seen) {
        $key = $key_fn($item);
        return !isset($seen[$key]) && ($seen[$key] = true);
    }));
}
$categories = get_terms(array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
    'parent'     => 0,
    'meta_key'   => 'order',
    'order'      => 'ASC',
    'orderby'    => 'meta_value_num',
));
$categories = distinct_by($categories, fn($cat) => strtolower($cat->name));
usort($categories, function ($a, $b) {
    $order_a = (int) get_term_meta($a->term_id, 'order', true);
    $order_b = (int) get_term_meta($b->term_id, 'order', true);
    if ($order_a === $order_b) {
        return strcasecmp($a->name, $b->name);
    }
    return $order_a - $order_b;
});
$count = count($categories);
// usort($categories, function ($a, $b) {
//     return strcasecmp($a->name, $b->name);
// });
function build_breadcrumb($cat, $shop_url)
{
    $output = '<a href="' . esc_url($shop_url) . '">shop</a>';

    if (!$cat instanceof WP_Term) {
        return $output; // just show "shop" with no crumbs
    }
    $crumbs  = array();
    $current = $cat;
    while ($current) {
        array_unshift($crumbs, array(
            'name' => $current->name,
            'url'  => get_term_link($current),
        ));

        $parent_id = isset($current->parent) ? $current->parent : 0;
        $current   = $parent_id ? get_term($parent_id, 'product_cat') : null;
    }
    foreach ($crumbs as $crumb) {
        $output .= '<span class="breadcrumb-separator"> ❯ </span>';
        $output .= ' <a href="' . esc_url($crumb['url']) . '">' . esc_html($crumb['name']) . '</a>';
    }
    return $output;
}
?>
<div class="tree-path">
    <?php echo build_breadcrumb($current_cat, $shop_url); ?>
</div>
<div class="catalog-wrap">
    <div class="product-tree">
        <div class="tree-content pb-5">
            <?php
            $search  = isset($_GET['q'])      ? sanitize_text_field($_GET['q']) : '';
            $instock = isset($_GET['instock']) ? '1' : '';
            $is_filtered = !empty($search) || !empty($instock);
            ?>

            <?php if ($is_filtered) : ?>
                <?php
                $query_args = [
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ];

                if (!empty($search)) {
                    $query_args['s']              = $search;
                    $query_args['search_columns'] = ['post_title'];
                }

                if (!empty($instock)) {
                    $query_args['meta_query'] = [[
                        'key'   => '_stock_status',
                        'value' => 'instock',
                    ]];
                }


                $query    = new WP_Query($query_args);
                $products = [];
                foreach ($query->posts as $post) {
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

                <form id="bulk-order-form" class="row row-cols-1 row-cols-md-3 row-cols-xl-4 g-3">
                    <?php if ($pcount === 0) : ?>
                        <p>No products found.</p>
                    <?php endif; ?>
                    <?php foreach ($products as $pi => $product) :
                        $pid = $product->get_id();
                    ?>
                        <div class="order-row">
                            <input
                                type="number"
                                class="order-qty"
                                data-product-id="<?php echo esc_attr($pid); ?>"
                                value="<?php echo esc_attr($cart_quantities[$pid] ?? 0); ?>"
                                min="0"
                                style="width:6em; margin: 0 8px;">
                            <a href="<?php echo esc_url(get_permalink($pid)); ?>" class="file">
                                <?php echo esc_html($product->get_name()); ?>
                            </a>
                            <img class="tree-row-image" width="80"
                                src="<?php echo esc_url(wp_get_attachment_url($product->get_image_id())); ?>"
                                alt="<?php echo esc_attr($product->get_name()); ?>" />
                        </div>
                    <?php endforeach; ?>
                </form>
            <?php else : ?>
                <?php foreach ($categories as $i => $cat) :
                    $is_last = ($i === $count - 1);
                    $prefix  = $is_last ? '└── ' : '├── ';
                ?>
                    <div class="tree-row">
                        <div style="display:flex; flex-direction:column;">
                            <div class="branch"><?php echo esc_html($prefix); ?></div>
                        </div>
                        <a href="<?php echo esc_url(get_term_link($cat, 'product_cat')); ?>" class="dir">
                            📁 <?php echo esc_html($cat->name); ?>
                            <!-- <?php echo esc_html($cat->term_order); ?> -->
                        </a>
                    </div>
                    <?php if (!$is_last) : ?>
                        <div class="tree-row">
                            <div class="branch">|</div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php get_template_part('bryson-sidebar', null, ['current_cat' => null]); ?>
    <?php get_footer('shop'); ?>
</div>