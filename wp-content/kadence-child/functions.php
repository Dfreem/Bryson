<?php
// Workaround for WooCommerce 10.7.0 bug: empty product_type tax_query returns zero results.

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'kadence-parent-style',
        get_template_directory_uri() . '/style.css'
    );
    wp_enqueue_style(
        'bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        array(),
        '5.3.3'
    );
    wp_enqueue_script(
        'bryson-order-qty',
        get_stylesheet_directory_uri() . '/assets/js/order-qty.js',
        array(),
        '1.0',
        true
    );
    wp_localize_script('bryson-order-qty', 'brysonOrder', array(
        'nonce' => wp_create_nonce('order_qty_nonce'),
    ));
});

add_action('pre_get_posts', function ($query) {
    if (!is_admin() && $query->is_main_query() && is_shop()) {
        $query->set('post_type', 'product');
        if (isset($_GET['min_qty']) && intval($_GET['min_qty']) > 0) {
            $meta_query = $query->get('meta_query') ?: [];
            $meta_query[] = [
                'key'     => '_stock',
                'value'   => intval($_GET['min_qty']),
                'compare' => '>=',
                'type'    => 'NUMERIC'
            ];
            $query->set('meta_query', $meta_query);
        }
    }
});

function bryson_build_product_args(): array
{
    $search = sanitize_text_field($_GET['s'] ?? '');
    $cat    = intval($_GET['cat'] ?? 0);
    $brand  = sanitize_text_field($_GET['brand'] ?? '');
    $args = [
        'status'  => 'publish',
        'limit'   => -1,
        'orderby' => 'title',
        'order'   => 'ASC',
    ];
    if (!empty($search)) {
        $args['search'] = $search;
    }
    $tax_query = [];
    if (!empty($cat)) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => [$cat],
        ];
    }
    if (!empty($brand)) {
        $tax_query[] = [
            'taxonomy' => 'product_brand',
            'field'    => 'slug',
            'terms'    => [$brand],
        ];
    }
    if (!empty($tax_query)) {
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        $args['tax_query'] = $tax_query;
    }
    return $args;
}
function bryson_update_order_qty()
{
    check_ajax_referer('order_qty_nonce', 'nonce');
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity   = isset($_POST['quantity'])   ? intval($_POST['quantity'])   : 0;
    if (!$product_id) {
        wp_send_json_error('Invalid product');
    }
    $cart_item_key = null;
    foreach (WC()->cart->get_cart() as $key => $item) {
        if ($item['product_id'] === $product_id) {
            $cart_item_key = $key;
            break;
        }
    }
    if ($quantity <= 0) {
        if ($cart_item_key) {
            WC()->cart->remove_cart_item($cart_item_key);
        }
    } elseif ($cart_item_key) {
        WC()->cart->set_quantity($cart_item_key, $quantity);
    } else {
        WC()->cart->add_to_cart($product_id, $quantity);
    }
    wp_send_json_success([
        'cart_count' => WC()->cart->get_cart_contents_count(),
    ]);
}


add_action('wp_ajax_update_order_qty', 'bryson_update_order_qty');
add_action('wp_ajax_nopriv_update_order_qty', 'bryson_update_order_qty');
