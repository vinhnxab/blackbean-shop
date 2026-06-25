<?php
/**
 * Public-facing styles for shop templates and header cart.
 *
 * @package Blackbean_Shop
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BB_Shop_Frontend_Assets {

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 15 );
	}

	public static function enqueue(): void {
		if ( is_admin() ) {
			return;
		}

		$css = BB_SHOP_PLUGIN_DIR . 'assets/css/shop-front.css';
		if ( ! is_readable( $css ) ) {
			return;
		}

		$version = filemtime( $css );
		if ( false === $version ) {
			$version = BB_SHOP_VERSION;
		}

		wp_enqueue_style(
			'blackbean-shop-front',
			BB_SHOP_PLUGIN_URL . 'assets/css/shop-front.css',
			array(),
			(string) $version
		);
	}
}
