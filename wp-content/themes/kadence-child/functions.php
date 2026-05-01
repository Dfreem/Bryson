<?php
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        array(),
        '5.3.3'
    );
    wp_enqueue_style(
        'kadence-parent-style',
        get_template_directory_uri() . '/style.css'
    );
    wp_enqueue_style(
        'kadence-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('kadence-parent-style'),
        wp_get_theme()->get('Version')
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

add_filter('woocommerce_product_get_price', 'wholesale_price', 10, 2);

function wholesale_price($price, $product)
{
    if (current_user_can('wholesale_customer')) {
        $wholesale = $product->get_meta('_wholesale_price');
        return $wholesale ?: $price;
    }
    return $price;
}

add_action('init', 'create_wholesale_role');

function create_wholesale_role()
{
    if (!get_role('wholesale_customer')) {
        add_role(
            'wholesale_customer',
            'Wholesale Customer',
            array(
                'read' => true,
                'edit_posts' => false,
            )
        );
    }
}
add_filter('woocommerce_account_menu_items', 'custom_account_menu');

function custom_account_menu($items)
{
    return array(
        'orders'          => 'Orders',
        'edit-account'    => 'Account Details',
        'customer-logout' => 'Logout',
    );
}
add_action('init', 'register_store_post_type');

function register_store_post_type()
{
    register_post_type('store', array(
        'labels' => array(
            'name'          => 'Stores',
            'singular_name' => 'Store',
            'add_new_item'  => 'Add New Store',
            'edit_item'     => 'Edit Store',
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'supports'     => array('title'),
        'menu_icon'    => 'dashicons-store',
    ));
}

add_action('wp_ajax_get_stores', 'get_stores_by_state');
add_action('wp_ajax_nopriv_get_stores', 'get_stores_by_state');

function get_stores_by_state()
{
    $state   = sanitize_text_field($_POST['state']);
    $country = sanitize_text_field($_POST['country']); // NEW

    $meta_query = array(
        array(
            'key'   => '_store_state',
            'value' => $state,
        )
    );

    // If you want to filter by country too:
    // if (!empty($country)) {
    //     $meta_query[] = array(
    //         'key'   => '_store_country',
    //         'value' => $country,
    //     );
    // }

    $query = new WP_Query(array(
        'post_type'      => 'store',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => $meta_query,
        'orderby'        => 'meta_value',
        'meta_key'       => '_store_city',
        'order'          => 'ASC',
    ));

    $stores = array();
    foreach ($query->posts as $post) {
        $stores[] = array(
            'name'    => $post->post_title,
            'city'    => get_post_meta($post->ID, '_store_city', true),
            'zip'     => get_post_meta($post->ID, '_store_zip', true),
            'address' => get_post_meta($post->ID, '_store_address', true),
            'phone'   => get_post_meta($post->ID, '_store_phone', true),
            'website' => get_post_meta($post->ID, '_store_website', true),
        );
    }

    wp_send_json_success($stores);
}


add_shortcode('store_locator', 'store_locator_shortcode');

function store_locator_shortcode()
{
    wp_enqueue_script('d3', 'https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js', array(), null, true);
    wp_enqueue_script('topojson', 'https://cdnjs.cloudflare.com/ajax/libs/topojson/3.0.2/topojson.min.js', array('d3'), null, true);
    wp_enqueue_script('store-locator', get_stylesheet_directory_uri() . '/assets/js/store-locator.js', array('d3', 'topojson'), null, true);
    wp_localize_script('store-locator', 'storeLocator', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('store_locator_nonce'),
    ));

    ob_start(); ?>
    <div id="store-locator-wrap" style="display:flex;gap:24px;align-items:flex-start;">
        <div id="store-map" style="flex:0 0 60%;max-width:60%;"></div>
        <div id="store-results" style="flex:1;">
            <div style="display:flex;gap:16px;margin:8px 0;font-size:13px;color:#666;">
                <span style="display:flex;align-items:center;gap:5px;">
                    <span style="width:12px;height:12px;background:#378ADD;border-radius:2px;display:inline-block;"></span> Click a state
                </span>
            </div>
            <div id="store-state-header" style="display:none;font-size:14px;color:#666;border-bottom:1px solid #eee;padding-bottom:8px;margin-top:24px;"></div>
            <div id="store-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px;margin-top:16px;overflow-y: auto; min-height: 60vh; max-height: 60vh;"></div>
        </div>
    </div>
<?php return ob_get_clean();
};

require_once get_stylesheet_directory() . '/includes/wholesale-register-functions.php';
require_once get_stylesheet_directory() . '/includes/header-functions.php';
