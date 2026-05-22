<?php
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        array(),
        '5.3.3'
    );
    wp_enqueue_script(
        'bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        array(),
        '5.3.3',
        true
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

function consoleLog($val)
{
    echo '
    <script>
        console.log(' . json_encode($val) . ')
    </script>
    ';
}

function bryson_update_order_qty()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
    }
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
    if ($quantity > 0 && $quantity < 3 && has_term('patterns', 'product_cat', $product_id)) {
        wp_send_json_error('Patterns require a minimum of 3 per item.');
    }
    if ($quantity <= 0) {
        if ($cart_item_key) {
            WC()->cart->remove_cart_item($cart_item_key);
        }
    } elseif ($cart_item_key) {
        $current = WC()->cart->get_cart()[$cart_item_key]['quantity'];
        WC()->cart->set_quantity($cart_item_key, $current + $quantity);
    } else {
        WC()->cart->add_to_cart($product_id, $quantity);
    }
    wp_send_json_success([
        'cart_count'   => WC()->cart->get_cart_contents_count(),
        'product_name' => get_the_title($product_id),
    ]);
}


add_action('wp_ajax_update_order_qty', 'bryson_update_order_qty');
add_action('wp_ajax_nopriv_update_order_qty', 'bryson_update_order_qty');
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
        'edit-address/billing' => 'Billing Address',
        'edit-address/shipping' => 'Shipping Address',
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

add_filter('manage_store_posts_columns', function ($columns) {
    $columns['store_address'] = 'Address';
    $columns['store_city']    = 'City';
    $columns['store_state']   = 'State';
    $columns['store_phone']   = 'Phone';
    return $columns;
});

add_action('manage_store_posts_custom_column', function ($column, $post_id) {
    switch ($column) {
        case 'store_address':
            echo esc_html(get_post_meta($post_id, '_store_address', true));
            break;
        case 'store_city':
            echo esc_html(get_post_meta($post_id, '_store_city', true));
            break;
        case 'store_state':
            echo esc_html(get_post_meta($post_id, '_store_state', true));
            break;
        case 'store_phone':
            echo esc_html(get_post_meta($post_id, '_store_phone', true));
            break;
    }
}, 10, 2);

add_action('add_meta_boxes', function () {
    add_meta_box(
        'store_details',
        'Store Details',
        'render_store_meta_box',
        'store',
        'normal',
        'high'
    );
});

function render_store_meta_box($post)
{
    wp_nonce_field('save_store_meta', 'store_meta_nonce');
    $fields = [
        '_store_address' => 'Address',
        '_store_city'    => 'City',
        '_store_state'   => 'State / Province',
        '_store_zip'     => 'ZIP / Postal Code',
        '_store_country' => 'Country',
        '_store_phone'   => 'Phone',
        '_store_website' => 'Website',
    ];
    foreach ($fields as $key => $label) {
        $value = get_post_meta($post->ID, $key, true);
        echo '<p>';
        echo '<label style="display:inline-block;width:140px;font-weight:600;">' . esc_html($label) . '</label>';
        echo '<input type="text" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" style="width:60%;" />';
        echo '</p>';
    }
}

add_action('save_post_store', function ($post_id) {
    if (! isset($_POST['store_meta_nonce'])) return;
    if (! wp_verify_nonce($_POST['store_meta_nonce'], 'save_store_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $fields = [
        '_store_address',
        '_store_city',
        '_store_state',
        '_store_zip',
        '_store_country',
        '_store_phone',
        '_store_website',
    ];
    foreach ($fields as $key) {
        if (isset($_POST[$key])) {
            update_post_meta($post_id, $key, sanitize_text_field($_POST[$key]));
        }
    }
});

//  Adds the Staff Guide to the admin menu
add_action('admin_menu', function () {
    $pdf_url = get_stylesheet_directory_uri() . '/bryson_wp_guide.html';

    add_menu_page(
        'Staff Guide',
        'Staff Guide',
        'manage_options',
        $pdf_url,
        '',
        'dashicons-book-alt',
        3
    );
});

add_action('admin_head', function () {
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var link = document.querySelector("#adminmenu a[href*=\"bryson_wp_guide\"]");
        if (link) link.setAttribute("target", "_blank");
    });
    </script>';
});

// Flag pattern products on add to cart
add_filter('woocommerce_add_cart_item_data', 'bryson_add_pattern_cart_data', 10, 2);
function bryson_add_pattern_cart_data($cart_item_data, $product_id)
{
    if (has_term('patterns', 'product_cat', $product_id)) {
        $cart_item_data['is_pattern'] = true;
    }
    return $cart_item_data;
}

// Server-side validation reads the flag directly
add_action('woocommerce_store_api_validate_cart_item', 'bryson_block_cart_min_qty_check', 10, 2);
function bryson_block_cart_min_qty_check($product, $cart_item)
{
    if (empty($cart_item['is_pattern'])) return;

    if ($cart_item['quantity'] < 3) {
        throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
            'bryson_min_quantity',
            sprintf('"%s" requires a minimum of 3 units.', $product->get_name()),
            400
        );
    }
}

// Mark pattern product rows in the classic cart table with a CSS class.
add_filter('woocommerce_cart_item_class', 'bryson_pattern_cart_row_class', 10, 2);
function bryson_pattern_cart_row_class($class, $cart_item)
{
    if (has_term('patterns', 'product_cat', $cart_item['product_id'])) {
        $class .= ' bryson-pattern-item';
    }
    return $class;
}

// Client-side validation
add_action('wp_footer', 'bryson_cart_min_qty_script');
function bryson_cart_min_qty_script()
{
    if (!is_cart()) return;

    $has_patterns = false;
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (has_term('patterns', 'product_cat', $cart_item['product_id'])) {
            $has_patterns = true;
            break;
        }
    }
    if (!$has_patterns) return;
?>
    <script>
        (function() {
            const MIN = 3;

            function getBtn() {
                // Classic cart uses an <a> inside .wc-proceed-to-checkout.
                return document.querySelector('.wc-proceed-to-checkout .checkout-button, .wc-block-cart__submit-button');
            }

            function hasInvalidQuantities() {
                // Classic cart: rows marked by bryson_pattern_cart_row_class.
                const rows = document.querySelectorAll('tr.bryson-pattern-item');
                if (rows.length > 0) {
                    for (const row of rows) {
                        const input = row.querySelector('input.qty');
                        const qty = input ? parseInt(input.value, 10) : 0;
                        if (qty < MIN) return true;
                    }
                    return false;
                }

                // Block cart fallback.
                try {
                    const cartItems = wp.data.select('wc/store/cart').getCartItems();
                    if (cartItems && cartItems.length) {
                        return cartItems.some(item => item.quantity < MIN &&
                            item.categories && item.categories.some(c => c.slug === 'patterns'));
                    }
                } catch (e) {}

                return false;
            }

            function updateBtn() {
                const btn = getBtn();
                if (!btn) return;
                const invalid = hasInvalidQuantities();
                btn.classList.toggle('disabled', invalid);
                btn.style.pointerEvents = invalid ? 'none' : '';
                btn.setAttribute('aria-disabled', String(invalid));
            }

            document.addEventListener('DOMContentLoaded', function() {
                updateBtn();

                // Re-check after WooCommerce classic cart AJAX update.
                if (typeof jQuery !== 'undefined') {
                    jQuery(document.body).on('updated_cart_totals wc_cart_button_updated', updateBtn);
                }

                // Re-check on any qty input change before user clicks update.
                document.addEventListener('change', function(e) {
                    if (e.target && e.target.matches('tr.bryson-pattern-item input.qty')) {
                        updateBtn();
                    }
                });

                // Hard block the click even if .disabled class is ignored.
                document.addEventListener('click', function(e) {
                    const btn = getBtn();
                    if (btn && btn.contains(e.target) && hasInvalidQuantities()) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                    }
                }, true);
            });
        })();
    </script>
<?php
}

add_action('wp_footer', 'bryson_cart_toast');
function bryson_cart_toast()
{
?>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
        <div id="bryson-cart-toast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
            <div class="d-flex">
                <div class="toast-body" id="bryson-toast-body">
                    Item added to cart.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <script>
        window.brysonShowToast = function(message, type) {
            const toastEl = document.getElementById('bryson-cart-toast');
            const toastBody = document.getElementById('bryson-toast-body');
            if (toastEl && toastBody) {
                toastEl.classList.remove('text-bg-success', 'text-bg-danger');
                toastEl.classList.add(type === 'error' ? 'text-bg-danger' : 'text-bg-success');
                toastBody.textContent = message || 'Item added to cart.';
                bootstrap.Toast.getOrCreateInstance(toastEl).show();
            }
        };

        // Standard WooCommerce AJAX add to cart
        jQuery(document).on('added_to_cart', function(e, fragments, cart_hash, $button) {
            const name = $button && $button.closest('form').find('.product_title').text();
            window.brysonShowToast(name ? name + ' added to cart.' : 'Item added to cart.');
        });
    </script>
<?php
}

add_action('woocommerce_before_single_product', 'bryson_in_cart_notice');
function bryson_in_cart_notice()
{
    global $product;
    if (!$product) return;

    $product_id = $product->get_id();
    $cart_qty = 0;

    foreach (WC()->cart->get_cart() as $cart_item) {
        if ($cart_item['product_id'] === $product_id) {
            $cart_qty = $cart_item['quantity'];
            break;
        }
    }

    if ($cart_qty > 0) {
        wc_add_notice(
            sprintf('You already have %d of this item in your cart.', $cart_qty),
            'notice'
        );
    }
}

add_filter('woocommerce_is_purchasable', function ($purchasable, $product) {
    if (!is_user_logged_in()) return false;
    return $purchasable;
}, 10, 2);

add_filter('woocommerce_get_price_html', function ($price, $product) {
    if (!is_user_logged_in()) return '<a href="' . esc_url(wp_login_url(get_permalink())) . '">Log in to see pricing</a>';
    return $price;
}, 10, 2);

add_filter('woocommerce_checkout_posted_data', function ($data) {
    if (empty($data['billing_company']) && is_user_logged_in()) {
        $company = get_user_meta(get_current_user_id(), 'billing_company', true);
        if (!empty($company)) {
            $data['billing_company'] = $company;
        }
    }
    if (empty($data['billing_first_name']) && is_user_logged_in()) {
        $first = get_user_meta(get_current_user_id(), 'billing_first_name', true);
        if (!empty($first)) {
            $data['billing_first_name'] = $first;
        }
    }
    if (empty($data['billing_last_name']) && is_user_logged_in()) {
        $last = get_user_meta(get_current_user_id(), 'billing_last_name', true);
        if (!empty($last)) {
            $data['billing_last_name'] = $last;
        }
    }
    return $data;
});

add_action('woocommerce_blocks_loaded', function () {
    if (function_exists('__experimental_woocommerce_blocks_deregister_checkout_field')) {
        __experimental_woocommerce_blocks_deregister_checkout_field('company');
    }
});

// Keep billing_company out of the checkout form (wholesale customers have it stored in user meta)
// add_filter('woocommerce_checkout_fields', function ($fields) {
//     unset($fields['billing']['billing_company']);
//     return $fields;
// });

// Prevent checkout from overwriting a stored billing_company with an empty value
// add_filter('woocommerce_checkout_update_customer_data', function ($customer_data, $customer) {
//     if (!empty(get_user_meta($customer->get_id(), 'billing_company', true))) {
//         unset($customer_data['billing_company']);
//     }
//     return $customer_data;
// }, 10, 2);

add_filter('woocommerce_billing_fields', function ($fields) {
    $fields['billing_company'] = array(
        'label'       => 'Company',
        'required'    => true,
        'class'       => array('form-row-wide'),
        'autocomplete' => 'section-billing billing organization',
        'priority'    => 25,
    );
    return $fields;
});

add_filter('register_url', function () {
    return home_url('/wholesale-register');
});
