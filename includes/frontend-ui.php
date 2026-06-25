<?php
/**
 * Frontend layout helpers for Black Bean Shop (no theme dependency).
 *
 * @package Blackbean_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared max-width container for shop templates.
 *
 * @param string $extra Optional extra classes.
 */
function blackbean_shop_layout_classes( string $extra = '' ): string {
	$classes = 'bb-shop-container mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8';

	if ( '' !== trim( $extra ) ) {
		$classes .= ' ' . trim( $extra );
	}

	return $classes;
}

/**
 * @param string $variant primary|secondary.
 */
function blackbean_shop_button_class( string $variant = 'primary' ): string {
	return 'secondary' === $variant
		? 'bb-shop-btn bb-shop-btn-secondary'
		: 'bb-shop-btn bb-shop-btn-primary';
}

/**
 * Text input / select / textarea.
 */
function blackbean_shop_input_class(): string {
	return 'bb-shop-input';
}

/**
 * Form field label.
 */
function blackbean_shop_label_class(): string {
	return 'bb-shop-label';
}
