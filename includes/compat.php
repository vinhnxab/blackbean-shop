<?php
/**
 * Legacy theme conflict detection and REST fallbacks.
 *
 * @package Blackbean_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether shop API helpers already exist (legacy theme bundle).
 */
function bb_shop_legacy_theme_conflict(): bool {
	if ( defined( 'BB_SHOP_LOADED' ) ) {
		return false;
	}

	return function_exists( 'blackbean_shop_currency_code' );
}

/**
 * Admin notice when the theme still ships shop PHP files.
 */
function bb_shop_legacy_theme_conflict_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo esc_html__(
				'Black Bean Shop could not load because the active theme still includes legacy shop files (for example inc/schema.php or inc/shop.php). Deploy Blackbean theme v1.19.1+ and remove old inc/shop*.php and inc/schema.php from the server, or ensure functions.php no longer requires them.',
				'blackbean-shop'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Minimal REST permission helpers when the theme has not loaded yet.
 */
function bb_shop_register_rest_fallbacks(): void {
	if ( function_exists( 'blackbean_rest_permission_public_read' ) ) {
		return;
	}

	/**
	 * @return bool
	 */
	function blackbean_rest_permission_public_read(): bool {
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	function blackbean_rest_permission_public_write( WP_REST_Request $request ) {
		unset( $request );
		return true;
	}

	/**
	 * @return bool|WP_Error
	 */
	function blackbean_rest_permission_manage_shop() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'blackbean_forbidden',
				__( 'You do not have permission to access this resource.', 'blackbean-shop' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	function blackbean_rest_permission_paypal_webhook( WP_REST_Request $request ) {
		return blackbean_rest_permission_public_write( $request );
	}
}
