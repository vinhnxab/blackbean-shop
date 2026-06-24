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
		add_action( 'plugins_loaded', array( $this, 'load' ), 100 );
	}

	public function load(): void {
		if ( ! $this->tables_available() ) {
			add_action( 'admin_notices', array( $this, 'missing_tables_notice' ) );

			return;
		}

		$this->require_includes();

		blackbean_schema_install();

		BB_Shop_Frontend_Routing::register();
		BB_Shop_Admin_Assets::register();
	}

	public function activate(): void {
		if ( ! $this->tables_available() ) {
			return;
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
		$loaded = true;

		require_once BB_SHOP_PLUGIN_DIR . 'includes/schema.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/data/products.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/data/orders.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/migrate-cpt.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-settings.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-digital.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-license.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-license-rest.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-license-admin.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-paypal.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-product-admin.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-admin.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/shop-rest.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/storefront.php';
		require_once BB_SHOP_PLUGIN_DIR . 'includes/rest-routes.php';
	}

	private function tables_available(): bool {
		if ( ! defined( 'BB_CT_PLUGIN_DIR' ) ) {
			return false;
		}

		return class_exists( 'BB_CT_Table_Registry' );
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
