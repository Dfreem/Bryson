<?php
defined('ABSPATH') || exit;
get_header('shop');

$current_cat   = get_queried_object();
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
// $categories = distinct_by($categories, fn($cat) => strtolower($cat->name));
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
<div class="d-flex flex-column flex-md-row-reverse gap-b w-100">
    <?php get_template_part('bryson-sidebar', null, ['current_cat' => null]); ?>
    <div class="catalog-wrap w-100">
        <div class="product-tree w-100">
            <div class="tree-content pb-5 w-100">
                <?php
                $search  = isset($_GET['q'])      ? sanitize_text_field($_GET['q']) : '';
                $instock = isset($_GET['instock']) ? '1' : '';
                $is_filtered = !empty($search) || !empty($instock);
                ?>
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
            </div>
        </div>
    </div>
</div>
<?php get_footer('shop'); ?>