<?php
/**
 * Shop admin — orders and product manager UI.
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, string>
 */
function blackbean_shop_admin_ui_classes() : array {
	return blackbean_admin_ui_classes();
}

/**
 * @param bool $in_stock Whether product is in stock.
 */
function blackbean_shop_admin_stock_badge_class( bool $in_stock ) : string {
	$ui = blackbean_shop_admin_ui_classes();
	return $in_stock ? $ui['badge_in'] : $ui['badge_out'];
}

/**
 * @param string $status Order status key.
 */
function blackbean_shop_admin_order_status_badge_class( string $status ) : string {
	$ui = blackbean_shop_admin_ui_classes();
	$map = array(
		'pending'    => $ui['badge_warn'],
		'processing' => $ui['badge_neutral'],
		'completed'  => $ui['badge_in'],
		'cancelled'  => $ui['badge_out'],
	);
	return $map[ $status ] ?? $ui['badge_neutral'];
}

/**
 * @param string $status Payment status key.
 */
function blackbean_shop_admin_payment_status_badge_class( string $status ) : string {
	$ui = blackbean_shop_admin_ui_classes();
	if ( 'paid' === $status ) {
		return $ui['badge_in'];
	}
	if ( in_array( $status, array( 'failed', 'refunded', 'cancelled' ), true ) ) {
		return $ui['badge_out'];
	}
	return $ui['badge_warn'];
}

/**
 * @param array<int, array<string, mixed>> $items Order line items.
 * @return array{primary: string, more: int}
 */
function blackbean_shop_order_items_summary( array $items ) : array {
	if ( empty( $items ) ) {
		return array(
			'primary' => '—',
			'more'    => 0,
		);
	}
	$first = $items[0];
	$title = isset( $first['title'] ) ? (string) $first['title'] : __( 'Item', 'blackbean' );
	$qty   = max( 1, (int) ( $first['qty'] ?? 1 ) );
	return array(
		'primary' => $title . ' × ' . $qty,
		'more'    => max( 0, count( $items ) - 1 ),
	);
}

/**
 * Count orders grouped by fulfillment status.
 *
 * @return array<string, int>
 */
function blackbean_shop_order_status_counts() : array {
	return blackbean_order_status_counts_from_table();
}

/**
 * Dedicated Product Manager page (custom UI, not wp-list-table).
 */
function blackbean_shop_render_manager_page() : void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage products.', 'blackbean' ) );
	}

	$status_filter = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : 'all';
	$query_args    = array(
		'limit'   => 200,
		'orderby' => 'title',
		'order'   => 'ASC',
	);
	if ( 'all' !== $status_filter && in_array( $status_filter, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
		$query_args['status'] = $status_filter;
	}

	$products = blackbean_products_query( $query_args );
	$counts   = blackbean_product_status_counts();
	$ui       = blackbean_shop_admin_ui_classes();
	$base_url = admin_url( 'admin.php?page=blackbean-shop-manager' );
	$shop_url = function_exists( 'blackbean_shop_products_url' ) ? blackbean_shop_products_url() : home_url( '/products/' );

	$tabs = array(
		'all'     => __( 'All', 'blackbean' ),
		'publish' => __( 'Published', 'blackbean' ),
		'draft'   => __( 'Drafts', 'blackbean' ),
	);
	?>
	<div class="<?php echo esc_attr( $ui['manager_wrap'] ); ?>">
		<header class="bb-shop-page-header">
			<div>
				<h1 class="bb-shop-page-title"><?php esc_html_e( 'Product manager', 'blackbean' ); ?></h1>
				<p class="bb-shop-page-desc"><?php esc_html_e( 'Manage catalog, pricing, stock, and digital delivery.', 'blackbean' ); ?></p>
			</div>
			<div class="<?php echo esc_attr( $ui['toolbar'] ); ?>">
				<a class="<?php echo esc_attr( $ui['btn_pri'] ); ?>" href="<?php echo esc_url( blackbean_shop_product_edit_url() ); ?>">
					<?php esc_html_e( 'Add product', 'blackbean' ); ?>
				</a>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( $shop_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View shop', 'blackbean' ); ?></a>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=blackbean-order-manager' ) ); ?>"><?php esc_html_e( 'Orders', 'blackbean' ); ?></a>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=blackbean-license-manager' ) ); ?>"><?php esc_html_e( 'Licenses', 'blackbean' ); ?></a>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( blackbean_shop_settings_admin_url() ); ?>"><?php esc_html_e( 'Settings', 'blackbean' ); ?></a>
			</div>
		</header>

		<nav class="bb-shop-tabs" aria-label="<?php esc_attr_e( 'Filter products', 'blackbean' ); ?>">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<?php
				$count = (int) ( $counts[ $key ] ?? 0 );
				$url   = add_query_arg( 'status', $key, $base_url );
				if ( 'all' === $key ) {
					$url = remove_query_arg( 'status', $base_url );
				}
				?>
				<a class="bb-shop-tab<?php echo $status_filter === $key || ( 'all' === $key && 'all' === $status_filter ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
					<?php echo esc_html( $label ); ?>
					<span class="bb-shop-tab-count"><?php echo esc_html( (string) $count ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="<?php echo esc_attr( $ui['table_wrap'] ); ?>">
			<?php if ( empty( $products ) ) : ?>
				<div class="bb-shop-empty-state">
					<p class="bb-shop-empty"><?php esc_html_e( 'No products yet. Create your first product.', 'blackbean' ); ?></p>
					<a class="<?php echo esc_attr( $ui['btn_pri'] ); ?>" href="<?php echo esc_url( blackbean_shop_product_edit_url() ); ?>"><?php esc_html_e( 'Add product', 'blackbean' ); ?></a>
				</div>
			<?php else : ?>
				<table class="<?php echo esc_attr( $ui['table'] ); ?>">
					<thead>
						<tr>
							<th scope="col" class="bb-shop-col-thumb"><?php esc_html_e( 'Image', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Product', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Price', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'SKU', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Stock', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Sales', 'blackbean' ); ?></th>
							<th scope="col" class="bb-shop-col-actions"><?php esc_html_e( 'Actions', 'blackbean' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $products as $product_row ) :
							$product = blackbean_shop_get_product_admin( (int) $product_row['id'] );
							if ( ! $product ) {
								continue;
							}
							$stats       = blackbean_shop_product_sales_stats( (int) $product_row['id'] );
							$status_lbl  = ucfirst( $product['status'] );
							$edit_url    = blackbean_shop_product_edit_url( (int) $product_row['id'] );
							$view_url    = 'publish' === $product['status'] ? $product['url'] : '';
							?>
							<tr>
								<td class="bb-shop-col-thumb">
									<?php if ( $product['thumb_html'] ) : ?>
										<?php echo wp_kses_post( $product['thumb_html'] ); ?>
									<?php else : ?>
										<span class="bb-shop-thumb-placeholder" aria-hidden="true"></span>
									<?php endif; ?>
								</td>
								<td>
									<a class="bb-shop-product-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $product['title'] ); ?></a>
									<span class="bb-shop-product-meta"><?php echo esc_html( $status_lbl ); ?> · <?php echo esc_html( mysql2date( get_option( 'date_format' ), $product['modified'] ) ); ?></span>
								</td>
								<td><strong><?php echo esc_html( $product['price_label'] ); ?></strong></td>
								<td><?php echo $product['sku'] ? '<code>' . esc_html( $product['sku'] ) . '</code>' : '—'; ?></td>
								<td>
									<span class="<?php echo esc_attr( blackbean_shop_admin_stock_badge_class( $product['in_stock'] ) ); ?>">
										<?php echo esc_html( $product['stock_label'] ); ?>
									</span>
								</td>
								<td>
									<span class="<?php echo esc_attr( $ui['badge_neutral'] ); ?>"><?php echo esc_html( $product['type_label'] ); ?></span>
									<?php if ( $product['is_digital'] && ! $product['has_download'] ) : ?>
										<br><span class="<?php echo esc_attr( $ui['badge_warn'] ); ?>"><?php esc_html_e( 'No download', 'blackbean' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $stats['units_sold'] > 0 ) : ?>
										<strong><?php echo esc_html( (string) $stats['units_sold'] ); ?></strong>
										<span class="bb-shop-sales-muted"><?php echo esc_html( $stats['revenue_label'] ); ?></span>
									<?php else : ?>
										<span class="bb-shop-sales-muted"><?php esc_html_e( 'No sales', 'blackbean' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="bb-shop-col-actions">
									<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'blackbean' ); ?></a>
									<?php if ( $view_url ) : ?>
										<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'blackbean' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * @param string $status Order status key.
 */
function blackbean_shop_order_status_label( string $status ) : string {
	$statuses = blackbean_shop_order_statuses();
	return $statuses[ $status ] ?? ucfirst( $status );
}

/**
 * @param int $order_id Order post ID.
 * @return array<string, mixed>|null
 */
function blackbean_shop_get_order( int $order_id ) : ?array {
	$row = blackbean_order_get_row( $order_id );
	if ( ! $row ) {
		return null;
	}
	return blackbean_order_format( $row );
}

/**
 * Register dedicated Order Manager admin page.
 */
function blackbean_shop_register_order_manager_page() : void {
	add_submenu_page(
		'blackbean-shop-manager',
		__( 'Orders', 'blackbean' ),
		__( 'Orders', 'blackbean' ),
		'edit_posts',
		'blackbean-order-manager',
		'blackbean_shop_render_order_manager_page'
	);
}
add_action( 'admin_menu', 'blackbean_shop_register_order_manager_page', 5 );

/**
 * Dedicated Order Manager page (custom UI, not wp-list-table).
 */
function blackbean_shop_render_order_manager_page() : void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage orders.', 'blackbean' ) );
	}

	$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
	if ( $order_id > 0 ) {
		blackbean_shop_render_order_detail_page( $order_id );
		return;
	}

	$status_filter = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : 'all';
	$query_args    = array(
		'limit' => 200,
	);
	if ( 'all' !== $status_filter && array_key_exists( $status_filter, blackbean_shop_order_statuses(), true ) ) {
		$query_args['status'] = $status_filter;
	}

	$orders     = blackbean_orders_query( $query_args );
	$counts     = blackbean_shop_order_status_counts();
	$ui         = blackbean_shop_admin_ui_classes();
	$base_url   = admin_url( 'admin.php?page=blackbean-order-manager' );
	$shop_url   = function_exists( 'blackbean_shop_products_url' ) ? blackbean_shop_products_url() : home_url( '/products/' );
	$product_url = admin_url( 'admin.php?page=blackbean-shop-manager' );

	$tabs = array_merge(
		array( 'all' => __( 'All', 'blackbean' ) ),
		blackbean_shop_order_statuses()
	);
	?>
	<div class="<?php echo esc_attr( $ui['manager_wrap'] ); ?>">
		<header class="bb-shop-page-header">
			<div>
				<h1 class="bb-shop-page-title"><?php esc_html_e( 'Order manager', 'blackbean' ); ?></h1>
				<p class="bb-shop-page-desc"><?php esc_html_e( 'Review customers, payments, fulfillment, and order status.', 'blackbean' ); ?></p>
			</div>
			<div class="<?php echo esc_attr( $ui['toolbar'] ); ?>">
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( $product_url ); ?>"><?php esc_html_e( 'Products', 'blackbean' ); ?></a>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=blackbean-license-manager' ) ); ?>"><?php esc_html_e( 'Licenses', 'blackbean' ); ?></a>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( $shop_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View shop', 'blackbean' ); ?></a>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( blackbean_shop_settings_admin_url() ); ?>"><?php esc_html_e( 'Settings', 'blackbean' ); ?></a>
			</div>
		</header>

		<nav class="bb-shop-tabs" aria-label="<?php esc_attr_e( 'Filter orders', 'blackbean' ); ?>">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<?php
				$count = (int) ( $counts[ $key ] ?? 0 );
				$url   = 'all' === $key ? remove_query_arg( 'status', $base_url ) : add_query_arg( 'status', $key, $base_url );
				?>
				<a class="bb-shop-tab<?php echo $status_filter === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
					<?php echo esc_html( $label ); ?>
					<span class="bb-shop-tab-count"><?php echo esc_html( (string) $count ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="<?php echo esc_attr( $ui['table_wrap'] ); ?>">
			<?php if ( empty( $orders ) ) : ?>
				<p class="bb-shop-empty"><?php esc_html_e( 'No orders yet. Orders appear here after checkout.', 'blackbean' ); ?></p>
			<?php else : ?>
				<table class="<?php echo esc_attr( $ui['table'] ); ?>">
					<thead>
						<tr>
							<th scope="col" class="bb-shop-col-order"><?php esc_html_e( 'Order', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Customer', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Items', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Total', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Payment', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'blackbean' ); ?></th>
							<th scope="col" class="bb-shop-col-actions"><?php esc_html_e( 'Actions', 'blackbean' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $orders as $order_row ) :
							$order = blackbean_shop_get_order( (int) $order_row['id'] );
							if ( ! $order ) {
								continue;
							}
							$items_summary = blackbean_shop_order_items_summary( $order['items'] );
							$customer      = $order['customer'];
							$edit_url      = $order['edit_url'];
							?>
							<tr>
								<td class="bb-shop-col-order">
									<a class="bb-shop-product-title" href="<?php echo esc_url( $edit_url ); ?>">#<?php echo esc_html( (string) $order['id'] ); ?></a>
									<span class="bb-shop-product-meta"><?php echo esc_html( $order['date'] ? mysql2date( get_option( 'date_format' ), $order['date'], true ) : '' ); ?></span>
								</td>
								<td>
									<strong><?php echo esc_html( $customer['name'] ?: '—' ); ?></strong>
									<?php if ( $customer['email'] ) : ?>
										<span class="bb-shop-product-meta"><a href="mailto:<?php echo esc_attr( $customer['email'] ); ?>"><?php echo esc_html( $customer['email'] ); ?></a></span>
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html( $items_summary['primary'] ); ?>
									<?php if ( $items_summary['more'] > 0 ) : ?>
										<span class="bb-shop-sales-muted">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %d: additional item count */
													_n( '+%d more', '+%d more', $items_summary['more'], 'blackbean' ),
													$items_summary['more']
												)
											);
											?>
										</span>
									<?php endif; ?>
								</td>
								<td><strong><?php echo esc_html( $order['total_label'] ); ?></strong></td>
								<td>
									<span class="<?php echo esc_attr( blackbean_shop_admin_payment_status_badge_class( $order['payment_status'] ) ); ?>">
										<?php echo esc_html( ucfirst( $order['payment_status'] ) ); ?>
									</span>
								</td>
								<td>
									<span class="<?php echo esc_attr( blackbean_shop_admin_order_status_badge_class( $order['status'] ) ); ?>">
										<?php echo esc_html( $order['status_label'] ); ?>
									</span>
								</td>
								<td class="bb-shop-col-actions">
									<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'View', 'blackbean' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
	<?php
}


/**
 * Order detail screen inside Order Manager.
 *
 * @param int $order_id Order ID.
 */
function blackbean_shop_render_order_detail_page( int $order_id ) : void {
	$order = blackbean_shop_get_order( $order_id );
	if ( ! $order ) {
		wp_die(
			wp_kses_post(
				sprintf(
					/* translators: %d: order ID */
					__( 'Order #%d was not found in the orders table.', 'blackbean' ),
					$order_id
				)
			),
			esc_html__( 'Order not found', 'blackbean' ),
			array( 'back_link' => true )
		);
	}

	$ui = blackbean_shop_admin_ui_classes();
	?>
	<div class="<?php echo esc_attr( $ui['manager_wrap'] ); ?>">
		<header class="bb-shop-page-header">
			<div>
				<h1 class="bb-shop-page-title">
					<?php
					printf(
						/* translators: %d: order ID */
						esc_html__( 'Order #%d', 'blackbean' ),
						$order_id
					);
					?>
				</h1>
			</div>
			<div class="<?php echo esc_attr( $ui['toolbar'] ); ?>">
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=blackbean-order-manager' ) ); ?>"><?php esc_html_e( 'All orders', 'blackbean' ); ?></a>
			</div>
		</header>

		<?php if ( ! empty( $_GET['saved'] ) ) : ?>
			<?php blackbean_admin_render_notice( esc_html__( 'Order saved.', 'blackbean' ), 'ok' ); ?>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blackbean_save_order" />
			<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order_id ); ?>" />
			<?php
			wp_nonce_field( 'blackbean_shop_order_save', 'blackbean_shop_order_nonce' );
			blackbean_shop_render_order_detail( $order_id );
			?>
		</form>
	</div>
	<?php
}

/**
 * Order detail markup (manager UI).
 *
 * @param int $order_id Order ID.
 */
function blackbean_shop_render_order_detail( int $order_id ) : void {
	$order = blackbean_shop_get_order( $order_id );
	if ( ! $order ) {
		echo '<p>' . esc_html__( 'Order data not found.', 'blackbean' ) . '</p>';
		return;
	}

	$c   = $order['customer'];
	$ui  = blackbean_shop_admin_ui_classes();
	$pay = (string) $order['payment_status'];
	$paypal_id = (string) blackbean_order_get_meta( $order_id, BLACKBEAN_SHOP_META_PAYPAL_ORDER );
	$fulfillment = function_exists( 'blackbean_shop_get_fulfillment_display' )
		? blackbean_shop_get_fulfillment_display( $order_id )
		: array();
	$license_rows = function_exists( 'blackbean_shop_license_list_for_order' )
		? blackbean_shop_license_list_for_order( $order_id )
		: array();
	$license_by_key = array();
	foreach ( $license_rows as $lic ) {
		$license_by_key[ (string) ( $lic['license_key'] ?? '' ) ] = $lic;
	}
	$date_label = $order['date']
		? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $order['date'], true )
		: '';
	?>
	<div class="<?php echo esc_attr( $ui['root'] ); ?> bb-shop-order-edit">
		<div class="<?php echo esc_attr( $ui['card'] ); ?>">
			<div class="<?php echo esc_attr( $ui['card_head'] ); ?>">
				<div>
					<p class="<?php echo esc_attr( $ui['card_title'] ); ?>"><?php esc_html_e( 'Order summary', 'blackbean' ); ?></p>
					<p class="<?php echo esc_attr( $ui['card_sub'] ); ?>">
						<?php echo esc_html( $date_label ); ?>
					</p>
				</div>
				<div class="bb-shop-order-meta-row">
					<span class="<?php echo esc_attr( blackbean_shop_admin_payment_status_badge_class( $pay ) ); ?>">
						<?php echo esc_html( ucfirst( $pay ) ); ?>
					</span>
					<span class="<?php echo esc_attr( blackbean_shop_admin_order_status_badge_class( (string) $order['status'] ) ); ?>">
						<?php echo esc_html( $order['status_label'] ); ?>
					</span>
				</div>
			</div>

			<div class="<?php echo esc_attr( $ui['grid'] ); ?>">
				<div class="<?php echo esc_attr( $ui['stat'] ); ?>">
					<p class="<?php echo esc_attr( $ui['label'] ); ?>"><?php esc_html_e( 'Total', 'blackbean' ); ?></p>
					<p class="<?php echo esc_attr( $ui['value'] ); ?>"><?php echo esc_html( $order['total_label'] ); ?></p>
				</div>
				<div class="<?php echo esc_attr( $ui['stat'] ); ?>">
					<p class="<?php echo esc_attr( $ui['label'] ); ?>"><?php esc_html_e( 'Payment', 'blackbean' ); ?></p>
					<p class="<?php echo esc_attr( $ui['value'] ); ?>">
						<span class="<?php echo esc_attr( blackbean_shop_admin_payment_status_badge_class( $pay ) ); ?>">
							<?php echo esc_html( ucfirst( $pay ) ); ?>
						</span>
					</p>
				</div>
				<?php if ( '' !== $paypal_id ) : ?>
					<div class="<?php echo esc_attr( $ui['stat'] ); ?>">
						<p class="<?php echo esc_attr( $ui['label'] ); ?>"><?php esc_html_e( 'PayPal', 'blackbean' ); ?></p>
						<p class="<?php echo esc_attr( $ui['value'] ); ?>"><code><?php echo esc_html( $paypal_id ); ?></code></p>
					</div>
				<?php endif; ?>
				<div class="<?php echo esc_attr( $ui['stat'] ); ?>">
					<p class="<?php echo esc_attr( $ui['label'] ); ?>"><?php esc_html_e( 'Items', 'blackbean' ); ?></p>
					<p class="<?php echo esc_attr( $ui['value'] ); ?>"><?php echo esc_html( (string) count( $order['items'] ) ); ?></p>
				</div>
			</div>

			<div class="<?php echo esc_attr( $ui['field'] ); ?>" style="margin-top:1rem;max-width:16rem;">
				<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_order_status"><?php esc_html_e( 'Fulfillment status', 'blackbean' ); ?></label>
				<select class="<?php echo esc_attr( $ui['input'] ); ?>" name="bb_order_status" id="bb_order_status">
					<?php foreach ( blackbean_shop_order_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $order['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="<?php echo esc_attr( $ui['hint'] ); ?>"><?php esc_html_e( 'Customer and line items are read-only.', 'blackbean' ); ?></p>
			</div>
		</div>

		<div class="<?php echo esc_attr( $ui['card'] ); ?>" style="margin-top:1rem;">
			<p class="<?php echo esc_attr( $ui['section_title'] ); ?>"><?php esc_html_e( 'Customer', 'blackbean' ); ?></p>
			<div class="<?php echo esc_attr( $ui['grid'] ); ?>">
				<div class="<?php echo esc_attr( $ui['stat'] ); ?>">
					<p class="<?php echo esc_attr( $ui['label'] ); ?>"><?php esc_html_e( 'Name', 'blackbean' ); ?></p>
					<p class="<?php echo esc_attr( $ui['value'] ); ?>"><?php echo esc_html( $c['name'] ); ?></p>
				</div>
				<div class="<?php echo esc_attr( $ui['stat'] ); ?>">
					<p class="<?php echo esc_attr( $ui['label'] ); ?>"><?php esc_html_e( 'Email', 'blackbean' ); ?></p>
					<p class="<?php echo esc_attr( $ui['value'] ); ?>">
						<a href="mailto:<?php echo esc_attr( $c['email'] ); ?>" style="color:#6d28d9;text-decoration:none;"><?php echo esc_html( $c['email'] ); ?></a>
					</p>
				</div>
				<?php if ( $c['phone'] ) : ?>
					<div class="<?php echo esc_attr( $ui['stat'] ); ?>">
						<p class="<?php echo esc_attr( $ui['label'] ); ?>"><?php esc_html_e( 'Phone', 'blackbean' ); ?></p>
						<p class="<?php echo esc_attr( $ui['value'] ); ?>"><?php echo esc_html( $c['phone'] ); ?></p>
					</div>
				<?php endif; ?>
				<?php if ( $c['user_id'] > 0 ) : ?>
					<div class="<?php echo esc_attr( $ui['stat'] ); ?>">
						<p class="<?php echo esc_attr( $ui['label'] ); ?>"><?php esc_html_e( 'Account', 'blackbean' ); ?></p>
						<p class="<?php echo esc_attr( $ui['value'] ); ?>">
							<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( get_edit_user_link( $c['user_id'] ) ); ?>"><?php esc_html_e( 'View profile', 'blackbean' ); ?></a>
						</p>
					</div>
				<?php endif; ?>
			</div>
			<?php if ( $c['address'] ) : ?>
				<div class="<?php echo esc_attr( $ui['panel'] ); ?>">
					<p class="<?php echo esc_attr( $ui['label'] ); ?>"><?php esc_html_e( 'Address', 'blackbean' ); ?></p>
					<p class="<?php echo esc_attr( $ui['value'] ); ?>" style="font-weight:400;white-space:pre-line;"><?php echo esc_html( $c['address'] ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( $c['notes'] ) : ?>
				<div class="<?php echo esc_attr( $ui['panel'] ); ?>">
					<p class="<?php echo esc_attr( $ui['label'] ); ?>"><?php esc_html_e( 'Notes', 'blackbean' ); ?></p>
					<p class="<?php echo esc_attr( $ui['value'] ); ?>" style="font-weight:400;white-space:pre-line;"><?php echo esc_html( $c['notes'] ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<div class="<?php echo esc_attr( $ui['card'] ); ?>" style="margin-top:1rem;">
			<p class="<?php echo esc_attr( $ui['section_title'] ); ?>"><?php esc_html_e( 'Line items', 'blackbean' ); ?></p>
			<?php if ( empty( $order['items'] ) ) : ?>
				<p class="<?php echo esc_attr( $ui['hint'] ); ?>"><?php esc_html_e( 'No items recorded.', 'blackbean' ); ?></p>
			<?php else : ?>
				<div class="<?php echo esc_attr( $ui['table_wrap'] ); ?>">
					<table class="<?php echo esc_attr( $ui['table'] ); ?>">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Product', 'blackbean' ); ?></th>
								<th><?php esc_html_e( 'Qty', 'blackbean' ); ?></th>
								<th><?php esc_html_e( 'Price', 'blackbean' ); ?></th>
								<th><?php esc_html_e( 'Line total', 'blackbean' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $order['items'] as $item ) : ?>
								<tr>
									<td>
										<?php if ( ! empty( $item['url'] ) ) : ?>
											<a class="bb-shop-product-title" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['title'] ?? '' ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $item['title'] ?? '' ); ?>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( (string) ( $item['qty'] ?? 1 ) ); ?></td>
									<td><?php echo esc_html( $item['price_label'] ?? '' ); ?></td>
									<td><strong><?php echo esc_html( $item['line_label'] ?? '' ); ?></strong></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="3" style="text-align:right;"><?php esc_html_e( 'Order total', 'blackbean' ); ?></th>
								<td><strong><?php echo esc_html( $order['total_label'] ); ?></strong></td>
							</tr>
						</tfoot>
					</table>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $fulfillment ) ) : ?>
			<div class="<?php echo esc_attr( $ui['card'] ); ?>" style="margin-top:1rem;">
				<p class="<?php echo esc_attr( $ui['section_title'] ); ?>"><?php esc_html_e( 'Digital delivery', 'blackbean' ); ?></p>
				<div class="<?php echo esc_attr( $ui['table_wrap'] ); ?>">
					<table class="<?php echo esc_attr( $ui['table'] ); ?>">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Product', 'blackbean' ); ?></th>
								<th><?php esc_html_e( 'License', 'blackbean' ); ?></th>
								<th><?php esc_html_e( 'Activations', 'blackbean' ); ?></th>
								<th><?php esc_html_e( 'Download', 'blackbean' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $fulfillment as $row ) : ?>
								<?php
								$lic_row = isset( $row['license'] ) ? ( $license_by_key[ (string) $row['license'] ] ?? null ) : null;
								?>
								<tr>
									<td><?php echo esc_html( $row['title'] ); ?></td>
									<td><code><?php echo esc_html( $row['license'] ); ?></code></td>
									<td>
										<?php
										if ( $lic_row ) {
											$used = (int) ( $lic_row['activation_count'] ?? 0 );
											$max  = (int) ( $lic_row['max_activations'] ?? 1 );
											echo esc_html( sprintf( '%1$d / %2$d', $used, $max ) );
											if ( ! empty( $lic_row['activations'] ) ) {
												echo '<ul class="bb-shop-order-license-sites">';
												foreach ( $lic_row['activations'] as $act ) {
													echo '<li><code>' . esc_html( (string) ( $act['site_url'] ?? '' ) ) . '</code></li>';
												}
												echo '</ul>';
											}
											if ( 'revoked' === (string) ( $lic_row['status'] ?? '' ) ) {
												echo '<p class="' . esc_attr( $ui['hint'] ) . '">' . esc_html__( 'Revoked', 'blackbean' ) . '</p>';
											}
										} else {
											echo '—';
										}
										?>
									</td>
									<td>
										<?php if ( ! empty( $row['download_url'] ) ) : ?>
											<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( $row['download_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download', 'blackbean' ); ?></a>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>

		<div class="bb-shop-order-save">
			<?php
			submit_button(
				__( 'Save order', 'blackbean' ),
				'primary large',
				'save',
				false,
				array(
					'id' => 'bb-order-save',
				)
			);
			?>
			<p class="<?php echo esc_attr( $ui['hint'] ); ?>"><?php esc_html_e( 'Saves fulfillment status.', 'blackbean' ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * @param int $post_id Post ID.
 */
function blackbean_shop_save_order_meta( int $post_id ) : void {
	if ( ! isset( $_POST['blackbean_shop_order_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['blackbean_shop_order_nonce'] ) ), 'blackbean_shop_order_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$status = isset( $_POST['bb_order_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['bb_order_status'] ) ) : 'pending';
	if ( ! array_key_exists( $status, blackbean_shop_order_statuses() ) ) {
		$status = 'pending';
	}
	blackbean_order_update_meta( $post_id, '_bb_order_status', $status );
}
add_action( 'admin_post_blackbean_save_order', 'blackbean_shop_save_order_meta_from_manager' );

/**
 * Save order status from order manager detail form.
 */
function blackbean_shop_save_order_meta_from_manager() : void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Forbidden', 'blackbean' ) );
	}
	$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
	if ( $order_id <= 0 || ! blackbean_order_get_row( $order_id ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=blackbean-order-manager' ) );
		exit;
	}
	blackbean_shop_save_order_meta( $order_id );
	wp_safe_redirect( admin_url( 'admin.php?page=blackbean-order-manager&order_id=' . $order_id . '&saved=1' ) );
	exit;
}

/**
 * Admin product row (any post status).
 *
 * @param int|WP_Post $post Post.
 * @return array<string, mixed>|null
 */
function blackbean_shop_get_product_admin( $product_ref ) : ?array {
	$id = is_numeric( $product_ref ) ? (int) $product_ref : 0;
	if ( $product_ref instanceof WP_Post ) {
		$id = (int) $product_ref->ID;
	}
	$row = blackbean_product_get_row( $id );
	if ( ! $row ) {
		return null;
	}

	$price     = (float) ( $row['price'] ?? 0 );
	$stock     = isset( $row['stock'] ) ? (int) $row['stock'] : -1;
	$is_digital = ! empty( $row['is_digital'] );
	$download   = function_exists( 'blackbean_shop_product_download_source' )
		? blackbean_shop_product_download_source( $id )
		: '';

	$thumb_html = '';
	if ( ! empty( $row['featured_image_id'] ) ) {
		$thumb_html = wp_get_attachment_image( (int) $row['featured_image_id'], array( 48, 48 ), false, array( 'style' => 'border-radius:6px;height:auto;max-width:48px;border:1px solid #e4e4e7;' ) );
	}

	return array(
		'id'                 => $id,
		'title'              => (string) $row['title'],
		'status'             => (string) $row['status'],
		'modified'           => (string) $row['updated_at'],
		'url'                => blackbean_product_permalink( (string) $row['slug'] ),
		'edit_url'           => blackbean_shop_product_edit_url( $id ),
		'price'              => $price,
		'price_label'        => blackbean_shop_format_price( $price ),
		'sku'                => (string) ( $row['sku'] ?? '' ),
		'stock'              => $stock,
		'in_stock'           => $stock < 0 || $stock > 0,
		'stock_label'        => blackbean_shop_stock_admin_label( $stock ),
		'is_digital'         => $is_digital,
		'type_label'         => $is_digital ? __( 'Digital', 'blackbean' ) : __( 'Physical', 'blackbean' ),
		'has_download'       => '' !== $download,
		'download_url'       => $download,
		'download_file_id'   => (int) ( $row['download_file_id'] ?? 0 ),
		'license_prefix'     => function_exists( 'blackbean_shop_product_license_prefix' )
			? blackbean_shop_product_license_prefix( $id )
			: (string) ( $row['license_prefix'] ?? '' ),
		'license_max_sites'  => function_exists( 'blackbean_shop_product_max_license_activations' )
			? blackbean_shop_product_max_license_activations( $id )
			: (int) ( $row['license_max_sites'] ?? 1 ),
		'thumb_html'         => is_string( $thumb_html ) ? $thumb_html : '',
	);
}

/**
 * Human-readable stock label for admin.
 *
 * @param int $stock Stock (-1 = unlimited).
 */
function blackbean_shop_stock_admin_label( int $stock ) : string {
	if ( $stock < 0 ) {
		return __( 'Unlimited', 'blackbean' );
	}
	if ( 0 === $stock ) {
		return __( 'Out of stock', 'blackbean' );
	}
	return sprintf(
		/* translators: %d: quantity */
		__( '%d in stock', 'blackbean' ),
		$stock
	);
}

/**
 * Paid-order sales stats for a product.
 *
 * @param int $product_id Product ID.
 * @return array{units_sold: int, order_count: int, revenue: float, revenue_label: string}
 */
function blackbean_shop_product_sales_stats( int $product_id ) : array {
	$stats = array(
		'units_sold'     => 0,
		'order_count'    => 0,
		'revenue'        => 0.0,
		'revenue_label'  => blackbean_shop_format_price( 0 ),
	);

	$order_rows = blackbean_orders_query(
		array(
			'payment_status' => 'paid',
			'limit'          => 500,
		)
	);

	foreach ( $order_rows as $order_row ) {
		$items_raw = (string) ( $order_row['items_json'] ?? '[]' );
		$items     = is_string( $items_raw ) ? json_decode( $items_raw, true ) : array();
		if ( ! is_array( $items ) ) {
			continue;
		}

		$matched = false;
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$pid = isset( $item['id'] ) ? (int) $item['id'] : (int) ( $item['product_id'] ?? 0 );
			if ( $pid !== $product_id ) {
				continue;
			}
			$qty = max( 1, (int) ( $item['qty'] ?? 1 ) );
			$stats['units_sold'] += $qty;
			$stats['revenue']    += (float) ( $item['price'] ?? 0 ) * $qty;
			$matched              = true;
		}
		if ( $matched ) {
			++$stats['order_count'];
		}
	}

	$stats['revenue_label'] = blackbean_shop_format_price( $stats['revenue'] );
	return $stats;
}
