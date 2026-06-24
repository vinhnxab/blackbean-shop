<?php
/**
 * Product data layer (Black Bean table).
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, string>
 */
function blackbean_product_meta_map() : array {
	return array(
		'_bb_price'                      => 'price',
		'_bb_sku'                        => 'sku',
		'_bb_stock'                      => 'stock',
		BLACKBEAN_SHOP_META_IS_DIGITAL   => 'is_digital',
		BLACKBEAN_SHOP_META_DOWNLOAD_URL => 'download_url',
		BLACKBEAN_SHOP_META_DOWNLOAD_FILE => 'download_file_id',
		BLACKBEAN_SHOP_META_LICENSE_PREFIX => 'license_prefix',
		BLACKBEAN_SHOP_META_LICENSE_MAX_SITES => 'license_max_sites',
	);
}

/**
 * @param int $product_id Product ID.
 * @return array<string, mixed>|null
 */
function blackbean_product_get_row( int $product_id ) : ?array {
	global $wpdb;

	if ( $product_id <= 0 ) {
		return null;
	}

	$table = blackbean_table_products();
	$row   = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $product_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);

	return is_array( $row ) ? $row : null;
}

/**
 * @param string $slug Product slug.
 * @return array<string, mixed>|null
 */
function blackbean_product_get_by_slug( string $slug ) : ?array {
	global $wpdb;

	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		return null;
	}

	$table = blackbean_table_products();
	$row   = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);

	return is_array( $row ) ? $row : null;
}

/**
 * CPT-compatible meta read for products.
 *
 * @param int    $product_id Product ID.
 * @param string $meta_key   Meta key.
 * @return mixed
 */
function blackbean_product_get_meta( int $product_id, string $meta_key ) {
	$row = blackbean_product_get_row( $product_id );
	if ( ! $row ) {
		return '';
	}

	$map = blackbean_product_meta_map();
	if ( isset( $map[ $meta_key ] ) ) {
		$col = $map[ $meta_key ];
		if ( 'is_digital' === $col ) {
			return ! empty( $row[ $col ] ) ? '1' : '0';
		}
		return $row[ $col ];
	}

	return '';
}

/**
 * @param int    $product_id Product ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $value      Value.
 */
function blackbean_product_update_meta( int $product_id, string $meta_key, $value ) : void {
	$map = blackbean_product_meta_map();
	if ( ! isset( $map[ $meta_key ] ) ) {
		return;
	}

	$col = $map[ $meta_key ];
	$data = array();

	switch ( $col ) {
		case 'price':
			$data['price'] = max( 0, (float) $value );
			break;
		case 'sku':
			$data['sku'] = sanitize_text_field( (string) $value );
			break;
		case 'stock':
			$data['stock'] = (int) $value;
			break;
		case 'is_digital':
			$data['is_digital'] = ( '0' === (string) $value || '' === (string) $value ) ? 0 : 1;
			break;
		case 'download_url':
			$data['download_url'] = esc_url_raw( (string) $value );
			break;
		case 'download_file_id':
			$data['download_file_id'] = max( 0, (int) $value );
			break;
		case 'license_prefix':
			$data['license_prefix'] = sanitize_text_field( (string) $value );
			break;
		case 'license_max_sites':
			$data['license_max_sites'] = max( 1, min( 99, (int) $value ) );
			break;
	}

	if ( ! empty( $data ) ) {
		$data['updated_at'] = current_time( 'mysql', true );
		blackbean_product_update( $product_id, $data );
	}
}

/**
 * @param string $slug Product slug.
 * @return string
 */
function blackbean_product_permalink( string $slug ) : string {
	return home_url( '/products/' . rawurlencode( sanitize_title( $slug ) ) . '/' );
}

/**
 * @param array<string, mixed> $row Product row.
 * @return array<string, mixed>
 */
function blackbean_product_format_public( array $row ) : array {
	$price = (float) ( $row['price'] ?? 0 );
	$stock = isset( $row['stock'] ) ? (int) $row['stock'] : -1;
	$image = '';

	if ( ! empty( $row['featured_image_id'] ) ) {
		$thumb = wp_get_attachment_image_url( (int) $row['featured_image_id'], 'medium' );
		$image = is_string( $thumb ) ? $thumb : '';
	}

	return array(
		'id'          => (int) $row['id'],
		'title'       => (string) $row['title'],
		'slug'        => (string) $row['slug'],
		'excerpt'     => wp_strip_all_tags( (string) ( $row['excerpt'] ?? '' ) ),
		'content'     => wp_strip_all_tags( (string) ( $row['content'] ?? '' ) ),
		'url'         => blackbean_product_permalink( (string) $row['slug'] ),
		'price'       => $price,
		'price_label' => blackbean_shop_format_price( $price ),
		'sku'         => (string) ( $row['sku'] ?? '' ),
		'stock'       => $stock,
		'in_stock'    => $stock < 0 || $stock > 0,
		'image'       => $image,
	);
}

/**
 * @param array{
 *   title?:string,
 *   slug?:string,
 *   content?:string,
 *   excerpt?:string,
 *   status?:string,
 *   price?:float,
 *   sku?:string,
 *   stock?:int,
 *   is_digital?:int,
 *   download_url?:string,
 *   download_file_id?:int,
 *   license_prefix?:string,
 *   license_max_sites?:int,
 *   featured_image_id?:int
 * } $data Product fields.
 * @return int|WP_Error New product ID.
 */
function blackbean_product_insert( array $data ) {
	global $wpdb;

	$now   = current_time( 'mysql', true );
	$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
	$slug  = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $title );

	if ( '' === $title ) {
		return new WP_Error( 'blackbean_product_invalid', __( 'Product title is required.', 'blackbean' ) );
	}

	$slug = blackbean_product_unique_slug( $slug, 0 );

	$wpdb->insert(
		blackbean_table_products(),
		array(
			'slug'              => $slug,
			'title'             => $title,
			'content'           => isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : '',
			'excerpt'           => isset( $data['excerpt'] ) ? sanitize_textarea_field( $data['excerpt'] ) : '',
			'status'            => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'draft',
			'price'             => max( 0, (float) ( $data['price'] ?? 0 ) ),
			'sku'               => isset( $data['sku'] ) ? sanitize_text_field( $data['sku'] ) : '',
			'stock'             => isset( $data['stock'] ) ? (int) $data['stock'] : -1,
			'is_digital'        => ! empty( $data['is_digital'] ) ? 1 : 0,
			'download_url'      => isset( $data['download_url'] ) ? esc_url_raw( $data['download_url'] ) : '',
			'download_file_id'  => max( 0, (int) ( $data['download_file_id'] ?? 0 ) ),
			'license_prefix'    => isset( $data['license_prefix'] ) ? sanitize_text_field( $data['license_prefix'] ) : '',
			'license_max_sites' => max( 1, min( 99, (int) ( $data['license_max_sites'] ?? 1 ) ) ),
			'featured_image_id' => max( 0, (int) ( $data['featured_image_id'] ?? 0 ) ),
			'created_at'        => $now,
			'updated_at'        => $now,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
	);

	$id = (int) $wpdb->insert_id;
	return $id > 0 ? $id : new WP_Error( 'blackbean_product_insert', __( 'Could not create product.', 'blackbean' ) );
}

/**
 * @param int                  $product_id Product ID.
 * @param array<string, mixed> $data       Fields to update.
 */
function blackbean_product_update( int $product_id, array $data ) : bool {
	global $wpdb;

	if ( $product_id <= 0 || empty( $data ) ) {
		return false;
	}

	if ( isset( $data['slug'] ) ) {
		$data['slug'] = blackbean_product_unique_slug( sanitize_title( (string) $data['slug'] ), $product_id );
	}

	$data['updated_at'] = current_time( 'mysql', true );

	$formats = array();
	foreach ( array_keys( $data ) as $key ) {
		if ( in_array( $key, array( 'price' ), true ) ) {
			$formats[] = '%f';
		} elseif ( in_array( $key, array( 'stock', 'is_digital', 'download_file_id', 'license_max_sites', 'featured_image_id' ), true ) ) {
			$formats[] = '%d';
		} else {
			$formats[] = '%s';
		}
	}

	return false !== $wpdb->update( blackbean_table_products(), $data, array( 'id' => $product_id ), $formats, array( '%d' ) );
}

/**
 * @param string $slug      Desired slug.
 * @param int    $exclude_id Product ID to exclude.
 */
function blackbean_product_unique_slug( string $slug, int $exclude_id = 0 ) : string {
	global $wpdb;

	$base = $slug ?: 'product';
	$try  = $base;
	$i    = 2;
	$table = blackbean_table_products();

	while ( true ) {
		if ( $exclude_id > 0 ) {
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$try,
					$exclude_id
				)
			);
		} else {
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE slug = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$try
				)
			);
		}
		if ( ! $exists ) {
			return $try;
		}
		$try = $base . '-' . $i;
		++$i;
	}
}

/**
 * @param array{
 *   status?:string,
 *   search?:string,
 *   limit?:int,
 *   offset?:int,
 *   orderby?:string,
 *   order?:string
 * } $args Query args.
 * @return list<array<string, mixed>>
 */
function blackbean_products_query( array $args = array() ) : array {
	global $wpdb;

	$table  = blackbean_table_products();
	$where  = array( '1=1' );
	$params = array();

	if ( ! empty( $args['status'] ) ) {
		$where[]  = 'status = %s';
		$params[] = sanitize_key( (string) $args['status'] );
	}

	if ( ! empty( $args['search'] ) ) {
		$like     = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
		$where[]  = '(title LIKE %s OR sku LIKE %s OR content LIKE %s)';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	$orderby = 'updated_at';
	if ( ! empty( $args['orderby'] ) && in_array( $args['orderby'], array( 'updated_at', 'created_at', 'title', 'price' ), true ) ) {
		$orderby = $args['orderby'];
	}
	$order = ( ! empty( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ) ? 'ASC' : 'DESC';

	$limit  = max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) );
	$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );

	$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$params[] = $limit;
	$params[] = $offset;

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

	return is_array( $rows ) ? $rows : array();
}

/**
 * @param string $status Post-like status or 'all'.
 * @return array<string, int>
 */
function blackbean_product_status_counts( string $status = 'all' ) : array {
	global $wpdb;

	$table = blackbean_table_products();
	$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$counts = array(
		'all'     => 0,
		'publish' => 0,
		'draft'   => 0,
		'pending' => 0,
		'private' => 0,
	);

	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			$key = (string) ( $row['status'] ?? '' );
			$cnt = (int) ( $row['cnt'] ?? 0 );
			$counts['all'] += $cnt;
			if ( isset( $counts[ $key ] ) ) {
				$counts[ $key ] = $cnt;
			}
		}
	}

	return $counts;
}

/**
 * @param int $product_id Product ID.
 */
function blackbean_product_delete( int $product_id ) : bool {
	global $wpdb;
	if ( $product_id <= 0 ) {
		return false;
	}
	return false !== $wpdb->delete( blackbean_table_products(), array( 'id' => $product_id ), array( '%d' ) );
}
