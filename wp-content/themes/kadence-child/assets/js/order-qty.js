document.addEventListener('DOMContentLoaded', function () {
    let timers = {};
    document.querySelectorAll('.order-qty').forEach(function (input) {
        input.addEventListener('change', function () {
            const productId = this.dataset.productId;
            const quantity = parseInt(this.value) || 0;

            clearTimeout(timers[productId]);
            timers[productId] = setTimeout(function () {
                fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'update_order_qty',
                        nonce: brysonOrder.nonce,
                        product_id: productId,
                        quantity: quantity,
                    })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            // Refresh WooCommerce cart fragments
                            fetch('/wp-admin/admin-ajax.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'action=woocommerce_get_refreshed_fragments'
                            })
                                .then(r => r.json())
                                .then(fragments => {
                                    if (fragments && fragments.fragments) {
                                        Object.keys(fragments.fragments).forEach(function (selector) {
                                            const els = document.querySelectorAll(selector);
                                            els.forEach(function (el) {
                                                el.outerHTML = fragments.fragments[selector];
                                            });
                                        });
                                    }
                                });
                        }
                    });
            }, 500);
        });
    });
});