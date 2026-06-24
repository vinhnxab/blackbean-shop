<?php
/**
 * Single product.
 *
 * @package Blackbean
 */

get_header();

$product = isset( $GLOBALS['blackbean_current_product'] ) && is_array( $GLOBALS['blackbean_current_product'] )
	? $GLOBALS['blackbean_current_product']
	: null;

if ( ! $product ) {
	get_template_part( 'template-parts/content', 'none' );
	get_footer();
	return;
}

$row = blackbean_product_get_row( (int) $product['id'] );
$content_html = $row ? apply_filters( 'the_content', (string) $row['content'] ) : '';
?>
<div class="<?php echo esc_attr( blackbean_layout_container_classes( 'py-10' ) ); ?>">
	<article class="bb-reveal grid gap-10 lg:grid-cols-2">
		<div>
			<?php if ( $product['image'] ) : ?>
				<img src="<?php echo esc_url( $product['image'] ); ?>" alt="" class="w-full rounded-2xl border border-stone-200 object-cover dark:border-stone-700" />
			<?php else : ?>
				<div class="flex aspect-square items-center justify-center rounded-2xl bg-stone-100 text-stone-400 dark:bg-stone-800"><?php esc_html_e( 'No image', 'blackbean' ); ?></div>
			<?php endif; ?>
		</div>
		<div>
			<p class="text-sm text-stone-500"><a class="hover:text-brand-600" href="<?php echo esc_url( blackbean_shop_products_url() ); ?>"><?php esc_html_e( 'Shop', 'blackbean' ); ?></a></p>
			<h1 class="mt-2 font-display text-3xl font-bold"><?php echo esc_html( $product['title'] ); ?></h1>
			<p class="mt-3 text-2xl font-semibold text-brand-700 dark:text-brand-300"><?php echo esc_html( $product['price_label'] ); ?></p>
			<?php if ( $product['sku'] ) : ?>
				<p class="mt-2 font-mono text-xs text-stone-500"><?php echo esc_html( sprintf( __( 'SKU: %s', 'blackbean' ), $product['sku'] ) ); ?></p>
			<?php endif; ?>
			<p class="mt-2 text-sm <?php echo $product['in_stock'] ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600'; ?>">
				<?php echo $product['in_stock'] ? esc_html__( 'In stock', 'blackbean' ) : esc_html__( 'Out of stock', 'blackbean' ); ?>
			</p>
			<div class="prose prose-stone mt-6 max-w-none dark:prose-invert"><?php echo wp_kses_post( $content_html ); ?></div>

			<?php if ( $product['in_stock'] ) : ?>
				<form class="blackbean-shop-add mt-8 flex flex-wrap items-end gap-3" data-product-id="<?php echo esc_attr( (string) $product['id'] ); ?>">
					<div>
						<label class="<?php echo esc_attr( blackbean_label_class() ); ?>" for="bb_qty"><?php esc_html_e( 'Quantity', 'blackbean' ); ?></label>
						<input class="<?php echo esc_attr( blackbean_input_class() ); ?> w-24" type="number" id="bb_qty" name="qty" value="1" min="1" max="<?php echo $product['stock'] >= 0 ? esc_attr( (string) $product['stock'] ) : '99'; ?>" />
					</div>
					<button type="submit" class="<?php echo esc_attr( blackbean_button_class( 'primary' ) ); ?>"><?php esc_html_e( 'Add to cart', 'blackbean' ); ?></button>
				</form>
				<div class="blackbean-shop-add-notice mt-3 flex flex-wrap items-center gap-3" hidden role="status" aria-live="polite"></div>
			<?php endif; ?>

			<?php if ( function_exists( 'blackbean_shop_paypal_enabled' ) && blackbean_shop_paypal_enabled() ) : ?>
				<p class="mt-6 text-sm text-stone-500 dark:text-stone-400">
					<?php esc_html_e( 'Secure PayPal checkout. License key and download link are emailed after payment.', 'blackbean' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</article>
</div>
<?php
get_footer();
