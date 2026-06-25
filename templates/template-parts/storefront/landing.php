<?php
/**
 * Theme sales landing (homepage when storefront is enabled).
 *
 * @package Blackbean
 */

$product = function_exists( 'blackbean_storefront_product' ) ? blackbean_storefront_product() : null;
if ( ! $product ) {
	return;
}

$features = array(
	__( 'Tailwind CSS — light & dark, no page builder required', 'blackbean' ),
	__( 'Built-in digital shop — PayPal, licenses, secure downloads', 'blackbean' ),
	__( 'Developer UI — docs-style layout, markdown, OpenAPI page', 'blackbean' ),
	__( 'License activation — one site per purchase, REST API included', 'blackbean' ),
	__( 'Classic WordPress — no WooCommerce, no block editor lock-in', 'blackbean' ),
);

$cart_url = function_exists( 'blackbean_shop_cart_url' ) ? blackbean_shop_cart_url() : home_url( '/shop/cart/' );
$paypal   = function_exists( 'blackbean_shop_paypal_enabled' ) && blackbean_shop_paypal_enabled();
?>
<div class="<?php echo esc_attr( blackbean_shop_layout_classes( 'bb-storefront' ) ); ?>">
	<section class="bb-reveal bb-storefront-hero">
		<div class="bb-storefront-copy">
			<p class="font-mono text-[10px] uppercase tracking-[0.25em] text-brand-600 dark:text-brand-400"><?php esc_html_e( 'WordPress theme', 'blackbean' ); ?></p>
			<h1 class="font-display text-4xl font-bold tracking-tight text-stone-900 dark:text-stone-50 sm:text-5xl">
				<?php echo esc_html( $product['title'] ); ?>
			</h1>
			<?php if ( $product['excerpt'] ) : ?>
				<p class="bb-storefront-copy__lead"><?php echo esc_html( $product['excerpt'] ); ?></p>
			<?php else : ?>
				<p class="bb-storefront-copy__lead">
					<?php esc_html_e( 'Ship a polished WordPress site with a built-in shop for plugins, themes, and digital goods.', 'blackbean' ); ?>
				</p>
			<?php endif; ?>

			<div class="bb-storefront-copy__price">
				<p class="text-4xl font-semibold text-brand-700 dark:text-brand-300"><?php echo esc_html( $product['price_label'] ); ?></p>
				<p class="text-sm text-stone-500 dark:text-stone-400"><?php esc_html_e( 'One site license · Lifetime updates · Instant download', 'blackbean' ); ?></p>
			</div>

			<div class="bb-storefront-copy__cta">
				<div class="flex flex-wrap items-center gap-3">
					<?php if ( $product['in_stock'] ) : ?>
						<form class="blackbean-shop-add inline-flex" data-product-id="<?php echo esc_attr( (string) $product['id'] ); ?>">
							<input type="hidden" name="qty" value="1" />
							<button type="submit" class="<?php echo esc_attr( blackbean_shop_button_class( 'primary' ) ); ?> text-base px-6 py-3"><?php esc_html_e( 'Buy now', 'blackbean' ); ?></button>
						</form>
						<a class="<?php echo esc_attr( blackbean_shop_button_class( 'secondary' ) ); ?> text-base px-6 py-3" href="<?php echo esc_url( $product['url'] ); ?>"><?php esc_html_e( 'Details', 'blackbean' ); ?></a>
					<?php else : ?>
						<span class="rounded-lg bg-red-50 px-4 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300"><?php esc_html_e( 'Currently unavailable', 'blackbean' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="blackbean-shop-add-notice flex flex-wrap items-center gap-3" hidden role="status" aria-live="polite"></div>

				<?php if ( $paypal ) : ?>
					<p class="text-xs text-stone-500 dark:text-stone-400"><?php esc_html_e( 'Secure checkout via PayPal. License key and download link sent by email.', 'blackbean' ); ?></p>
				<?php else : ?>
					<p class="text-xs text-amber-700 dark:text-amber-400"><?php esc_html_e( 'PayPal is not configured yet — enable it under Settings → Shop.', 'blackbean' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="bb-storefront-media">
			<?php if ( $product['image'] ) : ?>
				<img src="<?php echo esc_url( $product['image'] ); ?>" alt="" class="w-full rounded-2xl border border-stone-200 shadow-lg dark:border-stone-700" />
			<?php else : ?>
				<div class="flex aspect-[4/3] items-center justify-center rounded-2xl border border-dashed border-stone-300 bg-stone-50 font-mono text-sm text-stone-400 dark:border-stone-600 dark:bg-stone-800/50">
					<?php esc_html_e( 'Add a product featured image', 'blackbean' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="bb-reveal bb-storefront-section">
		<h2 class="font-display text-2xl font-semibold text-stone-900 dark:text-stone-50"><?php esc_html_e( 'What you get', 'blackbean' ); ?></h2>
		<ul class="bb-storefront-features">
			<?php foreach ( $features as $feature ) : ?>
				<li class="flex gap-3 rounded-xl border border-stone-200 bg-white p-4 text-sm text-stone-700 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-300">
					<span class="mt-0.5 shrink-0 text-brand-600 dark:text-brand-400" aria-hidden="true">✓</span>
					<span><?php echo esc_html( $feature ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="bb-reveal bb-storefront-panel">
		<h2 class="font-display text-xl font-semibold text-stone-900 dark:text-stone-50"><?php esc_html_e( 'How it works', 'blackbean' ); ?></h2>
		<ol class="bb-storefront-steps">
			<li class="flex flex-col gap-2">
				<p class="font-mono text-xs text-brand-600 dark:text-brand-400">01</p>
				<p class="font-medium text-stone-900 dark:text-stone-100"><?php esc_html_e( 'Checkout', 'blackbean' ); ?></p>
				<p class="text-sm leading-relaxed text-stone-600 dark:text-stone-400"><?php esc_html_e( 'Pay with PayPal. No account required.', 'blackbean' ); ?></p>
			</li>
			<li class="flex flex-col gap-2">
				<p class="font-mono text-xs text-brand-600 dark:text-brand-400">02</p>
				<p class="font-medium text-stone-900 dark:text-stone-100"><?php esc_html_e( 'Download', 'blackbean' ); ?></p>
				<p class="text-sm leading-relaxed text-stone-600 dark:text-stone-400"><?php esc_html_e( 'ZIP + license key arrive by email instantly.', 'blackbean' ); ?></p>
			</li>
			<li class="flex flex-col gap-2">
				<p class="font-mono text-xs text-brand-600 dark:text-brand-400">03</p>
				<p class="font-medium text-stone-900 dark:text-stone-100"><?php esc_html_e( 'Activate', 'blackbean' ); ?></p>
				<p class="text-sm leading-relaxed text-stone-600 dark:text-stone-400"><?php esc_html_e( 'Appearance → License on your site.', 'blackbean' ); ?></p>
			</li>
		</ol>
		<p class="bb-storefront-panel__cta">
			<a class="<?php echo esc_attr( blackbean_shop_button_class( 'primary' ) ); ?> inline-flex" href="<?php echo esc_url( $product['url'] ); ?>"><?php esc_html_e( 'View product page', 'blackbean' ); ?></a>
			<a class="text-sm text-stone-600 underline hover:text-brand-600 dark:text-stone-400" href="<?php echo esc_url( $cart_url ); ?>"><?php esc_html_e( 'Cart', 'blackbean' ); ?></a>
		</p>
	</section>
</div>
