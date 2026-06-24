<?php
/**
 * Storefront helpers — sell the theme from the homepage.
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return int Featured theme product ID (0 = none).
 */
function blackbean_storefront_product_id() : int {
	if ( ! function_exists( 'blackbean_shop_get_settings' ) ) {
		return 0;
	}
	$settings = blackbean_shop_get_settings();
	$id       = max( 0, (int) ( $settings['theme_product_id'] ?? 0 ) );
	return (int) apply_filters( 'blackbean_storefront_product_id', $id );
}

/**
 * Whether the sales landing should replace the blog home.
 */
function blackbean_storefront_enabled() : bool {
	if ( ! function_exists( 'blackbean_shop_get_settings' ) ) {
		return false;
	}
	$settings = blackbean_shop_get_settings();
	if ( empty( $settings['storefront_enabled'] ) ) {
		return false;
	}
	return blackbean_storefront_product_id() > 0 && function_exists( 'blackbean_shop_get_product' );
}

/**
 * @return array<string,mixed>|null
 */
function blackbean_storefront_product() : ?array {
	$id = blackbean_storefront_product_id();
	if ( $id <= 0 || ! function_exists( 'blackbean_shop_get_product' ) ) {
		return null;
	}
	return blackbean_shop_get_product( $id );
}

/**
 * Render storefront on front page when enabled.
 */
function blackbean_storefront_should_render() : bool {
	if ( ! is_front_page() || is_paged() ) {
		return false;
	}
	if ( 'page' === get_option( 'show_on_front' ) ) {
		return false;
	}
	return blackbean_storefront_enabled();
}
