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

	/** @var list<string> */
	private const ADMIN_HOOKS = array(
		'toplevel_page_blackbean-shop-manager',
		'shop_page_blackbean-order-manager',
		'shop_page_blackbean-shop-product',
		'shop_page_blackbean-license-manager',
		'shop_page_blackbean-shop',
	);

	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'register_styles' ), 5 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_styles' ), 20 );
		add_filter( 'admin_body_class', array( self::class, 'admin_body_class' ) );
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
			'blackbean-admin-shell',
			BB_SHOP_PLUGIN_URL . 'assets/css/shop-admin.css',
			array(),
			(string) $version
		);
	}

	public static function enqueue_styles( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, self::ADMIN_HOOKS, true ) ) {
			return;
		}

		if ( wp_style_is( 'blackbean-admin-shell', 'registered' ) ) {
			wp_enqueue_style( 'blackbean-admin-shell' );
		}
	}

	public static function admin_body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen instanceof WP_Screen ) {
			return $classes;
		}

		$id = (string) $screen->id;
		if ( 'shop_page_blackbean-shop' === $id ) {
			$classes .= ' blackbean-admin-settings-screen';
		}
		if ( str_contains( $id, 'blackbean-shop-manager' ) || str_contains( $id, 'blackbean-shop-product' ) ) {
			$classes .= ' blackbean-shop-product-screen';
		}
		if ( str_contains( $id, 'blackbean-order-manager' ) ) {
			$classes .= ' blackbean-shop-order-screen';
		}

		return $classes;
	}
}
