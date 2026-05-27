<?php if (! defined('ABSPATH')) exit; // Exit if accessed directly 
?>

<?php do_action('wpo_wcpdf_before_document', $this->get_type(), $this->order); ?>

<table class="head container">
	<tr>
		<td class="header">
			<?php if ($this->has_header_logo()) : ?>
				<?php do_action('wpo_wcpdf_before_shop_logo', $this->get_type(), $this->order); ?>
				<?php $this->header_logo(); ?>
				<?php do_action('wpo_wcpdf_after_shop_logo', $this->get_type(), $this->order); ?>
			<?php else : ?>
				<?php $this->title(); ?>
			<?php endif; ?>
		</td>
		<td class="shop-info">
			<?php do_action('wpo_wcpdf_before_shop_name', $this->get_type(), $this->order); ?>
			<div class="shop-name">
				<h3><?php $this->shop_name(); ?></h3>
			</div>
			<?php do_action('wpo_wcpdf_after_shop_name', $this->get_type(), $this->order); ?>
			<?php do_action('wpo_wcpdf_before_shop_address', $this->get_type(), $this->order); ?>
			<div class="shop-address"><?php $this->shop_address(); ?></div>
			<?php do_action('wpo_wcpdf_after_shop_address', $this->get_type(), $this->order); ?>
			<?php do_action('wpo_wcpdf_before_shop_phone_number', $this->get_type(), $this->order); ?>
			<?php if (! empty($this->get_shop_phone_number())) : ?>
				<div class="shop-phone-number"><?php $this->shop_phone_number(); ?></div>
			<?php endif; ?>
			<?php do_action('wpo_wcpdf_after_shop_phone_number', $this->get_type(), $this->order); ?>
			<?php if (! empty($this->get_shop_email_address())) : ?>
				<div class="shop-email-address"><?php $this->shop_email_address(); ?></div>
			<?php endif; ?>
			<?php do_action('wpo_wcpdf_after_shop_email_address', $this->get_type(), $this->order); ?>
		</td>
	</tr>
</table>

<?php do_action('wpo_wcpdf_before_document_label', $this->get_type(), $this->order); ?>

<?php if ($this->has_header_logo()) : ?>
	<h1 class="document-type-label"><?php $this->title(); ?></h1>
<?php endif; ?>

<?php do_action('wpo_wcpdf_after_document_label', $this->get_type(), $this->order); ?>

<table class="order-data-addresses">
	<tr>
		<td class="address shipping-address">
			<?php do_action('wpo_wcpdf_before_shipping_address', $this->get_type(), $this->order); ?>
			<?php
			$customer_id     = $this->order->get_customer_id();
			$billing_company = $customer_id
				? get_user_meta($customer_id, 'billing_company', true)
				: $this->order->get_billing_company();
			$phone_raw = $this->order->get_shipping_phone() ?: $this->order->get_billing_phone();
			$phone = '';
			if (!empty($phone_raw)) {
				$digits = preg_replace('/\D/', '', $phone_raw);
				$phone = strlen($digits) === 10
					? '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6)
					: $phone_raw;
			}
			$addr = array_filter([
				$this->order->get_billing_address_1(),
				$this->order->get_billing_address_2(),
				trim($this->order->get_billing_city() . ', ' . $this->order->get_billing_state() . ' ' . $this->order->get_billing_postcode()),
				$this->order->get_billing_country(),
				$phone,
			]);
			?>
			<?php if (!empty($billing_company)) : ?>
				<p style="font-weight: 800; font-size: 11pt; margin-bottom: 2px;"><?php echo esc_html($billing_company); ?></p>
			<?php endif; ?>
			<p style="margin-top: 0;">
				<?php echo esc_html(trim($this->order->get_shipping_first_name() . ' ' . $this->order->get_shipping_last_name())); ?> <br />
				<?php echo implode('<br/>', array_map('esc_html', $addr)); ?></p>
			<?php do_action('wpo_wcpdf_after_shipping_address', $this->get_type(), $this->order); ?>
			<?php if (isset($this->settings['display_email'])) : ?>
				<div class="billing-email"><?php $this->billing_email(); ?></div>
			<?php endif; ?>
		</td>
		<td class="address billing-address">
			<?php if ($this->show_billing_address()) : ?>
				<h3><?php $this->billing_address_title(); ?></h3>
				<?php do_action('wpo_wcpdf_before_billing_address', $this->get_type(), $this->order); ?>
				<p><?php $this->billing_address(); ?></p>
				<?php do_action('wpo_wcpdf_after_billing_address', $this->get_type(), $this->order); ?>
				<?php if (isset($this->settings['display_phone']) && ! empty($this->get_billing_phone())) : ?>
					<div class="billing-phone"><?php $this->billing_phone(); ?></div>
				<?php endif; ?>
			<?php endif; ?>
		</td>
		<td class="order-data">
			<table>
				<?php do_action('wpo_wcpdf_before_order_data', $this->get_type(), $this->order); ?>
				<tr class="order-number">
					<th><?php $this->order_number_title(); ?></th>
					<td><?php $this->order_number(); ?></td>
				</tr>
				<tr class="order-date">
					<th><?php $this->order_date_title(); ?></th>
					<td><?php $this->order_date(); ?></td>
				</tr>
				<?php if ($this->get_shipping_method()) : ?>
					<tr class="shipping-method">
						<th><?php $this->shipping_method_title(); ?></th>
						<td><?php $this->shipping_method(); ?></td>
					</tr>
				<?php endif; ?>
				<?php do_action('wpo_wcpdf_after_order_data', $this->get_type(), $this->order); ?>
			</table>
		</td>
	</tr>
</table>

<?php do_action('wpo_wcpdf_before_order_details', $this->get_type(), $this->order); ?>

<table class="order-details">
	<thead>
		<tr>
			<?php
			$default_headers = wpo_wcpdf_get_simple_template_default_table_headers($this);
			unset($default_headers['quantity']); // rendered manually in correct column order
			foreach ($default_headers as $column_class => $column_title) : ?>
				<th class="<?php echo esc_attr($column_class); ?>"><?php echo esc_html($column_title); ?></th>
			<?php endforeach; ?>
			<th class="unit-price" style="text-align: center;">Unit Price</th>
			<th class="quantity" style="text-align: center;">Qty</th>
			<th class="line-total" style="text-align: center;">Total</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($this->get_order_items() as $item_id => $item) :
			$wholesale_raw = get_post_meta($item['product_id'], 'wholesale_customer_wholesale_price', true);
			$unit_price    = (float) preg_replace('/[^0-9.]/', '', $wholesale_raw);
			$line_total    = $unit_price * (int) $item['quantity'];
		?>
			<tr class="<?php echo esc_html($item['row_class']); ?>">
				<td class="product">
					<p class="item-name"><?php echo esc_html($item['name']); ?></p>
					<?php do_action('wpo_wcpdf_before_item_meta', $this->get_type(), $item, $this->order); ?>
					<div class="item-meta">
						<?php if (! empty($item['sku'])) : ?>
							<p class="sku"><span class="label"><?php $this->sku_title(); ?></span> <strong style="font-size: 1.1em;"><?php echo esc_attr($item['sku']); ?></strong></p>
						<?php endif; ?>
						<?php if (! empty($item['weight'])) : ?>
							<p class="weight"><span class="label"><?php $this->weight_title(); ?></span> <?php echo esc_attr($item['weight']); ?><?php echo esc_attr(get_option('woocommerce_weight_unit')); ?></p>
						<?php endif; ?>
						<?php if (! empty($item['meta'])) : ?>
							<?php echo wp_kses_post($item['meta']); ?>
						<?php endif; ?>
					</div>
					<?php do_action('wpo_wcpdf_after_item_meta', $this->get_type(), $item, $this->order); ?>
				</td>
				<td class="unit-price" style="text-align: center;">
					<?php echo $unit_price ? esc_html(wc_price($unit_price)) : '&mdash;'; ?>
				</td>
				<td class="quantity" style="text-align: center;"><?php echo esc_html($item['quantity']); ?></td>
				<td class="line-total" style="text-align: center; font-weight: 600;">
					<?php echo $line_total ? esc_html(wc_price($line_total)) : '&mdash;'; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
		<tr style="border-top: 2px solid black;">
			<td class="product"><strong>Total</strong></td>
			<td class="unit-price"></td>
			<td class="quantity" style="text-align: center;">
				<?php
				$total_qty = 0;
				foreach ($this->get_order_items() as $item) {
					$total_qty += (int) $item['quantity'];
				}
				echo esc_html($total_qty);
				?>
			</td>
			<td class="line-total" style="text-align: center; font-weight: 600;">
				<?php
				$grand_total = 0;
				foreach ($this->get_order_items() as $item) {
					$wholesale = get_post_meta($item['product_id'], 'wholesale_customer_wholesale_price', true);
					$grand_total += (float) preg_replace('/[^0-9.]/', '', $wholesale) * (int) $item['quantity'];
				}
				echo esc_html(wc_price($grand_total));
				?>
			</td>
		</tr>
	</tfoot>
</table>

<div class="bottom-spacer"></div>

<?php do_action('wpo_wcpdf_after_order_details', $this->get_type(), $this->order); ?>

<?php do_action('wpo_wcpdf_before_customer_notes', $this->get_type(), $this->order); ?>
<?php if ($this->get_shipping_notes()) : ?>
	<div class="customer-notes">
		<h3><?php $this->customer_notes_title() ?></h3>
		<?php $this->shipping_notes(); ?>
	</div>
<?php endif; ?>
<?php do_action('wpo_wcpdf_after_customer_notes', $this->get_type(), $this->order); ?>

<?php if ($this->get_footer()) : ?>
	<htmlpagefooter name="docFooter"><!-- required for mPDF engine -->
		<div id="footer">
			<?php $this->footer(); ?>
		</div>
	</htmlpagefooter><!-- required for mPDF engine -->
<?php endif; ?>

<?php do_action('wpo_wcpdf_after_document', $this->get_type(), $this->order); ?>