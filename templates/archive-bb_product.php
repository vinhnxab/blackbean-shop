<?php
/**
 * Product archive.
 *
 * @package Blackbean
 */

get_header();

$products = isset( $GLOBALS['blackbean_products_archive'] ) && is_array( $GLOBALS['blackbean_products_archive'] )
	? $GLOBALS['blackbean_products_archive']
	: blackbean_products_query( array( 'status' => 'publish', 'limit' => 50 ) );
?>
<div class="<?php echo esc_attr( blackbean_shop_layout_classes( 'py-10' ) ); ?>">
	<header class="bb-reveal mb-10">
		<h1 class="font-display text-3xl font-bold text-stone-900 dark:text-stone-100"><?php esc_html_e( 'Shop', 'blackbean' ); ?></h1>
		<p class="mt-2 text-stone-600 dark:text-stone-400"><?php esc_html_e( 'Browse our latest products.', 'blackbean' ); ?></p>
		<p class="mt-4">
			<a class="<?php echo esc_attr( blackbean_shop_button_class( 'secondary' ) ); ?>" href="<?php echo esc_url( blackbean_shop_cart_url() ); ?>"><?php esc_html_e( 'View cart', 'blackbean' ); ?></a>
		</p>
	</header>

	<?php if ( ! empty( $products ) ) : ?>
		<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
			<?php
			foreach ( $products as $product_row ) :
				$product = blackbean_shop_get_product( $product_row );
				if ( ! $product ) {
					continue;
				}
				?>
				<article class="bb-shop-card bb-reveal flex flex-col overflow-hidden p-0">
					<?php if ( $product['image'] ) : ?>
						<a href="<?php echo esc_url( $product['url'] ); ?>" class="block aspect-[4/3] overflow-hidden bg-stone-100 dark:bg-stone-800">
							<img src="<?php echo esc_url( $product['image'] ); ?>" alt="" class="h-full w-full object-cover transition hover:scale-105" loading="lazy" />
						</a>
					<?php endif; ?>
					<div class="flex flex-1 flex-col p-5">
						<h2 class="text-lg font-semibold">
							<a class="hover:text-brand-600 dark:hover:text-brand-400" href="<?php echo esc_url( $product['url'] ); ?>"><?php echo esc_html( $product['title'] ); ?></a>
						</h2>
						<p class="mt-1 text-sm font-medium text-brand-700 dark:text-brand-300"><?php echo esc_html( $product['price_label'] ); ?></p>
						<?php if ( $product['excerpt'] ) : ?>
							<p class="mt-2 flex-1 text-sm text-stone-600 dark:text-stone-400"><?php echo esc_html( wp_html_excerpt( $product['excerpt'], 100, '…' ) ); ?></p>
						<?php endif; ?>
						<a class="<?php echo esc_attr( blackbean_shop_button_class( 'primary' ) ); ?> mt-4 w-full text-center" href="<?php echo esc_url( $product['url'] ); ?>"><?php esc_html_e( 'View product', 'blackbean' ); ?></a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
