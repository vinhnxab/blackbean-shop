<?php
/**
 * Register shop REST routes with shared theme security helpers.
 *
 * @package Blackbean_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'blackbean_rest_public_read_routes',
	static function ( array $routes ): array {
		$routes[] = '/shop/products';
		return $routes;
	}
);

add_filter(
	'blackbean_rest_public_write_routes',
	static function ( array $routes ): array {
		return array_merge(
			$routes,
			array(
				'/shop/cart',
				'/shop/license/activate',
				'/shop/license/deactivate',
				'/shop/license/check',
				'/shop/download',
				'/shop/paypal/webhook',
			)
		);
	}
);
