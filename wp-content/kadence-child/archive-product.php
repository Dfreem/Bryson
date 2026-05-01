<?php
defined('ABSPATH') || exit;
get_header('shop');

$new_stuff_id = 591;
$new_stuff    = get_term($new_stuff_id, 'product_cat');

$categories = get_terms(array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'parent'     => 0,
    'exclude'    => array(get_option('default_product_cat'), $new_stuff_id),
    'orderby'    => 'name',
    'order'      => 'ASC',
));
$count = count($categories);
?>

<div class="catalog-wrap">
    <div class="product-tree">
        <div class="tree-content">
            <?php
            $search  = isset($_GET['q'])       ? sanitize_text_field($_GET['q'])       : '';
            $brand   = isset($_GET['brand'])   ? sanitize_text_field($_GET['brand'])   : '';
            // $instock = isset($_GET['instock']) ? sanitize_text_field($_GET['instock']) : '';

            $is_filtered = !empty($search) || !empty($brand);
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

                if (!empty($brand)) {
                    $query_args['tax_query'] = [[
                        'taxonomy' => 'product_brand',
                        'field'    => 'slug',
                        'terms'    => $brand,
                    ]];
                }

                // if (!empty($instock)) {
                //     $query_args['meta_query'] = [[
                //         'key'   => '_stock_status',
                //         'value' => 'instock',
                //     ]];
                // }

                $cart_quantities = [];
                foreach (WC()->cart->get_cart() as $cart_item) {
                    $cart_quantities[$cart_item['product_id']] = $cart_item['quantity'];
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
                ?>

                <form id="bulk-order-form">
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
                                style="width:50px; margin: 0 8px;">
                            <a href="<?php echo esc_url(get_permalink($pid)); ?>" class="file">
                                <?php echo esc_html($product->get_name()); ?>
                            </a>
                            <img class="tree-row-image" width="80"
                                src="<?php echo esc_url(wp_get_attachment_image_url($product->get_image_id(), 'thumbnail')); ?>"
                                alt="<?php echo esc_attr($product->get_name()); ?>" />
                        </div>
                    <?php endforeach; ?>
                </form>
            <?php else : ?>
                <div class="tree-path">
                    <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>">shop</a>
                </div>

                <?php if ($new_stuff && !is_wp_error($new_stuff)) : ?>
                    <div class="tree-row">
                        <div style="display:flex; flex-direction:column;">
                            <div class="branch">├── </div>
                        </div>
                        <a href="<?php echo esc_url(get_term_link($new_stuff)); ?>" class="dir dir--featured">
                            ✨ <?php echo esc_html($new_stuff->name); ?>
                        </a>
                    </div>
                    <div class="tree-row">
                        <div class="branch">|</div>
                    </div>
                <?php endif; ?>

                <?php foreach ($categories as $i => $cat) :
                    $is_last = ($i === $count - 1);
                    $prefix  = $is_last ? '└── ' : '├── ';
                ?>
                    <div class="tree-row">
                        <div style="display:flex; flex-direction:column;">
                            <div class="branch"><?php echo esc_html($prefix); ?></div>
                        </div>
                        <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="dir">
                            📁 <?php echo esc_html($cat->name); ?>
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
    <?php get_template_part('bryson-sidebar'); ?>
</div>
<?php get_footer('shop'); ?>