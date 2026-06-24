<?php
/**
 * Checkout page.
 *
 * @package Blackbean
 */

$order_id      = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
$error         = isset( $_GET['shop_error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['shop_error'] ) ) : '';
$customer      = blackbean_shop_get_checkout_customer();
$logged_in     = is_user_logged_in();
$login_url     = wp_login_url( blackbean_shop_checkout_url() );
$paypal_cancel = ! empty( $_GET['paypal_cancel'] );
$order         = $order_id > 0 ? blackbean_shop_get_order( $order_id ) : null;
$paid          = $order && 'paid' === $order['payment_status'];
if ( $paid ) {
	blackbean_shop_cart_clear_for_order( $order_id );
}
$cart          = blackbean_shop_cart_get();
$fulfillment   = $paid && function_exists( 'blackbean_shop_get_fulfillment_display' )
	? blackbean_shop_get_fulfillment_display( $order_id )
	: array();
?>
<header class="bb-reveal mb-8">
	<h1 class="font-display text-3xl font-bold"><?php esc_html_e( 'Checkout', 'blackbean' ); ?></h1>
</header>

<?php if ( $order_id > 0 && $order ) : ?>
	<div class="bb-card space-y-4 p-6"<?php echo $paid ? ' data-bb-checkout-success="1"' : ''; ?>>
		<?php if ( $paypal_cancel && ! $paid ) : ?>
			<p class="text-amber-800 dark:text-amber-200"><?php esc_html_e( 'Payment was cancelled. You can try again below.', 'blackbean' ); ?></p>
			<?php if ( blackbean_shop_paypal_enabled() ) : ?>
				<a class="<?php echo esc_attr( blackbean_button_class( 'primary' ) ); ?> inline-flex" href="<?php echo esc_url( add_query_arg( array( 'order_id' => (string) $order_id, 'pay_order' => '1' ), blackbean_shop_checkout_url() ) ); ?>">
					<?php esc_html_e( 'Pay with PayPal', 'blackbean' ); ?>
				</a>
			<?php endif; ?>
		<?php elseif ( $paid ) : ?>
			<p class="text-lg font-semibold text-emerald-700 dark:text-emerald-400"><?php esc_html_e( 'Thank you! Payment received.', 'blackbean' ); ?></p>
			<p class="text-stone-600 dark:text-stone-400"><?php echo esc_html( sprintf( __( 'Order #%d — %s', 'blackbean' ), $order_id, $order['total_label'] ) ); ?></p>
			<p class="text-sm text-stone-600 dark:text-stone-400"><?php esc_html_e( 'We emailed your license keys and download links.', 'blackbean' ); ?></p>

			<?php if ( ! empty( $fulfillment ) ) : ?>
				<div class="mt-6 space-y-4 border-t border-stone-200 pt-6 dark:border-stone-700">
					<h2 class="text-lg font-semibold"><?php esc_html_e( 'Your downloads', 'blackbean' ); ?></h2>
					<?php foreach ( $fulfillment as $row ) : ?>
						<div class="rounded-lg border border-stone-200 bg-stone-50/80 p-4 dark:border-stone-700 dark:bg-stone-900/50">
							<p class="font-medium"><?php echo esc_html( $row['title'] ); ?></p>
							<?php if ( ! empty( $row['license'] ) ) : ?>
								<p class="mt-2 text-sm">
									<span class="text-stone-500"><?php esc_html_e( 'License:', 'blackbean' ); ?></span>
									<code class="ml-1 rounded bg-stone-200 px-2 py-0.5 text-stone-900 dark:bg-stone-800 dark:text-stone-100"><?php echo esc_html( $row['license'] ); ?></code>
								</p>
							<?php endif; ?>
							<?php if ( ! empty( $row['download_url'] ) ) : ?>
								<p class="mt-2">
									<a class="<?php echo esc_attr( blackbean_button_class( 'secondary' ) ); ?> inline-flex text-sm" href="<?php echo esc_url( $row['download_url'] ); ?>">
										<?php esc_html_e( 'Download', 'blackbean' ); ?>
									</a>
								</p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<p class="text-stone-600 dark:text-stone-400"><?php esc_html_e( 'Your order is awaiting payment.', 'blackbean' ); ?></p>
			<p class="text-sm"><?php echo esc_html( sprintf( __( 'Order #%d', 'blackbean' ), $order_id ) ); ?></p>
			<?php if ( blackbean_shop_paypal_enabled() ) : ?>
				<a class="<?php echo esc_attr( blackbean_button_class( 'primary' ) ); ?> mt-4 inline-flex" href="<?php echo esc_url( add_query_arg( array( 'order_id' => (string) $order_id, 'pay_order' => '1' ), blackbean_shop_checkout_url() ) ); ?>">
					<?php esc_html_e( 'Pay with PayPal', 'blackbean' ); ?>
				</a>
			<?php endif; ?>
		<?php endif; ?>

		<a class="<?php echo esc_attr( blackbean_button_class( 'secondary' ) ); ?> mt-4 inline-flex" href="<?php echo esc_url( blackbean_shop_products_url() ); ?>"><?php esc_html_e( 'Back to shop', 'blackbean' ); ?></a>
	</div>
<?php elseif ( empty( $cart['items'] ) ) : ?>
	<p class="text-stone-600"><?php esc_html_e( 'Your cart is empty.', 'blackbean' ); ?></p>
	<a class="<?php echo esc_attr( blackbean_button_class( 'primary' ) ); ?> mt-4 inline-flex" href="<?php echo esc_url( blackbean_shop_products_url() ); ?>"><?php esc_html_e( 'Go to shop', 'blackbean' ); ?></a>
<?php else : ?>
	<?php if ( $error ) : ?>
		<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>

	<?php if ( blackbean_shop_paypal_enabled() ) : ?>
		<p class="mb-4 text-sm text-stone-600 dark:text-stone-400"><?php esc_html_e( 'You will be redirected to PayPal to pay securely. After payment, download links and license keys are sent by email.', 'blackbean' ); ?></p>
	<?php endif; ?>

	<div class="grid gap-8 lg:grid-cols-2">
		<form method="post" action="<?php echo esc_url( blackbean_shop_checkout_url() ); ?>" class="bb-card space-y-4 p-6">
			<?php wp_nonce_field( 'blackbean_shop_checkout', 'blackbean_shop_checkout_nonce' ); ?>

			<?php if ( $logged_in ) : ?>
				<p class="rounded-lg border border-brand-200/80 bg-brand-50/80 px-3 py-2 text-sm text-brand-900 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-100">
					<?php esc_html_e( 'Using your account details. Updates here are saved for your next order.', 'blackbean' ); ?>
				</p>
			<?php else : ?>
				<p class="text-sm text-stone-600 dark:text-stone-400">
					<a class="font-medium text-brand-700 hover:underline dark:text-brand-300" href="<?php echo esc_url( $login_url ); ?>">
						<?php esc_html_e( 'Log in', 'blackbean' ); ?>
					</a>
					<?php esc_html_e( 'to use your saved billing details.', 'blackbean' ); ?>
				</p>
			<?php endif; ?>

			<div>
				<label class="<?php echo esc_attr( blackbean_label_class() ); ?>" for="customer_name"><?php esc_html_e( 'Name', 'blackbean' ); ?></label>
				<input
					class="<?php echo esc_attr( blackbean_input_class() ); ?>"
					type="text"
					name="customer_name"
					id="customer_name"
					value="<?php echo esc_attr( $customer['name'] ); ?>"
					autocomplete="name"
					required
				/>
			</div>
			<div>
				<label class="<?php echo esc_attr( blackbean_label_class() ); ?>" for="customer_email"><?php esc_html_e( 'Email', 'blackbean' ); ?></label>
				<?php if ( $logged_in ) : ?>
					<input
						class="<?php echo esc_attr( blackbean_input_class() ); ?> bg-stone-100/80 dark:bg-stone-800/50"
						type="email"
						id="customer_email"
						value="<?php echo esc_attr( $customer['email'] ); ?>"
						autocomplete="email"
						readonly
					/>
					<input type="hidden" name="customer_email" value="<?php echo esc_attr( $customer['email'] ); ?>" />
					<p class="mt-1 text-xs text-stone-500 dark:text-stone-400"><?php esc_html_e( 'License and download links are sent to this email.', 'blackbean' ); ?></p>
				<?php else : ?>
					<input
						class="<?php echo esc_attr( blackbean_input_class() ); ?>"
						type="email"
						name="customer_email"
						id="customer_email"
						value="<?php echo esc_attr( $customer['email'] ); ?>"
						autocomplete="email"
						required
					/>
				<?php endif; ?>
			</div>
			<div>
				<label class="<?php echo esc_attr( blackbean_label_class() ); ?>" for="customer_phone"><?php esc_html_e( 'Phone', 'blackbean' ); ?></label>
				<input
					class="<?php echo esc_attr( blackbean_input_class() ); ?>"
					type="tel"
					name="customer_phone"
					id="customer_phone"
					value="<?php echo esc_attr( $customer['phone'] ); ?>"
					autocomplete="tel"
				/>
			</div>
			<div>
				<label class="<?php echo esc_attr( blackbean_label_class() ); ?>" for="customer_address"><?php esc_html_e( 'Address', 'blackbean' ); ?> <span class="font-normal text-stone-500">(<?php esc_html_e( 'optional', 'blackbean' ); ?>)</span></label>
				<textarea
					class="<?php echo esc_attr( blackbean_textarea_class() ); ?>"
					name="customer_address"
					id="customer_address"
					rows="3"
					autocomplete="street-address"
				><?php echo esc_textarea( $customer['address'] ); ?></textarea>
			</div>
			<div>
				<label class="<?php echo esc_attr( blackbean_label_class() ); ?>" for="customer_notes"><?php esc_html_e( 'Notes', 'blackbean' ); ?></label>
				<textarea class="<?php echo esc_attr( blackbean_textarea_class() ); ?>" name="customer_notes" id="customer_notes" rows="2"><?php echo esc_textarea( $customer['notes'] ); ?></textarea>
			</div>
			<button type="submit" class="<?php echo esc_attr( blackbean_button_class( 'primary' ) ); ?> w-full">
				<?php echo esc_html( blackbean_shop_paypal_enabled() ? __( 'Continue to PayPal', 'blackbean' ) : __( 'Place order', 'blackbean' ) ); ?>
			</button>
		</form>
		<div class="bb-card p-6">
			<h2 class="text-lg font-semibold"><?php esc_html_e( 'Order summary', 'blackbean' ); ?></h2>
			<ul class="mt-4 space-y-2 text-sm">
				<?php foreach ( $cart['items'] as $item ) : ?>
					<li class="flex justify-between gap-4">
						<span><?php echo esc_html( $item['title'] ); ?> × <?php echo esc_html( (string) $item['qty'] ); ?></span>
						<span><?php echo esc_html( $item['line_label'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="mt-6 border-t border-stone-200 pt-4 text-lg font-semibold dark:border-stone-700"><?php esc_html_e( 'Total', 'blackbean' ); ?>: <?php echo esc_html( $cart['subtotal_label'] ); ?></p>
		</div>
	</div>
<?php endif; ?>
