<?php
/**
 * Verify required shop plugin files exist (prevents partial deploy fatals).
 *
 * @package Blackbean_Shop
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BB_Shop_Health_Check {

	/** @var list<string> */
	private const REQUIRED_FILES = array(
		'includes/class-frontend-assets.php',
		'includes/class-admin-assets.php',
		'includes/class-autoloader.php',
		'assets/css/shop-front.css',
		'assets/css/shop-admin.css',
		'assets/js/shop.js',
	);

	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
	}

	public static function missing_files(): array {
		$missing = array();
		foreach ( self::REQUIRED_FILES as $relative ) {
			$path = BB_SHOP_PLUGIN_DIR . $relative;
			if ( ! is_readable( $path ) ) {
				$missing[] = $relative;
			}
		}
		return $missing;
	}

	public static function admin_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$missing = self::missing_files();
		if ( array() === $missing ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>Black Bean Shop:</strong> ';
		echo esc_html(
			sprintf(
				/* translators: %s: comma-separated file paths */
				__( 'Incomplete install — missing files: %s. Re-deploy the full plugin (extract blackbean-shop.zip into wp-content/plugins/blackbean-shop/).', 'blackbean-shop' ),
				implode( ', ', $missing )
			)
		);
		echo '</p></div>';
	}
}
