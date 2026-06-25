<?php
/**
 * Autoloader for Black Bean Shop.
 *
 * @package Blackbean_Shop
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BB_Shop_Autoloader {

	/** @var array<string, string> */
	private static array $map = array(
		'BB_Shop_Plugin'            => 'includes/class-plugin.php',
		'BB_Shop_Frontend_Routing'  => 'includes/class-frontend-routing.php',
		'BB_Shop_Template_Loader'   => 'includes/class-template-loader.php',
		'BB_Shop_Admin_Assets'      => 'includes/class-admin-assets.php',
		'BB_Shop_Frontend_Assets'   => 'includes/class-frontend-assets.php',
		'BB_Shop_Health_Check'      => 'includes/class-health-check.php',
	);

	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * @param class-string $class Class name.
	 */
	public static function load( string $class ): void {
		if ( ! isset( self::$map[ $class ] ) ) {
			return;
		}

		$file = BB_SHOP_PLUGIN_DIR . self::$map[ $class ];
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}

BB_Shop_Autoloader::register();
