<?php

/**
 * Plugin Name: Bryson Product Tools
 * Description: Custom product export/import and other Bryson-specific tools.
 * Version: 1.0.0
 * Author: Devin Freeman
 */

if (! defined('ABSPATH')) exit;

// Add wholesale_price to export
add_filter('woocommerce_product_export_column_names', 'bryson_add_export_columns');
add_filter('woocommerce_product_export_product_default_columns', 'bryson_add_export_columns');
function bryson_add_export_columns($columns)
{
    $columns['wholesale_price'] = 'Wholesale Price';
    return $columns;
}

add_filter('woocommerce_product_export_product_column_wholesale_price', 'bryson_export_wholesale_price', 10, 2);
function bryson_export_wholesale_price($value, $product)
{
    return $product->get_meta('_wholesale_price', true, 'edit');
}

// Add wholesale_price to import
add_filter('woocommerce_csv_product_import_mapping_options', 'bryson_add_import_columns');
function bryson_add_import_columns($options)
{
    $options['wholesale_price'] = 'Wholesale Price';
    return $options;
}

add_filter('woocommerce_csv_product_import_mapping_default_columns', 'bryson_add_import_mapping');
function bryson_add_import_mapping($columns)
{
    $columns['Wholesale Price'] = 'wholesale_price';
    $columns['wholesale_price'] = 'wholesale_price';
    return $columns;
}

add_filter('woocommerce_product_import_pre_insert_product_object', 'bryson_import_wholesale_price', 10, 2);
function bryson_import_wholesale_price($product, $data)
{
    if (isset($data['wholesale_price']) && $data['wholesale_price'] !== '') {
        $product->update_meta_data('_wholesale_price', wc_format_decimal($data['wholesale_price']));
    }
    return $product;
}

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
                'sale_price', 'regular_price', 'wholesale_price',
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
add_filter( 'wpo_wcpdf_order_items_data', function( $data_list, $order, $document_type ) {
    if ( $document_type !== 'packing-slip' ) return $data_list;

    uasort( $data_list, function( $a, $b ) {
        $sku_a = isset( $a['sku'] ) ? $a['sku'] : '';
        $sku_b = isset( $b['sku'] ) ? $b['sku'] : '';
        return strcmp( $sku_a, $sku_b );
    });

    return $data_list;
}, 10, 3 );
