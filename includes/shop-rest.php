<?php
/**
 * Shop REST API.
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart REST response (refreshes session cookie for this request).
 *
 * @return WP_REST_Response
 */
function blackbean_shop_rest_cart_response() {
	blackbean_shop_cart_session_id();
	return rest_ensure_response( blackbean_shop_cart_get() );
}

/**
 * Register shop routes.
 */
function blackbean_shop_register_rest_routes() : void {
	register_rest_route(
		'blackbean/v1',
		'/shop/products',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => 'blackbean_rest_permission_public_read',
			'callback'            => static function ( WP_REST_Request $request ) {
				$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
				$limit  = max( 1, min( 20, (int) $request->get_param( 'per_page' ) ?: 12 ) );
				if ( '' !== $search ) {
					return rest_ensure_response( blackbean_shop_search_products( $search, $limit ) );
				}
				$rows = blackbean_products_query(
					array(
						'status'  => 'publish',
						'limit'   => $limit,
						'orderby' => 'created_at',
						'order'   => 'DESC',
					)
				);
				$list = array();
				foreach ( $rows as $row ) {
					$p = blackbean_shop_get_product( $row );
					if ( $p ) {
						$list[] = $p;
					}
				}
				return rest_ensure_response( $list );
			},
		)
	);

	register_rest_route(
		'blackbean/v1',
		'/shop/cart',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => 'blackbean_rest_permission_public_read',
				'callback'            => static function () {
					return blackbean_shop_rest_cart_response();
				},
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => 'blackbean_rest_permission_public_write',
				'callback'            => static function ( WP_REST_Request $request ) {
					$action     = sanitize_key( (string) $request->get_param( 'action' ) );
					$product_id = (int) $request->get_param( 'product_id' );
					$qty        = max( 1, (int) $request->get_param( 'qty' ) ?: 1 );

					if ( 'add' === $action ) {
						$result = blackbean_shop_cart_add( $product_id, $qty );
					} elseif ( 'set' === $action ) {
						$result = blackbean_shop_cart_set_qty( $product_id, (int) $request->get_param( 'qty' ) );
					} elseif ( 'remove' === $action ) {
						blackbean_shop_cart_remove( $product_id );
						$result = true;
					} elseif ( 'clear' === $action ) {
						blackbean_shop_cart_clear();
						$result = true;
					} else {
						return new WP_Error( 'blackbean_shop_bad_action', __( 'Invalid cart action.', 'blackbean' ), array( 'status' => 400 ) );
					}

					if ( is_wp_error( $result ) ) {
						return $result;
					}
					return blackbean_shop_rest_cart_response();
				},
			),
		)
	);
}
add_action( 'rest_api_init', 'blackbean_shop_register_rest_routes' );
