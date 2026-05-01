<aside class="catalog-sidebar">
    <?php
    $brands = get_terms(array(
        'taxonomy'   => 'product_brand',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ));
    $shop_url       = get_permalink(wc_get_page_id('shop'));
    $in_stock       = isset($_GET['instock']) ? $_GET['instock'] : '';
    $current_search = isset($_GET['q']) ? $_GET['q'] : '';

    $current_brand = isset($args['current_brand']) && ($args['current_brand'] instanceof WP_Term)
        ? $args['current_brand']
        : null;

    $base_url    = $current_brand ? get_term_link($current_brand) : $shop_url;
    $current_path = strtok($_SERVER['REQUEST_URI'], '?');
    ?>
    <form method="GET" action="<?php echo esc_url($current_path); ?>">

        <!-- Search -->
        <div class="sidebar-section">
            <div class="sidebar-heading">Search</div>
            <div class="sidebar-search-wrap">
                <input
                    type="text"
                    name="q"
                    class="sidebar-search-input"
                    placeholder="Search products..."
                    value="<?php echo esc_attr($current_search); ?>">
                <button type="submit" class="sidebar-search-btn">Go</button>
                <a href="<?php echo esc_url($current_path); ?>" class="sidebar-clear-btn">Clear</a>
            </div>
        </div>

        <!-- In Stock Toggle -->
        <div class="sidebar-section">
            <div class="sidebar-heading">Availability</div>
            <div class="sidebar-search-wrap">
                <label class="sidebar-toggle-label">
                    <input
                        type="checkbox"
                        name="instock"
                        value="1"
                        class="sidebar-toggle"
                        <?php checked($in_stock, '1'); ?>>
                    <span class="sidebar-toggle-text">In stock only</span>
                </label>
            </div>
        </div>

        <!-- Brand Filter -->
        <div class="sidebar-section">
            <div class="sidebar-heading">Brand</div>
            <div class="sidebar-search-wrap">
                <select id="sidebar-brand-select" name="brand" class="sidebar-brand-select">
                    <option value="" data-url="<?php echo esc_url($shop_url); ?>">All Brands</option>
                    <?php foreach ($brands as $brand) :
                        $term_url   = get_term_link($brand);
                        $is_current = $current_brand && $current_brand->slug === $brand->slug;
                    ?>
                        <option
                            value="<?php echo esc_attr($brand->slug); ?>"
                            data-url="<?php echo esc_url($term_url); ?>"
                            <?php selected($is_current, true); ?>>
                            <?php echo esc_html($brand->name); ?>
                            (<?php echo $brand->count; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
</aside>

<script>
    (function() {
        var brandSelect = document.getElementById('sidebar-brand-select');
        var searchInput = document.querySelector('.sidebar-search-input');
        var stockToggle = document.querySelector('.sidebar-toggle');

        function buildUrl(base) {
            var params = [];
            var q = searchInput ? searchInput.value.trim() : '';
            if (q) params.push('q=' + encodeURIComponent(q));
            if (stockToggle && stockToggle.checked) params.push('instock=1');
            return params.length ? base + '?' + params.join('&') : base;
        }

        brandSelect.addEventListener('change', function() {
            var url = this.options[this.selectedIndex].dataset.url;
            window.location.href = buildUrl(url);
        });

        stockToggle.addEventListener('change', function() {
            var url = new URL(window.location.href);
            if (this.checked) {
                url.searchParams.set('instock', '1');
            } else {
                url.searchParams.delete('instock');
            }
            window.location.href = url.toString();
        });
    })();
</script>