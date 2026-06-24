<?php
/**
 * Front-end routing for products, cart, and checkout.
 *
 * @package Blackbean_Shop
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BB_Shop_Frontend_Routing {

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_rewrites' ), 10 );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'template_redirect', array( self::class, 'template_redirect' ), 4 );
		add_filter( 'rewrite_rules_array', array( self::class, 'rewrite_rules_array' ) );
	}

	public static function register_rewrites(): void {
		add_rewrite_rule( '^products/?$', 'index.php?blackbean_view=products_archive', 'top' );
		add_rewrite_rule( '^products/([^/]+)/?$', 'index.php?blackbean_view=product&blackbean_slug=$matches[1]', 'top' );
		blackbean_shop_register_rewrites();
	}

	/**
	 * @param list<string> $vars Query vars.
	 * @return list<string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'blackbean_view';
		$vars[] = 'blackbean_slug';

		return blackbean_shop_query_vars( $vars );
	}

	public static function template_redirect(): void {
		$view = get_query_var( 'blackbean_view' );
		if ( $view ) {
			if ( 'products_archive' === $view ) {
				$GLOBALS['blackbean_products_archive'] = blackbean_products_query(
					array(
						'status' => 'publish',
						'limit'  => 50,
					)
				);
				$template = BB_Shop_Template_Loader::locate( 'archive-bb_product.php' );
				if ( $template ) {
					load_template( $template );
					exit;
				}
			}

			if ( 'product' === $view ) {
				$slug = sanitize_title( (string) get_query_var( 'blackbean_slug' ) );
				$row  = blackbean_product_get_by_slug( $slug );
				if ( ! $row || 'publish' !== ( $row['status'] ?? '' ) ) {
					self::render_404();
				}
				$GLOBALS['blackbean_current_product'] = blackbean_product_format_public( $row );
				$template                             = BB_Shop_Template_Loader::locate( 'single-bb_product.php' );
				if ( $template ) {
					load_template( $template );
					exit;
				}
				self::render_404();
			}
		}

		blackbean_shop_template_redirect();
	}

	/**
	 * @param array<string, string> $rules Rewrite rules.
	 * @return array<string, string>
	 */
	public static function rewrite_rules_array( array $rules ): array {
		$new = array(
			'^products/?$'         => 'index.php?blackbean_view=products_archive',
			'^products/([^/]+)/?$' => 'index.php?blackbean_view=product&blackbean_slug=$matches[1]',
		);

		return $new + $rules;
	}

	private static function render_404(): void {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		get_template_part( '404' );
		exit;
	}
}
