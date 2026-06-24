<?php
/**
 * Plugin Name: Black Bean Shop
 * Plugin URI: https://github.com/vinhnxab/blackbean-shop
 * Description: Products, cart, checkout, orders, PayPal, and license sales. Requires Black Bean Tables.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Requires Plugins: blackbean-tables
 * Author: Blackbean
 * License: GPL-2.0-or-later
 * Text Domain: blackbean-shop
 *
 * @package Blackbean_Shop
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BB_SHOP_VERSION', '0.1.0' );
define( 'BB_SHOP_PLUGIN_FILE', __FILE__ );
define( 'BB_SHOP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BB_SHOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once BB_SHOP_PLUGIN_DIR . 'includes/class-autoloader.php';
require_once BB_SHOP_PLUGIN_DIR . 'includes/class-plugin.php';

BB_Shop_Plugin::instance()->boot();

register_activation_hook(
	__FILE__,
	static function (): void {
		BB_Shop_Plugin::instance()->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);
