<?php
/**
 * Blackbean license client — copy this file into your plugin or theme.
 *
 * Usage:
 *   $client = new Blackbean_License_Client( 'https://yoursite.com', 123 ); // shop URL + product ID
 *   $result = $client->activate( 'PREFIX-1-123-ABCD1234' );
 *   if ( is_wp_error( $result ) ) { ... }
 *   if ( ! empty( $result['success'] ) ) { // licensed }
 *
 * @package Blackbean_License_Client
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to Blackbean shop license REST API.
 */
class Blackbean_License_Client {

	/** @var string Shop site URL (no trailing path required). */
	private string $api_base;

	/** @var int Product post ID on the shop. */
	private int $product_id;

	/** @var int Request timeout seconds. */
	private int $timeout;

	/**
	 * @param string $shop_url   WordPress shop home URL, e.g. https://blackbean.example
	 * @param int    $product_id Shop product ID from the shop.
	 * @param int    $timeout    HTTP timeout.
	 */
	public function __construct( string $shop_url, int $product_id, int $timeout = 15 ) {
		$this->api_base    = rtrim( $shop_url, '/' ) . '/wp-json/blackbean/v1/shop/license';
		$this->product_id  = $product_id;
		$this->timeout     = max( 5, $timeout );
	}

	/**
	 * @return string
	 */
	private function site_url() : string {
		return home_url( '/' );
	}

	/**
	 * @param string               $endpoint activate|deactivate|check
	 * @param string               $license_key License key.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( string $endpoint, string $license_key ) {
		$url  = $this->api_base . '/' . $endpoint;
		$body = wp_json_encode(
			array(
				'license_key' => $license_key,
				'site_url'    => $this->site_url(),
				'product_id'  => $this->product_id,
			)
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $this->timeout,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'blackbean_license_bad_response', __( 'Invalid response from license server.', 'blackbean' ) );
		}

		if ( $code >= 400 ) {
			$message = isset( $data['message'] ) ? (string) $data['message'] : __( 'License request failed.', 'blackbean' );
			return new WP_Error( 'blackbean_license_http', $message, array( 'status' => $code, 'data' => $data ) );
		}

		return $data;
	}

	/**
	 * Activate this site.
	 *
	 * @param string $license_key License key.
	 * @return array<string, mixed>|WP_Error
	 */
	public function activate( string $license_key ) {
		return $this->request( 'activate', $license_key );
	}

	/**
	 * Deactivate this site (free a slot).
	 *
	 * @param string $license_key License key.
	 * @return array<string, mixed>|WP_Error
	 */
	public function deactivate( string $license_key ) {
		return $this->request( 'deactivate', $license_key );
	}

	/**
	 * Check whether this site is activated (also refreshes last seen).
	 *
	 * @param string $license_key License key.
	 * @return array<string, mixed>|WP_Error
	 */
	public function check( string $license_key ) {
		return $this->request( 'check', $license_key );
	}

	/**
	 * Convenience: true if check reports success.
	 *
	 * @param string $license_key License key.
	 */
	public function is_valid( string $license_key ) : bool {
		$result = $this->check( $license_key );
		return ! is_wp_error( $result ) && ! empty( $result['success'] );
	}
}
