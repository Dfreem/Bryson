<?php

/**
 * Plugin Name: Bryson Product Tools
 * Description: Custom product export/import and other Bryson-specific tools.
 * Version: 1.0.0
 * Author: Devin Freeman
 */

if (! defined('ABSPATH')) exit;

// Add wholesale_price to export

add_action('admin_footer', function () {
    if (! isset($_GET['page']) || $_GET['page'] !== 'product_exporter') return;
?>
    <script>
        jQuery(function($) {
            // Remove subcategories from category dropdown
            $('#woocommerce-exporter-category option').each(function() {
                if ($(this).text().startsWith('\u00a0') || $(this).text().startsWith('-')) {
                    $(this).remove();
                }
            });
            $('#woocommerce-exporter-category').trigger('change');

            // Pre-select default columns
            var defaultColumns = [
                'id', 'type', 'sku', 'name', 'description',
                'date_on_sale_from', 'date_on_sale_to',
                'tax_status', 'tax_class', 'stock', 'stock_status',
                'low_stock_amount', 'backorders',
                'weight', 'length', 'width', 'height',
                'sale_price', 'regular_price',
                'brand_ids'
            ];

            var $colSelect = $('#woocommerce-exporter-columns');
            $colSelect.find('option').each(function() {
                $(this).prop('selected', defaultColumns.indexOf($(this).val()) !== -1);
            });
            $colSelect.trigger('change');
        });
    </script>
<?php
});


// Add brand filter row via the hook
add_action('woocommerce_product_export_row', function () {
    $brands = get_terms(array(
        'taxonomy'   => 'product_brand',
        'hide_empty' => true,
        'orderby'    => 'name',
    ));
    if (empty($brands) || is_wp_error($brands)) return;
?>
    <tr>
        <th scope="row">
            <label for="woocommerce-exporter-brand"><?php esc_html_e('Which brand should be exported?', 'woocommerce'); ?></label>
        </th>
        <td>
            <select id="woocommerce-exporter-brand" name="export_brand[]" class="wc-enhanced-select" style="width:100%;" multiple data-placeholder="<?php esc_attr_e('Export all brands', 'woocommerce'); ?>">
                <?php foreach ($brands as $brand) : ?>
                    <option value="<?php echo esc_attr($brand->slug); ?>"><?php echo esc_html($brand->name); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
<?php
});

// Sort packing slip line items by SKU
add_filter('wpo_wcpdf_order_items_data', function ($data_list, $order, $document_type) {
    if ($document_type !== 'packing-slip') return $data_list;

    uasort($data_list, function ($a, $b) {
        $sku_a = isset($a['sku']) ? $a['sku'] : '';
        $sku_b = isset($b['sku']) ? $b['sku'] : '';
        return strcmp($sku_a, $sku_b);
    });

    return $data_list;
}, 10, 3);

add_filter('wpo_wcpdf_template_file', function ($file_path, $document_type, $order) {
    $filename   = basename($file_path);
    $custom     = plugin_dir_path(__FILE__) . 'templates/Simple/' . $filename;
    if ($document_type === 'packing-slip' && file_exists($custom)) {
        return $custom;
    }
    return $file_path;
}, 10, 3);

// Add Regular Price column to products list
add_filter('manage_product_posts_columns', function ($columns) {
    $new = [];
    foreach ($columns as $key => $value) {
        $new[$key] = $value;
        if ($key === 'price') {
            $new['regular_price'] = 'Regular Price';
        }
    }
    return $new;
});

add_action('manage_product_posts_custom_column', function ($column, $post_id) {
    if ($column === 'regular_price') {
        $product = wc_get_product($post_id);
        if ($product) {
            echo esc_html(wc_price($product->get_regular_price()));
        }
    }
}, 10, 25);

add_filter('wpo_wcpdf_settings_fields_documents_packing_slip', function ($settings_fields, $page, $option_group, $option_name) {
    $settings_fields[] = array(
        'type'     => 'setting',
        'id'       => 'display_customer_name',
        'title'    => __('Display customer name', 'woocommerce-pdf-invoices-packing-slips'),
        'callback' => 'checkbox',
        'section'  => 'packing_slip',
        'args'     => array(
            'option_name' => $option_name,
            'id'          => 'display_customer_name',
            'default'     => 0,
        )
    );
    return $settings_fields;
}, 10, 4);
