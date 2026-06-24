<?php
/**
 * One-time migration from bb_product / bb_order CPTs to Black Bean tables.
 *
 * @package Blackbean_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLACKBEAN_SHOP_MIGRATE_CPT_OPT = 'blackbean_shop_cpt_migrated_v1';

/**
 * Run CPT → Black Bean table migration once (products and orders).
 */
function blackbean_shop_maybe_migrate_cpt_to_tables(): void {
	if ( get_option( BLACKBEAN_SHOP_MIGRATE_CPT_OPT ) ) {
		return;
	}

	if ( BLACKBEAN_SCHEMA_VERSION !== get_option( BLACKBEAN_SCHEMA_VERSION_OPT ) ) {
		return;
	}

	blackbean_migrate_products_from_cpt();
	blackbean_migrate_orders_from_cpt();

	update_option( BLACKBEAN_SHOP_MIGRATE_CPT_OPT, 1, false );
}

/**
 * Migrate bb_product posts.
 */
if ( ! function_exists( 'blackbean_migrate_products_from_cpt' ) ) {
function blackbean_migrate_products_from_cpt(): void {
	global $wpdb;

	$table = blackbean_table_products();
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $count > 0 ) {
		return;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'bb_product',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	foreach ( $posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}

		$thumb_id = (int) get_post_thumbnail_id( $post->ID );
		$stock    = get_post_meta( $post->ID, '_bb_stock', true );
		if ( '' === $stock || false === $stock ) {
			$stock = -1;
		}

		$wpdb->insert(
			$table,
			array(
				'id'                => $post->ID,
				'slug'              => $post->post_name ?: sanitize_title( $post->post_title ),
				'title'             => $post->post_title,
				'content'           => $post->post_content,
				'excerpt'           => $post->post_excerpt,
				'status'            => $post->post_status,
				'price'             => max( 0, (float) get_post_meta( $post->ID, '_bb_price', true ) ),
				'sku'               => (string) get_post_meta( $post->ID, '_bb_sku', true ),
				'stock'             => (int) $stock,
				'is_digital'        => '0' === (string) get_post_meta( $post->ID, BLACKBEAN_SHOP_META_IS_DIGITAL, true ) ? 0 : 1,
				'download_url'      => (string) get_post_meta( $post->ID, BLACKBEAN_SHOP_META_DOWNLOAD_URL, true ),
				'download_file_id'  => (int) get_post_meta( $post->ID, BLACKBEAN_SHOP_META_DOWNLOAD_FILE, true ),
				'license_prefix'    => (string) get_post_meta( $post->ID, BLACKBEAN_SHOP_META_LICENSE_PREFIX, true ),
				'license_max_sites' => max( 1, min( 99, (int) get_post_meta( $post->ID, BLACKBEAN_SHOP_META_LICENSE_MAX_SITES, true ) ?: 1 ) ),
				'featured_image_id' => $thumb_id,
				'created_at'        => $post->post_date_gmt && '0000-00-00 00:00:00' !== $post->post_date_gmt ? get_gmt_from_date( $post->post_date ) : current_time( 'mysql', true ),
				'updated_at'        => $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt ? get_gmt_from_date( $post->post_modified ) : current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
		);
	}
}
}

/**
 * Migrate bb_order posts.
 */
if ( ! function_exists( 'blackbean_migrate_orders_from_cpt' ) ) {
function blackbean_migrate_orders_from_cpt(): void {
	global $wpdb;

	$table = blackbean_table_orders();
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $count > 0 ) {
		return;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'bb_order',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	foreach ( $posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}

		$items_raw = get_post_meta( $post->ID, '_bb_order_items', true );
		if ( ! is_string( $items_raw ) ) {
			$items_raw = '[]';
		}

		$fulfillment = get_post_meta( $post->ID, BLACKBEAN_SHOP_META_FULFILLMENT, true );
		if ( ! is_string( $fulfillment ) ) {
			$fulfillment = is_array( $fulfillment ) ? wp_json_encode( $fulfillment ) : '[]';
		}

		$wpdb->insert(
			$table,
			array(
				'id'               => $post->ID,
				'title'            => $post->post_title,
				'order_status'     => (string) ( get_post_meta( $post->ID, '_bb_order_status', true ) ?: 'pending' ),
				'payment_status'   => (string) get_post_meta( $post->ID, BLACKBEAN_SHOP_META_PAYMENT_STATUS, true ),
				'order_total'      => (float) get_post_meta( $post->ID, '_bb_order_total', true ),
				'items_json'       => $items_raw,
				'customer_name'    => (string) get_post_meta( $post->ID, '_bb_customer_name', true ),
				'customer_email'   => (string) get_post_meta( $post->ID, '_bb_customer_email', true ),
				'customer_phone'   => (string) get_post_meta( $post->ID, '_bb_customer_phone', true ),
				'customer_address' => (string) get_post_meta( $post->ID, '_bb_customer_address', true ),
				'customer_notes'   => (string) get_post_meta( $post->ID, '_bb_customer_notes', true ),
				'customer_user_id' => (int) get_post_meta( $post->ID, '_bb_customer_user_id', true ),
				'cart_session'     => (string) get_post_meta( $post->ID, BLACKBEAN_SHOP_META_CART_SESSION, true ),
				'fulfilled'          => get_post_meta( $post->ID, '_bb_fulfilled', true ) ? 1 : 0,
				'fulfillment_json'   => $fulfillment,
				'paypal_order_id'    => (string) get_post_meta( $post->ID, BLACKBEAN_SHOP_META_PAYPAL_ORDER, true ),
				'created_at'         => $post->post_date_gmt && '0000-00-00 00:00:00' !== $post->post_date_gmt ? get_gmt_from_date( $post->post_date ) : current_time( 'mysql', true ),
				'updated_at'         => $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt ? get_gmt_from_date( $post->post_modified ) : current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}
}
}

add_action( 'init', 'blackbean_shop_maybe_migrate_cpt_to_tables', 25 );
