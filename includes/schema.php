<?php
/**
 * Black Bean table schema for shop (products, orders).
 *
 * Docs tables are managed by Black Bean Tables + Black Bean Docs plugins.
 *
 * @package Blackbean_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLACKBEAN_SCHEMA_VERSION     = '2.1.0';
const BLACKBEAN_SCHEMA_VERSION_OPT = 'blackbean_schema_version';

/**
 * Table name helpers (shop only until blackbean-shop plugin).
 */
function blackbean_table_products() : string {
	global $wpdb;

	return $wpdb->prefix . 'bb_products';
}

function blackbean_table_orders() : string {
	global $wpdb;

	return $wpdb->prefix . 'bb_orders';
}

/**
 * Create or upgrade shop Black Bean tables (legacy fallback when Tables plugin is inactive).
 */
function blackbean_schema_install() : void {
	if ( class_exists( 'BB_CT_Schema_Installer', false ) ) {
		return;
	}

	global $wpdb;

	if ( BLACKBEAN_SCHEMA_VERSION === get_option( BLACKBEAN_SCHEMA_VERSION_OPT ) ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset  = $wpdb->get_charset_collate();
	$products = blackbean_table_products();
	$orders   = blackbean_table_orders();

	dbDelta(
		"CREATE TABLE {$products} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		slug varchar(200) NOT NULL DEFAULT '',
		title varchar(255) NOT NULL DEFAULT '',
		content longtext NOT NULL,
		excerpt text NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'publish',
		price decimal(12,2) NOT NULL DEFAULT 0.00,
		sku varchar(64) NOT NULL DEFAULT '',
		stock int NOT NULL DEFAULT -1,
		is_digital tinyint(1) NOT NULL DEFAULT 1,
		download_url varchar(500) NOT NULL DEFAULT '',
		download_file_id bigint(20) unsigned NOT NULL DEFAULT 0,
		license_prefix varchar(32) NOT NULL DEFAULT '',
		license_max_sites smallint(5) unsigned NOT NULL DEFAULT 1,
		featured_image_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		UNIQUE KEY slug (slug),
		KEY status (status)
	) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$orders} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		title varchar(255) NOT NULL DEFAULT '',
		order_status varchar(32) NOT NULL DEFAULT 'pending',
		payment_status varchar(32) NOT NULL DEFAULT '',
		order_total decimal(12,2) NOT NULL DEFAULT 0.00,
		items_json longtext NOT NULL,
		customer_name varchar(190) NOT NULL DEFAULT '',
		customer_email varchar(190) NOT NULL DEFAULT '',
		customer_phone varchar(64) NOT NULL DEFAULT '',
		customer_address text NOT NULL,
		customer_notes text NOT NULL,
		customer_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		cart_session varchar(32) NOT NULL DEFAULT '',
		fulfilled tinyint(1) NOT NULL DEFAULT 0,
		fulfillment_json longtext NOT NULL,
		paypal_order_id varchar(64) NOT NULL DEFAULT '',
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY order_status (order_status),
		KEY payment_status (payment_status),
		KEY customer_email (customer_email)
	) {$charset};"
	);

	update_option( BLACKBEAN_SCHEMA_VERSION_OPT, BLACKBEAN_SCHEMA_VERSION, false );
}
add_action( 'init', 'blackbean_schema_install', 4 );
