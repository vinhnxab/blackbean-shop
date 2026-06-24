<?php
/**
 * PayPal Checkout (Orders API v2) integration.
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLACKBEAN_SHOP_META_PAYPAL_ORDER = '_bb_paypal_order_id';

/**
 * Allow wp_safe_redirect to PayPal (otherwise WordPress falls back to wp-admin).
 *
 * @param list<string> $hosts Allowed hosts.
 * @return list<string>
 */
function blackbean_shop_paypal_allowed_redirect_hosts( array $hosts ) : array {
	$hosts[] = 'paypal.com';
	$hosts[] = 'www.paypal.com';
	$hosts[] = 'sandbox.paypal.com';
	$hosts[] = 'www.sandbox.paypal.com';
	return $hosts;
}
add_filter( 'allowed_redirect_hosts', 'blackbean_shop_paypal_allowed_redirect_hosts' );

/**
 * Redirect to PayPal approval URL (external; not allowed by wp_safe_redirect alone).
 *
 * @param string $location PayPal URL from API.
 * @return never
 */
function blackbean_shop_redirect_to_paypal( string $location ) : void {
	$host = wp_parse_url( $location, PHP_URL_HOST );
	if ( ! is_string( $host ) || '' === $host ) {
		wp_safe_redirect(
			add_query_arg( 'shop_error', rawurlencode( __( 'Invalid PayPal redirect.', 'blackbean' ) ), blackbean_shop_checkout_url() )
		);
		exit;
	}

	$host    = strtolower( $host );
	$allowed = array( 'paypal.com', 'www.paypal.com', 'sandbox.paypal.com', 'www.sandbox.paypal.com' );
	$ok      = false;
	foreach ( $allowed as $suffix ) {
		if ( $host === $suffix || substr( $host, -strlen( $suffix ) - 1 ) === '.' . $suffix ) {
			$ok = true;
			break;
		}
	}

	if ( ! $ok ) {
		wp_safe_redirect(
			add_query_arg( 'shop_error', rawurlencode( __( 'Invalid PayPal redirect.', 'blackbean' ) ), blackbean_shop_checkout_url() )
		);
		exit;
	}

	wp_redirect( $location, 302, 'Blackbean Shop' );
	exit;
}

/**
 * OAuth2 access token (cached).
 *
 * @return string|WP_Error
 */
function blackbean_shop_paypal_access_token() {
	$cache_key = 'blackbean_paypal_token_' . ( blackbean_shop_paypal_is_sandbox() ? 'sandbox' : 'live' );
	$cached    = get_transient( $cache_key );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}

	$client_id = blackbean_shop_paypal_client_id();
	$secret    = blackbean_shop_paypal_secret();
	if ( '' === $client_id || '' === $secret ) {
		return new WP_Error( 'blackbean_paypal_config', __( 'PayPal is not configured.', 'blackbean' ) );
	}

	$response = wp_remote_post(
		blackbean_shop_paypal_api_base() . '/v1/oauth2/token',
		array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => 'grant_type=client_credentials',
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 || empty( $data['access_token'] ) ) {
		$message = isset( $data['error_description'] ) ? (string) $data['error_description'] : __( 'Could not authenticate with PayPal.', 'blackbean' );
		return new WP_Error( 'blackbean_paypal_auth', $message );
	}

	$token = (string) $data['access_token'];
	$ttl   = isset( $data['expires_in'] ) ? max( 60, (int) $data['expires_in'] - 60 ) : 3000;
	set_transient( $cache_key, $token, $ttl );

	return $token;
}

/**
 * PayPal REST request.
 *
 * @param string               $method HTTP method.
 * @param string               $path   Path e.g. /v2/checkout/orders.
 * @param array<string, mixed> $body   JSON body.
 * @return array<string, mixed>|WP_Error
 */
function blackbean_shop_paypal_request( string $method, string $path, array $body = array() ) {
	$token = blackbean_shop_paypal_access_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$args = array(
		'method'  => strtoupper( $method ),
		'timeout' => 45,
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		),
	);

	if ( ! empty( $body ) ) {
		$args['body'] = wp_json_encode( $body );
	}

	$response = wp_remote_request( blackbean_shop_paypal_api_base() . $path, $args );
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'blackbean_paypal_bad_response', __( 'Invalid PayPal response.', 'blackbean' ) );
	}

	if ( $code < 200 || $code >= 300 ) {
		$message = isset( $data['message'] ) ? (string) $data['message'] : __( 'PayPal request failed.', 'blackbean' );
		if ( ! empty( $data['details'][0]['description'] ) ) {
			$message = (string) $data['details'][0]['description'];
		}
		return new WP_Error( 'blackbean_paypal_api', $message, array( 'status' => $code ) );
	}

	return $data;
}

/**
 * Format amount for PayPal.
 *
 * @param float $amount Amount.
 */
function blackbean_shop_paypal_amount( float $amount ) : string {
	return number_format( max( 0, $amount ), 2, '.', '' );
}

/**
 * Find link href from PayPal order response.
 *
 * @param array<string, mixed> $paypal_order Order object.
 * @param string               $rel          Link rel.
 */
function blackbean_shop_paypal_link_href( array $paypal_order, string $rel ) : string {
	if ( empty( $paypal_order['links'] ) || ! is_array( $paypal_order['links'] ) ) {
		return '';
	}
	foreach ( $paypal_order['links'] as $link ) {
		if ( is_array( $link ) && isset( $link['rel'], $link['href'] ) && $rel === $link['rel'] ) {
			return (string) $link['href'];
		}
	}
	return '';
}

/**
 * Create PayPal order and return approval URL.
 *
 * @param int $order_id Shop order ID.
 * @return string|WP_Error Approval URL.
 */
function blackbean_shop_paypal_create_checkout( int $order_id ) {
	$order = blackbean_shop_get_order( $order_id );
	if ( ! $order || empty( $order['items'] ) ) {
		return new WP_Error( 'blackbean_shop_invalid_order', __( 'Order not found.', 'blackbean' ) );
	}

	$total = (float) $order['total'];
	if ( $total <= 0 ) {
		return new WP_Error( 'blackbean_paypal_zero', __( 'Order total must be greater than zero for PayPal payment.', 'blackbean' ) );
	}

	$currency = strtoupper( blackbean_shop_currency_code() );

	$return_url = add_query_arg(
		array(
			'order_id'        => (string) $order_id,
			'paypal_success'  => '1',
		),
		blackbean_shop_checkout_url()
	);
	$cancel_url = add_query_arg(
		array(
			'order_id'       => (string) $order_id,
			'paypal_cancel'  => '1',
		),
		blackbean_shop_checkout_url()
	);

	$body = array(
		'intent'              => 'CAPTURE',
		'purchase_units'      => array(
			array(
				'reference_id' => (string) $order_id,
				'custom_id'    => (string) $order_id,
				'description'  => sprintf(
					/* translators: %d: order ID */
					__( 'Order #%d', 'blackbean' ),
					$order_id
				),
				'amount'       => array(
					'currency_code' => $currency,
					'value'         => blackbean_shop_paypal_amount( $total ),
				),
			),
		),
		'application_context' => array(
			'brand_name'          => get_bloginfo( 'name' ),
			'landing_page'        => 'NO_PREFERENCE',
			'user_action'         => 'PAY_NOW',
			'return_url'          => $return_url,
			'cancel_url'          => $cancel_url,
			'shipping_preference' => 'NO_SHIPPING',
		),
	);

	$paypal_order = blackbean_shop_paypal_request( 'POST', '/v2/checkout/orders', $body );
	if ( is_wp_error( $paypal_order ) ) {
		return $paypal_order;
	}

	$paypal_id = isset( $paypal_order['id'] ) ? (string) $paypal_order['id'] : '';
	$approve   = blackbean_shop_paypal_link_href( $paypal_order, 'approve' );
	if ( '' === $paypal_id || '' === $approve ) {
		return new WP_Error( 'blackbean_paypal_session', __( 'Could not start PayPal Checkout.', 'blackbean' ) );
	}

	blackbean_order_update_meta( $order_id, BLACKBEAN_SHOP_META_PAYPAL_ORDER, $paypal_id );
	blackbean_order_update_meta( $order_id, BLACKBEAN_SHOP_META_PAYMENT_STATUS, 'pending' );

	return $approve;
}

/**
 * Get PayPal order details.
 *
 * @param string $paypal_order_id PayPal order ID.
 * @return array<string, mixed>|WP_Error
 */
function blackbean_shop_paypal_get_order( string $paypal_order_id ) {
	return blackbean_shop_paypal_request( 'GET', '/v2/checkout/orders/' . rawurlencode( $paypal_order_id ), array() );
}

/**
 * Capture an approved PayPal order.
 *
 * @param string $paypal_order_id PayPal order ID.
 * @return array<string, mixed>|WP_Error
 */
function blackbean_shop_paypal_capture( string $paypal_order_id ) {
	return blackbean_shop_paypal_request( 'POST', '/v2/checkout/orders/' . rawurlencode( $paypal_order_id ) . '/capture', array() );
}

/**
 * Whether PayPal order has a completed capture.
 *
 * @param array<string, mixed> $paypal_order PayPal order or capture response.
 */
function blackbean_shop_paypal_is_paid( array $paypal_order ) : bool {
	$status = isset( $paypal_order['status'] ) ? (string) $paypal_order['status'] : '';
	if ( 'COMPLETED' === $status ) {
		return true;
	}
	if ( empty( $paypal_order['purchase_units'] ) || ! is_array( $paypal_order['purchase_units'] ) ) {
		return false;
	}
	foreach ( $paypal_order['purchase_units'] as $unit ) {
		if ( ! is_array( $unit ) || empty( $unit['payments']['captures'] ) ) {
			continue;
		}
		foreach ( $unit['payments']['captures'] as $capture ) {
			if ( is_array( $capture ) && 'COMPLETED' === ( $capture['status'] ?? '' ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Resolve shop order ID from PayPal order payload.
 *
 * @param array<string, mixed> $paypal_order PayPal order.
 */
function blackbean_shop_paypal_order_id_from_payload( array $paypal_order ) : int {
	if ( ! empty( $paypal_order['purchase_units'][0]['custom_id'] ) ) {
		return (int) $paypal_order['purchase_units'][0]['custom_id'];
	}
	if ( ! empty( $paypal_order['purchase_units'][0]['reference_id'] ) ) {
		return (int) $paypal_order['purchase_units'][0]['reference_id'];
	}
	return 0;
}

/**
 * Confirm payment and fulfill shop order.
 *
 * @param int         $order_id        Shop order ID.
 * @param string|null $paypal_order_id PayPal order ID (optional).
 * @return true|WP_Error
 */
function blackbean_shop_paypal_confirm_order( int $order_id, ?string $paypal_order_id = null ) {
	if ( 'paid' === blackbean_order_get_meta( $order_id, BLACKBEAN_SHOP_META_PAYMENT_STATUS ) ) {
		return true;
	}

	if ( null === $paypal_order_id || '' === $paypal_order_id ) {
		$paypal_order_id = (string) blackbean_order_get_meta( $order_id, BLACKBEAN_SHOP_META_PAYPAL_ORDER );
	}
	if ( '' === $paypal_order_id && ! empty( $_GET['token'] ) ) {
		$paypal_order_id = sanitize_text_field( wp_unslash( (string) $_GET['token'] ) );
	}
	if ( '' === $paypal_order_id ) {
		return new WP_Error( 'blackbean_paypal_no_order', __( 'No PayPal payment for this order.', 'blackbean' ) );
	}

	blackbean_order_update_meta( $order_id, BLACKBEAN_SHOP_META_PAYPAL_ORDER, $paypal_order_id );

	$paypal_order = blackbean_shop_paypal_get_order( $paypal_order_id );
	if ( is_wp_error( $paypal_order ) ) {
		return $paypal_order;
	}

	if ( blackbean_shop_paypal_is_paid( $paypal_order ) ) {
		return blackbean_shop_fulfill_order( $order_id );
	}

	$status = isset( $paypal_order['status'] ) ? (string) $paypal_order['status'] : '';
	if ( in_array( $status, array( 'APPROVED', 'PAYER_ACTION_REQUIRED' ), true ) ) {
		$captured = blackbean_shop_paypal_capture( $paypal_order_id );
		if ( is_wp_error( $captured ) ) {
			return $captured;
		}
		if ( blackbean_shop_paypal_is_paid( $captured ) ) {
			return blackbean_shop_fulfill_order( $order_id );
		}
	}

	return new WP_Error( 'blackbean_paypal_unpaid', __( 'Payment not completed yet.', 'blackbean' ) );
}

/**
 * Begin checkout: create order then redirect to PayPal or fulfill free orders.
 *
 * @param array<string, string> $customer Customer fields.
 * @return never
 */
function blackbean_shop_begin_checkout( array $customer ) : void {
	$order_id = blackbean_shop_create_order( $customer, array( 'defer_fulfillment' => true ) );
	if ( is_wp_error( $order_id ) ) {
		wp_safe_redirect( add_query_arg( 'shop_error', rawurlencode( $order_id->get_error_message() ), blackbean_shop_checkout_url() ) );
		exit;
	}

	$order = blackbean_shop_get_order( (int) $order_id );
	if ( ! $order ) {
		wp_safe_redirect( add_query_arg( 'shop_error', rawurlencode( __( 'Could not create order.', 'blackbean' ) ), blackbean_shop_checkout_url() ) );
		exit;
	}

	if ( $order['total'] <= 0 || ! blackbean_shop_paypal_enabled() ) {
		blackbean_shop_fulfill_order( (int) $order_id );
		wp_safe_redirect( add_query_arg( array( 'order_id' => (string) $order_id, 'paid' => '1' ), blackbean_shop_checkout_url() ) );
		exit;
	}

	$checkout_url = blackbean_shop_paypal_create_checkout( (int) $order_id );
	if ( is_wp_error( $checkout_url ) ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'shop_error' => rawurlencode( $checkout_url->get_error_message() ),
					'order_id'   => (string) $order_id,
				),
				blackbean_shop_checkout_url()
			)
		);
		exit;
	}

	blackbean_shop_redirect_to_paypal( $checkout_url );
}

/**
 * PayPal webhook handler.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function blackbean_shop_paypal_webhook_rest( WP_REST_Request $request ) {
	$payload = $request->get_body();
	$event   = json_decode( $payload, true );
	if ( ! is_array( $event ) || empty( $event['event_type'] ) ) {
		return new WP_Error( 'blackbean_paypal_event', __( 'Invalid event.', 'blackbean' ), array( 'status' => 400 ) );
	}

	$type     = (string) $event['event_type'];
	$resource = isset( $event['resource'] ) && is_array( $event['resource'] ) ? $event['resource'] : array();
	$order_id = 0;

	if ( 'CHECKOUT.ORDER.APPROVED' === $type ) {
		$paypal_id = isset( $resource['id'] ) ? (string) $resource['id'] : '';
		$order_id  = blackbean_shop_paypal_order_id_from_payload( $resource );
		if ( $paypal_id && $order_id > 0 ) {
			blackbean_shop_paypal_confirm_order( $order_id, $paypal_id );
		}
	} elseif ( 'PAYMENT.CAPTURE.COMPLETED' === $type ) {
		if ( ! empty( $resource['custom_id'] ) ) {
			$order_id = (int) $resource['custom_id'];
		}
		if ( $order_id > 0 && 'paid' !== blackbean_order_get_meta( $order_id, BLACKBEAN_SHOP_META_PAYMENT_STATUS ) ) {
			blackbean_shop_fulfill_order( $order_id );
		}
	}

	return rest_ensure_response( array( 'received' => true ) );
}

/**
 * Register PayPal webhook route.
 */
function blackbean_shop_register_paypal_webhook() : void {
	register_rest_route(
		'blackbean/v1',
		'/shop/paypal-webhook',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => 'blackbean_rest_permission_paypal_webhook',
			'callback'            => 'blackbean_shop_paypal_webhook_rest',
		)
	);
}
add_action( 'rest_api_init', 'blackbean_shop_register_paypal_webhook' );

/**
 * On checkout return from PayPal, capture and fulfill.
 */
function blackbean_shop_paypal_handle_return() : void {
	if ( ! get_query_var( 'blackbean_shop_view' ) || 'checkout' !== get_query_var( 'blackbean_shop_view' ) ) {
		return;
	}
	$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
	if ( $order_id <= 0 ) {
		return;
	}
	if ( ! empty( $_GET['paypal_success'] ) ) {
		$result = blackbean_shop_paypal_confirm_order( $order_id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'order_id'   => (string) $order_id,
						'shop_error' => rawurlencode( $result->get_error_message() ),
					),
					blackbean_shop_checkout_url()
				)
			);
			exit;
		}
		blackbean_shop_cart_clear_for_order( $order_id );
		wp_safe_redirect( add_query_arg( array( 'order_id' => (string) $order_id, 'paid' => '1' ), blackbean_shop_checkout_url() ) );
		exit;
	}
}
add_action( 'template_redirect', 'blackbean_shop_paypal_handle_return', 5 );

/**
 * Ensure cart is empty when viewing a paid order confirmation (e.g. after webhook fulfilled first).
 */
function blackbean_shop_clear_cart_on_paid_checkout_view() : void {
	if ( 'checkout' !== get_query_var( 'blackbean_shop_view' ) ) {
		return;
	}
	$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
	if ( $order_id <= 0 ) {
		return;
	}
	$order = blackbean_shop_get_order( $order_id );
	if ( ! $order || 'paid' !== $order['payment_status'] ) {
		return;
	}
	blackbean_shop_cart_clear_for_order( $order_id );
}
add_action( 'template_redirect', 'blackbean_shop_clear_cart_on_paid_checkout_view', 6 );

/**
 * Retry PayPal payment for a pending order.
 */
function blackbean_shop_paypal_handle_pay_order() : void {
	if ( empty( $_GET['pay_order'] ) || empty( $_GET['order_id'] ) ) {
		return;
	}
	$order_id = (int) $_GET['order_id'];
	if ( $order_id <= 0 || ! blackbean_shop_paypal_enabled() ) {
		return;
	}
	if ( 'paid' === blackbean_order_get_meta( $order_id, BLACKBEAN_SHOP_META_PAYMENT_STATUS ) ) {
		wp_safe_redirect( add_query_arg( array( 'order_id' => (string) $order_id, 'paid' => '1' ), blackbean_shop_checkout_url() ) );
		exit;
	}
	$url = blackbean_shop_paypal_create_checkout( $order_id );
	if ( is_wp_error( $url ) ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'order_id'   => (string) $order_id,
					'shop_error' => rawurlencode( $url->get_error_message() ),
				),
				blackbean_shop_checkout_url()
			)
		);
		exit;
	}
	blackbean_shop_redirect_to_paypal( $url );
}
add_action( 'template_redirect', 'blackbean_shop_paypal_handle_pay_order', 4 );
