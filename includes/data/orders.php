<?php
/**
 * Order data layer (Black Bean table).
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, string>
 */
function blackbean_shop_order_statuses() : array {
	return array(
		'pending'    => __( 'Pending', 'blackbean' ),
		'processing' => __( 'Processing', 'blackbean' ),
		'completed'  => __( 'Completed', 'blackbean' ),
		'cancelled'  => __( 'Cancelled', 'blackbean' ),
	);
}

/**
 * Whether the orders Black Bean table exists.
 */
function blackbean_orders_table_ready() : bool {
	global $wpdb;

	$table = blackbean_table_orders();
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

	return is_string( $found ) && $found === $table;
}

/**
 * @return array<string, string>
 */
function blackbean_order_meta_map() : array {
	return array(
		'_bb_order_status'               => 'order_status',
		BLACKBEAN_SHOP_META_PAYMENT_STATUS => 'payment_status',
		'_bb_order_total'                => 'order_total',
		'_bb_order_items'                => 'items_json',
		'_bb_customer_name'              => 'customer_name',
		'_bb_customer_email'             => 'customer_email',
		'_bb_customer_phone'             => 'customer_phone',
		'_bb_customer_address'           => 'customer_address',
		'_bb_customer_notes'             => 'customer_notes',
		'_bb_customer_user_id'           => 'customer_user_id',
		BLACKBEAN_SHOP_META_CART_SESSION => 'cart_session',
		'_bb_fulfilled'                  => 'fulfilled',
		BLACKBEAN_SHOP_META_FULFILLMENT  => 'fulfillment_json',
		BLACKBEAN_SHOP_META_PAYPAL_ORDER => 'paypal_order_id',
	);
}

/**
 * Normalize stored line items for admin display.
 *
 * @param list<array<string, mixed>> $items Raw items.
 * @return list<array<string, mixed>>
 */
function blackbean_order_normalize_items( array $items ) : array {
	$normalized = array();

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$product_id = (int) ( $item['id'] ?? $item['product_id'] ?? 0 );
		$qty        = max( 1, (int) ( $item['qty'] ?? 1 ) );
		$price      = (float) ( $item['price'] ?? 0 );
		$line_total = isset( $item['line_total'] ) ? (float) $item['line_total'] : ( $price * $qty );
		$title      = (string) ( $item['title'] ?? '' );

		if ( '' === $title && $product_id > 0 ) {
			$row = blackbean_product_get_row( $product_id );
			if ( $row ) {
				$title = (string) $row['title'];
			}
		}

		$url = (string) ( $item['url'] ?? '' );
		if ( '' === $url && $product_id > 0 ) {
			$row = isset( $row ) && is_array( $row ) ? $row : blackbean_product_get_row( $product_id );
			if ( $row && ! empty( $row['slug'] ) ) {
				$url = blackbean_product_permalink( (string) $row['slug'] );
			}
		}

		$normalized[] = array_merge(
			$item,
			array(
				'id'           => $product_id,
				'product_id'   => $product_id,
				'title'        => $title,
				'qty'          => $qty,
				'price'        => $price,
				'line_total'   => $line_total,
				'price_label'  => (string) ( $item['price_label'] ?? blackbean_shop_format_price( $price ) ),
				'line_label'   => (string) ( $item['line_label'] ?? blackbean_shop_format_price( $line_total ) ),
				'url'          => $url,
			)
		);
	}

	return $normalized;
}

/**
 * @param int    $order_id Order ID.
 * @return array<string, mixed>|null
 */
function blackbean_order_get_row( int $order_id ) : ?array {
	global $wpdb;

	if ( $order_id <= 0 ) {
		return null;
	}

	$table = blackbean_table_orders();
	$row   = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $order_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);

	return is_array( $row ) ? $row : null;
}

/**
 * CPT-compatible meta read.
 *
 * @param int    $order_id Order ID.
 * @param string $meta_key Meta key.
 * @return mixed
 */
function blackbean_order_get_meta( int $order_id, string $meta_key ) {
	$row = blackbean_order_get_row( $order_id );
	if ( ! $row ) {
		return '';
	}

	$map = blackbean_order_meta_map();
	if ( ! isset( $map[ $meta_key ] ) ) {
		return '';
	}

	$col = $map[ $meta_key ];
	if ( 'fulfilled' === $col ) {
		return ! empty( $row[ $col ] ) ? '1' : '';
	}

	return $row[ $col ] ?? '';
}

/**
 * @param int    $order_id Order ID.
 * @param string $meta_key Meta key.
 * @param mixed  $value    Value.
 */
function blackbean_order_update_meta( int $order_id, string $meta_key, $value ) : void {
	$map = blackbean_order_meta_map();
	if ( ! isset( $map[ $meta_key ] ) ) {
		return;
	}

	$col  = $map[ $meta_key ];
	$data = array();

	switch ( $col ) {
		case 'order_status':
			$data['order_status'] = sanitize_key( (string) $value );
			break;
		case 'payment_status':
			$data['payment_status'] = sanitize_key( (string) $value );
			break;
		case 'order_total':
			$data['order_total'] = (float) $value;
			break;
		case 'items_json':
			$data['items_json'] = is_string( $value ) ? $value : wp_json_encode( $value );
			break;
		case 'customer_name':
			$data['customer_name'] = sanitize_text_field( (string) $value );
			break;
		case 'customer_email':
			$data['customer_email'] = sanitize_email( (string) $value );
			break;
		case 'customer_phone':
			$data['customer_phone'] = sanitize_text_field( (string) $value );
			break;
		case 'customer_address':
			$data['customer_address'] = sanitize_textarea_field( (string) $value );
			break;
		case 'customer_notes':
			$data['customer_notes'] = sanitize_textarea_field( (string) $value );
			break;
		case 'customer_user_id':
			$data['customer_user_id'] = max( 0, (int) $value );
			break;
		case 'cart_session':
			$data['cart_session'] = sanitize_text_field( (string) $value );
			break;
		case 'fulfilled':
			$data['fulfilled'] = $value ? 1 : 0;
			break;
		case 'fulfillment_json':
			$data['fulfillment_json'] = is_string( $value ) ? $value : wp_json_encode( $value );
			break;
		case 'paypal_order_id':
			$data['paypal_order_id'] = sanitize_text_field( (string) $value );
			break;
	}

	if ( ! empty( $data ) ) {
		$data['updated_at'] = current_time( 'mysql', true );
		blackbean_order_update( $order_id, $data );
	}
}

/**
 * @param array<string, mixed> $customer Customer fields.
 * @param array<string, mixed> $cart     Hydrated cart.
 * @param array<string, bool>  $args     defer_fulfillment.
 * @return int|WP_Error Order ID.
 */
function blackbean_order_insert( array $customer, array $cart, array $args = array() ) {
	global $wpdb;

	$defer = ! empty( $args['defer_fulfillment'] );
	$name  = isset( $customer['name'] ) ? sanitize_text_field( $customer['name'] ) : '';
	$email = isset( $customer['email'] ) ? sanitize_email( $customer['email'] ) : '';

	if ( '' === $name || '' === $email || ! is_email( $email ) ) {
		return new WP_Error( 'blackbean_shop_invalid_customer', __( 'Name and a valid email are required.', 'blackbean' ) );
	}

	$now   = current_time( 'mysql', true );
	$title = sprintf(
		/* translators: %s: customer name */
		__( 'Order — %s', 'blackbean' ),
		$name
	);

	$wpdb->insert(
		blackbean_table_orders(),
		array(
			'title'            => $title,
			'order_status'     => $defer ? 'pending' : 'completed',
			'payment_status'   => $defer ? 'pending' : 'paid',
			'order_total'      => (float) ( $cart['subtotal'] ?? 0 ),
			'items_json'       => wp_json_encode( $cart['items'] ?? array() ),
			'customer_name'    => $name,
			'customer_email'   => $email,
			'customer_phone'   => isset( $customer['phone'] ) ? sanitize_text_field( $customer['phone'] ) : '',
			'customer_address' => isset( $customer['address'] ) ? sanitize_textarea_field( $customer['address'] ) : '',
			'customer_notes'   => isset( $customer['notes'] ) ? sanitize_textarea_field( $customer['notes'] ) : '',
			'customer_user_id' => is_user_logged_in() ? get_current_user_id() : 0,
			'cart_session'     => blackbean_shop_cart_session_id(),
			'fulfilled'        => 0,
			'fulfillment_json' => '[]',
			'paypal_order_id'  => '',
			'created_at'       => $now,
			'updated_at'       => $now,
		),
		array( '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
	);

	$order_id = (int) $wpdb->insert_id;
	if ( $order_id <= 0 ) {
		return new WP_Error( 'blackbean_order_insert', __( 'Could not create order.', 'blackbean' ) );
	}

	return $order_id;
}

/**
 * @param int                  $order_id Order ID.
 * @param array<string, mixed> $data     Fields.
 */
function blackbean_order_update( int $order_id, array $data ) : bool {
	global $wpdb;

	if ( $order_id <= 0 || empty( $data ) ) {
		return false;
	}

	$data['updated_at'] = current_time( 'mysql', true );

	$formats = array();
	foreach ( array_keys( $data ) as $key ) {
		if ( 'order_total' === $key ) {
			$formats[] = '%f';
		} elseif ( in_array( $key, array( 'customer_user_id', 'fulfilled' ), true ) ) {
			$formats[] = '%d';
		} else {
			$formats[] = '%s';
		}
	}

	return false !== $wpdb->update( blackbean_table_orders(), $data, array( 'id' => $order_id ), $formats, array( '%d' ) );
}

/**
 * @param array{
 *   status?:string,
 *   payment_status?:string,
 *   search?:string,
 *   limit?:int,
 *   offset?:int
 * } $args Query args.
 * @return list<array<string, mixed>>
 */
function blackbean_orders_query( array $args = array() ) : array {
	global $wpdb;

	if ( ! blackbean_orders_table_ready() ) {
		return array();
	}

	$table  = blackbean_table_orders();
	$where  = array( '1=1' );
	$params = array();

	if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
		$where[]  = 'order_status = %s';
		$params[] = sanitize_key( (string) $args['status'] );
	}

	if ( ! empty( $args['payment_status'] ) ) {
		$where[]  = 'payment_status = %s';
		$params[] = sanitize_key( (string) $args['payment_status'] );
	}

	if ( ! empty( $args['search'] ) ) {
		$like     = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
		$where[]  = '(customer_name LIKE %s OR customer_email LIKE %s OR title LIKE %s)';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	$limit  = max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) );
	$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );

	$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$params[] = $limit;
	$params[] = $offset;

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

	return is_array( $rows ) ? $rows : array();
}

/**
 * @return array<string, int>
 */
function blackbean_order_status_counts_from_table() : array {
	global $wpdb;

	$counts = array( 'all' => 0 );
	foreach ( array_keys( blackbean_shop_order_statuses() ) as $key ) {
		$counts[ $key ] = 0;
	}

	if ( ! blackbean_orders_table_ready() ) {
		return $counts;
	}

	$table = blackbean_table_orders();
	$rows  = $wpdb->get_results( "SELECT order_status, COUNT(*) AS cnt FROM {$table} GROUP BY order_status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			$key = (string) ( $row['order_status'] ?? '' );
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
 * Build admin/API order array from row.
 *
 * @param array<string, mixed> $row Order row.
 * @return array<string, mixed>|null
 */
function blackbean_order_format( array $row ) : ?array {
	$status = (string) ( $row['order_status'] ?? 'pending' );
	if ( ! array_key_exists( $status, blackbean_shop_order_statuses() ) ) {
		$status = 'pending';
	}

	$items = json_decode( (string) ( $row['items_json'] ?? '[]' ), true );
	if ( ! is_array( $items ) ) {
		$items = array();
	}

	$total          = (float) ( $row['order_total'] ?? 0 );
	$payment_status = (string) ( $row['payment_status'] ?? '' );
	$items          = blackbean_order_normalize_items( $items );

	return array(
		'id'             => (int) $row['id'],
		'title'          => (string) ( $row['title'] ?? '' ),
		'date'           => (string) ( $row['created_at'] ?? '' ),
		'status'         => $status,
		'status_label'   => blackbean_shop_order_statuses()[ $status ] ?? $status,
		'payment_status' => $payment_status,
		'payment_label'  => ucfirst( $payment_status ?: 'pending' ),
		'total'          => $total,
		'total_label'    => blackbean_shop_format_price( $total ),
		'items'          => $items,
		'customer'       => array(
			'name'    => (string) ( $row['customer_name'] ?? '' ),
			'email'   => (string) ( $row['customer_email'] ?? '' ),
			'phone'   => (string) ( $row['customer_phone'] ?? '' ),
			'address' => (string) ( $row['customer_address'] ?? '' ),
			'notes'   => (string) ( $row['customer_notes'] ?? '' ),
			'user_id' => (int) ( $row['customer_user_id'] ?? 0 ),
		),
		'created_at'     => (string) ( $row['created_at'] ?? '' ),
		'edit_url'       => admin_url( 'admin.php?page=blackbean-order-manager&order_id=' . (int) $row['id'] ),
	);
}
