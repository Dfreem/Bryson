document.addEventListener('DOMContentLoaded', function () {
    function sendToCart(productId, quantity) {
        return fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'update_order_qty',
                nonce: brysonOrder.nonce,
                product_id: productId,
                quantity: quantity,
            })
        }).then(r => r.json());
    }

    function refreshFragments() {
        jQuery(document.body).trigger('wc_fragment_refresh');
    }

    // Individual per-product add-to-cart buttons
    document.querySelectorAll('.add-to-cart-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const productId = this.dataset.productId;
            const input = document.querySelector('.order-qty[data-product-id="' + productId + '"]');
            const quantity = parseInt(input ? input.value : 0) || 0;
            const minQty = parseInt(input ? input.dataset.minQty : 0) || 0;
            if (minQty > 0 && quantity > 0 && quantity < minQty) {
                window.brysonShowToast('Minimum order for this item is ' + minQty + '.', 'error');
                return;
            }
            btn.disabled = true;
            sendToCart(productId, quantity)
                .then(function (data) {
                    if (data.success) {
                        refreshFragments();
                        window.brysonShowToast(data.data.product_name + ' was added to your cart');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    });

    // Bulk add-to-cart button (taxonomy-product_cat page)
    const bulkBtn = document.getElementById('bulk-add-to-cart-btn');
    if (!bulkBtn) return;

    bulkBtn.addEventListener('click', async function () {
        const inputs = Array.from(document.querySelectorAll('.order-qty'));
        const toAdd = [];

        for (const input of inputs) {
            const pid = input.dataset.productId;
            const qty = parseInt(input.value) || 0;
            const minQty = parseInt(input.dataset.minQty) || 0;
            if (!pid || qty < 1) continue;
            if (minQty > 0 && qty < minQty) {
                window.brysonShowToast('Minimum order for this item is ' + minQty + '.', 'error');
                return;
            }
            toAdd.push({ pid, qty });
        }

        if (!toAdd.length) {
            window.brysonShowToast('No quantities entered.', 'error');
            return;
        }

        bulkBtn.disabled = true;
        bulkBtn.textContent = 'Adding…';

        let added = 0;
        for (const { pid, qty } of toAdd) {
            try {
                const data = await sendToCart(pid, qty);
                if (data.success) added++;
            } catch (e) {
                console.warn('Add to cart failed for product', pid, e);
            }
        }

        refreshFragments();
        bulkBtn.disabled = false;
        bulkBtn.textContent = 'Add to Cart';

        window.brysonShowToast(
            added > 0
                ? 'Added ' + added + ' item' + (added !== 1 ? 's' : '') + ' to your cart.'
                : 'Nothing was added to your cart.',
            added > 0 ? 'success' : 'error'
        );
    });
});
