<?php
/**
 * Admin styles for shop screens.
 *
 * @package Blackbean_Shop
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BB_Shop_Admin_Assets {

	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'register_styles' ), 5 );
	}

	public static function register_styles(): void {
		if ( ! is_admin() ) {
			return;
		}

		$css = BB_SHOP_PLUGIN_DIR . 'assets/css/shop-admin.css';
		if ( ! is_readable( $css ) ) {
			return;
		}

		$version = filemtime( $css );
		if ( false === $version ) {
			$version = BB_SHOP_VERSION;
		}

		wp_register_style(
			'blackbean-shop-admin',
			BB_SHOP_PLUGIN_URL . 'assets/css/shop-admin.css',
			array(),
			(string) $version
		);
	}
}
