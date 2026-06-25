<?php
/**
 * Plugin bootstrap.
 *
 * @package Blackbean_Shop
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BB_Shop_Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		add_action( 'after_setup_theme', array( $this, 'load' ), 1 );
	}

	public function load(): void {
		require_once BB_SHOP_PLUGIN_DIR . 'includes/compat.php';

		if ( bb_shop_legacy_theme_conflict() ) {
			add_action( 'admin_notices', 'bb_shop_legacy_theme_conflict_notice' );

			return;
		}

		if ( ! $this->tables_available() ) {
			add_action( 'admin_notices', array( $this, 'missing_tables_notice' ) );

			return;
		}

		$this->require_includes();

		blackbean_schema_install();

		BB_Shop_Frontend_Routing::register();
		BB_Shop_Admin_Assets::register();
		BB_Shop_Frontend_Assets::register();
	}

	public function activate(): void {
		require_once BB_SHOP_PLUGIN_DIR . 'includes/compat.php';

		if ( bb_shop_legacy_theme_conflict() ) {
			return;
		}

		if ( ! $this->tables_available() ) {
			deactivate_plugins( plugin_basename( BB_SHOP_PLUGIN_FILE ) );
			wp_die(
				esc_html__(
					'Black Bean Shop requires Black Bean Tables to be installed and active first.',
					'blackbean-shop'
				),
				esc_html__( 'Plugin activation failed', 'blackbean-shop' ),
				array( 'back_link' => true )
			);
		}

		$this->require_includes();

		blackbean_schema_install();
		blackbean_shop_activate();
		BB_Shop_Frontend_Routing::register_rewrites();
		flush_rewrite_rules();
	}

	private function require_includes(): void {
		static $loaded = false;
		if ( $loaded ) {
			return;
		}

		if ( bb_shop_legacy_theme_conflict() ) {
			return;
		}

		$loaded = true;
		define( 'BB_SHOP_LOADED', true );

		require_once BB_SHOP_PLUGIN_DIR . 'includes/schema.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-digital.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-license.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-paypal.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/data/products.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/data/orders.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/migrate-cpt.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/frontend-ui.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/admin-ui.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-settings.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-license-rest.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-license-admin.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-product-admin.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-admin.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-rest.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/storefront.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/rest-routes.php';
		bb_shop_register_rest_fallbacks();
	}

	private function tables_available(): bool {
		if ( ! defined( 'BB_CT_PLUGIN_DIR' ) ) {
			return false;
		}

		return class_exists( 'BB_CT_Plugin', false );
	}

	public function missing_tables_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				echo esc_html__(
					'Black Bean Shop requires the Black Bean Tables plugin to be active.',
					'blackbean-shop'
				);
				?>
			</p>
		</div>
		<?php
	}
}
