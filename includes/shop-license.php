<?php
/**
 * License storage and activation (site limits per key).
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLACKBEAN_SHOP_LICENSE_DB_VERSION     = '1';
const BLACKBEAN_SHOP_LICENSE_DB_VERSION_OPT = 'blackbean_shop_license_db_version';
const BLACKBEAN_SHOP_META_LICENSE_MAX_SITES = '_bb_license_max_sites';

/**
 * Create or upgrade license tables.
 */
function blackbean_shop_license_install_tables() : void {
	global $wpdb;

	if ( BLACKBEAN_SHOP_LICENSE_DB_VERSION === get_option( BLACKBEAN_SHOP_LICENSE_DB_VERSION_OPT ) ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$licenses = $wpdb->prefix . 'bb_licenses';
	$acts     = $wpdb->prefix . 'bb_license_activations';

	dbDelta(
		"CREATE TABLE {$licenses} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		license_key varchar(64) NOT NULL,
		order_id bigint(20) unsigned NOT NULL DEFAULT 0,
		product_id bigint(20) unsigned NOT NULL DEFAULT 0,
		customer_email varchar(190) NOT NULL DEFAULT '',
		max_activations smallint(5) unsigned NOT NULL DEFAULT 1,
		status varchar(20) NOT NULL DEFAULT 'active',
		expires_at datetime NULL DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		UNIQUE KEY license_key (license_key),
		KEY order_id (order_id),
		KEY product_id (product_id)
	) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$acts} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		license_id bigint(20) unsigned NOT NULL,
		site_url varchar(255) NOT NULL DEFAULT '',
		activated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		last_check_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		UNIQUE KEY license_site (license_id, site_url),
		KEY license_id (license_id)
	) {$charset};"
	);
	update_option( BLACKBEAN_SHOP_LICENSE_DB_VERSION_OPT, BLACKBEAN_SHOP_LICENSE_DB_VERSION, false );
}
add_action( 'init', 'blackbean_shop_license_install_tables', 5 );

/**
 * Max simultaneous site activations for a product.
 *
 * @param int $product_id Product ID.
 */
function blackbean_shop_product_max_license_activations( int $product_id ) : int {
	$max = (int) blackbean_product_get_meta( $product_id, BLACKBEAN_SHOP_META_LICENSE_MAX_SITES );
	if ( $max < 1 ) {
		return 1;
	}
	return min( 99, $max );
}

/**
 * Normalize a site URL to host (no scheme/path).
 *
 * @param string $url Site URL.
 */
function blackbean_shop_license_normalize_site( string $url ) : string {
	$url = trim( $url );
	if ( '' === $url ) {
		return '';
	}
	if ( false === strpos( $url, '://' ) ) {
		$url = 'https://' . $url;
	}
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! is_string( $host ) || '' === $host ) {
		return '';
	}
	$host = strtolower( $host );
	if ( str_starts_with( $host, 'www.' ) ) {
		$host = substr( $host, 4 );
	}
	return $host;
}

/**
 * @return string
 */
function blackbean_shop_license_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'bb_licenses';
}

/**
 * @return string
 */
function blackbean_shop_license_activations_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'bb_license_activations';
}

/**
 * @param string $license_key License key.
 * @return array<string, mixed>|null
 */
function blackbean_shop_license_get_by_key( string $license_key ) : ?array {
	global $wpdb;

	$key = sanitize_text_field( $license_key );
	if ( '' === $key ) {
		return null;
	}

	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . blackbean_shop_license_table() . ' WHERE license_key = %s LIMIT 1',
			$key
		),
		ARRAY_A
	);

	return is_array( $row ) ? $row : null;
}

/**
 * Register licenses from fulfillment rows (idempotent per key).
 *
 * @param int                             $order_id    Order ID.
 * @param list<array<string, mixed>>      $fulfillment Fulfillment rows.
 */
function blackbean_shop_license_register_fulfillment( int $order_id, array $fulfillment ) : void {
	global $wpdb;

	$order = blackbean_shop_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$email = (string) ( $order['customer']['email'] ?? '' );
	$now   = current_time( 'mysql', true );

	foreach ( $fulfillment as $row ) {
		$license_key = (string) ( $row['license'] ?? '' );
		$product_id  = (int) ( $row['product_id'] ?? 0 );
		if ( '' === $license_key || $product_id <= 0 ) {
			continue;
		}
		if ( blackbean_shop_license_get_by_key( $license_key ) ) {
			continue;
		}

		$wpdb->insert(
			blackbean_shop_license_table(),
			array(
				'license_key'     => $license_key,
				'order_id'        => $order_id,
				'product_id'      => $product_id,
				'customer_email'  => $email,
				'max_activations' => blackbean_shop_product_max_license_activations( $product_id ),
				'status'          => 'active',
				'expires_at'      => null,
				'created_at'      => $now,
			),
			array( '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}
}

/**
 * Whether license record is valid for activation.
 *
 * @param array<string, mixed> $license License row.
 * @return true|WP_Error
 */
function blackbean_shop_license_validate_record( array $license ) {
	if ( 'active' !== (string) ( $license['status'] ?? '' ) ) {
		return new WP_Error( 'blackbean_license_revoked', __( 'This license has been revoked.', 'blackbean' ), array( 'status' => 403 ) );
	}

	$expires = $license['expires_at'] ?? null;
	if ( is_string( $expires ) && '' !== $expires && '0000-00-00 00:00:00' !== $expires ) {
		if ( strtotime( $expires ) < time() ) {
			return new WP_Error( 'blackbean_license_expired', __( 'This license has expired.', 'blackbean' ), array( 'status' => 403 ) );
		}
	}

	$order_id = (int) ( $license['order_id'] ?? 0 );
	if ( $order_id > 0 && 'paid' !== blackbean_order_get_meta( $order_id, BLACKBEAN_SHOP_META_PAYMENT_STATUS ) ) {
		return new WP_Error( 'blackbean_license_unpaid', __( 'The order for this license is not paid.', 'blackbean' ), array( 'status' => 403 ) );
	}

	return true;
}

/**
 * @param int $license_id License ID.
 * @return list<array<string, mixed>>
 */
function blackbean_shop_license_get_activations( int $license_id ) : array {
	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . blackbean_shop_license_activations_table() . ' WHERE license_id = %d ORDER BY activated_at ASC',
			$license_id
		),
		ARRAY_A
	);

	return is_array( $rows ) ? $rows : array();
}

/**
 * @param int $license_id License ID.
 */
function blackbean_shop_license_count_activations( int $license_id ) : int {
	global $wpdb;

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . blackbean_shop_license_activations_table() . ' WHERE license_id = %d',
			$license_id
		)
	);
}

/**
 * Build REST payload for a license check/activate response.
 *
 * @param array<string, mixed> $license     License row.
 * @param string               $site        Normalized site host.
 * @param bool                 $site_active Whether this site is activated.
 */
function blackbean_shop_license_response_payload( array $license, string $site, bool $site_active ) : array {
	$license_id = (int) ( $license['id'] ?? 0 );
	$used       = blackbean_shop_license_count_activations( $license_id );
	$max        = (int) ( $license['max_activations'] ?? 1 );

	return array(
		'status'      => (string) ( $license['status'] ?? 'active' ),
		'product_id'  => (int) ( $license['product_id'] ?? 0 ),
		'order_id'    => (int) ( $license['order_id'] ?? 0 ),
		'expires_at'  => $license['expires_at'] ?? null,
		'activations' => array(
			'used' => $used,
			'max'  => $max,
		),
		'site'        => $site,
		'site_active' => $site_active,
	);
}

/**
 * Activate a license on a site.
 *
 * @param string   $license_key License key.
 * @param string   $site_url    Customer site URL.
 * @param int|null $product_id  Optional product ID guard.
 * @return array{success:bool, license:array<string,mixed>}|WP_Error
 */
function blackbean_shop_license_activate( string $license_key, string $site_url, ?int $product_id = null ) {
	global $wpdb;

	$license = blackbean_shop_license_get_by_key( $license_key );
	if ( ! $license ) {
		return new WP_Error( 'blackbean_license_invalid', __( 'Invalid license key.', 'blackbean' ), array( 'status' => 404 ) );
	}

	if ( null !== $product_id && $product_id > 0 && (int) $license['product_id'] !== $product_id ) {
		return new WP_Error( 'blackbean_license_product', __( 'This license is not valid for this product.', 'blackbean' ), array( 'status' => 403 ) );
	}

	$valid = blackbean_shop_license_validate_record( $license );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}

	$site = blackbean_shop_license_normalize_site( $site_url );
	if ( '' === $site ) {
		return new WP_Error( 'blackbean_license_site', __( 'A valid site URL is required.', 'blackbean' ), array( 'status' => 400 ) );
	}

	$license_id = (int) $license['id'];
	$existing   = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT id FROM ' . blackbean_shop_license_activations_table() . ' WHERE license_id = %d AND site_url = %s LIMIT 1',
			$license_id,
			$site
		)
	);

	$now = current_time( 'mysql', true );

	if ( $existing ) {
		$wpdb->update(
			blackbean_shop_license_activations_table(),
			array( 'last_check_at' => $now ),
			array( 'id' => (int) $existing->id ),
			array( '%s' ),
			array( '%d' )
		);

		return array(
			'success' => true,
			'license' => blackbean_shop_license_response_payload( $license, $site, true ),
		);
	}

	$used = blackbean_shop_license_count_activations( $license_id );
	$max  = (int) ( $license['max_activations'] ?? 1 );
	if ( $used >= $max ) {
		return new WP_Error(
			'blackbean_license_limit',
			sprintf(
				/* translators: %d: max sites */
				__( 'Activation limit reached (%d site(s)). Deactivate another site first.', 'blackbean' ),
				$max
			),
			array( 'status' => 403 )
		);
	}

	$wpdb->insert(
		blackbean_shop_license_activations_table(),
		array(
			'license_id'    => $license_id,
			'site_url'      => $site,
			'activated_at'  => $now,
			'last_check_at' => $now,
		),
		array( '%d', '%s', '%s', '%s' )
	);

	return array(
		'success' => true,
		'license' => blackbean_shop_license_response_payload( $license, $site, true ),
	);
}

/**
 * Deactivate a license on a site.
 *
 * @param string   $license_key License key.
 * @param string   $site_url    Site URL.
 * @param int|null $product_id  Optional product ID guard.
 * @return array{success:bool, license:array<string,mixed>}|WP_Error
 */
function blackbean_shop_license_deactivate( string $license_key, string $site_url, ?int $product_id = null ) {
	global $wpdb;

	$license = blackbean_shop_license_get_by_key( $license_key );
	if ( ! $license ) {
		return new WP_Error( 'blackbean_license_invalid', __( 'Invalid license key.', 'blackbean' ), array( 'status' => 404 ) );
	}

	if ( null !== $product_id && $product_id > 0 && (int) $license['product_id'] !== $product_id ) {
		return new WP_Error( 'blackbean_license_product', __( 'This license is not valid for this product.', 'blackbean' ), array( 'status' => 403 ) );
	}

	$site = blackbean_shop_license_normalize_site( $site_url );
	if ( '' === $site ) {
		return new WP_Error( 'blackbean_license_site', __( 'A valid site URL is required.', 'blackbean' ), array( 'status' => 400 ) );
	}

	$license_id = (int) $license['id'];
	$wpdb->delete(
		blackbean_shop_license_activations_table(),
		array(
			'license_id' => $license_id,
			'site_url'   => $site,
		),
		array( '%d', '%s' )
	);

	return array(
		'success' => true,
		'license' => blackbean_shop_license_response_payload( $license, $site, false ),
	);
}

/**
 * Check license status for a site (no side effects except last_check_at).
 *
 * @param string   $license_key License key.
 * @param string   $site_url    Site URL.
 * @param int|null $product_id  Optional product ID guard.
 * @return array{success:bool, license:array<string,mixed>}|WP_Error
 */
function blackbean_shop_license_check( string $license_key, string $site_url, ?int $product_id = null ) {
	global $wpdb;

	$license = blackbean_shop_license_get_by_key( $license_key );
	if ( ! $license ) {
		return new WP_Error( 'blackbean_license_invalid', __( 'Invalid license key.', 'blackbean' ), array( 'status' => 404 ) );
	}

	if ( null !== $product_id && $product_id > 0 && (int) $license['product_id'] !== $product_id ) {
		return new WP_Error( 'blackbean_license_product', __( 'This license is not valid for this product.', 'blackbean' ), array( 'status' => 403 ) );
	}

	$valid = blackbean_shop_license_validate_record( $license );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}

	$site = blackbean_shop_license_normalize_site( $site_url );
	if ( '' === $site ) {
		return new WP_Error( 'blackbean_license_site', __( 'A valid site URL is required.', 'blackbean' ), array( 'status' => 400 ) );
	}

	$license_id = (int) $license['id'];
	$row        = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT id FROM ' . blackbean_shop_license_activations_table() . ' WHERE license_id = %d AND site_url = %s LIMIT 1',
			$license_id,
			$site
		)
	);

	$active = (bool) $row;
	if ( $active ) {
		$wpdb->update(
			blackbean_shop_license_activations_table(),
			array( 'last_check_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $row->id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	return array(
		'success' => $active,
		'license' => blackbean_shop_license_response_payload( $license, $site, $active ),
	);
}

/**
 * Licenses for an order (with activation summary).
 *
 * @param int $order_id Order ID.
 * @return list<array<string, mixed>>
 */
function blackbean_shop_license_list_for_order( int $order_id ) : array {
	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . blackbean_shop_license_table() . ' WHERE order_id = %d ORDER BY id ASC',
			$order_id
		),
		ARRAY_A
	);

	if ( ! is_array( $rows ) ) {
		return array();
	}

	foreach ( $rows as &$row ) {
		$row['activations'] = blackbean_shop_license_get_activations( (int) $row['id'] );
		$row['activation_count'] = count( $row['activations'] );
	}
	unset( $row );

	return $rows;
}

/**
 * Revoke a license by key (admin).
 *
 * @param string $license_key License key.
 * @return true|WP_Error
 */
function blackbean_shop_license_revoke( string $license_key ) {
	global $wpdb;

	$license = blackbean_shop_license_get_by_key( $license_key );
	if ( ! $license ) {
		return new WP_Error( 'blackbean_license_invalid', __( 'License not found.', 'blackbean' ) );
	}

	$wpdb->update(
		blackbean_shop_license_table(),
		array( 'status' => 'revoked' ),
		array( 'id' => (int) $license['id'] ),
		array( '%s' ),
		array( '%d' )
	);

	return true;
}

/**
 * Restore a revoked license.
 *
 * @param string $license_key License key.
 * @return true|WP_Error
 */
function blackbean_shop_license_restore( string $license_key ) {
	global $wpdb;

	$license = blackbean_shop_license_get_by_key( $license_key );
	if ( ! $license ) {
		return new WP_Error( 'blackbean_license_invalid', __( 'License not found.', 'blackbean' ) );
	}

	$wpdb->update(
		blackbean_shop_license_table(),
		array( 'status' => 'active' ),
		array( 'id' => (int) $license['id'] ),
		array( '%s' ),
		array( '%d' )
	);

	return true;
}

/**
 * Remove a single site activation (admin).
 *
 * @param int $activation_id Activation row ID.
 * @return true|WP_Error
 */
function blackbean_shop_license_remove_activation( int $activation_id ) {
	global $wpdb;

	$deleted = $wpdb->delete(
		blackbean_shop_license_activations_table(),
		array( 'id' => $activation_id ),
		array( '%d' )
	);

	if ( ! $deleted ) {
		return new WP_Error( 'blackbean_license_activation', __( 'Activation not found.', 'blackbean' ) );
	}

	return true;
}

/**
 * Count licenses by status.
 *
 * @return array{all:int, active:int, revoked:int}
 */
function blackbean_shop_license_status_counts() : array {
	global $wpdb;

	$table   = blackbean_shop_license_table();
	$all     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	$active  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'active' ) );
	$revoked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'revoked' ) );

	return array(
		'all'     => $all,
		'active'  => $active,
		'revoked' => $revoked,
	);
}

/**
 * Search and paginate licenses for admin.
 *
 * @param array<string, mixed> $args Query args: status, search, paged, per_page.
 * @return array{items:list<array<string,mixed>>, total:int, pages:int, page:int}
 */
function blackbean_shop_license_query( array $args = array() ) : array {
	global $wpdb;

	$page     = max( 1, (int) ( $args['paged'] ?? 1 ) );
	$per_page = min( 100, max( 10, (int) ( $args['per_page'] ?? 50 ) ) );
	$status   = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : 'all';
	$search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';

	$table  = blackbean_shop_license_table();
	$where  = array( '1=1' );
	$params = array();

	if ( in_array( $status, array( 'active', 'revoked' ), true ) ) {
		$where[]  = 'status = %s';
		$params[] = $status;
	}

	if ( '' !== $search ) {
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		if ( ctype_digit( $search ) ) {
			$where[]  = '(order_id = %d OR product_id = %d OR license_key LIKE %s OR customer_email LIKE %s)';
			$params[] = (int) $search;
			$params[] = (int) $search;
			$params[] = $like;
			$params[] = $like;
		} else {
			$where[]  = '(license_key LIKE %s OR customer_email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
	}

	$where_sql   = implode( ' AND ', $where );
	$count_sql   = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
	$total       = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) : $wpdb->get_var( $count_sql ) );
	$offset      = ( $page - 1 ) * $per_page;
	$list_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
	$list_params = array_merge( $params, array( $per_page, $offset ) );
	$rows        = $wpdb->get_results( $wpdb->prepare( $list_sql, ...$list_params ), ARRAY_A );

	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	foreach ( $rows as &$row ) {
		$license_id              = (int) ( $row['id'] ?? 0 );
		$row['activations']      = blackbean_shop_license_get_activations( $license_id );
		$row['activation_count'] = count( $row['activations'] );
		$row['product_title']    = '';
		if ( ! empty( $row['product_id'] ) ) {
			$row['product_title'] = get_the_title( (int) $row['product_id'] );
		}
	}
	unset( $row );

	$pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;

	return array(
		'items' => $rows,
		'total' => $total,
		'pages' => $pages,
		'page'  => $page,
	);
}
