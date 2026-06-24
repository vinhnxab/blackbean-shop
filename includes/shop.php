<?php
/**
 * Blackbean Shop (no WooCommerce): products, session cart, orders.
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLACKBEAN_SHOP_CART_COOKIE      = 'blackbean_cart';
const BLACKBEAN_SHOP_REWRITE_OPT      = 'blackbean_shop_rewrite_version';
const BLACKBEAN_SHOP_META_CART_SESSION = '_bb_cart_session';

const BLACKBEAN_SHOP_META_BILLING_NAME    = '_bb_billing_name';
const BLACKBEAN_SHOP_META_BILLING_PHONE   = '_bb_billing_phone';
const BLACKBEAN_SHOP_META_BILLING_ADDRESS = '_bb_billing_address';

/**
 * @return string
 */
function blackbean_shop_currency_code() : string {
	return (string) apply_filters( 'blackbean_shop_currency', 'USD' );
}

/**
 * @return string
 */
function blackbean_shop_currency_symbol() : string {
	$map = array(
		'USD' => '$',
		'VND' => '₫',
		'EUR' => '€',
		'GBP' => '£',
	);
	$code = blackbean_shop_currency_code();
	return $map[ $code ] ?? $code . ' ';
}

/**
 * @param float $amount Amount.
 * @return string
 */
function blackbean_shop_format_price( float $amount ) : string {
	$symbol = blackbean_shop_currency_symbol();
	$code   = blackbean_shop_currency_code();
	if ( 'VND' === $code ) {
		return $symbol . number_format_i18n( $amount, 0 );
	}
	return $symbol . number_format_i18n( $amount, 2 );
}

/**
 * Shop uses Black Bean tables (see inc/schema.php). CPT registration removed in v2.
 */

/**
 * @param int|array<string,mixed> $product Product ID or row.
 * @return array<string,mixed>|null
 */
function blackbean_shop_get_product( $product ) : ?array {
	if ( is_array( $product ) ) {
		return blackbean_product_format_public( $product );
	}

	$id = is_numeric( $product ) ? (int) $product : 0;
	if ( $id <= 0 && $product instanceof WP_Post ) {
		$id = (int) $product->ID;
	}

	$row = blackbean_product_get_row( $id );
	if ( ! $row || 'publish' !== ( $row['status'] ?? '' ) ) {
		return null;
	}

	return blackbean_product_format_public( $row );
}

/**
 * 32-character hex cart session ID (cookie-safe).
 */
function blackbean_shop_generate_session_id() : string {
	return bin2hex( random_bytes( 16 ) );
}

/**
 * Persist cart session cookie.
 *
 * @param string $id Session ID.
 */
function blackbean_shop_set_cart_cookie( string $id ) : void {
	if ( headers_sent() || ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
		return;
	}
	setcookie(
		BLACKBEAN_SHOP_CART_COOKIE,
		$id,
		array(
			'expires'  => time() + DAY_IN_SECONDS * 14,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => false,
			'samesite' => 'Lax',
		)
	);
	// Available for the remainder of this request (e.g. REST add-to-cart then cart read).
	$_COOKIE[ BLACKBEAN_SHOP_CART_COOKIE ] = $id;
}

/**
 * @return string Cart session key.
 */
function blackbean_shop_cart_session_id() : string {
	if ( isset( $_COOKIE[ BLACKBEAN_SHOP_CART_COOKIE ] ) ) {
		$id = strtolower( sanitize_text_field( wp_unslash( (string) $_COOKIE[ BLACKBEAN_SHOP_CART_COOKIE ] ) ) );
		if ( preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
			return $id;
		}
	}
	$id = blackbean_shop_generate_session_id();
	blackbean_shop_set_cart_cookie( $id );
	return $id;
}

/**
 * @return string
 */
function blackbean_shop_cart_transient_key() : string {
	return 'blackbean_cart_' . blackbean_shop_cart_session_id();
}

/**
 * @return array{items:list<array{product_id:int,qty:int}>,updated:int}
 */
function blackbean_shop_cart_get_raw() : array {
	$data = get_transient( blackbean_shop_cart_transient_key() );
	if ( ! is_array( $data ) || ! isset( $data['items'] ) || ! is_array( $data['items'] ) ) {
		return array(
			'items'   => array(),
			'updated' => time(),
		);
	}
	return $data;
}

/**
 * @param array{items:list<array{product_id:int,qty:int}>,updated:int} $cart Cart.
 */
function blackbean_shop_cart_save_raw( array $cart ) : void {
	$cart['updated'] = time();
	set_transient( blackbean_shop_cart_transient_key(), $cart, DAY_IN_SECONDS * 14 );
}

/**
 * Hydrated cart for API/templates.
 *
 * @return array{items:list<array<string,mixed>>,subtotal:float,subtotal_label:string,count:int}
 */
function blackbean_shop_cart_get() : array {
	$raw   = blackbean_shop_cart_get_raw();
	$items = array();
	$total = 0.0;
	$count = 0;

	foreach ( $raw['items'] as $row ) {
		$product_id = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
		$qty        = isset( $row['qty'] ) ? max( 1, (int) $row['qty'] ) : 1;
		$product    = blackbean_shop_get_product( $product_id );
		if ( ! $product || ! $product['in_stock'] ) {
			continue;
		}
		if ( $product['stock'] >= 0 && $qty > $product['stock'] ) {
			$qty = $product['stock'];
		}
		if ( $qty < 1 ) {
			continue;
		}
		$line = $product['price'] * $qty;
		$items[] = array_merge(
			$product,
			array(
				'qty'        => $qty,
				'line_total' => $line,
				'line_label' => blackbean_shop_format_price( $line ),
			)
		);
		$total += $line;
		$count += $qty;
	}

	return array(
		'items'          => $items,
		'subtotal'       => $total,
		'subtotal_label' => blackbean_shop_format_price( $total ),
		'count'          => $count,
	);
}

/**
 * @param int $product_id Product ID.
 * @param int $qty        Quantity.
 * @return true|WP_Error
 */
function blackbean_shop_cart_add( int $product_id, int $qty = 1 ) {
	$product = blackbean_shop_get_product( $product_id );
	if ( ! $product ) {
		return new WP_Error( 'blackbean_shop_invalid_product', __( 'Product not found.', 'blackbean' ) );
	}
	if ( ! $product['in_stock'] ) {
		return new WP_Error( 'blackbean_shop_out_of_stock', __( 'This product is out of stock.', 'blackbean' ) );
	}
	$qty = max( 1, $qty );
	$raw = blackbean_shop_cart_get_raw();
	$found = false;
	foreach ( $raw['items'] as &$row ) {
		if ( (int) $row['product_id'] === $product_id ) {
			$row['qty'] = (int) $row['qty'] + $qty;
			if ( $product['stock'] >= 0 && $row['qty'] > $product['stock'] ) {
				$row['qty'] = $product['stock'];
			}
			$found = true;
			break;
		}
	}
	unset( $row );
	if ( ! $found ) {
		if ( $product['stock'] >= 0 && $qty > $product['stock'] ) {
			$qty = $product['stock'];
		}
		$raw['items'][] = array(
			'product_id' => $product_id,
			'qty'        => $qty,
		);
	}
	blackbean_shop_cart_save_raw( $raw );
	return true;
}

/**
 * @param int $product_id Product ID.
 * @param int $qty        Quantity (0 removes).
 * @return true|WP_Error
 */
function blackbean_shop_cart_set_qty( int $product_id, int $qty ) {
	if ( $qty <= 0 ) {
		return blackbean_shop_cart_remove( $product_id );
	}
	$product = blackbean_shop_get_product( $product_id );
	if ( ! $product ) {
		return new WP_Error( 'blackbean_shop_invalid_product', __( 'Product not found.', 'blackbean' ) );
	}
	$raw = blackbean_shop_cart_get_raw();
	$found = false;
	foreach ( $raw['items'] as &$row ) {
		if ( (int) $row['product_id'] === $product_id ) {
			$row['qty'] = $product['stock'] >= 0 ? min( $qty, $product['stock'] ) : $qty;
			$found = true;
			break;
		}
	}
	unset( $row );
	if ( ! $found ) {
		return blackbean_shop_cart_add( $product_id, $qty );
	}
	blackbean_shop_cart_save_raw( $raw );
	return true;
}

/**
 * @param int $product_id Product ID.
 * @return true
 */
function blackbean_shop_cart_remove( int $product_id ) : bool {
	$raw = blackbean_shop_cart_get_raw();
	$raw['items'] = array_values(
		array_filter(
			$raw['items'],
			static function ( $row ) use ( $product_id ) {
				return (int) $row['product_id'] !== $product_id;
			}
		)
	);
	blackbean_shop_cart_save_raw( $raw );
	return true;
}

/**
 * @param string|null $session_id 32-char hex session (optional).
 */
function blackbean_shop_cart_clear_for_session( ?string $session_id = null ) : void {
	if ( is_string( $session_id ) && preg_match( '/^[a-f0-9]{32}$/', $session_id ) ) {
		delete_transient( 'blackbean_cart_' . $session_id );
	}
	delete_transient( blackbean_shop_cart_transient_key() );
}

function blackbean_shop_cart_clear() : void {
	blackbean_shop_cart_clear_for_session( null );
}

/**
 * Clear the cart session tied to an order (and the current browser session).
 *
 * @param int $order_id Order post ID.
 */
function blackbean_shop_cart_clear_for_order( int $order_id ) : void {
	$session = (string) blackbean_order_get_meta( $order_id, BLACKBEAN_SHOP_META_CART_SESSION );
	if ( '' !== $session ) {
		blackbean_shop_cart_clear_for_session( $session );
		return;
	}
	blackbean_shop_cart_clear();
}

/**
 * Search published products.
 *
 * @param string $query Search string.
 * @param int    $limit Max results.
 * @return list<array<string,mixed>>
 */
function blackbean_shop_search_products( string $query, int $limit = 6 ) : array {
	$rows = blackbean_products_query(
		array(
			'status' => 'publish',
			'search' => $query,
			'limit'  => max( 1, min( 12, $limit ) ),
		)
	);
	$list = array();
	foreach ( $rows as $row ) {
		$p = blackbean_shop_get_product( $row );
		if ( $p ) {
			$list[] = $p;
		}
	}
	return $list;
}

/**
 * Published products (newest first).
 *
 * @param int $limit Max items.
 * @return list<array<string,mixed>>
 */
function blackbean_shop_list_products( int $limit = 8 ) : array {
	$rows = blackbean_products_query(
		array(
			'status'  => 'publish',
			'limit'   => max( 1, min( 20, $limit ) ),
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
	return $list;
}

/**
 * Default checkout fields for the current visitor.
 *
 * @return array{name:string,email:string,phone:string,address:string,notes:string}
 */
function blackbean_shop_get_checkout_customer() : array {
	$customer = array(
		'name'    => '',
		'email'   => '',
		'phone'   => '',
		'address' => '',
		'notes'   => '',
	);

	if ( ! is_user_logged_in() ) {
		return $customer;
	}

	$user = wp_get_current_user();
	if ( ! $user->exists() ) {
		return $customer;
	}

	$saved_name = trim( (string) get_user_meta( $user->ID, BLACKBEAN_SHOP_META_BILLING_NAME, true ) );
	$name       = '' !== $saved_name ? $saved_name : trim( $user->display_name );
	if ( '' === $name ) {
		$name = trim( $user->first_name . ' ' . $user->last_name );
	}
	if ( '' === $name ) {
		$name = $user->user_login;
	}

	$customer['name']    = $name;
	$customer['email']   = $user->user_email;
	$customer['phone']   = (string) get_user_meta( $user->ID, BLACKBEAN_SHOP_META_BILLING_PHONE, true );
	$customer['address'] = (string) get_user_meta( $user->ID, BLACKBEAN_SHOP_META_BILLING_ADDRESS, true );

	return $customer;
}

/**
 * Save billing details on the user account (logged-in only).
 *
 * @param array<string,string> $customer Customer fields.
 */
function blackbean_shop_save_checkout_customer( array $customer ) : void {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		return;
	}

	$name = isset( $customer['name'] ) ? sanitize_text_field( $customer['name'] ) : '';
	if ( '' !== $name ) {
		update_user_meta( $user_id, BLACKBEAN_SHOP_META_BILLING_NAME, $name );
	}

	$phone = isset( $customer['phone'] ) ? sanitize_text_field( $customer['phone'] ) : '';
	update_user_meta( $user_id, BLACKBEAN_SHOP_META_BILLING_PHONE, $phone );

	$address = isset( $customer['address'] ) ? sanitize_textarea_field( $customer['address'] ) : '';
	update_user_meta( $user_id, BLACKBEAN_SHOP_META_BILLING_ADDRESS, $address );
}

/**
 * Build customer payload from checkout POST (uses account email when logged in).
 *
 * @return array{name:string,email:string,phone:string,address:string,notes:string}
 */
function blackbean_shop_customer_from_post() : array {
	$customer = array(
		'name'    => isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['customer_name'] ) ) : '',
		'email'   => isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['customer_email'] ) ) : '',
		'phone'   => isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['customer_phone'] ) ) : '',
		'address' => isset( $_POST['customer_address'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['customer_address'] ) ) : '',
		'notes'   => isset( $_POST['customer_notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['customer_notes'] ) ) : '',
	);

	if ( is_user_logged_in() ) {
		$user = wp_get_current_user();
		if ( $user->exists() ) {
			$customer['email'] = $user->user_email;
		}
	}

	return $customer;
}

/**
 * @param array<string,string>      $customer Customer fields.
 * @param array<string, bool>       $args     defer_fulfillment: wait for PayPal before stock/email/cart clear.
 * @return int|WP_Error Order ID.
 */
function blackbean_shop_create_order( array $customer, array $args = array() ) {
	$defer = ! empty( $args['defer_fulfillment'] );
	$cart = blackbean_shop_cart_get();
	if ( empty( $cart['items'] ) ) {
		return new WP_Error( 'blackbean_shop_empty_cart', __( 'Your cart is empty.', 'blackbean' ) );
	}

	$name  = isset( $customer['name'] ) ? sanitize_text_field( $customer['name'] ) : '';
	$email = isset( $customer['email'] ) ? sanitize_email( $customer['email'] ) : '';
	$phone = isset( $customer['phone'] ) ? sanitize_text_field( $customer['phone'] ) : '';
	$addr  = isset( $customer['address'] ) ? sanitize_textarea_field( $customer['address'] ) : '';
	$notes = isset( $customer['notes'] ) ? sanitize_textarea_field( $customer['notes'] ) : '';

	if ( '' === $name || '' === $email || ! is_email( $email ) ) {
		return new WP_Error( 'blackbean_shop_invalid_customer', __( 'Name and a valid email are required.', 'blackbean' ) );
	}

	$order_id = blackbean_order_insert( $customer, $cart, $args );

	if ( is_wp_error( $order_id ) ) {
		return $order_id;
	}

	$user_id = get_current_user_id();
	if ( $user_id > 0 ) {
		blackbean_shop_save_checkout_customer( $customer );
	}

	if ( $defer ) {
		$admin_email = get_option( 'admin_email' );
		wp_mail(
			$admin_email,
			sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'New shop order (awaiting payment)', 'blackbean' ) ),
			sprintf(
				"Order #%d\nCustomer: %s <%s>\nTotal: %s\n\nAwaiting PayPal payment.",
				$order_id,
				$name,
				$email,
				$cart['subtotal_label']
			)
		);
		return (int) $order_id;
	}

	blackbean_shop_fulfill_order( (int) $order_id );

	return (int) $order_id;
}

/**
 * Shop page URLs.
 */
function blackbean_shop_products_url() : string {
	return home_url( '/products/' );
}

function blackbean_shop_cart_url() : string {
	return home_url( '/shop/cart/' );
}

function blackbean_shop_checkout_url() : string {
	return home_url( '/shop/checkout/' );
}

/**
 * HTML for header mini-cart panel body.
 *
 * @param array{items:list<array<string,mixed>>,subtotal:float,subtotal_label:string,count:int} $cart Cart.
 * @return string
 */
function blackbean_shop_header_cart_panel_html( array $cart ) : string {
	ob_start();
	if ( empty( $cart['items'] ) ) {
		echo '<p class="bb-header-cart__empty">';
		esc_html_e( 'Your cart is empty.', 'blackbean' );
		echo '</p>';
	} else {
		echo '<ul class="bb-header-cart__list">';
		$shown = 0;
		foreach ( $cart['items'] as $item ) {
			if ( $shown >= 5 ) {
				break;
			}
			++$shown;
			echo '<li class="bb-header-cart__line">';
			echo '<a class="bb-header-cart__line-title" href="' . esc_url( $item['url'] ) . '">';
			echo esc_html( $item['title'] );
			echo '</a>';
			echo '<span class="bb-header-cart__line-meta">';
			echo esc_html( sprintf( '%1$s × %2$d', $item['price_label'], (int) $item['qty'] ) );
			echo '</span>';
			echo '</li>';
		}
		echo '</ul>';
		$extra = count( $cart['items'] ) - $shown;
		if ( $extra > 0 ) {
			echo '<p class="bb-header-cart__more">';
			echo esc_html( sprintf( /* translators: %d: additional item count */ _n( '+%d more item', '+%d more items', $extra, 'blackbean' ), $extra ) );
			echo '</p>';
		}
		echo '<div class="bb-header-cart__footer">';
		echo '<p class="bb-header-cart__subtotal">';
		echo esc_html( sprintf( /* translators: %s: formatted subtotal */ __( 'Subtotal: %s', 'blackbean' ), $cart['subtotal_label'] ) );
		echo '</p>';
		echo '<div class="bb-header-cart__actions">';
		echo '<a class="bb-header-cart__link" href="' . esc_url( blackbean_shop_cart_url() ) . '">';
		esc_html_e( 'View cart', 'blackbean' );
		echo '</a>';
		echo '<a class="bb-header-cart__link bb-header-cart__link--primary" href="' . esc_url( blackbean_shop_checkout_url() ) . '">';
		esc_html_e( 'Checkout', 'blackbean' );
		echo '</a>';
		echo '</div></div>';
	}
	return (string) ob_get_clean();
}

/**
 * Output header cart widget.
 *
 * @param string $variant `site` or `dev`.
 */
function blackbean_shop_render_header_cart( string $variant = 'site' ) : void {
	BB_Shop_Template_Loader::get_part( 'shop/header-cart', array( 'variant' => $variant ) );
}

/**
 * Rewrites for cart and checkout.
 */
function blackbean_shop_register_rewrites() : void {
	add_rewrite_rule( '^shop/cart/?$', 'index.php?blackbean_shop_view=cart', 'top' );
	add_rewrite_rule( '^shop/checkout/?$', 'index.php?blackbean_shop_view=checkout', 'top' );
}
/**
 * @param list<string> $vars Vars.
 * @return list<string>
 */
function blackbean_shop_query_vars( array $vars ) : array {
	$vars[] = 'blackbean_shop_view';
	return $vars;
}

function blackbean_shop_maybe_flush_rewrites() : void {
	if ( get_option( BLACKBEAN_SHOP_REWRITE_OPT ) === BB_SHOP_VERSION ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( BLACKBEAN_SHOP_REWRITE_OPT, BB_SHOP_VERSION, false );
}
add_action( 'init', 'blackbean_shop_maybe_flush_rewrites', 99 );

/**
 * Cart / checkout templates (called from BB_Shop_Frontend_Routing).
 */
function blackbean_shop_template_redirect() : void {
	$view = get_query_var( 'blackbean_shop_view' );
	if ( 'cart' === $view ) {
		blackbean_shop_render_page( 'cart' );
		exit;
	}
	if ( 'checkout' === $view ) {
		blackbean_shop_render_page( 'checkout' );
		exit;
	}
}

/**
 * @param string $view cart|checkout.
 */
function blackbean_shop_render_page( string $view ) : void {
	status_header( 200 );
	get_header();
	echo '<div class="' . esc_attr( blackbean_layout_container_classes( 'py-10' ) ) . '">';
	BB_Shop_Template_Loader::get_part( 'shop/' . $view );
	echo '</div>';
	get_footer();
	exit;
}

/**
 * Handle checkout form POST.
 */
function blackbean_shop_handle_checkout_post() : void {
	if ( ! isset( $_POST['blackbean_shop_checkout_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['blackbean_shop_checkout_nonce'] ) ), 'blackbean_shop_checkout' ) ) {
		wp_die( esc_html__( 'Invalid request.', 'blackbean' ) );
	}
	blackbean_shop_begin_checkout( blackbean_shop_customer_from_post() );
}
add_action( 'template_redirect', 'blackbean_shop_handle_checkout_post', 1 );

/**
 * Shop script config for frontend.
 *
 * @return array<string,mixed>
 */
function blackbean_shop_script_config() : array {
	return array(
		'restUrl'      => rest_url( 'blackbean/v1/shop/' ),
		'nonce'        => wp_create_nonce( 'wp_rest' ),
		'cartUrl'      => blackbean_shop_cart_url(),
		'checkoutUrl'  => blackbean_shop_checkout_url(),
		'strings'      => array(
			'added'       => __( 'Added to cart.', 'blackbean' ),
			'error'       => __( 'Could not update cart.', 'blackbean' ),
			'viewCart'    => __( 'View cart', 'blackbean' ),
			'checkout'    => __( 'Checkout', 'blackbean' ),
			'emptyCart'   => __( 'Your cart is empty.', 'blackbean' ),
			'goToShop'    => __( 'Go to shop', 'blackbean' ),
			'subtotal'    => __( 'Subtotal', 'blackbean' ),
			'subtotalFmt' => __( 'Subtotal: %s', 'blackbean' ),
			'each'        => __( 'each', 'blackbean' ),
			'remove'      => __( 'Remove', 'blackbean' ),
			'decreaseQty' => __( 'Decrease quantity', 'blackbean' ),
			'increaseQty' => __( 'Increase quantity', 'blackbean' ),
			'quantity'    => __( 'Quantity', 'blackbean' ),
			'moreItems'   => __( '+%d more items', 'blackbean' ),
			'moreItem'    => __( '+%d more item', 'blackbean' ),
			'cartLabel'   => __( 'Cart', 'blackbean' ),
			'cartItems'   => __( 'Cart, %d items', 'blackbean' ),
			'cartItem'    => __( 'Cart, %d item', 'blackbean' ),
		),
		'shopUrl'             => blackbean_shop_products_url(),
		'inputClass'          => blackbean_input_class(),
		'viewCartButtonClass' => blackbean_button_class( 'secondary' ),
		'primaryButtonClass'  => blackbean_button_class( 'primary' ),
	);
}

/**
 * Enqueue shop JS (site-wide for header cart).
 */
function blackbean_shop_enqueue_assets() : void {
	if ( is_admin() ) {
		return;
	}
	wp_enqueue_script(
		'blackbean-shop',
		BB_SHOP_PLUGIN_URL . 'assets/js/shop.js',
		array(),
		BB_SHOP_VERSION,
		true
	);
	wp_localize_script( 'blackbean-shop', 'blackbeanShop', blackbean_shop_script_config() );
}
add_action( 'wp_enqueue_scripts', 'blackbean_shop_enqueue_assets' );

function blackbean_shop_activate() : void {
	blackbean_shop_register_post_types();
	blackbean_shop_register_rewrites();
	if ( function_exists( 'blackbean_shop_license_install_tables' ) ) {
		blackbean_shop_license_install_tables();
	}
	flush_rewrite_rules( false );
	update_option( BLACKBEAN_SHOP_REWRITE_OPT, BB_SHOP_VERSION, false );
}
