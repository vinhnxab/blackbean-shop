<?php
/**
 * License manager admin screen.
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register License Manager under Products menu.
 */
function blackbean_shop_register_license_manager_page() : void {
	add_submenu_page(
		'blackbean-shop-manager',
		__( 'License manager', 'blackbean' ),
		__( 'Licenses', 'blackbean' ),
		'edit_posts',
		'blackbean-license-manager',
		'blackbean_shop_render_license_manager_page'
	);
}
add_action( 'admin_menu', 'blackbean_shop_register_license_manager_page', 6 );

/**
 * Handle revoke / restore / remove activation POST actions.
 */
function blackbean_shop_license_admin_handle_actions() : void {
	if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
	if ( 'blackbean-license-manager' !== $page ) {
		return;
	}

	if ( empty( $_POST['bb_license_action'] ) ) {
		return;
	}

	check_admin_referer( 'blackbean_license_admin' );

	$action      = sanitize_key( (string) wp_unslash( $_POST['bb_license_action'] ) );
	$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
	$redirect    = wp_get_referer();
	if ( ! is_string( $redirect ) || '' === $redirect ) {
		$redirect = admin_url( 'admin.php?page=blackbean-license-manager' );
	}
	$redirect = remove_query_arg( array( 'bb_notice', 'bb_error' ), $redirect );

	$notice = '';
	$error  = '';

	if ( 'revoke' === $action && '' !== $license_key ) {
		$result = blackbean_shop_license_revoke( $license_key );
		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
		} else {
			$notice = 'revoked';
		}
	} elseif ( 'restore' === $action && '' !== $license_key ) {
		$result = blackbean_shop_license_restore( $license_key );
		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
		} else {
			$notice = 'restored';
		}
	} elseif ( 'remove_activation' === $action ) {
		$activation_id = isset( $_POST['activation_id'] ) ? (int) wp_unslash( $_POST['activation_id'] ) : 0;
		$result        = blackbean_shop_license_remove_activation( $activation_id );
		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
		} else {
			$notice = 'activation_removed';
		}
	}

	if ( '' !== $notice ) {
		$redirect = add_query_arg( 'bb_notice', $notice, $redirect );
	}
	if ( '' !== $error ) {
		$redirect = add_query_arg( 'bb_error', rawurlencode( $error ), $redirect );
	}

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_init', 'blackbean_shop_license_admin_handle_actions' );

/**
 * Admin notice messages for license manager.
 */
function blackbean_shop_license_admin_notices() : void {
	if ( ! isset( $_GET['page'] ) || 'blackbean-license-manager' !== sanitize_key( (string) $_GET['page'] ) ) {
		return;
	}

	if ( ! empty( $_GET['bb_error'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( rawurldecode( (string) wp_unslash( $_GET['bb_error'] ) ) ) . '</p></div>';
	}

	$notice = isset( $_GET['bb_notice'] ) ? sanitize_key( (string) $_GET['bb_notice'] ) : '';
	if ( '' === $notice ) {
		return;
	}

	$messages = array(
		'revoked'            => __( 'License revoked.', 'blackbean' ),
		'restored'           => __( 'License restored.', 'blackbean' ),
		'activation_removed' => __( 'Site activation removed.', 'blackbean' ),
	);

	if ( isset( $messages[ $notice ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'blackbean_shop_license_admin_notices' );

/**
 * Render License Manager page.
 */
function blackbean_shop_render_license_manager_page() : void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage licenses.', 'blackbean' ) );
	}

	$status_filter = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : 'all';
	$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
	$paged         = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

	$result = blackbean_shop_license_query(
		array(
			'status'   => $status_filter,
			'search'   => $search,
			'paged'    => $paged,
			'per_page' => 50,
		)
	);

	$counts   = blackbean_shop_license_status_counts();
	$ui       = blackbean_shop_admin_ui_classes();
	$base_url = admin_url( 'admin.php?page=blackbean-license-manager' );

	$tabs = array(
		'all'     => __( 'All', 'blackbean' ),
		'active'  => __( 'Active', 'blackbean' ),
		'revoked' => __( 'Revoked', 'blackbean' ),
	);
	?>
	<div class="<?php echo esc_attr( $ui['manager_wrap'] ); ?>">
		<header class="bb-admin-page-header">
			<div>
				<h1 class="bb-admin-page-title"><?php esc_html_e( 'License manager', 'blackbean' ); ?></h1>
				<p class="bb-admin-page-desc"><?php esc_html_e( 'View license keys, site activations, revoke or restore access.', 'blackbean' ); ?></p>
			</div>
			<div class="<?php echo esc_attr( $ui['toolbar'] ); ?>">
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=blackbean-shop-manager' ) ); ?>"><?php esc_html_e( 'Products', 'blackbean' ); ?></a>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=blackbean-order-manager' ) ); ?>"><?php esc_html_e( 'Orders', 'blackbean' ); ?></a>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( blackbean_shop_settings_admin_url() ); ?>"><?php esc_html_e( 'Settings', 'blackbean' ); ?></a>
			</div>
		</header>

		<form class="bb-shop-license-search" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="blackbean-license-manager" />
			<?php if ( 'all' !== $status_filter ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>" />
			<?php endif; ?>
			<label class="screen-reader-text" for="bb-license-search"><?php esc_html_e( 'Search licenses', 'blackbean' ); ?></label>
			<input
				type="search"
				id="bb-license-search"
				name="s"
				class="<?php echo esc_attr( $ui['input'] ); ?>"
				value="<?php echo esc_attr( $search ); ?>"
				placeholder="<?php esc_attr_e( 'License key, email, order or product ID…', 'blackbean' ); ?>"
			/>
			<button type="submit" class="<?php echo esc_attr( $ui['btn_sec'] ); ?>"><?php esc_html_e( 'Search', 'blackbean' ); ?></button>
			<?php if ( '' !== $search ) : ?>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( remove_query_arg( array( 's', 'paged' ), $base_url ) ); ?>"><?php esc_html_e( 'Clear', 'blackbean' ); ?></a>
			<?php endif; ?>
		</form>

		<nav class="bb-shop-tabs" aria-label="<?php esc_attr_e( 'Filter licenses', 'blackbean' ); ?>">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<?php
				$count = (int) ( $counts[ $key ] ?? 0 );
				$url   = add_query_arg(
					array_filter(
						array(
							'page'   => 'blackbean-license-manager',
							'status' => 'all' === $key ? false : $key,
							's'      => $search ?: false,
						)
					),
					admin_url( 'admin.php' )
				);
				if ( 'all' === $key ) {
					$url = remove_query_arg( 'status', $url );
				}
				?>
				<a class="bb-shop-tab<?php echo $status_filter === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
					<?php echo esc_html( $label ); ?>
					<span class="bb-shop-tab-count"><?php echo esc_html( (string) $count ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="<?php echo esc_attr( $ui['table_wrap'] ); ?>">
			<?php if ( empty( $result['items'] ) ) : ?>
				<p class="bb-shop-empty"><?php esc_html_e( 'No licenses found. Keys are created when paid orders are fulfilled.', 'blackbean' ); ?></p>
			<?php else : ?>
				<p class="bb-shop-table-meta">
					<?php
					printf(
						/* translators: 1: from, 2: to, 3: total */
						esc_html__( 'Showing %1$d–%2$d of %3$d licenses', 'blackbean' ),
						( ( $result['page'] - 1 ) * 50 ) + 1,
						min( $result['total'], $result['page'] * 50 ),
						$result['total']
					);
					?>
				</p>
				<table class="<?php echo esc_attr( $ui['table'] ); ?>">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'License key', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Product', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Order', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Customer', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Activations', 'blackbean' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'blackbean' ); ?></th>
							<th scope="col" class="bb-shop-col-actions"><?php esc_html_e( 'Actions', 'blackbean' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $result['items'] as $lic ) : ?>
							<?php
							$license_key = (string) ( $lic['license_key'] ?? '' );
							$status      = (string) ( $lic['status'] ?? 'active' );
							$used        = (int) ( $lic['activation_count'] ?? 0 );
							$max         = (int) ( $lic['max_activations'] ?? 1 );
							$order_id    = (int) ( $lic['order_id'] ?? 0 );
							$product_id  = (int) ( $lic['product_id'] ?? 0 );
							$badge       = 'revoked' === $status ? $ui['badge_out'] : $ui['badge_in'];
							?>
							<tr>
								<td><code class="bb-license-key-cell"><?php echo esc_html( $license_key ); ?></code></td>
								<td>
									<?php if ( $product_id > 0 ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $product_id, 'raw' ) ?: '#' ); ?>"><?php echo esc_html( (string) ( $lic['product_title'] ?: '#' . $product_id ) ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $order_id > 0 ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $order_id, 'raw' ) ?: admin_url( 'admin.php?page=blackbean-order-manager' ) ); ?>">#<?php echo esc_html( (string) $order_id ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! empty( $lic['customer_email'] ) ) : ?>
										<a href="mailto:<?php echo esc_attr( (string) $lic['customer_email'] ); ?>"><?php echo esc_html( (string) $lic['customer_email'] ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html( sprintf( '%1$d / %2$d', $used, $max ) ); ?>
									<?php if ( ! empty( $lic['activations'] ) ) : ?>
										<ul class="bb-license-sites-list">
											<?php foreach ( $lic['activations'] as $act ) : ?>
												<li>
													<code><?php echo esc_html( (string) ( $act['site_url'] ?? '' ) ); ?></code>
													<form method="post" class="bb-license-inline-form" style="display:inline;">
														<?php wp_nonce_field( 'blackbean_license_admin' ); ?>
														<input type="hidden" name="bb_license_action" value="remove_activation" />
														<input type="hidden" name="activation_id" value="<?php echo esc_attr( (string) (int) ( $act['id'] ?? 0 ) ); ?>" />
														<button type="submit" class="button-link delete-link" onclick="return confirm('<?php echo esc_js( __( 'Remove activation for this site?', 'blackbean' ) ); ?>');">
															<?php esc_html_e( 'Remove', 'blackbean' ); ?>
														</button>
													</form>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</td>
								<td><span class="<?php echo esc_attr( $badge ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
								<td class="bb-shop-col-actions">
									<?php if ( 'active' === $status ) : ?>
										<form method="post" class="bb-license-inline-form">
											<?php wp_nonce_field( 'blackbean_license_admin' ); ?>
											<input type="hidden" name="bb_license_action" value="revoke" />
											<input type="hidden" name="license_key" value="<?php echo esc_attr( $license_key ); ?>" />
											<button type="submit" class="button-link" onclick="return confirm('<?php echo esc_js( __( 'Revoke this license? Active sites will fail license checks.', 'blackbean' ) ); ?>');">
												<?php esc_html_e( 'Revoke', 'blackbean' ); ?>
											</button>
										</form>
									<?php else : ?>
										<form method="post" class="bb-license-inline-form">
											<?php wp_nonce_field( 'blackbean_license_admin' ); ?>
											<input type="hidden" name="bb_license_action" value="restore" />
											<input type="hidden" name="license_key" value="<?php echo esc_attr( $license_key ); ?>" />
											<button type="submit" class="button-link"><?php esc_html_e( 'Restore', 'blackbean' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $result['pages'] > 1 ) : ?>
					<?php
					$pagination_base = add_query_arg(
						array_filter(
							array(
								'page'   => 'blackbean-license-manager',
								'status' => 'all' !== $status_filter ? $status_filter : false,
								's'      => $search ?: false,
								'paged'  => '%#%',
							)
						),
						admin_url( 'admin.php' )
					);
					?>
					<nav class="bb-shop-pagination" aria-label="<?php esc_attr_e( 'License pages', 'blackbean' ); ?>">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => $pagination_base,
									'format'    => '',
									'current'   => $result['page'],
									'total'     => $result['pages'],
									'prev_text' => __( '&laquo; Previous', 'blackbean' ),
									'next_text' => __( 'Next &raquo;', 'blackbean' ),
								)
							)
						);
						?>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<p class="description" style="margin-top:1rem;">
			<?php esc_html_e( 'API endpoints for customer plugins: POST /wp-json/blackbean/v1/shop/license/activate, deactivate, check. See license-client/ in the Black Bean Shop plugin.', 'blackbean-shop' ); ?>
		</p>
	</div>
	<?php
}
