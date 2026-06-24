<?php
/**
 * Header cart icon + hover mini cart.
 *
 * @package Blackbean
 *
 * @var array{variant?:string} $args
 */

$variant = isset( $args['variant'] ) && 'dev' === $args['variant'] ? 'dev' : 'site';
$cart    = blackbean_shop_cart_get();
$count   = (int) $cart['count'];
$is_dev  = 'dev' === $variant;

$trigger_class = $is_dev
	? 'bb-dev-icon-btn bb-header-cart__trigger inline-flex shrink-0 items-center justify-center leading-none'
	: 'bb-header-cart__trigger inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-stone-200/80 bg-white/80 text-stone-700 shadow-sm transition hover:border-brand-300 hover:text-brand-700 dark:border-stone-600 dark:bg-stone-800/80 dark:text-stone-200 dark:hover:border-brand-500 dark:hover:text-brand-300';
?>
<div class="bb-header-cart group shrink-0" id="bb-header-cart-widget" data-bb-header-cart data-variant="<?php echo esc_attr( $variant ); ?>">
	<div class="bb-header-cart__drop">
		<a
			class="<?php echo esc_attr( $trigger_class ); ?>"
			href="<?php echo esc_url( blackbean_shop_cart_url() ); ?>"
			aria-label="<?php echo esc_attr( $count > 0 ? sprintf( /* translators: %d: cart item count */ _n( 'Cart, %d item', 'Cart, %d items', $count, 'blackbean' ), $count ) : __( 'Cart', 'blackbean' ) ); ?>"
		>
			<svg class="bb-header-cart__icon h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M6 6h15l-1.5 9h-12L6 6z" />
				<path stroke-linecap="round" stroke-linejoin="round" d="M6 6 5 3H2" />
				<circle cx="9" cy="20" r="1.25" fill="currentColor" stroke="none" />
				<circle cx="18" cy="20" r="1.25" fill="currentColor" stroke="none" />
			</svg>
			<?php if ( $count > 0 ) : ?>
				<span class="bb-header-cart__badge" data-bb-cart-badge><?php echo esc_html( (string) min( 99, $count ) ); ?></span>
			<?php else : ?>
				<span class="bb-header-cart__badge" data-bb-cart-badge hidden></span>
			<?php endif; ?>
		</a>

		<div
			class="bb-header-cart__panel"
			data-bb-header-cart-panel
			data-bb-header-cart-for="bb-header-cart-widget"
			role="region"
			aria-label="<?php esc_attr_e( 'Cart preview', 'blackbean' ); ?>"
		>
			<div class="bb-header-cart__panel-inner" data-bb-cart-panel-body>
				<?php echo blackbean_shop_header_cart_panel_html( $cart ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>
</div>
