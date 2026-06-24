<?php
/**
 * Front-end template loader (theme override → plugin default).
 *
 * @package Blackbean_Shop
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BB_Shop_Template_Loader {

	public static function locate( string $relative ): string {
		$relative = ltrim( $relative, '/' );

		$theme = locate_template( 'blackbean-shop/' . $relative );
		if ( $theme ) {
			return $theme;
		}

		$legacy = locate_template( $relative );
		if ( $legacy ) {
			return $legacy;
		}

		$plugin = BB_SHOP_PLUGIN_DIR . 'templates/' . $relative;

		return is_readable( $plugin ) ? $plugin : '';
	}

	/**
	 * @param array<string, mixed> $args Template args.
	 */
	public static function get_part( string $slug, array $args = array() ): void {
		$theme = locate_template( 'blackbean-shop/template-parts/' . $slug . '.php' );
		if ( $theme ) {
			load_template( $theme, false, $args );
			return;
		}

		$legacy = locate_template( 'template-parts/' . $slug . '.php' );
		if ( $legacy ) {
			load_template( $legacy, false, $args );
			return;
		}

		$plugin = BB_SHOP_PLUGIN_DIR . 'templates/template-parts/' . $slug . '.php';
		if ( is_readable( $plugin ) ) {
			load_template( $plugin, false, $args );
		}
	}
}
