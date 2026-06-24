<?php
/**
 * Digital product delivery: licenses, download links, fulfillment emails.
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLACKBEAN_SHOP_META_DOWNLOAD_URL     = '_bb_download_url';
const BLACKBEAN_SHOP_META_DOWNLOAD_FILE    = '_bb_download_file_id';
const BLACKBEAN_SHOP_META_LICENSE_PREFIX   = '_bb_license_prefix';
const BLACKBEAN_SHOP_META_IS_DIGITAL       = '_bb_is_digital';
const BLACKBEAN_SHOP_META_FULFILLMENT      = '_bb_fulfillment';
const BLACKBEAN_SHOP_META_PAYMENT_STATUS   = '_bb_payment_status';

/**
 * Public download URL for a product (admin-configured).
 *
 * @param int $product_id Product ID.
 */
function blackbean_shop_product_download_source( int $product_id ) : string {
	$url = trim( (string) blackbean_product_get_meta( $product_id, BLACKBEAN_SHOP_META_DOWNLOAD_URL ) );
	if ( '' !== $url ) {
		return esc_url_raw( $url );
	}
	$file_id = (int) blackbean_product_get_meta( $product_id, BLACKBEAN_SHOP_META_DOWNLOAD_FILE );
	if ( $file_id > 0 ) {
		$file_url = wp_get_attachment_url( $file_id );
		if ( is_string( $file_url ) && '' !== $file_url ) {
			return $file_url;
		}
	}
	return '';
}

/**
 * Whether product is digital (default true when meta unset).
 *
 * @param int $product_id Product ID.
 */
function blackbean_shop_product_is_digital( int $product_id ) : bool {
	$flag = blackbean_product_get_meta( $product_id, BLACKBEAN_SHOP_META_IS_DIGITAL );
	if ( '' === $flag || false === $flag ) {
		return true;
	}
	return (bool) $flag;
}

/**
 * @param int $product_id Product ID.
 */
function blackbean_shop_product_license_prefix( int $product_id ) : string {
	$prefix = trim( (string) blackbean_product_get_meta( $product_id, BLACKBEAN_SHOP_META_LICENSE_PREFIX ) );
	if ( '' !== $prefix ) {
		return strtoupper( preg_replace( '/[^A-Z0-9]/', '', $prefix ) );
	}
	$sku = trim( (string) blackbean_product_get_meta( $product_id, '_bb_sku' ) );
	if ( '' !== $sku ) {
		return strtoupper( preg_replace( '/[^A-Z0-9]/', '', $sku ) );
	}
	return 'BB';
}

/**
 * Generate a license key for an order line.
 *
 * @param int $order_id   Order ID.
 * @param int $product_id Product ID.
 */
function blackbean_shop_generate_license_key( int $order_id, int $product_id ) : string {
	$prefix = blackbean_shop_product_license_prefix( $product_id );
	$rand   = strtoupper( bin2hex( random_bytes( 4 ) ) );
	return sprintf( '%s-%d-%d-%s', $prefix, $order_id, $product_id, $rand );
}

/**
 * Secure download URL for a fulfilled line item.
 *
 * @param int    $order_id   Order ID.
 * @param int    $product_id Product ID.
 * @param string $token      Plain token.
 */
function blackbean_shop_build_download_link( int $order_id, int $product_id, string $token ) : string {
	return add_query_arg(
		array(
			'order_id'   => $order_id,
			'product_id' => $product_id,
			'token'      => $token,
		),
		rest_url( 'blackbean/v1/shop/download' )
	);
}

/**
 * Build fulfillment rows for each order line (licenses + download tokens).
 *
 * @param int $order_id Order ID.
 * @return list<array<string, mixed>>
 */
function blackbean_shop_build_fulfillment( int $order_id ) : array {
	$order = blackbean_shop_get_order( $order_id );
	if ( ! $order || empty( $order['items'] ) ) {
		return array();
	}

	$rows = array();
	foreach ( $order['items'] as $item ) {
		$product_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
		if ( $product_id <= 0 || ! blackbean_shop_product_is_digital( $product_id ) ) {
			continue;
		}

		$qty = max( 1, (int) ( $item['qty'] ?? 1 ) );
		for ( $i = 0; $i < $qty; $i++ ) {
			$token   = bin2hex( random_bytes( 16 ) );
			$license = blackbean_shop_generate_license_key( $order_id, $product_id );
			$rows[]  = array(
				'product_id'   => $product_id,
				'title'        => (string) ( $item['title'] ?? '' ),
				'license'      => $license,
				'token'        => wp_hash( $token, 'blackbean_shop_download' ),
				'token_plain'  => $token,
				'download_url' => blackbean_shop_build_download_link( $order_id, $product_id, $token ),
			);
		}
	}

	return $rows;
}

/**
 * Fulfill a paid order: stock, licenses, emails (idempotent).
 *
 * @param int $order_id Order ID.
 * @return true|WP_Error
 */
function blackbean_shop_fulfill_order( int $order_id ) {
	if ( blackbean_order_get_meta( $order_id, '_bb_fulfilled' ) ) {
		blackbean_shop_cart_clear_for_order( $order_id );
		return true;
	}

	$order = blackbean_shop_get_order( $order_id );
	if ( ! $order ) {
		return new WP_Error( 'blackbean_shop_invalid_order', __( 'Order not found.', 'blackbean' ) );
	}

	$fulfillment = blackbean_shop_build_fulfillment( $order_id );
	blackbean_order_update_meta( $order_id, BLACKBEAN_SHOP_META_FULFILLMENT, wp_json_encode( $fulfillment ) );
	if ( function_exists( 'blackbean_shop_license_register_fulfillment' ) ) {
		blackbean_shop_license_register_fulfillment( $order_id, $fulfillment );
	}
	blackbean_order_update_meta( $order_id, BLACKBEAN_SHOP_META_PAYMENT_STATUS, 'paid' );
	blackbean_order_update_meta( $order_id, '_bb_order_status', 'completed' );

	foreach ( $order['items'] as $item ) {
		$pid   = (int) ( $item['id'] ?? 0 );
		$stock = (int) blackbean_product_get_meta( $pid, '_bb_stock' );
		if ( $pid > 0 && $stock >= 0 ) {
			blackbean_product_update_meta( $pid, '_bb_stock', max( 0, $stock - (int) ( $item['qty'] ?? 1 ) ) );
		}
	}

	blackbean_shop_send_fulfillment_email( $order_id, $fulfillment );
	blackbean_shop_send_admin_paid_order_email( $order_id, $fulfillment );

	blackbean_order_update_meta( $order_id, '_bb_fulfilled', '1' );
	blackbean_shop_cart_clear_for_order( $order_id );

	return true;
}

/**
 * @param int                             $order_id    Order ID.
 * @param list<array<string, mixed>>|null $fulfillment Fulfillment rows.
 */
function blackbean_shop_send_fulfillment_email( int $order_id, ?array $fulfillment = null ) : void {
	$order = blackbean_shop_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	if ( null === $fulfillment ) {
		$raw = blackbean_order_get_meta( $order_id, BLACKBEAN_SHOP_META_FULFILLMENT );
		$fulfillment = is_string( $raw ) ? json_decode( $raw, true ) : array();
		if ( ! is_array( $fulfillment ) ) {
			$fulfillment = array();
		}
	}

	$c = $order['customer'];
	$lines = array();
	$lines[] = sprintf(
		__( 'Thank you, %1$s.', 'blackbean' ),
		$c['name']
	);
	$lines[] = sprintf(
		__( 'Your order #%1$d is paid. Total: %2$s', 'blackbean' ),
		$order_id,
		$order['total_label']
	);
	$lines[] = '';
	$lines[] = __( 'Your digital products:', 'blackbean' );
	$lines[] = '';

	if ( empty( $fulfillment ) ) {
		$lines[] = __( '(No digital delivery configured for these products.)', 'blackbean' );
	} else {
		foreach ( $fulfillment as $row ) {
			$lines[] = '---';
			$lines[] = (string) ( $row['title'] ?? '' );
			$lines[] = __( 'License key:', 'blackbean' ) . ' ' . (string) ( $row['license'] ?? '' );
			$lines[] = __( 'Enter this key in your plugin/theme license settings on your WordPress site to activate.', 'blackbean' );
			if ( ! empty( $row['download_url'] ) ) {
				$lines[] = __( 'Download:', 'blackbean' ) . ' ' . (string) $row['download_url'];
			}
			$lines[] = '';
		}
	}

	$lines[] = __( 'Keep this email for your records.', 'blackbean' );

	wp_mail(
		$c['email'],
		sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Your download & license keys', 'blackbean' ) ),
		implode( "\n", $lines )
	);
}

/**
 * @param int                             $order_id    Order ID.
 * @param list<array<string, mixed>>|null $fulfillment Fulfillment rows.
 */
function blackbean_shop_send_admin_paid_order_email( int $order_id, ?array $fulfillment = null ) : void {
	$order = blackbean_shop_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$c = $order['customer'];
	$body = sprintf(
		"Order #%d paid\nCustomer: %s <%s>\nTotal: %s\n",
		$order_id,
		$c['name'],
		$c['email'],
		$order['total_label']
	);

	wp_mail(
		get_option( 'admin_email' ),
		sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Shop order paid', 'blackbean' ) ),
		$body
	);
}

/**
 * Verify download token and stream file / redirect.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function blackbean_shop_download_rest( WP_REST_Request $request ) {
	$order_id   = (int) $request->get_param( 'order_id' );
	$product_id = (int) $request->get_param( 'product_id' );
	$token      = sanitize_text_field( (string) $request->get_param( 'token' ) );

	if ( $order_id <= 0 || $product_id <= 0 || '' === $token ) {
		return new WP_Error( 'blackbean_shop_download_invalid', __( 'Invalid download link.', 'blackbean' ), array( 'status' => 400 ) );
	}

	if ( 'paid' !== blackbean_order_get_meta( $order_id, BLACKBEAN_SHOP_META_PAYMENT_STATUS ) ) {
		return new WP_Error( 'blackbean_shop_download_unpaid', __( 'This order is not paid.', 'blackbean' ), array( 'status' => 403 ) );
	}

	$raw = blackbean_order_get_meta( $order_id, BLACKBEAN_SHOP_META_FULFILLMENT );
	$rows = is_string( $raw ) ? json_decode( $raw, true ) : array();
	if ( ! is_array( $rows ) ) {
		return new WP_Error( 'blackbean_shop_download_missing', __( 'Download not found.', 'blackbean' ), array( 'status' => 404 ) );
	}

	$hash = wp_hash( $token, 'blackbean_shop_download' );
	$ok   = false;
	foreach ( $rows as $row ) {
		if ( (int) ( $row['product_id'] ?? 0 ) === $product_id && hash_equals( (string) ( $row['token'] ?? '' ), $hash ) ) {
			$ok = true;
			break;
		}
	}

	if ( ! $ok ) {
		return new WP_Error( 'blackbean_shop_download_denied', __( 'Invalid or expired download link.', 'blackbean' ), array( 'status' => 403 ) );
	}

	$source = blackbean_shop_product_download_source( $product_id );
	if ( '' === $source ) {
		return new WP_Error( 'blackbean_shop_download_unavailable', __( 'No download file configured for this product.', 'blackbean' ), array( 'status' => 404 ) );
	}

	$file_id = (int) blackbean_product_get_meta( $product_id, BLACKBEAN_SHOP_META_DOWNLOAD_FILE );
	if ( $file_id > 0 ) {
		$path = get_attached_file( $file_id );
		if ( is_string( $path ) && is_readable( $path ) ) {
			$mime = get_post_mime_type( $file_id ) ?: 'application/octet-stream';
			$name = basename( $path );
			header( 'Content-Type: ' . $mime );
			header( 'Content-Disposition: attachment; filename="' . $name . '"' );
			header( 'Content-Length: ' . (string) filesize( $path ) );
			readfile( $path );
			exit;
		}
	}

	wp_safe_redirect( $source );
	exit;
}

/**
 * Register download route.
 */
function blackbean_shop_register_download_route() : void {
	register_rest_route(
		'blackbean/v1',
		'/shop/download',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => 'blackbean_rest_permission_public_write',
			'callback'            => 'blackbean_shop_download_rest',
			'args'                => array(
				'order_id'   => array( 'required' => true, 'type' => 'integer' ),
				'product_id' => array( 'required' => true, 'type' => 'integer' ),
				'token'      => array( 'required' => true, 'type' => 'string' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'blackbean_shop_register_download_route' );

/**
 * Fulfillment rows for display (strips token hashes).
 *
 * @param int $order_id Order ID.
 * @return list<array<string, mixed>>
 */
function blackbean_shop_get_fulfillment_display( int $order_id ) : array {
	$raw = blackbean_order_get_meta( $order_id, BLACKBEAN_SHOP_META_FULFILLMENT );
	$rows = is_string( $raw ) ? json_decode( $raw, true ) : array();
	if ( ! is_array( $rows ) ) {
		return array();
	}
	$out = array();
	foreach ( $rows as $row ) {
		$out[] = array(
			'product_id'   => (int) ( $row['product_id'] ?? 0 ),
			'title'        => (string) ( $row['title'] ?? '' ),
			'license'      => (string) ( $row['license'] ?? '' ),
			'download_url' => (string) ( $row['download_url'] ?? '' ),
		);
	}
	return $out;
}
