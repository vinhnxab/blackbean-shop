<?php
/**
 * License activation REST API (for customer plugins/themes).
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse license REST body / params.
 *
 * @param WP_REST_Request $request Request.
 * @return array{license_key:string, site_url:string, product_id:int|null}
 */
function blackbean_shop_license_rest_params( WP_REST_Request $request ) : array {
	$license_key = sanitize_text_field( (string) $request->get_param( 'license_key' ) );
	$site_url    = esc_url_raw( (string) $request->get_param( 'site_url' ) );
	$product_id  = $request->get_param( 'product_id' );
	$product_id  = ( null === $product_id || '' === $product_id ) ? null : (int) $product_id;

	if ( '' === $site_url && function_exists( 'home_url' ) ) {
		$site_url = home_url( '/' );
	}

	return array(
		'license_key' => $license_key,
		'site_url'    => $site_url,
		'product_id'  => $product_id,
	);
}

/**
 * @param array{success:bool, license:array<string,mixed>}|WP_Error $result Result.
 */
function blackbean_shop_license_rest_response( $result ) {
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return rest_ensure_response( $result );
}

/**
 * Register license routes.
 */
function blackbean_shop_register_license_routes() : void {
	$args = array(
		'license_key' => array(
			'required' => true,
			'type'     => 'string',
		),
		'site_url'    => array(
			'required' => false,
			'type'     => 'string',
		),
		'product_id'  => array(
			'required' => false,
			'type'     => 'integer',
		),
	);

	register_rest_route(
		'blackbean/v1',
		'/shop/license/activate',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => 'blackbean_rest_permission_public_write',
			'callback'            => static function ( WP_REST_Request $request ) {
				$p = blackbean_shop_license_rest_params( $request );
				if ( '' === $p['license_key'] ) {
					return new WP_Error( 'blackbean_license_missing', __( 'License key is required.', 'blackbean' ), array( 'status' => 400 ) );
				}
				return blackbean_shop_license_rest_response(
					blackbean_shop_license_activate( $p['license_key'], $p['site_url'], $p['product_id'] )
				);
			},
			'args'                => $args,
		)
	);

	register_rest_route(
		'blackbean/v1',
		'/shop/license/deactivate',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => 'blackbean_rest_permission_public_write',
			'callback'            => static function ( WP_REST_Request $request ) {
				$p = blackbean_shop_license_rest_params( $request );
				if ( '' === $p['license_key'] ) {
					return new WP_Error( 'blackbean_license_missing', __( 'License key is required.', 'blackbean' ), array( 'status' => 400 ) );
				}
				return blackbean_shop_license_rest_response(
					blackbean_shop_license_deactivate( $p['license_key'], $p['site_url'], $p['product_id'] )
				);
			},
			'args'                => $args,
		)
	);

	register_rest_route(
		'blackbean/v1',
		'/shop/license/check',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => 'blackbean_rest_permission_public_write',
			'callback'            => static function ( WP_REST_Request $request ) {
				$p = blackbean_shop_license_rest_params( $request );
				if ( '' === $p['license_key'] ) {
					return new WP_Error( 'blackbean_license_missing', __( 'License key is required.', 'blackbean' ), array( 'status' => 400 ) );
				}
				return blackbean_shop_license_rest_response(
					blackbean_shop_license_check( $p['license_key'], $p['site_url'], $p['product_id'] )
				);
			},
			'args'                => $args,
		)
	);
}
add_action( 'rest_api_init', 'blackbean_shop_register_license_routes' );
