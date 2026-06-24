<?php
/**
 * Cart page.
 *
 * @package Blackbean
 */

$cart = blackbean_shop_cart_get();
?>
<header class="bb-reveal mb-8">
	<h1 class="font-display text-3xl font-bold"><?php esc_html_e( 'Your cart', 'blackbean' ); ?></h1>
	<p class="mt-2"><a class="text-brand-700 hover:underline dark:text-brand-300" href="<?php echo esc_url( blackbean_shop_products_url() ); ?>"><?php esc_html_e( 'Continue shopping', 'blackbean' ); ?></a></p>
</header>

<div class="bb-shop-cart-page" data-bb-cart-page>
	<div data-bb-cart-alert class="bb-shop-cart-alert" hidden role="alert"></div>

	<?php if ( empty( $cart['items'] ) ) : ?>
		<div data-bb-cart-empty>
			<p class="text-stone-600 dark:text-stone-400"><?php esc_html_e( 'Your cart is empty.', 'blackbean' ); ?></p>
			<a class="<?php echo esc_attr( blackbean_button_class( 'primary' ) ); ?> mt-4 inline-flex" href="<?php echo esc_url( blackbean_shop_products_url() ); ?>"><?php esc_html_e( 'Go to shop', 'blackbean' ); ?></a>
		</div>
	<?php else : ?>
		<div class="bb-card overflow-hidden" data-bb-cart-panel>
			<ul class="divide-y divide-stone-200 dark:divide-stone-700" data-bb-cart-lines>
				<?php foreach ( $cart['items'] as $item ) : ?>
					<?php
					$product_id = (int) $item['id'];
					$max_qty    = (int) $item['stock'] >= 0 ? (int) $item['stock'] : 0;
					?>
					<li
						class="bb-cart-line flex flex-wrap items-center justify-between gap-4 p-4"
						data-bb-cart-line
						data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
					>
						<div class="min-w-0 flex-1">
							<a class="font-semibold hover:text-brand-600 dark:hover:text-brand-400" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
							<p class="mt-1 text-sm text-stone-500 dark:text-stone-400">
								<span data-bb-unit-price><?php echo esc_html( $item['price_label'] ); ?></span>
								<?php esc_html_e( 'each', 'blackbean' ); ?>
							</p>
						</div>
						<div class="flex flex-wrap items-center gap-3 sm:gap-4">
							<div class="bb-cart-qty" data-bb-cart-qty>
								<button type="button" class="bb-cart-qty__btn" data-bb-qty-dec aria-label="<?php esc_attr_e( 'Decrease quantity', 'blackbean' ); ?>">−</button>
								<label class="sr-only" for="bb-qty-<?php echo esc_attr( (string) $product_id ); ?>"><?php esc_html_e( 'Quantity', 'blackbean' ); ?></label>
								<input
									type="number"
									class="<?php echo esc_attr( blackbean_input_class() ); ?> bb-cart-qty__input"
									id="bb-qty-<?php echo esc_attr( (string) $product_id ); ?>"
									data-bb-qty-input
									value="<?php echo esc_attr( (string) $item['qty'] ); ?>"
									min="1"
									<?php echo $max_qty > 0 ? 'max="' . esc_attr( (string) $max_qty ) . '"' : ''; ?>
								/>
								<button type="button" class="bb-cart-qty__btn" data-bb-qty-inc aria-label="<?php esc_attr_e( 'Increase quantity', 'blackbean' ); ?>">+</button>
							</div>
							<p class="min-w-[5rem] text-right font-medium" data-bb-line-total><?php echo esc_html( $item['line_label'] ); ?></p>
							<button type="button" class="bb-cart-remove text-sm font-medium text-stone-500 underline-offset-2 hover:text-red-600 hover:underline dark:text-stone-400 dark:hover:text-red-400" data-bb-cart-remove>
								<?php esc_html_e( 'Remove', 'blackbean' ); ?>
							</button>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="flex flex-wrap items-center justify-between gap-4 border-t border-stone-200 bg-stone-50/80 p-4 dark:border-stone-700 dark:bg-stone-900/50" data-bb-cart-footer>
				<p class="text-lg font-semibold">
					<?php esc_html_e( 'Subtotal', 'blackbean' ); ?>:
					<span data-bb-cart-subtotal><?php echo esc_html( $cart['subtotal_label'] ); ?></span>
				</p>
				<a class="<?php echo esc_attr( blackbean_button_class( 'primary' ) ); ?>" href="<?php echo esc_url( blackbean_shop_checkout_url() ); ?>"><?php esc_html_e( 'Checkout', 'blackbean' ); ?></a>
			</div>
		</div>
	<?php endif; ?>
</div>
